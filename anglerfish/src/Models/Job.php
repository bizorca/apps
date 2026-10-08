<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;
use PDO;

/**
 * Job queue (SPEC §5.2, §13). Jobs are scoped to the smallest retryable unit,
 * are idempotent, and are leased rather than assigned so a dead worker's work
 * returns to the queue on its own.
 */
final class Job
{
    public static function enqueue(
        string $type,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $payload = [],
        int $priority = 5,
        ?int $maxAttempts = null
    ): int {
        return Database::insert(
            'INSERT INTO af_jobs (type, subject_type, subject_id, payload, priority,
                               max_attempts)
             VALUES (?, ?, ?, ?, ?, COALESCE(?, 3))',
            [$type, $subjectType, $subjectId, json_encode($payload), $priority,
             $maxAttempts]
        );
    }

    /**
     * Enqueue work that must not run yet.
     *
     * A batch poller is the case this exists for. Gemini answers a batch inside
     * 24 hours, and a poll job with no floor under it is leased again the
     * moment the cron ticks — 1,440 pointless requests for one image run. The
     * queue skips a job whose `not_before` is in the future, so waiting costs
     * nothing at all.
     */
    public static function enqueueIn(
        int $seconds,
        string $type,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $payload = [],
        int $priority = 5,
        ?int $maxAttempts = null
    ): int {
        return Database::insert(
            'INSERT INTO af_jobs (type, subject_type, subject_id, payload, priority,
                               max_attempts, not_before)
             VALUES (?, ?, ?, ?, ?, COALESCE(?, 3),
                     DATE_ADD(NOW(), INTERVAL ? SECOND))',
            [$type, $subjectType, $subjectId, json_encode($payload), $priority,
             $maxAttempts, max(0, $seconds)]
        );
    }

    /**
     * Enqueue only if an identical unfinished job is not already queued.
     * Keeps a re-run of the intake scan from piling up duplicate work.
     */
    public static function enqueueUnique(
        string $type,
        ?string $subjectType,
        ?int $subjectId,
        array $payload = [],
        int $priority = 5
    ): ?int {
        $existing = Database::value(
            "SELECT id FROM af_jobs
              WHERE type = ? AND subject_type <=> ? AND subject_id <=> ?
                AND status IN ('queued','leased','running')
              LIMIT 1",
            [$type, $subjectType, $subjectId]
        );
        if ($existing) {
            return null;
        }
        return self::enqueue($type, $subjectType, $subjectId, $payload, $priority);
    }

    /**
     * Atomically lease up to $limit queued jobs.
     * SKIP LOCKED keeps concurrent workers from colliding (MySQL 8+).
     *
     * @param string[] $types
     * @return array<int,array<string,mixed>>
     */
    public static function lease(array $types, int $limit, int $leaseSeconds): array
    {
        // An empty type list matches nothing, deliberately. If it meant "all",
        // a probe or a misconfigured client would silently lease real work and
        // hold it until the lease expired.
        if (!$types) {
            return [];
        }

        $pdo = Database::pdo();

        // Expired leases first, so a crashed worker's job is available again.
        $pdo->exec(
            "UPDATE af_jobs
                SET status='queued', lease_token=NULL, leased_at=NULL, lease_expires_at=NULL
              WHERE status IN ('leased','running') AND lease_expires_at < NOW()"
        );

        // Anything past max_attempts is dead; stop handing it out.
        $pdo->exec(
            "UPDATE af_jobs SET status='dead'
              WHERE status='queued' AND attempts >= max_attempts"
        );

        $pdo->beginTransaction();
        try {
            $in = implode(',', array_fill(0, count($types), '?'));
            $st = $pdo->prepare(
                "SELECT id FROM af_jobs
                  WHERE status='queued' AND attempts < max_attempts
                    AND (not_before IS NULL OR not_before <= NOW())
                    AND type IN ($in)
                  ORDER BY priority ASC, id ASC
                  LIMIT $limit
                  FOR UPDATE SKIP LOCKED"
            );
            $st->execute($types);
            $ids = $st->fetchAll(PDO::FETCH_COLUMN);

            if (!$ids) {
                $pdo->commit();
                return [];
            }

            $leased = [];
            foreach ($ids as $id) {
                $token = bin2hex(random_bytes(16));
                $upd = $pdo->prepare(
                    "UPDATE af_jobs
                        SET status='leased', lease_token=?, leased_at=NOW(),
                            lease_expires_at=DATE_ADD(NOW(), INTERVAL ? SECOND),
                            attempts = attempts + 1, started_at = COALESCE(started_at, NOW()),
                            error = NULL
                      WHERE id=?"
                );
                $upd->execute([$token, $leaseSeconds, $id]);
                $leased[$id] = $token;
            }
            $pdo->commit();

            $rows = Database::all(
                'SELECT id, type, subject_type, subject_id, payload, attempts, max_attempts
                   FROM af_jobs WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')
                  ORDER BY priority ASC, id ASC'
            );

            foreach ($rows as &$r) {
                $r['payload'] = $r['payload'] ? json_decode($r['payload'], true) : [];
                $r['lease_token'] = $leased[$r['id']];
            }
            return $rows;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function verifyLease(int $id, string $token): ?array
    {
        return Database::one(
            'SELECT * FROM af_jobs WHERE id = ? AND lease_token = ?',
            [$id, $token]
        );
    }

    public static function heartbeat(int $id, string $token, int $leaseSeconds): bool
    {
        return Database::run(
            "UPDATE af_jobs
                SET lease_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), status = 'running'
              WHERE id = ? AND lease_token = ?",
            [$leaseSeconds, $id, $token]
        ) > 0;
    }

    public static function complete(int $id, string $token, float $cost = 0): bool
    {
        return Database::run(
            "UPDATE af_jobs
                SET status='done', finished_at=NOW(), cost_usd=?, lease_token=NULL,
                    lease_expires_at=NULL, error=NULL
              WHERE id=? AND lease_token=?",
            [$cost, $id, $token]
        ) > 0;
    }

    /**
     * Put a job back without holding it against the job.
     *
     * The cron window defers whatever it cannot start inside its tick. That is
     * not a failed attempt — nothing was tried — but routing it through fail()
     * spends one, and at 481 queued directions the head of the queue gets
     * leased and deferred on every tick until it exhausts max_attempts and dies
     * having never run. Deferral returns the attempt it did not use.
     */
    public static function defer(int $id, string $token, string $why): bool
    {
        return Database::run(
            "UPDATE af_jobs
                SET status = 'queued', error = ?, lease_token = NULL,
                    lease_expires_at = NULL, leased_at = NULL,
                    attempts = GREATEST(0, attempts - 1)
              WHERE id = ? AND lease_token = ?",
            [mb_substr($why, 0, 4000), $id, $token]
        ) > 0;
    }

    public static function fail(int $id, string $token, string $error): bool
    {
        // Back to queued for another attempt unless attempts are exhausted.
        return Database::run(
            "UPDATE af_jobs
                SET status = IF(attempts >= max_attempts, 'dead', 'queued'),
                    error = ?, finished_at = NOW(), lease_token = NULL, lease_expires_at = NULL
              WHERE id = ? AND lease_token = ?",
            [mb_substr($error, 0, 4000), $id, $token]
        ) > 0;
    }
}
