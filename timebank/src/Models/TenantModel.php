<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

/**
 * TenantModel — named to avoid collision with TimeBank\Core\Tenant.
 * Manages the tenants table. This model is unique in that it
 * does NOT scope queries to a single tenant_id the way BaseModel does —
 * tenants are the root entity, not a child of another tenant.
 */
class TenantModel
{
    protected string $table = 'tm_tenants';

    // -------------------------------------------------------------------------
    // Lookup
    // -------------------------------------------------------------------------

    public function find(int $id): array|false
    {
        return DB::fetch("SELECT * FROM `{$this->table}` WHERE id = ? LIMIT 1", [$id]);
    }

    public function findBySubdomain(string $subdomain): array|false
    {
        return DB::fetch(
            "SELECT * FROM `{$this->table}` WHERE subdomain = ? AND is_active = 1 LIMIT 1",
            [$subdomain]
        );
    }

    public function all(): array
    {
        return DB::fetchAll("SELECT * FROM `{$this->table}` ORDER BY name ASC");
    }

    // -------------------------------------------------------------------------
    // Settings helpers
    // -------------------------------------------------------------------------

    /**
     * Decode and return the JSON settings blob for a tenant row.
     * Pass in the tenant array (e.g., the result of find() or findBySubdomain()).
     */
    public function getSettings(array $tenant): array
    {
        if (empty($tenant['settings'])) {
            return [];
        }

        $decoded = json_decode($tenant['settings'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Merge $settings into the existing settings blob and persist.
     */
    public function updateSettings(int $id, array $settings): int
    {
        $tenant  = $this->find($id);
        $current = $tenant ? $this->getSettings($tenant) : [];
        $merged  = array_merge($current, $settings);

        return DB::update(
            $this->table,
            ['settings' => json_encode($merged)],
            ['id' => $id]
        );
    }

    /**
     * Get a single setting value by key, with optional default.
     */
    public function getSetting(int $id, string $key, mixed $default = null): mixed
    {
        $tenant = $this->find($id);
        if (!$tenant) {
            return $default;
        }

        $settings = $this->getSettings($tenant);
        return $settings[$key] ?? $default;
    }

    /**
     * Whether self-registration is open for this tenant.
     */
    public function isRegistrationOpen(int $id): bool
    {
        return (bool) $this->getSetting($id, 'allow_self_registration', true);
    }

    /**
     * Whether new registrations require admin approval.
     */
    public function requiresApproval(int $id): bool
    {
        return (bool) $this->getSetting($id, 'require_approval', false);
    }

    // -------------------------------------------------------------------------
    // Mutations
    // -------------------------------------------------------------------------

    public function create(array $data): int|string
    {
        return DB::insert($this->table, $data);
    }

    public function update(int $id, array $data): int
    {
        return DB::update($this->table, $data, ['id' => $id]);
    }

    public function deactivate(int $id): int
    {
        return DB::update($this->table, ['is_active' => 0], ['id' => $id]);
    }

    public function activate(int $id): int
    {
        return DB::update($this->table, ['is_active' => 1], ['id' => $id]);
    }
}
