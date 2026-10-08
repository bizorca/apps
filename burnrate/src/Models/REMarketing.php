<?php

namespace App\Models;

use App\Core\Database;

class REMarketing
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getAll(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_remarketing WHERE PlayerID = {$playerId} ORDER BY REMarketingID"
        );
    }

    public function findById(int $playerId, int $id): ?object
    {
        return $this->db->fetch(
            "SELECT * FROM br_remarketing WHERE PlayerID = {$playerId} AND REMarketingID = ? LIMIT 1",
            [$id]
        );
    }

    public function create(int $playerId, array $data): int
    {
        return $this->db->insert('br_remarketing', ['PlayerID' => $playerId] + $data);
    }

    public function update(int $playerId, int $id, array $data): int
    {
        return $this->db->update('br_remarketing', $data, 'REMarketingID = ? AND PlayerID = ?', [$id, $playerId]);
    }

    public function delete(int $playerId, int $id): int
    {
        return $this->db->delete('br_remarketing', 'REMarketingID = ? AND PlayerID = ?', [$id, $playerId]);
    }

    public function count(int $playerId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM br_remarketing WHERE PlayerID = {$playerId}"
        );
    }
}
