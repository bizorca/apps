<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;

/**
 * How much mail a firm is allowed to send to people who are not yet in it.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS PROTECTS, AND WHY IT IS NOT AN APPROVAL QUEUE
 *
 * Every firm sends from the one domain (SPEC §7), so deliverability is shared.
 * One firm blasting invitations damages every other firm's ability to have a
 * sign-in link land in an inbox, on a sending domain that is young and still
 * at DMARC p=none. That reputation takes months to rebuild.
 *
 * Signup is deliberately self-serve with no human in the path (FR-1.6). An
 * approval step would put a person in front of every genuine trial while
 * filtering almost nothing, because a name and an email address do not tell
 * you whether a firm is real. So the control sits where the actual harm is —
 * on mail leaving the building — rather than on the front door.
 *
 * THE CHOKEPOINT IS Invitation::issue(), AND IT HAS TO STAY THERE.
 *
 * An invitation is the only way this product sends mail to somebody who does
 * not already have an account. Everything else — digests, notifications,
 * document deliveries, mentions — can only reach a user who was invited first.
 * Gate invitations and the whole path from "signed up two minutes ago" to
 * "mail in a stranger's inbox" is closed, in one place.
 *
 * Controllers re-check before doing work, so a blocked firm gets a clean
 * message instead of a half-created record. Those are courtesies. This is the
 * check that counts, and a third call site that forgets it is still covered.
 *
 * THE ONE OTHER PATH, NAMED SO IT IS NOT FORGOTTEN
 *
 * A firm that switches on public intake can cause one acknowledgement email
 * per form submission, and the submitter's address is attacker-chosen. It is
 * already rate limited (RateLimiter 'intake', 5 per submitter and 10 per IP
 * per hour), needs the firm to enable the form, and sends one message rather
 * than a list. Left alone on purpose; revisit if it is ever seen in the wild.
 * ---------------------------------------------------------------------------
 */
final class SendingTrust
{
    /**
     * Invitations a firm may send before it has any clients.
     *
     * Three is enough to bring a couple of colleagues in on the first
     * afternoon, which is a real thing people do, and useless as a spam
     * channel. It is a lifetime allowance, not a daily one.
     */
    public const UNPROVEN_TOTAL = 3;

    /** Per rolling day, once the firm has a client but is still new. */
    public const NEW_DAILY = 25;

    /** Per rolling day thereafter. Generous — a real firm should never see it. */
    public const ESTABLISHED_DAILY = 250;

    /** How long a firm counts as new. */
    public const SETTLING_DAYS = 7;

    public const UNPROVEN    = 'unproven';
    public const NEW         = 'new';
    public const ESTABLISHED = 'established';

    /**
     * Which band is this firm in?
     *
     * Derived rather than stored, so there is no second copy of the answer to
     * fall out of date. A vouched firm skips straight to established: that is
     * the point of vouching.
     */
    public static function state(int $tenantId): string
    {
        $tenant = self::tenant($tenantId);

        if ($tenant === null) {
            return self::UNPROVEN;
        }

        if ($tenant['vouched_at'] !== null) {
            return self::ESTABLISHED;
        }

        if (!self::hasClient($tenantId)) {
            return self::UNPROVEN;
        }

        $age = time() - strtotime((string) $tenant['created_at']);

        return $age < self::SETTLING_DAYS * 86400 ? self::NEW : self::ESTABLISHED;
    }

    /**
     * May this firm send one more invitation right now?
     *
     * @return array{allowed:bool, state:string, remaining:int, reason:?string}
     */
    public static function check(int $tenantId): array
    {
        $state = self::state($tenantId);

        if ($state === self::UNPROVEN) {
            $used = self::invitationsSince($tenantId, null);
            $remaining = max(0, self::UNPROVEN_TOTAL - $used);

            return [
                'allowed'   => $remaining > 0,
                'state'     => $state,
                'remaining' => $remaining,
                'reason'    => $remaining > 0 ? null : self::UNPROVEN_MESSAGE,
            ];
        }

        $limit = $state === self::NEW ? self::NEW_DAILY : self::ESTABLISHED_DAILY;
        $used = self::invitationsSince($tenantId, 86400);
        $remaining = max(0, $limit - $used);

        return [
            'allowed'   => $remaining > 0,
            'state'     => $state,
            'remaining' => $remaining,
            'reason'    => $remaining > 0 ? null : sprintf(
                'That is %d invitations in a day, which is the current ceiling for this workspace. '
                . 'It lifts as the account settles in, and we can raise it now if you are onboarding '
                . 'a large intake — just ask.',
                $limit
            ),
        ];
    }

    /**
     * Refuse loudly if the firm may not send.
     *
     * 429 rather than 403: this is "not yet / not so fast", not "never". The
     * message says what to do about it, because a limit somebody cannot see a
     * way past is indistinguishable from a bug.
     *
     * HttpException is deliberately not a RuntimeException in this codebase,
     * so this survives the `catch (\RuntimeException)` blocks that both
     * invitation call sites use for domain errors. See Core\HttpException.
     */
    public static function assertMayInvite(int $tenantId): void
    {
        $check = self::check($tenantId);

        if (!$check['allowed']) {
            throw new HttpException(429, (string) $check['reason']);
        }
    }

    public const UNPROVEN_MESSAGE =
        'New workspaces can send a few invitations before their first client is added — '
        . 'this one has used them all. Add your first client organization and the limit lifts '
        . 'immediately. This exists so that one new account cannot damage email delivery for '
        . 'every firm here; it is not a judgement about yours.';

    /**
     * A short line for the staff screen, so the limit is visible before
     * somebody hits it rather than only after.
     */
    public static function notice(int $tenantId): ?string
    {
        $check = self::check($tenantId);

        if ($check['state'] !== self::UNPROVEN) {
            return null;
        }

        if (!$check['allowed']) {
            return self::UNPROVEN_MESSAGE;
        }

        return sprintf(
            'This workspace has no clients yet, so it can send %d more invitation%s for now. '
            . 'Adding your first client organization lifts that.',
            $check['remaining'],
            $check['remaining'] === 1 ? '' : 's'
        );
    }

    /** Promote a firm by hand. Platform-admin action. */
    public static function vouch(int $tenantId): void
    {
        Database::conn()
            ->prepare('UPDATE pl_tenants SET vouched_at = NOW() WHERE id = :id AND vouched_at IS NULL')
            ->execute(['id' => $tenantId]);
    }

    // ------------------------------------------------------------ internals

    /**
     * Invitations issued by this firm, optionally within a rolling window.
     *
     * Revoked and expired ones still count. The resource being spent is a
     * message that already left the building; withdrawing the invitation
     * afterwards does not un-send it, and letting a revoke refund the quota
     * would hand back the exact loop worth closing.
     *
     * Window arithmetic stays in SQL, on the same clock that wrote the row.
     * See the UTC note in CLAUDE.md for why that is not optional here.
     */
    private static function invitationsSince(int $tenantId, ?int $seconds): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM pl_invitations WHERE tenant_id = :tid';
        $params = ['tid' => $tenantId];

        if ($seconds !== null) {
            $sql .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL :secs SECOND)';
            $params['secs'] = $seconds;
        }

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetch()['c'];
    }

    private static function hasClient(int $tenantId): bool
    {
        $stmt = Database::conn()->prepare(
            'SELECT 1 FROM pl_client_orgs WHERE tenant_id = :tid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetch() !== false;
    }

    /** @return array<string,mixed>|null */
    private static function tenant(int $tenantId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT created_at, vouched_at FROM pl_tenants WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
