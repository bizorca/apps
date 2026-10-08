<?php

namespace App\Models;

use App\Core\Database;

class SiteSettings
{
    private Database $db;
    private static array $cache = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function get(string $key, string $default = ''): string
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $row = $this->db->fetch(
            "SELECT setting_value FROM br_site_settings WHERE setting_key = ? LIMIT 1",
            [$key]
        );
        $value = $row ? $row->setting_value : $default;
        self::$cache[$key] = $value;
        return $value;
    }

    public function set(string $key, string $value): void
    {
        $this->db->query(
            "INSERT INTO br_site_settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
            [$key, $value]
        );
        self::$cache[$key] = $value;
    }

    public function getAll(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM br_site_settings ORDER BY setting_key");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row->setting_key] = $row->setting_value;
            self::$cache[$row->setting_key] = $row->setting_value;
        }
        return $settings;
    }

    public function isRegistrationOpen(): bool
    {
        return (bool) $this->get('registration_open', '1');
    }

    public function isStripeEnabled(): bool
    {
        return (bool) $this->get('stripe_enabled', '0');
    }
}
