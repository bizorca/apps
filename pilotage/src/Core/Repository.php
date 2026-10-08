<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

use PDO;

/**
 * Tenant-scoped data access.
 *
 * Every tenant-owned table is read and written through a subclass of this.
 * The tenant filter is injected here, once, rather than written into
 * individual queries where it will eventually be forgotten. That forgetting
 * is the failure mode that ends this product (spec §6), so the rule is:
 *
 *   No SQL touching a tenant-owned table is written outside a Repository.
 *
 * If you need something this class cannot express, add a method here that
 * builds it with the scope applied — do not reach for the PDO handle.
 */
abstract class Repository
{
    protected PDO $db;
    protected int $tenantId;

    /** Table name, including the pl_ prefix. */
    abstract protected function table(): string;

    /**
     * Columns a caller is allowed to write. Anything not listed is dropped
     * on insert/update. An allowlist rather than a denylist, so adding a
     * sensitive column later is safe by default.
     *
     * @return string[]
     */
    abstract protected function writable(): array;

    /**
     * @param int|null $tenantId Defaults to the tenant resolved for this request.
     * @throws TenantScopeException when no tenant is in scope.
     */
    public function __construct(?int $tenantId = null)
    {
        $resolved = $tenantId ?? Tenant::currentId();

        if ($resolved === null || $resolved <= 0) {
            throw new TenantScopeException(
                static::class . ' was constructed without a tenant in scope.'
            );
        }

        $this->db = Database::conn();
        $this->tenantId = $resolved;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $sql = 'SELECT * FROM ' . $this->table()
             . ' WHERE id = :__id AND tenant_id = :__tid LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['__id' => $id, '__tid' => $this->tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string,mixed> $conditions Column => value, ANDed together.
     * @return array<string,mixed>|null
     */
    public function firstWhere(array $conditions): ?array
    {
        $rows = $this->all($conditions, null, 1);
        return $rows[0] ?? null;
    }

    /**
     * @param array<string,mixed> $conditions Column => value, ANDed together.
     * @param string|null $orderBy Column name, optionally suffixed " asc"/" desc".
     * @return array<int,array<string,mixed>>
     */
    public function all(array $conditions = [], ?string $orderBy = null, ?int $limit = null): array
    {
        [$where, $params] = $this->buildWhere($conditions);

        $sql = 'SELECT * FROM ' . $this->table() . ' WHERE ' . $where;

        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . $this->safeOrderBy($orderBy);
        }
        if ($limit !== null) {
            if ($limit < 1) {
                throw new QueryException('LIMIT must be a positive integer.');
            }
            $sql .= ' LIMIT ' . $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @param array<string,mixed> $conditions */
    public function count(array $conditions = []): int
    {
        [$where, $params] = $this->buildWhere($conditions);

        $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM ' . $this->table() . ' WHERE ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetch()['c'];
    }

    /** @param array<string,mixed> $conditions */
    public function exists(array $conditions = []): bool
    {
        return $this->count($conditions) > 0;
    }

    /**
     * Insert a row into this tenant. Any tenant_id or id supplied by the
     * caller is discarded — the scope wins, always.
     *
     * @param array<string,mixed> $data
     * @return int Inserted row id.
     */
    public function insert(array $data): int
    {
        $data = $this->filterWritable($data);

        if ($data === []) {
            throw new QueryException('insert() called with no writable columns.');
        }

        $data['tenant_id'] = $this->tenantId;

        $cols = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);

        $sql = 'INSERT INTO ' . $this->table()
             . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a row belonging to this tenant.
     *
     * @param array<string,mixed> $data
     * @return int Rows matched by the scoped WHERE. Zero means the row was not
     *             this tenant's — treat that as a 404, never as a retry.
     */
    public function update(int $id, array $data): int
    {
        $data = $this->filterWritable($data);

        if ($data === []) {
            throw new QueryException('update() called with no writable columns.');
        }

        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = $col . ' = :' . $col;
        }

        $sql = 'UPDATE ' . $this->table() . ' SET ' . implode(', ', $sets)
             . ' WHERE id = :__id AND tenant_id = :__tid';

        $params = $data;
        $params['__id'] = $id;
        $params['__tid'] = $this->tenantId;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /** @return int Rows deleted; zero means the row was not this tenant's. */
    public function delete(int $id): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM ' . $this->table() . ' WHERE id = :__id AND tenant_id = :__tid'
        );
        $stmt->execute(['__id' => $id, '__tid' => $this->tenantId]);

        return $stmt->rowCount();
    }

    // ---------------------------------------------------------------- internals

    /**
     * Build a WHERE clause that always leads with the tenant filter.
     *
     * @param array<string,mixed> $conditions
     * @return array{0: string, 1: array<string,mixed>}
     */
    private function buildWhere(array $conditions): array
    {
        $clauses = ['tenant_id = :__tid'];
        $params = ['__tid' => $this->tenantId];

        $i = 0;
        foreach ($conditions as $col => $value) {
            $col = (string) $col;
            $this->assertColumn($col);

            // tenant_id is not a caller-supplied condition. Silently honouring
            // one would let a caller widen their own scope.
            if ($col === 'tenant_id') {
                throw new QueryException('tenant_id cannot be supplied as a condition; it is always enforced.');
            }

            $key = '__c' . $i++;

            if ($value === null) {
                $clauses[] = $col . ' IS NULL';
                continue;
            }

            if (is_array($value)) {
                if ($value === []) {
                    // An empty IN () is a syntax error in MySQL and semantically
                    // matches nothing. Say so explicitly.
                    $clauses[] = '1 = 0';
                    continue;
                }
                $inKeys = [];
                foreach (array_values($value) as $j => $v) {
                    $inKey = $key . '_' . $j;
                    $inKeys[] = ':' . $inKey;
                    $params[$inKey] = $v;
                }
                $clauses[] = $col . ' IN (' . implode(', ', $inKeys) . ')';
                continue;
            }

            $clauses[] = $col . ' = :' . $key;
            $params[$key] = $value;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Drop anything not on the subclass allowlist, and never let a caller
     * set id or tenant_id.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function filterWritable(array $data): array
    {
        $allowed = array_flip($this->writable());
        unset($allowed['id'], $allowed['tenant_id']);

        $out = [];
        foreach ($data as $col => $value) {
            $col = (string) $col;
            if (!isset($allowed[$col])) {
                continue;
            }
            $this->assertColumn($col);
            $out[$col] = $value;
        }

        return $out;
    }

    /**
     * Column names are concatenated into SQL, so they can never come from
     * unvalidated input.
     */
    private function assertColumn(string $col): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $col)) {
            throw new QueryException('Illegal column name: "' . $col . '".');
        }
    }

    private function safeOrderBy(string $orderBy): string
    {
        $parts = preg_split('/\s+/', trim($orderBy)) ?: [];
        $col = (string) ($parts[0] ?? '');
        $this->assertColumn($col);

        $dir = strtolower((string) ($parts[1] ?? 'asc'));
        if (!in_array($dir, ['asc', 'desc'], true)) {
            throw new QueryException('ORDER BY direction must be asc or desc.');
        }
        if (count($parts) > 2) {
            throw new QueryException('ORDER BY accepts one column and one direction.');
        }

        return $col . ' ' . strtoupper($dir);
    }
}
