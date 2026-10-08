<?php

declare(strict_types=1);

namespace Dispatch\Core;

use PDO;
use PDOStatement;

/**
 * Dispatch's query helpers over the shared MySQL handle (tl_db()).
 *
 * The shared handle starts in UTC. Dispatch's SQL leans on CURDATE() for
 * "overdue" and "due within N days", so the session is moved to the same
 * zone PHP uses (DP_TIMEZONE), as an offset because Cloudways' MySQL may not
 * have the named-zone tables loaded.
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $this->pdo = tl_db();
        $offset = (new \DateTimeImmutable('now', new \DateTimeZone(DP_TIMEZONE)))->format('P');
        $this->pdo->exec("SET time_zone = '{$offset}'");
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }
}
