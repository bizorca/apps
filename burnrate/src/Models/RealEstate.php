<?php

namespace App\Models;

use App\Core\Database;

class RealEstate
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getForSale(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_re WHERE PlayerID = {$playerId} AND Status IN (1, 21) ORDER BY REID"
        );
    }

    public function getOwned(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_re WHERE PlayerID = {$playerId} AND Status > 7 AND Status < 21 ORDER BY REID"
        );
    }

    public function getAll(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_re WHERE PlayerID = {$playerId} ORDER BY REID"
        );
    }

    public function findById(int $playerId, int $reId): ?object
    {
        return $this->db->fetch(
            "SELECT * FROM br_re WHERE PlayerID = {$playerId} AND REID = ? LIMIT 1",
            [$reId]
        );
    }

    public function create(int $playerId, array $data): int
    {
        return $this->db->insert('br_re', ['PlayerID' => $playerId] + $data);
    }

    public function update(int $playerId, int $reId, array $data): int
    {
        return $this->db->update('br_re', $data, 'REID = ? AND PlayerID = ?', [$reId, $playerId]);
    }

    public function delete(int $playerId, int $reId): int
    {
        return $this->db->delete('br_re', 'REID = ? AND PlayerID = ?', [$reId, $playerId]);
    }

    public function countOwned(int $playerId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM br_re WHERE PlayerID = {$playerId} AND Status > 7 AND Status < 21"
        );
    }

    public function getTotalEquity(int $playerId): float
    {
        $row = $this->db->fetch(
            "SELECT SUM(CurrentValue - LoanBalance) as equity
             FROM br_re WHERE PlayerID = {$playerId} AND Status > 7 AND Status < 21"
        );
        return $row ? (float) ($row->equity ?? 0) : 0.0;
    }

    public function getByStatus(int $playerId, int $status): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_re WHERE PlayerID = {$playerId} AND Status = ? ORDER BY REID",
            [$status]
        );
    }

    public function getPendingOffers(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_re WHERE PlayerID = {$playerId} AND Status IN (2, 3, 4, 5, 6, 7) ORDER BY REID"
        );
    }
}
