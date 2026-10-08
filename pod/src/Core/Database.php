<?php

namespace Bizorca\Pod\Core;

use PDO;

/**
 * Pod's query helpers over the shared MySQL handle (tl_db()).
 *
 * The shared handle starts in UTC. Pod stores event times as Pacific wall-clock
 * times and compares them with NOW(), so the session is moved to PD_TIMEZONE,
 * as an offset because Cloudways' MySQL may not have the named-zone tables.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = tl_db();
            $offset = (new \DateTimeImmutable('now', new \DateTimeZone(PD_TIMEZONE)))->format('P');
            self::$pdo->exec("SET time_zone = '{$offset}'");
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::connect()->lastInsertId();
    }
}
