<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Mailer;

/**
 * Digests (FR-11.2, FR-11.3).
 *
 * Two of them, one mechanism:
 *
 *   firm_daily     — the coach's morning read. Everything their clients did
 *                    since yesterday, in one email instead of forty.
 *   client_weekly  — the business owner's Monday. What is due, what is overdue,
 *                    what is new, and ONE thing to go and do.
 *
 * They differ in an important way. The firm digest is purely a summary of
 * things that happened, so an empty one is not sent. The client digest is
 * partly a summary and partly a live look at open commitments, because the
 * whole product is an accountability loop and a client with three overdue
 * commitments and no new events is exactly the client who needs the email.
 *
 * ---------------------------------------------------------------------------
 * ON WINDOWS AND CLOCKS
 *
 * Every boundary here is computed in SQL, never in PHP. CLAUDE.md explains why
 * at length: PHP's date() and MySQL's NOW() run on independent clocks, and a
 * window computed in one and compared against a timestamp written by the other
 * is silently off by the UTC offset. That fault disabled all rate limiting
 * once. A digest built the same way would send Monday's email on Sunday
 * evening, or skip a day entirely, and nobody would be able to reproduce it.
 *
 * The window is also the idempotency key: `pl_digests` has a unique index on
 * (tenant, user, kind, window_start), so a tick that runs every five minutes
 * either inserts the row or gets a duplicate-key error. It never sends twice.
 * ---------------------------------------------------------------------------
 */
final class Digest
{
    public const FIRM_DAILY    = 'firm_daily';
    public const CLIENT_WEEKLY = 'client_weekly';

    /**
     * The most recent daily boundary at or before now, in UTC.
     *
     * @return array{start:string, end:string}
     */
    public static function dailyWindow(int $hourUtc): array
    {
        $hourUtc = max(0, min(23, $hourUtc));

        $stmt = Database::conn()->prepare(
            'SELECT
                CASE WHEN HOUR(NOW()) >= :h1
                     THEN TIMESTAMP(CURDATE(), MAKETIME(:h2, 0, 0))
                     ELSE TIMESTAMP(CURDATE() - INTERVAL 1 DAY, MAKETIME(:h3, 0, 0))
                END AS window_end'
        );
        $stmt->execute(['h1' => $hourUtc, 'h2' => $hourUtc, 'h3' => $hourUtc]);

        $end = (string) $stmt->fetch()['window_end'];

        // CAST rather than relying on a bound string being coerced — the same
        // class of fragility as the user variable above.
        $stmt = Database::conn()->prepare('SELECT CAST(:e AS DATETIME) - INTERVAL 1 DAY AS window_start');
        $stmt->execute(['e' => $end]);

        return ['start' => (string) $stmt->fetch()['window_start'], 'end' => $end];
    }

    /**
     * The most recent weekly boundary at or before now, in UTC.
     *
     * @param int $day 0 = Sunday .. 6 = Saturday. MySQL's DAYOFWEEK is 1-based
     *        from Sunday, hence the +1 — a fencepost worth naming rather than
     *        leaving as a bare arithmetic surprise in the SQL.
     * @return array{start:string, end:string}
     */
    public static function weeklyWindow(int $day, int $hourUtc): array
    {
        $day = max(0, min(6, $day));
        $hourUtc = max(0, min(23, $hourUtc));

        /**
         * A derived table, not a MySQL user variable.
         *
         * The first version assigned a user variable and read it back in the
         * same SELECT. MySQL's documentation is explicit that the evaluation
         * order of expressions involving user variables is undefined; locally
         * it evaluated in the helpful order, and on SiteGround it did not. The
         * tick threw an illegal-mix-of-collations error (an unassigned user
         * variable carries the server default) and every digest stopped,
         * silently, while the tick went on reporting success.
         *
         * A derived table has no ordering ambiguity and no collation of its
         * own, and is no harder to read.
         */
        $stmt = Database::conn()->prepare(
            'SELECT IF(cand > NOW(), cand - INTERVAL 7 DAY, cand) AS window_end
             FROM (
                SELECT TIMESTAMP(
                    CURDATE() - INTERVAL ((DAYOFWEEK(CURDATE()) - (:d1 + 1) + 7) % 7) DAY,
                    MAKETIME(:h1, 0, 0)
                ) AS cand
             ) AS boundary'
        );
        $stmt->execute(['d1' => $day, 'h1' => $hourUtc]);

        $end = (string) $stmt->fetch()['window_end'];

        $stmt = Database::conn()->prepare('SELECT CAST(:e AS DATETIME) - INTERVAL 7 DAY AS window_start');
        $stmt->execute(['e' => $end]);

        return ['start' => (string) $stmt->fetch()['window_start'], 'end' => $end];
    }

