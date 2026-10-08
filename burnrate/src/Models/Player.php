<?php

namespace App\Models;

use App\Core\Database;

class Player
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $playerId): ?object
    {
        return $this->db->fetch(
            "SELECT * FROM br_players WHERE PlayerID = ? LIMIT 1",
            [$playerId]
        );
    }

    public function findByOwner(int $ownerId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM br_players WHERE OwnerID = ? ORDER BY PlayerID",
            [$ownerId]
        );
    }

    public function findByOwnerAndId(int $ownerId, int $playerId): ?object
    {
        return $this->db->fetch(
            "SELECT * FROM br_players WHERE OwnerID = ? AND PlayerID = ? LIMIT 1",
            [$ownerId, $playerId]
        );
    }

    public function create(int $ownerId, string $playerName): int
    {
        $playerId = $this->db->insert('br_players', [
            'OwnerID' => $ownerId,
            'PlayerName' => $playerName,
            'CreatedDate' => date('Y-m-d H:i:s'),
            'LastPlayedDate' => date('Y-m-d H:i:s'),
            'Turn' => 1,
            'TurnAction' => 0,
            'TurnProcessedThrough' => 0,
            'ShowTab' => 'summary',
            'VanityIncome' => 2500.00,
            'VanityExpenses' => 15000.00,
            'LifestyleBurn' => 500000.00,
            'BankruptTurn' => 0,
            'InterestRate' => 6.00,
            'InflationRate' => 4.00,
            'DollarConversion' => 1.00,
            'ConsumerPriceIndex' => 100.00,
            'OneDollar' => 1.00,
            'StockMarketRate' => 0.00,
            'PersonalShopperRating' => 0.00,
            'PartyPlannerRating' => 0.00,
            'YesManRating' => 0.00,
            'CelebrityChefRating' => 0.00,
            'FashionConsultantRating' => 0.00,
            'ArtDealerRating' => 0.00,
            'InteriorDesignerRating' => 0.00,
            'TravelAgentRating' => 0.00,
            'SocialMediaManagerRating' => 0.00,
            'LifeCoachRating' => 0.00,
            'AstrologerRating' => 0.00,
            'ConciergeRating' => 0.00,
            'ActiveCrisis' => '',
            'CrisisTurnsRemaining' => 0,
            'GameLogProcessedThrough' => 0,
        ]);

        // Insert initial bank balance - $1 BILLION inheritance
        $this->db->insert('br_bank', [
            'PlayerID' => $playerId,
            'Turn' => 1,
            'Description' => 'Inheritance from dear old Dad (R.I.P. your finances)',
            'EntryType' => 'INHERIT',
            'RecordID' => 0,
            'Credit' => 1000000000.00,
            'Debit' => 0,
            'Balance' => 1000000000.00,
        ]);

        return $playerId;
    }

    /**
     * The original created six tables per player (Bank{id}, RE{id}, ...) and
     * dropped them here. They are shared br_ tables keyed by PlayerID now, and
     * every one cascades from br_players, so deleting the row is the whole job.
     */
    public function deleteWithTables(int $ownerId, int $playerId): bool
    {
        $player = $this->findByOwnerAndId($ownerId, $playerId);
        if (!$player) return false;

        $this->db->delete('br_players', 'PlayerID = ? AND OwnerID = ?', [$playerId, $ownerId]);
        return true;
    }

    public function update(int $playerId, array $data): int
    {
        return $this->db->update('br_players', $data, 'PlayerID = ?', [$playerId]);
    }

    /**
     * Top 10: Fastest to go bankrupt wins.
     * Players who went bankrupt are ranked first (lowest BankruptTurn = best).
     * Players still rich are ranked last (losers) by highest Turn (most time wasted still rich).
     */
    public function getTop10(): array
    {
        // First get bankrupt players (winners) sorted by fastest
        $bankrupt = $this->db->fetchAll(
            "SELECT p.PlayerID, p.PlayerName, p.Turn, p.BankruptTurn, p.OwnerID,
                    SUBSTRING_INDEX(u.name, ' ', 1) AS FirstName,
                    TRIM(SUBSTRING(u.name, LENGTH(SUBSTRING_INDEX(u.name, ' ', 1)) + 1)) AS LastName
             FROM br_players p
             JOIN users u ON p.OwnerID = u.id
             WHERE p.BankruptTurn > 0
             ORDER BY p.BankruptTurn ASC, p.PlayerID ASC
             LIMIT 10"
        );

        // If fewer than 10 bankrupt, fill with still-rich players (shame section)
        if (count($bankrupt) < 10) {
            $remaining = 10 - count($bankrupt);
            $stillRich = $this->db->fetchAll(
                "SELECT p.PlayerID, p.PlayerName, p.Turn, p.BankruptTurn, p.OwnerID,
                        SUBSTRING_INDEX(u.name, ' ', 1) AS FirstName,
                        TRIM(SUBSTRING(u.name, LENGTH(SUBSTRING_INDEX(u.name, ' ', 1)) + 1)) AS LastName
                 FROM br_players p
                 JOIN users u ON p.OwnerID = u.id
                 WHERE p.BankruptTurn = 0
                 ORDER BY p.Turn DESC, p.PlayerID ASC
                 LIMIT {$remaining}"
            );
            $bankrupt = array_merge($bankrupt, $stillRich);
        }

        return $bankrupt;
    }

    public function count(): int
    {
        return (int) $this->db->fetchColumn("SELECT COUNT(*) FROM br_players");
    }
}
