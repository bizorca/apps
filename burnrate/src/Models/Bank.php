<?php

namespace App\Models;

use App\Core\Database;

class Bank
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getBalance(int $playerId): float
    {
        $row = $this->db->fetch(
            "SELECT Balance FROM br_bank WHERE PlayerID = {$playerId} ORDER BY BankID DESC LIMIT 1"
        );
        return $row ? (float) $row->Balance : 0.0;
    }

    public function addCredit(int $playerId, int $turn, string $description, string $entryType, float $amount, int $recordId = 0): float
    {
        $balance = $this->getBalance($playerId);
        $newBalance = $balance + $amount;

        $this->db->insert('br_bank', [
            'PlayerID' => $playerId,
            'Turn' => $turn,
            'Description' => $description,
            'EntryType' => $entryType,
            'RecordID' => $recordId,
            'Credit' => $amount,
            'Debit' => 0,
            'Balance' => $newBalance,
        ]);

        return $newBalance;
    }

    public function addDebit(int $playerId, int $turn, string $description, string $entryType, float $amount, int $recordId = 0): float
    {
        $balance = $this->getBalance($playerId);
        $newBalance = $balance - $amount;

        $this->db->insert('br_bank', [
            'PlayerID' => $playerId,
            'Turn' => $turn,
            'Description' => $description,
            'EntryType' => $entryType,
            'RecordID' => $recordId,
            'Credit' => 0,
            'Debit' => $amount,
            'Balance' => $newBalance,
        ]);

        return $newBalance;
    }

    public function getRecentTransactions(int $playerId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_bank WHERE PlayerID = {$playerId} ORDER BY BankID DESC LIMIT ?",
            [$limit]
        );
    }

    public function getTransactionsByTurn(int $playerId, int $turn): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_bank WHERE PlayerID = {$playerId} AND Turn = ? ORDER BY BankID",
            [$turn]
        );
    }

    public function getAllTransactions(int $playerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_bank WHERE PlayerID = {$playerId} ORDER BY BankID"
        );
    }

    public function getBalanceHistory(int $playerId, int $limit = 50): array
    {
        // Get the last transaction per turn for chart data
        return $this->db->fetchAll(
            "SELECT Turn, Balance FROM br_bank WHERE PlayerID = {$playerId}
             AND BankID IN (
                SELECT MAX(BankID) FROM br_bank WHERE PlayerID = {$playerId} GROUP BY Turn
             )
             ORDER BY Turn DESC LIMIT ?",
            [$limit]
        );
    }
}