    /**
     * Run both digests for one tenant. Called from the tick.
     *
     * @param array<string,mixed> $tenant
     * @return array{firm:int, client:int}
     */
    public static function run(array $tenant): array
    {
        $tenantId = (int) $tenant['id'];
        $hour = (int) ($tenant['digest_hour'] ?? 13);

        $sent = ['firm' => 0, 'client' => 0];

        $daily = self::dailyWindow($hour);

        foreach (self::readersWithHeldItems($tenantId, true) as $user) {
            if (!self::wants($tenantId, (int) $user['id'], 'digest.daily')) {
                continue;
            }

            if (self::sendFirmDaily($tenantId, $tenant, $user, $daily)) {
                $sent['firm']++;
            }
        }

        if ((int) ($tenant['client_digest_enabled'] ?? 1) === 1) {
            $weekly = self::weeklyWindow((int) ($tenant['client_digest_day'] ?? 1), $hour);

            foreach (self::clientSideReaders($tenantId) as $user) {
                if (!self::wants($tenantId, (int) $user['id'], 'digest.weekly')) {
                    continue;
                }

                if (self::sendClientWeekly($tenantId, $tenant, $user, $weekly)) {
                    $sent['client']++;
                }
            }
        }

        return $sent;
    }

    /**
     * Does this reader still want the digest?
     *
     * The digests are events in the M11 catalogue precisely so this check can
     * exist. Without it, unsubscribing switched off every individual notice and
     * the summary kept arriving — which is the one thing FR-11.5 is explicitly
     * about.
     */
    private static function wants(int $tenantId, int $userId, string $event): bool
    {
        return !in_array(
            Notifications::channelFor($tenantId, $userId, $event),
            [Notifications::OFF, Notifications::IN_APP],
            true
        );
    }

    // ------------------------------------------------------------ assembling

