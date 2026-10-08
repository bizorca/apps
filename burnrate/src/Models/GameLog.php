<?php

namespace App\Models;

use App\Core\Database;

class GameLog
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function log(array $data): int
    {
        return $this->db->insert('br_gamelog', $data);
    }

    public function getByPlayer(int $playerId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_gamelog WHERE PlayerID = ? ORDER BY Turn DESC LIMIT ?",
            [$playerId, $limit]
        );
    }

    public function getNetWorthHistory(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT Turn, NetWorth, IANetWorth, BankBalance, PropertyNetWorth, ToyNetWorth,
                    InterestRate, InflationRate
             FROM br_gamelog WHERE PlayerID = ? ORDER BY Turn",
            [$playerId]
        );
    }

    public function getLatest(int $playerId): ?object
    {
        return $this->db->fetch(
            "SELECT * FROM br_gamelog WHERE PlayerID = ? ORDER BY Turn DESC LIMIT 1",
            [$playerId]
        );
    }
}
