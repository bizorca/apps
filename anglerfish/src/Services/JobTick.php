<?php

namespace Anglerfish\Services;

use Anglerfish\Core\Database;
use Anglerfish\Models\Job;

/**
 * Page-load job processing, in place of cron.
 *
 * The web SAPI here is apache2handler, so there is no fastcgi_finish_request
 * to detach work from the response — doing it inline would just make pages
 * slow. Instead the browser fires /tick in the background after the page has
 * rendered, and that request does the work.
 *
 * The tradeoff, stated plainly: nothing runs while nobody is looking at the
 * site. That is fine for interactive work (composing, generating an image),
 * which only matters when someone is there anyway.
 */
final class JobTick
{
    /** Do not re-run more often than this unless work is actually waiting. */
    private const QUIET_SECONDS = 15;

    /** Web requests cap at 120s here; stop well short of it. */
    private const TIME_BUDGET = 70;

    /** Interactive types — always worth running immediately. */
    private const URGENT = ['compose', 'generate_image'];

    public static function run(int $max = 2, bool $force = false): array
    {
        $types = ServerJobs::types();
        $pending = self::pending($types);

        if ($pending === 0) {
            self::stamp();
            return ['ran' => false, 'reason' => 'nothing queued', 'pending' => 0];
        }

        $urgent = self::pending(array_intersect($types, self::URGENT)) > 0;
        if (!$force && !$urgent && self::sinceLastRun() < self::QUIET_SECONDS) {
            return ['ran' => false, 'reason' => 'throttled', 'pending' => $pending];
        }

        // One tick at a time. Overlapping page loads must not double-process.
        $lockPath = AF_STORAGE . '/.tick.lock';
        $lock = @fopen($lockPath, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ran' => false, 'reason' => 'busy', 'pending' => $pending];
        }

        self::stamp();
        $started = time();
        $done = $failed = 0;
        $errors = [];

        foreach (Job::lease($types, $max, 600) as $job) {
            if (time() - $started > self::TIME_BUDGET) {
                Job::fail((int) $job['id'], $job['lease_token'], 'deferred: request window');
                break;
            }
            try {
                $data = ServerJobs::run($job['type'], $job['payload'] ?? []);
                Database::run(
                    "UPDATE af_jobs SET status='done', finished_at=NOW(), lease_token=NULL,
                                     lease_expires_at=NULL, error=NULL
                      WHERE id=? AND lease_token=?",
                    [(int) $job['id'], $job['lease_token']]
                );
                ServerJobs::apply($job['type'], $data);
                $done++;
            } catch (\Throwable $e) {
                Job::fail((int) $job['id'], $job['lease_token'],
                    get_class($e) . ': ' . $e->getMessage());
                $errors[] = $e->getMessage();
                $failed++;
            }
        }

        flock($lock, LOCK_UN);
        fclose($lock);

        return ['ran' => true, 'done' => $done, 'failed' => $failed,
                'errors' => $errors, 'pending' => self::pending($types),
                'seconds' => time() - $started];
    }

    /** @param string[] $types */
    public static function pending(array $types): int
    {
        if (!$types) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($types), '?'));
        return (int) Database::value(
            "SELECT COUNT(*) FROM af_jobs
              WHERE status='queued' AND attempts < max_attempts AND type IN ($in)",
            array_values($types)
        );
    }

    /** Work only the Mac can do, so the UI can say why nothing is moving. */
    public static function pendingLocal(): int
    {
        $server = ServerJobs::types();
        $in = implode(',', array_fill(0, count($server), '?'));
        return (int) Database::value(
            "SELECT COUNT(*) FROM af_jobs
              WHERE status='queued' AND attempts < max_attempts AND type NOT IN ($in)",
            array_values($server)
        );
    }

    private static function sinceLastRun(): int
    {
        $at = (int) Database::value("SELECT value FROM af_settings WHERE `key`='tick_at'");
        return $at ? time() - $at : PHP_INT_MAX;
    }

    private static function stamp(): void
    {
        Database::run(
            "INSERT INTO af_settings (`key`, value) VALUES ('tick_at', ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [(string) time()]
        );
    }
}
