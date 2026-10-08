<?php

namespace App\Models;

use App\Core\Database;

class Stock
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getAll(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_stocks WHERE PlayerID = {$playerId} ORDER BY StockID"
        );
    }

    public function getOwned(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_stocks WHERE PlayerID = {$playerId} AND NumberShares > 0 ORDER BY StockID"
        );
    }

    public function findById(int $playerId, int $stockId): ?object
    {
        return $this->db->fetch(
            "SELECT * FROM br_stocks WHERE PlayerID = {$playerId} AND StockID = ? LIMIT 1",
            [$stockId]
        );
    }

    public function create(int $playerId, array $data): int
    {
        return $this->db->insert('br_stocks', ['PlayerID' => $playerId] + $data);
    }

    public function update(int $playerId, int $stockId, array $data): int
    {
        return $this->db->update('br_stocks', $data, 'StockID = ? AND PlayerID = ?', [$stockId, $playerId]);
    }

    public function addPriceHistory(int $playerId, int $stockId, float $price, int $turn): void
    {
        $this->db->insert('br_stockdata', [
            'PlayerID' => $playerId,
            'StockID' => $stockId,
            'StockPrice' => $price,
            'Turn' => $turn,
        ]);
    }

    public function getPriceHistory(int $playerId, int $stockId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_stockdata WHERE PlayerID = {$playerId} AND StockID = ? ORDER BY Turn DESC LIMIT ?",
            [$stockId, $limit]
        );
    }

    public function getTotalValue(int $playerId): float
    {
        $row = $this->db->fetch(
            "SELECT SUM(NumberShares * StockPrice) as total
             FROM br_stocks WHERE PlayerID = {$playerId} AND NumberShares > 0"
        );
        return $row ? (float) ($row->total ?? 0) : 0.0;
    }

    public function countOwned(int $playerId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM br_stocks WHERE PlayerID = {$playerId} AND NumberShares > 0"
        );
    }

    public function getWithOrders(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_stocks WHERE PlayerID = {$playerId} AND OrderType > 0 ORDER BY StockID"
        );
    }
}
