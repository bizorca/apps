<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

use PDO;
use PDOException;

/**
 * PDO singleton. House style: raw PDO, no ORM, no query builder beyond
 * Repository.
 */
final class Database
{
    private static ?PDO $conn = null;

    public static function conn(): PDO
    {
        if (self::$conn instanceof PDO) {
            return self::$conn;
        }

        // On tools.bizorca.com Pilotage shares the site's one MySQL handle, so
        // the shared account and the firm membership can change in one
        // transaction. tl_db() already pins UTC and uses real prepares, the two
        // settings below that the code depends on.
        if (Config::get('db.shared', false) && function_exists('tl_db')) {
            self::$conn = tl_db();
            self::$conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
            return self::$conn;
        }

        $cfg = Config::get('db');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            (int) $cfg['port'],
            $cfg['name']
        );

        try {
            self::$conn = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements. Emulation silently re-types bound
                // values, which has bitten every project that left it on.
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            // Never leak credentials from the DSN into a stack trace that
            // might reach a browser.
            throw new \RuntimeException('Database connection failed.', 0, $e);
        }

        /**
         * One clock, and it is UTC.
         *
         * PHP's date() and MySQL's NOW() otherwise run on independent
         * timezones. That is not a cosmetic difference: a window computed in
         * PHP and compared against a timestamp written by MySQL is off by the
         * UTC offset, which silently disabled rate limiting entirely until it
         * was caught by hand. Pinning both to UTC makes every PHP-computed
         * boundary and every SQL-written timestamp directly comparable.
         *
         * Never remove this without also removing every mixed-clock comparison.
         */
        self::$conn->exec("SET time_zone = '+00:00'");

        return self::$conn;
    }

    /** Test seam: swap in a connection (e.g. pointed at the test schema). */
    public static function setConnection(?PDO $conn): void
    {
        self::$conn = $conn;
    }

    public static function reset(): void
    {
        self::$conn = null;
    }
}
