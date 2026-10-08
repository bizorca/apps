<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Business;
use App\Models\Player;

class InvestmentService
{
    private Database $db;
    private Business $bizModel;
    private BankService $bankService;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->bizModel = new Business($db);
        $this->bankService = new BankService($db);
    }

    /**
     * Get a random bad investment deal for the player to consider.
     * Astrologers make investments cost MORE - "The stars say this is THE one!"
     */
    public function getRandomDeal(object $player, int $memberLevel = 0): ?array
    {
        $deal = $this->db->fetch(
            "SELECT * FROM br_badinvestments WHERE MinTier <= ? ORDER BY RAND() LIMIT 1",
            [$memberLevel]
        );
        if (!$deal) return null;

        // Adjust for inflation
        $cost = round($deal->InitialInvestment * $player->OneDollar, 2);

        // Astrologer effect: costs MORE (up to 15% markup)
        $astrologerMarkup = 1 + (0.15 * $player->AstrologerRating / 100);
        $cost = round($cost * $astrologerMarkup, 2);

        return [
            'deal' => $deal,
            'adjustedCost' => $cost,
        ];
    }

    /**
     * Make a terrible investment. Your financial advisor (who doesn't exist) would be horrified.
     */
    public function makeInvestment(object $player, int $dealId): array
    {
        $playerId = $player->PlayerID;

        // Get the deal
        $deal = $this->db->fetch("SELECT * FROM br_badinvestments WHERE InvestmentID = ? LIMIT 1", [$dealId]);
        if (!$deal) return ['error' => 'Investment opportunity not found. It probably already went bankrupt.'];

        // Tier gate. The original only applied MinTier when picking a random
        // deal, so posting any deal_id to /game/biz/start bought a Gold
        // Digger-only investment on a Freeloader account.
        $owner = $this->db->fetch("SELECT MemberLevel FROM br_owners WHERE OwnerID = ? LIMIT 1", [(int) $player->OwnerID]);
        if ((int) $deal->MinTier > (int) ($owner->MemberLevel ?? 0)) {
            return ['error' => 'Investment opportunity not found. It probably already went bankrupt.'];
        }

        // Calculate adjusted cost (inflation + astrologer markup)
        $cost = round($deal->InitialInvestment * $player->OneDollar, 2);
        $astrologerMarkup = 1 + (0.15 * $player->AstrologerRating / 100);
        $cost = round($cost * $astrologerMarkup, 2);

        // Check funds
        $balance = $this->bankService->getBalance($playerId);
        if ($balance < $cost) {
            return ['error' => sprintf(
                'Insufficient funds. Investment cost: $%s, Balance: $%s. Even your bad decisions require money.',
                number_format($cost, 2),
                number_format($balance, 2)
            )];
        }

        // Debit the investment cost
        $this->bankService->debit(
            $playerId, $player->Turn,
            "Invested in: {$deal->ShortDescription}",
            'INVEST', $cost
        );

        // Create investment record in Businesses{playerId} table
        $bizId = $this->bizModel->create($playerId, [
            'Turn' => $player->Turn,
            'ShortDescription' => $deal->ShortDescription,
            'LongDescription' => $deal->LongDescription ?? '',
            'PurchasePrice' => $cost,
            'PurchaseTurn' => $player->Turn,
            'StartupCost' => $cost,
            'CurrentValue' => $cost,
            'ExpensesPerTurn' => $deal->MonthlyFees,
            'Category' => $deal->Category,
            'NumberClients' => 100,
            'MaxNumberClients' => 100,
            'PercentClientsLostPerTurn' => $deal->CatastropheChance ?? 0,
            'AdvertisingPerTurn' => 0,
            'RevenuePerClientPerTurn' => 0,
            'AdvertisingEffectiveness' => $deal->VolatilityFactor ?? 1.0,
        ]);

        // Reset turn action & advance turn
        $playerModel = new Player($this->db);
        $playerModel->update($playerId, [
            'TurnAction' => 0,
            'Turn' => $player->Turn + 1,
        ]);

        return [
            'title' => 'Investment Made!',
            'body' => sprintf(
                '<p>Congratulations! You\'ve invested $%s in <b>%s</b>.</p>
                 <p>Your financial advisor (who doesn\'t exist) would be horrified.</p>',
                number_format($cost, 2),
                htmlspecialchars($deal->ShortDescription)
            ),
        ];
    }

    /**
     * Process all investments each turn.
     * Spoiler: they mostly lose money, with occasional false hope.
     */
    public function processTurn(object $player): array
    {
        $messages = [];
        $playerId = $player->PlayerID;
        $investments = $this->bizModel->getAll($playerId);
        $oneDollar = $player->OneDollar;

        foreach ($investments as $inv) {
            $name = $inv->ShortDescription;
            $volatilityFactor = (float) $inv->AdvertisingEffectiveness;
            $catastropheChance = (float) $inv->PercentClientsLostPerTurn;
            $currentValue = (float) $inv->CurrentValue;

            // --- 1. Monthly management fees ---
            $fees = round($inv->ExpensesPerTurn * $oneDollar, 2);
            if ($fees > 0) {
                $this->bankService->debit(
                    $playerId, $player->Turn,
                    "Management Fees - {$name}",
                    'INVEST', $fees, $inv->BusinessID
                );
            }

            // --- 2. Value changes (WILD SWINGS) ---
            $roll = rand(1, 100);
            $percentChange = 0;

            if ($roll <= 40) {
                // 40%: lose 2-10% scaled by volatility
                $baseLoss = rand(200, 1000) / 100;
                $percentChange = -$baseLoss * $volatilityFactor;
            } elseif ($roll <= 70) {
                // 30%: lose 0-2% (treading water)
                $percentChange = -(rand(0, 200) / 100);
            } elseif ($roll <= 90) {
                // 20%: gain 1-5% (false hope!)
                $percentChange = rand(100, 500) / 100;
            } else {
                // 10%: gain 5-15% (the rally before the crash!)
                $percentChange = rand(500, 1500) / 100;
            }

            $newValue = max(0, round($currentValue * (1 + $percentChange / 100), 2));
            $gainAmount = $newValue - $currentValue;

            // Success fee on gains (because of course there's a fee)
            if ($gainAmount > 0) {
                $successFee = round($gainAmount * 0.20, 2);
                $this->bankService->debit(
                    $playerId, $player->Turn,
                    "Performance Fee - {$name}",
                    'INVEST', $successFee, $inv->BusinessID
                );
            }

            $currentValue = $newValue;

            // --- 3. Catastrophe check ---
            if ($catastropheChance > 0 && rand(1, 100) <= $catastropheChance) {
                $catastropheLoss = rand(30, 80);
                $currentValue = max(0, round($currentValue * (1 - $catastropheLoss / 100), 2));

                $catastropheMessage = $this->getCatastropheMessage($inv->Category, $name, $catastropheLoss);
                $messages[] = [
                    'title' => "BREAKING NEWS",
                    'body' => $catastropheMessage,
                ];
            }

            // --- 4. Total wipeout check ---
            if ($currentValue < ($inv->PurchasePrice * 0.01)) {
                $currentValue = 0;
                $messages[] = [
                    'title' => "Investment Wiped Out",
                    'body' => sprintf(
                        '<p><b>%s</b> has officially gone to zero. But hey, you got a great tax write-off! (Just kidding, you don\'t have an accountant.)</p>',
                        htmlspecialchars($name)
                    ),
                ];
            }

            // --- 5. Update CurrentValue ---
            $this->bizModel->update($playerId, $inv->BusinessID, [
                'CurrentValue' => $currentValue,
            ]);
        }

        return $messages;
    }

    /**
     * Generate a catastrophe message based on investment category.
     */
    private function getCatastropheMessage(string $category, string $name, int $lossPercent): string
    {
        $escapedName = htmlspecialchars($name);

        $flavorText = match (strtolower($category)) {
            'crypto' => "The exchange got 'hacked' (the CEO fled to Belize)",
            'startup' => "The founder pivoted to selling NFTs of their resignation letter",
            'hedge_fund' => "Turns out the 'hedge' was just a shrubbery",
            'vc_fund' => "All 47 portfolio companies pivoted to AI and then pivoted to bankruptcy",
            'nft' => "Someone right-clicked and saved your entire collection",
            default => "Something terrible happened, but nobody can explain what",
        };

        return sprintf(
            '<p>BREAKING: <b>%s</b> just lost %d%% of its value!</p>
             <p>Your fund manager says "these things happen."</p>
             <p><em>%s</em></p>',
            $escapedName,
            $lossPercent,
            $flavorText
        );
    }

    /**
     * Liquidate an investment with early withdrawal penalty.
     * Because even leaving costs money.
     */
    public function liquidate(object $player, int $bizId): array
    {
        $playerId = $player->PlayerID;
        $inv = $this->bizModel->findById($playerId, $bizId);

        if (!$inv) return ['error' => 'Investment not found. Perhaps it already evaporated.'];

        $currentValue = (float) $inv->CurrentValue;
        $purchasePrice = (float) $inv->PurchasePrice;

        // 5% early withdrawal penalty
        $penalty = round($currentValue * 0.05, 2);
        $proceeds = round($currentValue - $penalty, 2);

        if ($proceeds > 0) {
            $this->bankService->credit(
                $playerId, $player->Turn,
                "Liquidated: {$inv->ShortDescription}",
                'INVEST', $proceeds, $bizId
            );
        }

        // Delete the investment record
        $this->bizModel->delete($playerId, $bizId);

        $totalLoss = $purchasePrice - $proceeds;

        return [
            'title' => 'Investment Liquidated',
            'body' => sprintf(
                '<p>You liquidated <b>%s</b>.</p>
                 <p>Original Investment: $%s</p>
                 <p>Current Value: $%s</p>
                 <p>Early Withdrawal Penalty (5%%): -$%s</p>
                 <p>You Received: $%s</p>
                 <p>Total Loss: <span class="text-red-600">-$%s</span></p>
                 <p><em>Your money is in a better place now. (It\'s gone.)</em></p>',
                htmlspecialchars($inv->ShortDescription),
                number_format($purchasePrice, 2),
                number_format($currentValue, 2),
                number_format($penalty, 2),
                number_format($proceeds, 2),
                number_format($totalLoss, 2)
            ),
        ];
    }

    /**
     * Get a summary of the player's investment portfolio.
     * It's like a report card, but worse.
     */
    public function getPortfolioSummary(int $playerId, float $oneDollar): array
    {
        $investments = $this->bizModel->getAll($playerId);
        $totalCurrentValue = 0;
        $totalInvested = 0;
        $totalMonthlyFees = 0;

        foreach ($investments as $inv) {
            $totalCurrentValue += (float) $inv->CurrentValue;
            $totalInvested += (float) $inv->PurchasePrice;
            $totalMonthlyFees += (float) $inv->ExpensesPerTurn * $oneDollar;
        }

        $totalLoss = $totalInvested - $totalCurrentValue;

        return [
            'count' => count($investments),
            'totalCurrentValue' => $totalCurrentValue,
            'totalInvested' => $totalInvested,
            'totalLoss' => $totalLoss,
            'totalMonthlyFees' => $totalMonthlyFees,
            'investments' => $investments,
        ];
    }
}
