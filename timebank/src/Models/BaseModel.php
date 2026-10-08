<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

abstract class BaseModel
{
    protected string $table;
    protected int $tenantId;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Find a single record by id within the current tenant.
     */
    public function find(int $id): array|false
    {
        return DB::fetch(
            "SELECT * FROM `{$this->table}` WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$id, $this->tenantId]
        );
    }

    /**
     * Return all records for the current tenant.
     */
    public function all(string $orderBy = 'created_at DESC', ?int $limit = null): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE tenant_id = ?";

        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        return DB::fetchAll($sql, [$this->tenantId]);
    }

    /**
     * Flexible query builder. Each $conditions entry is a raw SQL snippet
     * (e.g. "status = ?", "is_active = 1"). tenant_id is always prepended.
     *
     * @param string[] $conditions
     * @param array    $params     Positional params matching the ?-placeholders in $conditions
     */
    public function where(
        array $conditions = [],
        array $params = [],
        string $orderBy = '',
        ?int $limit = null
    ): array {
        $where = ['tenant_id = ?'];
        $bound = [$this->tenantId];

        foreach ($conditions as $condition) {
            $where[] = $condition;
        }

        foreach ($params as $p) {
            $bound[] = $p;
        }

        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $where);

        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        return DB::fetchAll($sql, $bound);
    }

    /**
     * Insert a new record. tenant_id is automatically injected.
     */
    public function create(array $data): int|string
    {
        $data['tenant_id'] = $this->tenantId;
        return DB::insert($this->table, $data);
    }

    /**
     * Update a record by id within the current tenant.
     */
    public function update(int $id, array $data): int
    {
        return DB::update(
            $this->table,
            $data,
            ['id' => $id, 'tenant_id' => $this->tenantId]
        );
    }

    /**
     * Delete a record by id within the current tenant.
     */
    public function delete(int $id): int
    {
        return DB::delete(
            $this->table,
            'id = ? AND tenant_id = ?',
            [$id, $this->tenantId]
        );
    }

    /**
     * COUNT with optional extra conditions.
     *
     * @param string[] $conditions
     * @param array    $params
     */
    public function count(array $conditions = [], array $params = []): int
    {
        $where = ['tenant_id = ?'];
        $bound = [$this->tenantId];

        foreach ($conditions as $condition) {
            $where[] = $condition;
        }

        foreach ($params as $p) {
            $bound[] = $p;
        }

        $sql = "SELECT COUNT(*) AS cnt FROM `{$this->table}` WHERE " . implode(' AND ', $where);
        $row = DB::fetch($sql, $bound);

        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Paginate results. Returns data array plus pagination metadata.
     *
     * @param string[] $conditions
     * @param array    $params
     * @return array{data: array, total: int, pages: int, current: int}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        array $conditions = [],
        array $params = [],
        string $orderBy = 'created_at DESC'
    ): array {
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;
        $total   = $this->count($conditions, $params);
        $pages   = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $where = ['tenant_id = ?'];
        $bound = [$this->tenantId];

        foreach ($conditions as $condition) {
            $where[] = $condition;
        }

        foreach ($params as $p) {
            $bound[] = $p;
        }

        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $where);

        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }

        $sql   .= " LIMIT {$perPage} OFFSET {$offset}";
        $data   = DB::fetchAll($sql, $bound);

        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
        ];
    }
}