    /**
     * Everything held for a reader and not yet sent.
     *
     * Deliberately NOT filtered to the current window. An item queued before
     * the window opened is still owed to the reader, and filtering by window
     * would strand it forever — held, unsent, and invisible. The window names
     * the digest; it does not decide its contents.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function heldItems(int $tenantId, int $userId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT n.*, o.name AS org_name
             FROM pl_notifications n
             LEFT JOIN pl_client_orgs o ON o.id = n.client_org_id AND o.tenant_id = n.tenant_id
             WHERE n.tenant_id = :tid AND n.user_id = :uid
               AND n.delivery = 'digest' AND n.emailed_at IS NULL
             ORDER BY n.client_org_id ASC, n.created_at ASC"
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Open commitments for a client-side reader, for the live half of FR-11.3.
     *
     * @return array{overdue:array<int,array<string,mixed>>, due:array<int,array<string,mixed>>}
     */
    public static function openCommitments(int $tenantId, int $userId, int $daysAhead = 7): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT t.id, t.title, t.due_on, o.name AS org_name,
                    t.due_on < CURDATE() AS is_overdue
             FROM pl_tasks t
             JOIN pl_engagements e ON e.id = t.engagement_id AND e.tenant_id = t.tenant_id
             JOIN pl_client_orgs o ON o.id = e.client_org_id AND o.tenant_id = t.tenant_id
             WHERE t.tenant_id = :tid
               AND t.owner_user_id = :uid
               AND t.status IN ('open','in_progress')
               AND t.due_on IS NOT NULL
               AND t.due_on <= CURDATE() + INTERVAL :days DAY
             ORDER BY t.due_on ASC"
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId, 'days' => max(0, min(60, $daysAhead))]);

        $overdue = [];
        $due = [];

        foreach ($stmt->fetchAll() as $row) {
            if ((int) $row['is_overdue'] === 1) {
                $overdue[] = $row;
            } else {
                $due[] = $row;
            }
        }

        return ['overdue' => $overdue, 'due' => $due];
    }

    // ---------------------------------------------------------------- firm

    /**
     * @param array<string,mixed> $tenant
     * @param array<string,mixed> $user
     * @param array{start:string, end:string} $window
     */
    public static function sendFirmDaily(int $tenantId, array $tenant, array $user, array $window): bool
    {
        $items = self::heldItems($tenantId, (int) $user['id']);

        // A summary of nothing is not worth an email, and a daily email that is
        // routinely empty is how a digest gets filtered as noise.
        if ($items === []) {
            return false;
        }

        $digestId = self::claim($tenantId, (int) $user['id'], self::FIRM_DAILY, $window, count($items));

        if ($digestId === null) {
            return false;   // already sent for this window
        }

        $slug = (string) $tenant['slug'];
        $byOrg = [];

        foreach ($items as $item) {
            $key = trim((string) ($item['org_name'] ?? '')) ?: 'Your firm';
            $byOrg[$key][] = $item;
        }

        $paragraphs = [
            'Here is what moved since yesterday across your clients.',
        ];

        foreach ($byOrg as $orgName => $rows) {
            $lines = $orgName . ':';

            foreach ($rows as $row) {
                $lines .= "\n  · " . $row['title'];
            }

            $paragraphs[] = $lines;
        }

        $body = MailTemplate::action(
            'Good morning ' . self::firstName((string) $user['name']) . ',',
            $paragraphs,
            'Open your dashboard',
            tenant_url('/', $slug),
            ['Change what lands here, or stop it, at ' . tenant_url('/settings/notifications', $slug) . '.'],
            Mailer::PLATFORM_NAME
        );

        $count = count($items);

        $ok = Mailer::send(
            (string) $user['email'],
            (string) $user['name'],
            $count . ' update' . ($count === 1 ? '' : 's') . ' from your clients',
            $body['text'],
            $body['html'],
            Mailer::PLATFORM_NAME
        );

        self::markCarried($tenantId, $items, $digestId);

        return $ok;
    }

    // -------------------------------------------------------------- client

    /**
     * @param array<string,mixed> $tenant
     * @param array<string,mixed> $user
     * @param array{start:string, end:string} $window
     */
    public static function sendClientWeekly(int $tenantId, array $tenant, array $user, array $window): bool
    {
        $items = self::heldItems($tenantId, (int) $user['id']);
        $commitments = self::openCommitments($tenantId, (int) $user['id']);

        $hasSomething = $items !== []
            || $commitments['overdue'] !== []
            || $commitments['due'] !== [];

        if (!$hasSomething) {
            return false;
        }

        $digestId = self::claim($tenantId, (int) $user['id'], self::CLIENT_WEEKLY, $window, count($items));

        if ($digestId === null) {
            return false;
        }

        $slug = (string) $tenant['slug'];
        $firm = Mailer::senderName((int) $user['client_org_id'], $tenant);

        $paragraphs = [];

        if ($commitments['overdue'] !== []) {
            $lines = 'Past due:';

            foreach ($commitments['overdue'] as $t) {
                $lines .= "\n  · " . $t['title'] . ' — was due ' . date('j F', strtotime((string) $t['due_on']));
            }

            $paragraphs[] = $lines;
        }

        if ($commitments['due'] !== []) {
            $lines = 'Coming up this week:';

            foreach ($commitments['due'] as $t) {
                $lines .= "\n  · " . $t['title'] . ' — due ' . date('j F', strtotime((string) $t['due_on']));
            }

            $paragraphs[] = $lines;
        }

        if ($items !== []) {
            $lines = 'New since last week:';

            foreach ($items as $item) {
                $lines .= "\n  · " . $item['title'];
            }

            $paragraphs[] = $lines;
        }

        // FR-11.3 wants ONE call to action. The most overdue commitment beats
        // the soonest due, which beats a generic link — a digest that ends in
        // "log in and have a look" is a digest nobody acts on.
        [$ctaLabel, $ctaPath] = self::callToAction($commitments);

        if ($commitments['overdue'] !== []) {
            $paragraphs[] = 'If something on that list is no longer the right thing to be doing, say so. '
                . 'That is a more useful conversation than another reminder.';
        }

        $body = MailTemplate::action(
            'Hello ' . self::firstName((string) $user['name']) . ',',
            $paragraphs,
            $ctaLabel,
            tenant_url($ctaPath, $slug),
            [
                'This is your weekly summary from ' . $firm . '.',
                'To stop these weekly summaries, visit ' . tenant_url('/settings/notifications', $slug) . '.',
            ],
            $firm
        );

        $ok = Mailer::send(
            (string) $user['email'],
            (string) $user['name'],
            'Your week with ' . $firm,
            $body['text'],
            $body['html'],
            $firm
        );

        self::markCarried($tenantId, $items, $digestId);

        return $ok;
    }

    /**
     * @param array{overdue:array<int,array<string,mixed>>, due:array<int,array<string,mixed>>} $commitments
     * @return array{0:string, 1:string}
     */
    private static function callToAction(array $commitments): array
    {
        $first = $commitments['overdue'][0] ?? $commitments['due'][0] ?? null;

        if ($first === null) {
            return ['Open your workspace', '/'];
        }

        return ['Open "' . mb_substr((string) $first['title'], 0, 60) . '"', '/tasks/' . (int) $first['id']];
    }

    // ------------------------------------------------------------ internals

    /**
     * Reserve the window. Returns null if someone already has it.
     *
     * The unique index does the work, which is the point — two ticks racing
     * cannot both win, and no amount of check-then-insert in PHP would give
     * that guarantee.
     *
     * @param array{start:string, end:string} $window
     */
    private static function claim(int $tenantId, int $userId, string $kind, array $window, int $itemCount): ?int
    {
        $db = Database::conn();

        try {
            $db->prepare(
                'INSERT INTO pl_digests (tenant_id, user_id, kind, window_start, window_end, item_count)
                 VALUES (:tid, :uid, :kind, :ws, :we, :n)'
            )->execute([
                'tid' => $tenantId, 'uid' => $userId, 'kind' => $kind,
                'ws' => $window['start'], 'we' => $window['end'], 'n' => $itemCount,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return null;   // this window is already spoken for
            }

            throw $e;
        }

        return (int) $db->lastInsertId();
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private static function markCarried(int $tenantId, array $items, int $digestId): void
    {
        if ($items === []) {
            return;
        }

        $stmt = Database::conn()->prepare(
            'UPDATE pl_notifications SET emailed_at = NOW(), digest_id = :did
             WHERE tenant_id = :tid AND id = :id'
        );

        foreach ($items as $item) {
            $stmt->execute(['did' => $digestId, 'tid' => $tenantId, 'id' => (int) $item['id']]);
        }
    }

    /**
     * Firm-side readers who have something held.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function readersWithHeldItems(int $tenantId, bool $firmSide): array
    {
        $wall = $firmSide ? 'u.client_org_id IS NULL' : 'u.client_org_id IS NOT NULL';

        $stmt = Database::conn()->prepare(
            "SELECT DISTINCT u.id, u.name, u.email, u.client_org_id
             FROM pl_users u
             JOIN pl_notifications n ON n.user_id = u.id AND n.tenant_id = u.tenant_id
             WHERE u.tenant_id = :tid AND u.status = 'active' AND {$wall}
               AND n.delivery = 'digest' AND n.emailed_at IS NULL"
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }

    /**
     * Every active client-side reader. Unlike the firm digest this is not
     * limited to people with held items — the live commitment list is reason
     * enough to write to someone.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function clientSideReaders(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT u.id, u.name, u.email, u.client_org_id
             FROM pl_users u
             JOIN pl_client_orgs o ON o.id = u.client_org_id AND o.tenant_id = u.tenant_id
             WHERE u.tenant_id = :tid AND u.status = 'active'
               AND u.client_org_id IS NOT NULL
               AND o.status <> 'archived'"
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }

    private static function firstName(string $name): string
    {
        $first = trim(explode(' ', trim($name))[0] ?? '');

        return $first === '' ? 'there' : $first;
    }
}
