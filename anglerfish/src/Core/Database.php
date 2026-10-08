<?php

namespace Anglerfish\Core;

use PDO;
use PDOException;

/**
 * PDO singleton. Raw SQL everywhere — no ORM, no query builder.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        $db = $cfg['db'];

        try {
            self::$pdo = new PDO(
                "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
                $db['user'],
                $db['pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Real prepares: keeps types intact and closes the emulation
                    // injection edge cases.
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            // MySQL here has no named-zone tables, so send the zone's current
            // offset. Recomputed on every connect, which follows DST.
            $offset = (new \DateTimeImmutable('now', new \DateTimeZone($db['timezone'] ?? 'UTC')))->format('P');
            self::$pdo->exec("SET time_zone = '$offset'");
        } catch (PDOException $e) {
            // Never leak credentials into a stack trace on a production page.
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Database unavailable.');
        }

        return self::$pdo;
    }

    /**
     * Run a statement, reconnecting once if the connection died while idle.
     *
     * A compose job holds the request open for the length of a Claude call —
     * about 68 seconds for a weekly post, which is 900-1400 words — and MySQL
     * closes the connection out from under it well before that. The API call
     * then succeeds and the write of its result fails with 2006, which is the
     * worst possible shape: the post was generated and paid for, the job looks
     * like it simply never ran, and the error write fails on the same dead
     * connection so nothing is recorded. Three silent attempts and a job stuck
     * at 3/3 was how it presented.
     *
     * Retrying a statement is only safe outside a transaction. Inside one the
     * work so far is already lost, so the exception is rethrown rather than
     * quietly re-running one statement of several against a fresh connection.
     */
    private static function attempt(callable $fn): mixed
    {
        try {
            return $fn(self::pdo());
        } catch (PDOException $e) {
            $inTx = self::$pdo !== null && self::$pdo->inTransaction();
            if ($inTx || !self::isGoneAway($e)) {
                throw $e;
            }
            error_log('DB connection lost; reconnecting: ' . $e->getMessage());
            self::$pdo = null;
            return $fn(self::pdo());
        }
    }

    /** MySQL 2006 "server has gone away" / 2013 "lost connection". */
    private static function isGoneAway(PDOException $e): bool
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        return in_array($code, [2006, 2013], true)
            || str_contains($e->getMessage(), 'server has gone away')
            || str_contains($e->getMessage(), 'Lost connection');
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::attempt(static function (PDO $pdo) use ($sql, $params): array {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll();
        });
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        return self::attempt(static function (PDO $pdo) use ($sql, $params): ?array {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetch() ?: null;
        });
    }

    public static function value(string $sql, array $params = []): mixed
    {
        return self::attempt(static function (PDO $pdo) use ($sql, $params): mixed {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchColumn();
        });
    }

    public static function run(string $sql, array $params = []): int
    {
        return self::attempt(static function (PDO $pdo) use ($sql, $params): int {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->rowCount();
        });
    }

    public static function insert(string $sql, array $params = []): int
    {
        // The insert and its lastInsertId share one closure: split across two
        // calls, a reconnect between them would return 0 from a fresh
        // connection that has never inserted anything.
        return self::attempt(static function (PDO $pdo) use ($sql, $params): int {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return (int) $pdo->lastInsertId();
        });
    }
}
