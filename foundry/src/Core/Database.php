<?php

namespace Bizorca\Consulting\Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    /**
     * The shared tools connection (tl_db(): native prepares, exceptions,
     * assoc fetches), with the MySQL session moved to Foundry's clock so
     * CURRENT_TIMESTAMP and PHP's date() agree. See FD_TIMEZONE.
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = tl_db();
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $stmt = self::pdo()->prepare("INSERT INTO `$table` ($cols) VALUES ($vals)");
        $stmt->execute(array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set  = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $stmt = self::pdo()->prepare("UPDATE `$table` SET $set WHERE $where");
        $stmt->execute([...array_values($data), ...$whereParams]);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    public static function beginTransaction(): void { self::pdo()->beginTransaction(); }
    public static function commit(): void           { self::pdo()->commit(); }
    public static function rollback(): void         { self::pdo()->rollBack(); }
}
