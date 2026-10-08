<?php

declare(strict_types=1);

namespace TimeBank\Core;

use PDO;
use PDOStatement;

/**
 * Thin wrapper over the shared MySQL handle (tl_db(): native prepares, UTC).
 * Keeps the original's static API so the models did not need rewriting.
 */
class DB
{
    public static function getInstance(): PDO
    {
        return tl_db();
    }

    /**
     * Ints are bound as ints. With native prepares a LIMIT ? bound as a string
     * is refused, and several original queries pass their limit that way.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = static::getInstance()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): array|false
    {
        return static::query($sql, $params)->fetch();
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return static::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int|string
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn($c) => '`' . $c . '`', $columns)),
            implode(', ', array_map(fn($c) => ':' . $c, $columns))
        );

        $params = [];
        foreach ($data as $col => $value) {
            $params[':' . $col] = $value;
        }

        static::query($sql, $params);
        return static::lastInsertId();
    }

    public static function update(string $table, array $data, array $where): int
    {
        $set   = array_map(fn($c) => '`' . $c . '` = :set_' . $c, array_keys($data));
        $conds = array_map(fn($c) => '`' . $c . '` = :where_' . $c, array_keys($where));

        $params = [];
        foreach ($data as $col => $value) {
            $params[':set_' . $col] = $value;
        }
        foreach ($where as $col => $value) {
            $params[':where_' . $col] = $value;
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), implode(' AND ', $conds));
        return static::query($sql, $params)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return static::query(sprintf('DELETE FROM `%s` WHERE %s', $table, $where), $params)->rowCount();
    }

    public static function lastInsertId(): int|string
    {
        return static::getInstance()->lastInsertId();
    }

    /**
     * Run $fn in one database transaction; any exception rolls everything back.
     * Re-entrant: inside an open transaction $fn simply joins it, so a ledger
     * method may call another (creditFromCommunityFund() calls record()).
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = static::getInstance();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
