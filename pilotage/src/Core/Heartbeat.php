<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Background work, triggered by page loads instead of cron.
 *
 * ---------------------------------------------------------------------------
 * HOW IT WORKS, AND WHY THIS SHAPE
 *
 * A request that arrives when work is due claims the slot, fires a detached
 * HTTP request at /_tick/{token}, and returns. That second request does the
 * actual work with its own execution budget. The page load pays a couple of
 * hundred milliseconds — once per interval, not once per request.
 *
 * The detour through a second request is not decoration. This host runs
 * apache2handler, so there is no `fastcgi_finish_request()` to hand the
 * response back and keep working. The alternatives were:
 *
 *   - flush and continue under `ignore_user_abort`. Under mod_php the Apache
 *     worker stays tied up for the whole tick, which on shared hosting is
 *     antisocial and eventually self-defeating.
 *   - `exec('php cron/tick.php &')`. CLAUDE.md already records that SiteGround
 *     kills background processes, and exec is often disabled anyway.
 *
 * A second HTTP request has neither problem: it is an ordinary request with an
 * ordinary budget, and the caller hangs up after 200ms without waiting.
 *
 * THE HONEST COST: A SITE WITH NO TRAFFIC NEVER TICKS. No arrangement of this
 * code fixes that. What makes it survivable is that /_tick is reachable from
 * outside, so any uptime pinger keeps a quiet site alive, and that everything
 * the tick does is idempotent and window-based — a late tick does the right
 * thing once rather than the wrong thing repeatedly.
 *
 * AND THE FAILURE THIS IS BUILT TO AVOID REPEATING: the background layer was
 * dormant in production for a day and nothing said so. `staleness()` is read by
 * /_health and the firm settings screen, so silence is visible.
 * ---------------------------------------------------------------------------
 */
final class Heartbeat
{
    /** How often each mode should run, in seconds. */
    public const INTERVALS = [
        'five-minute' => 300,
        'nightly'     => 86400,
    ];

    /**
     * A claim older than this with no finish is assumed dead and may be
     * retried. Long enough that a slow-but-working tick is not trampled;
     * short enough that a lost one is not waited on all day.
     */
    public const CLAIM_TIMEOUT = 600;

    /** How long the trigger waits before hanging up. */
    public const FIRE_TIMEOUT_MS = 250;

    /** Past this with nothing finished, the installation is told it is broken. */
    public const STALE_AFTER = 1800;

    /**
     * Called at the very end of a request. Never throws, never blocks for long.
     *
     * Everything here is wrapped: a heartbeat that can break a page load is
     * worse than no heartbeat, because it turns a background problem into an
     * outage.
     */
    public static function afterResponse(): void
    {
        try {
            foreach (array_keys(self::INTERVALS) as $mode) {
                if (self::claim($mode)) {
                    self::fire($mode);

                    // One per request. If both are due, the next request takes
                    // the other — no reason to make one visitor pay twice.
                    return;
                }
            }
        } catch (\Throwable $e) {
            error_log('Heartbeat: ' . $e->getMessage());
        }
    }

