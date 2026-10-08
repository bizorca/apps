<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Player;

class EconomyService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Process economy changes each turn.
     * More volatile than original - wilder swings, higher inflation tendency.
     * "The economy is just vibes and we're all along for the ride."
     */
    public function processEconomy(object $player): array
    {
        $messages = [];
        $playerId = $player->PlayerID;
        $playerModel = new Player($this->db);

        // Interest Rate changes (more volatile: ±0.75%)
        $interestRateChange = (rand(0, 150) - 75) / 100;
        $newInterestRate = max(1, min(15, $player->InterestRate + $interestRateChange));

        // Inflation Rate changes (tends higher: ±0.50%, floor 2%)
        $inflationChange = (rand(0, 100) - 40) / 100; // Biased upward
        $newInflationRate = max(2, min(12, $player->InflationRate + $inflationChange));

        // CPI changes based on inflation
        $cpiChange = $newInflationRate / 12;
        $newCPI = $player->ConsumerPriceIndex * (1 + $cpiChange / 100);

        // Dollar Conversion (OneDollar tracks cumulative inflation)
        $newOneDollar = $player->OneDollar * (1 + $newInflationRate / 1200);

        // Stock Market Rate (general market sentiment - very volatile)
        $stockMarketChange = (rand(0, 200) - 100) / 100; // ±1.00%
        $newStockMarketRate = $player->StockMarketRate + $stockMarketChange;

        $playerModel->update($playerId, [
            'InterestRate' => round($newInterestRate, 2),
            'InflationRate' => round($newInflationRate, 2),
            'ConsumerPriceIndex' => round($newCPI, 2),
            'OneDollar' => round($newOneDollar, 4),
            'StockMarketRate' => round($newStockMarketRate, 2),
        ]);

        // Inflation milestone messages (sardonic)
        if ($newInflationRate >= 8 && $player->InflationRate < 8) {
            $messages[] = [
                'title' => 'Inflation Alert: Everything Costs More!',
                'body' => '<p>Inflation has hit ' . number_format($newInflationRate, 1) . '%. Your lifestyle just got even more expensive. Your celebrity chef is thrilled - he can justify charging even more for those gold-flaked eggs.</p>',
            ];
        }

        if ($newInterestRate >= 10 && $player->InterestRate < 10) {
            $messages[] = [
                'title' => 'Interest Rates Soaring!',
                'body' => '<p>Interest rates have hit ' . number_format($newInterestRate, 1) . '%. On the bright side, your rapidly shrinking bank balance is earning slightly more interest. Silver linings!</p>',
            ];
        }

        return $messages;
    }

    public function getEconomyData(object $player): array
    {
        return [
            'interestRate' => $player->InterestRate,
            'inflationRate' => $player->InflationRate,
            'cpi' => $player->ConsumerPriceIndex,
            'oneDollar' => $player->OneDollar,
            'stockMarketRate' => $player->StockMarketRate,
        ];
    }
}
