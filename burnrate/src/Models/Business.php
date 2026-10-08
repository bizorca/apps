<?php

namespace App\Models;

use App\Core\Database;

class Business
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getAll(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_businesses WHERE PlayerID = {$playerId} ORDER BY BusinessID"
        );
    }

    public function findById(int $playerId, int $bizId): ?object
    {
        return $this->db->fetch(
            "SELECT * FROM br_businesses WHERE PlayerID = {$playerId} AND BusinessID = ? LIMIT 1",
            [$bizId]
        );
    }

    public function create(int $playerId, array $data): int
    {
        return $this->db->insert('br_businesses', ['PlayerID' => $playerId] + $data);
    }

    public function update(int $playerId, int $bizId, array $data): int
    {
        return $this->db->update('br_businesses', $data, 'BusinessID = ? AND PlayerID = ?', [$bizId, $playerId]);
    }

    public function delete(int $playerId, int $bizId): int
    {
        return $this->db->delete('br_businesses', 'BusinessID = ? AND PlayerID = ?', [$bizId, $playerId]);
    }

    public function count(int $playerId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM br_businesses WHERE PlayerID = {$playerId}"
        );
    }

    public function getRandomDeal(): ?object
    {
        return $this->db->fetch(
            "SELECT * FROM br_badinvestments ORDER BY RAND() LIMIT 1"
        );
    }

    public function getProfitLoss(int $playerId, int $bizId, float $oneDollar): float
    {
        $biz = $this->findById($playerId, $bizId);
        if (!$biz) return 0.0;

        return ($biz->NumberClients * $biz->RevenuePerClientPerTurn * $oneDollar)
             - ($biz->ExpensesPerTurn * $oneDollar)
             - $biz->AdvertisingPerTurn;
    }

    public function getAllDeals(int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_badinvestments ORDER BY InvestmentID LIMIT ?",
            [$limit]
        );
    }
}