    /**
     * Take the slot for a mode, if it is due.
     *
     * The UPDATE is the lock. Whoever changes the row wins; everyone else gets
     * rowCount() === 0 and moves on. No SELECT-then-UPDATE, because between
     * those two statements every concurrent page load also decides it should
     * run.
     */
    public static function claim(string $mode): bool
    {
        if (!isset(self::INTERVALS[$mode])) {
            return false;
        }

        $stmt = Database::conn()->prepare(
            'UPDATE pl_tick_state
             SET last_started_at = NOW(),
                 stalled_count = stalled_count + 1
             WHERE mode = :mode
               AND (
                    last_started_at IS NULL
                    -- due, and the previous run reported finishing
                    OR (last_finished_at IS NOT NULL
                        AND last_started_at <= NOW() - INTERVAL :interval SECOND)
                    -- or the previous run never came back and has had long enough
                    OR (last_finished_at IS NULL
                        AND last_started_at <= NOW() - INTERVAL :timeout SECOND)
               )'
        );
        $stmt->execute([
            'mode' => $mode,
            'interval' => self::INTERVALS[$mode],
            'timeout' => self::CLAIM_TIMEOUT,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Was this mode claimed a moment ago?
     *
     * The trigger claims, then fires. The receiving request must not claim
     * again — it would find the slot not due and refuse to do the work it was
     * just asked to do. So it checks for a claim it can adopt.
     *
     * An external pinger arrives with no claim of its own and takes one
     * normally.
     */
    public static function claimedRecently(string $mode, int $withinSeconds = 30): bool
    {
        $stmt = Database::conn()->prepare(
            'SELECT COUNT(*) AS c FROM pl_tick_state
             WHERE mode = :mode
               AND last_started_at IS NOT NULL
               AND last_started_at >= NOW() - INTERVAL :secs SECOND
               AND (last_finished_at IS NULL OR last_finished_at < last_started_at)'
        );
        $stmt->execute(['mode' => $mode, 'secs' => max(1, $withinSeconds)]);

        return (int) $stmt->fetch()['c'] === 1;
    }

    /**
     * Record that a run completed. Clears the stall counter.
     */
    public static function finished(string $mode, string $note, int $seconds): void
    {
        Database::conn()->prepare(
            'UPDATE pl_tick_state
             SET last_finished_at = NOW(), last_note = :note, last_duration = :secs,
                 run_count = run_count + 1, stalled_count = 0
             WHERE mode = :mode'
        )->execute([
            'note' => mb_substr($note, 0, 500),
            'secs' => max(0, min(65535, $seconds)),
            'mode' => $mode,
        ]);
    }

    /**
     * Fire the detached request.
     *
     * Aimed at THIS server rather than at the public URL, by pinning the
     * request's own host to 127.0.0.1 with CURLOPT_RESOLVE. Three reasons:
     *
     *   - it works in development, where the tenant host does not resolve to
     *     anything and a public-URL call would silently go nowhere;
     *   - it skips the Cloudflare round trip, which is latency and quota spent
     *     to reach a server we are already running on;
     *   - the Host header and SNI stay correct, so tenant resolution and TLS
     *     both behave exactly as they would for a real visitor.
     *
     * A TIMEOUT IS THE SUCCESS CASE. We hang up on purpose. A connection error
     * is different — it means the pin was wrong — and falls back to the public
     * URL rather than leaving the tick undone.
     */
    private static function fire(string $mode): void
    {
        // The route, then the site-relative URL that reaches it: on
        // tools.bizorca.com that is /pilotage/?r=/_tick/... (see pl_route()).
        $route = '/_tick/' . self::token() . '/' . rawurlencode($mode);
        $path = pl_route($route);

        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);

        if ($host !== '' && $port > 0) {
            $bare = preg_replace('/:\d+$/', '', $host) ?? $host;
            $scheme = $port === 443 ? 'https' : 'http';

            // The URL's authority and the CURLOPT_RESOLVE pin must agree on the
            // port, or the pin simply does not apply and curl goes to DNS. That
            // is not theoretical: the first version put the port in the pin and
            // not in the URL, so every trigger quietly took the slow path to a
            // hostname that does not resolve in development.
            $default = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
            $authority = $bare . ($default ? '' : ':' . $port);

            if (self::request($scheme . '://' . $authority . $path, [$bare . ':' . $port . ':127.0.0.1'])) {
                return;
            }
        }

        // The pin did not connect. Go the long way round rather than skip it.
        self::request(app_url($route), []);
    }

    /**
     * @param array<int,string> $resolve
     * @return bool True when the request reached something — including by
     *         timing out on us, which is what we want.
     */
    private static function request(string $url, array $resolve): bool
    {
        $ch = curl_init($url);

        if ($ch === false) {
            return false;
        }

        $options = [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_TIMEOUT_MS        => self::FIRE_TIMEOUT_MS,
            CURLOPT_CONNECTTIMEOUT_MS => self::FIRE_TIMEOUT_MS,
            CURLOPT_NOSIGNAL          => true,
            CURLOPT_FOLLOWLOCATION    => false,
            CURLOPT_HTTPHEADER        => ['X-Pilotage-Heartbeat: 1'],
        ];

        if ($resolve !== []) {
            $options[CURLOPT_RESOLVE] = $resolve;
        }

        curl_setopt_array($ch, $options);
        curl_exec($ch);

        $errno = curl_errno($ch);

        // 28 is "operation timed out", which is the whole plan. 0 means it
        // answered before we hung up, which is fine too. Anything else — could
        // not connect, could not resolve, TLS refused — means this route did
        // not work.
        return in_array($errno, [0, CURLE_OPERATION_TIMEDOUT], true);
    }

    /**
     * The shared token, generated once.
     *
     * Kept in the database rather than the environment so the settings screen
     * can show it — its whole purpose is to be pasted into an uptime monitor,
     * and a value nobody can find is a value nobody uses.
     */
    public static function token(): string
    {
        $db = Database::conn();

        $row = $db->query('SELECT token FROM pl_tick_token WHERE id = 1')->fetch();

        if ($row !== false) {
            return (string) $row['token'];
        }

        $token = bin2hex(random_bytes(32));

        // INSERT IGNORE, then read back: two requests racing to create it must
        // end up agreeing on which one won.
        $db->prepare('INSERT IGNORE INTO pl_tick_token (id, token) VALUES (1, :t)')
           ->execute(['t' => $token]);

        $row = $db->query('SELECT token FROM pl_tick_token WHERE id = 1')->fetch();

        return $row === false ? $token : (string) $row['token'];
    }

    public static function verifyToken(string $presented): bool
    {
        return hash_equals(self::token(), $presented);
    }

    /**
     * How the background layer is doing, in words.
     *
     * Read by /_health and the firm settings screen. This exists because the
     * alternative — inferring from missing emails that nothing has run for a
     * day — is what actually happened.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function status(): array
    {
        $rows = Database::conn()->query(
            'SELECT mode, last_started_at, last_finished_at, last_note, last_duration,
                    run_count, stalled_count,
                    TIMESTAMPDIFF(SECOND, last_finished_at, NOW()) AS since_finished
             FROM pl_tick_state ORDER BY mode'
        )->fetchAll();

        foreach ($rows as $i => $row) {
            $since = $row['since_finished'] === null ? null : (int) $row['since_finished'];
            $interval = self::INTERVALS[(string) $row['mode']] ?? 300;

            // Stale is judged against the mode's own interval, with slack. A
            // nightly job that ran twenty hours ago is fine; a five-minute one
            // is not.
            $limit = max(self::STALE_AFTER, $interval * 3);

            $rows[$i]['healthy'] = $since !== null && $since <= $limit;
            $rows[$i]['never_run'] = $row['last_finished_at'] === null;
            $rows[$i]['human'] = self::describe($since, (int) $row['stalled_count'], $limit);
        }

        return $rows;
    }

    /** Is anything wrong enough to say so on a settings page? */
    public static function unhealthy(): bool
    {
        foreach (self::status() as $row) {
            if (!$row['healthy']) {
                return true;
            }
        }

        return false;
    }

    private static function describe(?int $since, int $stalled, int $limit): string
    {
        if ($since === null) {
            return 'Has never completed a run.';
        }

        $ago = match (true) {
            $since < 90    => 'just now',
            $since < 5400  => (int) round($since / 60) . ' minutes ago',
            $since < 172800 => (int) round($since / 3600) . ' hours ago',
            default        => (int) round($since / 86400) . ' days ago',
        };

        if ($since > $limit) {
            return 'Last completed ' . $ago . ' — that is longer than it should be.'
                 . ($stalled > 1 ? ' ' . $stalled . ' runs have started without finishing.' : '');
        }

        return 'Last completed ' . $ago . '.';
    }
}
