<?php

namespace App\Services;

use App\Models\Bank;
use App\Core\Database;

class BankService
{
    private Bank $bank;
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->bank = new Bank($db);
    }

    public function getBalance(int $playerId): float
    {
        return $this->bank->getBalance($playerId);
    }

    public function credit(int $playerId, int $turn, string $desc, string $type, float $amount, int $recordId = 0): float
    {
        return $this->bank->addCredit($playerId, $turn, $desc, $type, $amount, $recordId);
    }

    public function debit(int $playerId, int $turn, string $desc, string $type, float $amount, int $recordId = 0): float
    {
        return $this->bank->addDebit($playerId, $turn, $desc, $type, $amount, $recordId);
    }

    public function getRecentTransactions(int $playerId, int $limit = 20): array
    {
        return $this->bank->getRecentTransactions($playerId, $limit);
    }

    public function getBalanceHistory(int $playerId, int $limit = 50): array
    {
        return $this->bank->getBalanceHistory($playerId, $limit);
    }

    /**
     * Calculate monthly burn rate from all sources.
     * In Burn Rate, this shows how fast you're hemorrhaging money.
     */
    public function calculateMonthlyBurn(int $playerId, object $player): float
    {
        $burn = 0.0;

        // Base lifestyle burn
        $burn += $player->LifestyleBurn;

        // Vanity project net cost
        $burn += ($player->VanityExpenses - $player->VanityIncome);

        // Property costs
        $reModel = new \App\Models\RealEstate($this->db);
        $properties = $reModel->getOwned($playerId);
        foreach ($properties as $prop) {
            // Staff costs
            $burn += $prop->StaffCost ?? 0;
            // Maintenance
            $avgRating = ($prop->RoofRating + $prop->KitchenRating + $prop->BathroomsRating
                        + $prop->FlooringRating + $prop->PaintRating + $prop->MajorSystemsRating) / 6;
            $burn += (100 - $avgRating) / 100 * $prop->CurrentValue * 0.003;
            // Insurance (2% annually)
            $burn += (0.02 * $prop->CurrentValue) / 12;
            // Property tax
            $burn += (($prop->TaxRate / 100) * $prop->CurrentValue) / 12;
        }

        // Toy ongoing costs
        $stockModel = new \App\Models\Stock($this->db);
        $toys = $stockModel->getOwned($playerId);
        foreach ($toys as $toy) {
            if ($toy->NumberShares > 0) {
                $burn += $toy->MonthlyCost ?? 0;
            }
        }

        return $burn;
    }
}
