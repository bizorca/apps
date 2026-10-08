<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Stock;
use App\Models\Player;

class ToyService
{
    private Database $db;
    private Stock $stockModel;
    private BankService $bankService;

    private const TOY_CATEGORIES = [
        0 => 'Supercar',
        1 => 'Megayacht',
        2 => 'Private Jet',
        3 => 'Fine Art',
        4 => 'Racehorse',
        5 => 'Space Tourism',
        6 => 'NFT Collection',
        7 => 'Designer Wardrobe',
        8 => 'Exotic Car Fleet',
        9 => 'Wine Collection',
    ];

    private const TOY_NAMES = [
        // Supercars (category 0)
        'Ferrari SF90',
        'Bugatti Chiron',
        'Lamborghini Revuelto',
        'Pagani Huayra',
        'Koenigsegg Jesko',
        'McLaren Speedtail',
        // Megayachts (category 1)
        'MY Excess',
        'SS Money Pit',
        'HMS Depreciation',
        'The Floating Regret',
        'Aqua Bankruptcy',
        // Private Jets (category 2)
        'G700 "Sky Burner"',
        'Global 8000 "Cash Vaporizer"',
        'BBJ "Flying Fortune"',
        // Fine Art (category 3)
        'Pollock Splatter #47',
        'Banksy (Probably Fake)',
        'Gold-Leaf Nothing',
        'Abstract Confusion',
        // Racehorses (category 4)
        'Debt Runner',
        'Fiscal Folly',
        'Margin Call',
        'Negative Returns',
        // Space Tourism (category 5)
        'Orbital Ticket Alpha',
        'Moon Vacation Voucher',
        'Mars One-Way',
        // NFT Collection (category 6)
        'Bored Billionaire #4201',
        'CryptoPunk Knockoff',
        'AI-Generated Nonsense',
        // Designer Wardrobe (category 7)
        'Supreme Everything',
        'Hermes Closet Full',
        'Seasonal Couture Set',
        // Exotic Car Fleet (category 8)
        'The 12-Car Garage',
        'Mood-Based Fleet',
        'One For Each Day',
        // Wine Collection (category 9)
        'Chateau Overpriced 1982',
        'Probably Vinegar Reserve',
        '10000 Bottle Cellar',
    ];

    /** Premium toys unlocked at Silver Spoon tier (level 16+). */
    private const PREMIUM_TOY_NAMES = [
        'Luxury Personal Submarine',
        'Supersonic Business Jet (Mach 2)',
        'Private Space Shuttle Seat (reserved)',
        'Custom 200m Giga-Yacht',
        'Attack Helicopter (Civilian Paint)',
        'Armored Hypercar — One-of-One',
        'Underwater Hotel Suite — One Week Buyout',
        'Zero-G Flight Experience (12-pack)',
    ];

    private const PREMIUM_TOY_NAME_CATEGORIES = [
        'Luxury Personal Submarine'              => 1,
        'Supersonic Business Jet (Mach 2)'       => 2,
        'Private Space Shuttle Seat (reserved)'  => 5,
        'Custom 200m Giga-Yacht'                 => 1,
        'Attack Helicopter (Civilian Paint)'     => 2,
        'Armored Hypercar — One-of-One'          => 0,
        'Underwater Hotel Suite — One Week Buyout' => 3,
        'Zero-G Flight Experience (12-pack)'     => 5,
    ];

    /** Maps each toy name to its category ID */
    private const TOY_NAME_CATEGORIES = [
        'Ferrari SF90' => 0, 'Bugatti Chiron' => 0, 'Lamborghini Revuelto' => 0,
        'Pagani Huayra' => 0, 'Koenigsegg Jesko' => 0, 'McLaren Speedtail' => 0,
        'MY Excess' => 1, 'SS Money Pit' => 1, 'HMS Depreciation' => 1,
        'The Floating Regret' => 1, 'Aqua Bankruptcy' => 1,
        'G700 "Sky Burner"' => 2, 'Global 8000 "Cash Vaporizer"' => 2, 'BBJ "Flying Fortune"' => 2,
        'Pollock Splatter #47' => 3, 'Banksy (Probably Fake)' => 3,
        'Gold-Leaf Nothing' => 3, 'Abstract Confusion' => 3,
        'Debt Runner' => 4, 'Fiscal Folly' => 4, 'Margin Call' => 4, 'Negative Returns' => 4,
        'Orbital Ticket Alpha' => 5, 'Moon Vacation Voucher' => 5, 'Mars One-Way' => 5,
        'Bored Billionaire #4201' => 6, 'CryptoPunk Knockoff' => 6, 'AI-Generated Nonsense' => 6,
        'Supreme Everything' => 7, 'Hermes Closet Full' => 7, 'Seasonal Couture Set' => 7,
        'The 12-Car Garage' => 8, 'Mood-Based Fleet' => 8, 'One For Each Day' => 8,
        'Chateau Overpriced 1982' => 9, 'Probably Vinegar Reserve' => 9, '10000 Bottle Cellar' => 9,
    ];

    /** Sardonic purchase messages by category */
    private const PURCHASE_QUIPS = [
        0 => [
            "Congratulations, it lost 15%% of its value just reading this message.",
            "Nothing says 'fiscally responsible' like a car that costs more than most houses.",
            "The insurance alone could feed a small country. But you do you.",
        ],
        1 => [
            "Welcome aboard! The crew costs more than most people earn in a lifetime.",
            "It's not sinking -- that's just your net worth doing that.",
            "A yacht: because burning money on land wasn't scenic enough.",
        ],
        2 => [
            "Wheels up! Your accountant's blood pressure is also up.",
            "The hangar fees alone could fund a space program. Oh wait, that's category 5.",
            "Nothing like burning jet fuel to burn through a fortune.",
        ],
        3 => [
            "Is it upside down? Nobody knows. That's the beauty of modern art.",
            "You're not buying art, you're buying a conversation piece about poor decisions.",
            "The frame probably cost more than most people's cars.",
        ],
        4 => [
            "It eats money for breakfast. Literally. Have you seen hay prices?",
            "A horse: the original money pit, now with added veterinary bills.",
            "May your horse run fast, because your money certainly does.",
        ],
        5 => [
            "Launch delayed again. But your payment wasn't!",
            "To infinity and beyond! Your bank balance, however, only goes one direction.",
            "Space: the final frontier of irresponsible spending.",
        ],
        6 => [
            "You now own a unique receipt for a picture anyone can screenshot. Congrats.",
            "Right-click, save as... just kidding, this is very serious digital art.",
            "The blockchain will remember this purchase forever. So will your accountant.",
        ],
        7 => [
            "It'll be out of style next season. But the debt? That's timeless.",
            "Your closet is now worth more than some startup valuations.",
            "Fashion fades, but the credit card statement is eternal.",
        ],
        8 => [
            "One car per mood. Finally, a practical solution.",
            "The garage costs more than the cars. Just kidding -- nothing costs more than the cars.",
            "You can only drive one at a time, but why would that stop you?",
        ],
        9 => [
            "Aged to perfection. Your financial decisions, however, have not.",
            "It pairs beautifully with regret and a side of bankruptcy.",
            "A cellar full of wine and a wallet full of nothing. Chef's kiss.",
        ],
    ];

    /** Sardonic sell messages */
    private const SELL_QUIPS = [
        "Another beautiful loss. Your accountant sends their condolences.",
        "Sold at a loss, as is tradition.",
        "The buyer got a deal. You got a lesson. An expensive one.",
        "And just like that, the money is gone. Well, MORE gone.",
        "Depreciation: 1, You: 0. Actually, Depreciation is undefeated.",
        "At least the memories were expensive.",
        "You sold it for less than you paid. Shocked? Nobody else is.",
        "The loss has been noted. And by 'noted' we mean 'added to the pile.'",
        "Consider this a charitable donation to whoever bought it from you.",
        "The only thing that depreciated faster was your confidence in this purchase.",
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->stockModel = new Stock($db);
        $this->bankService = new BankService($db);
    }

    /**
     * Generate luxury toys available for purchase.
     * Up to 5 toys on the showroom floor at any time.
     */
    public function generateToys(object $player, int $memberLevel = 0): void
    {
        $playerId = $player->PlayerID;
        $existing = $this->stockModel->getAll($playerId);

        // Only count unowned toys toward the cap — once you buy them all, restock the showroom
        $available = array_filter($existing, fn($t) => $t->NumberShares == 0);
        if (count($available) >= 5) return;

        $numToGenerate = rand(1, 3);
        $usedNames = array_column($existing, 'StockSymbol');

        // At Silver Spoon tier (16+), mix in premium toys ~25% of the time
        $allNames = self::TOY_NAMES;
        $allCategories = self::TOY_NAME_CATEGORIES;
        if ($memberLevel >= 16) {
            $allNames = array_merge($allNames, self::PREMIUM_TOY_NAMES);
            $allCategories = array_merge($allCategories, self::PREMIUM_TOY_NAME_CATEGORIES);
        }

        for ($i = 0; $i < $numToGenerate; $i++) {
            $available = array_diff($allNames, $usedNames);
            if (empty($available)) break;

            $name = $available[array_rand($available)];
            $usedNames[] = $name;

            $categoryId = $allCategories[$name];
            $basePrice = $this->getBasePriceForCategory($categoryId);
            $price = round($basePrice * $player->OneDollar, 2);

            // Personal Shopper effect: finds "exclusive" deals (higher price, naturally)
            $shopperMarkup = 1 + (0.20 * $player->PersonalShopperRating / 100);
            $price = round($price * $shopperMarkup, 2);

            $monthlyCost = $this->getMonthlyCostForCategory($categoryId);

            $allTimeLow = round($price * (rand(80, 95) / 100), 2);
            $allTimeHigh = round($price * (rand(105, 130) / 100), 2);

            $this->stockModel->create($playerId, [
                'StockSymbol' => $name,
                'Turn' => $player->Turn,
                'IndustryID' => $categoryId,
                'NumberShares' => 0,
                'AllTimeHigh' => $allTimeHigh,
                'AllTimeLow' => $allTimeLow,
                'StockPrice' => $price,
                'PurchasePrice' => 0,
                'RateOfReturn' => 0,
                'InterestRateSensitivity' => 0,
                'EarningsPerShare' => 0,
                'Dividends' => -$monthlyCost,
                'ReinvestDividends' => 'N',
                'DividendTurn' => 0,
                'StockPriceChange' => 0,
                'OrderType' => 0,
                'OrderPrice' => 0,
                'OrderQuantity' => 0,
                'MonthlyCost' => $monthlyCost,
                'ToyCategory' => self::TOY_CATEGORIES[$categoryId],
            ]);

            // Record initial price
            $toyId = $this->db->lastInsertId();
            $this->stockModel->addPriceHistory($playerId, $toyId, $price, $player->Turn);
        }
    }

    /**
     * Process turn for all owned toys.
     * Depreciation, ongoing costs, insurance, and value swings.
     */
    public function processTurn(object $player): array
    {
        $messages = [];
        $playerId = $player->PlayerID;
        $toys = $this->stockModel->getAll($playerId);

        foreach ($toys as $toy) {
            if ($toy->NumberShares <= 0) continue;

            $categoryId = (int) $toy->IndustryID;
            $name = $toy->StockSymbol;
            $currentPrice = (float) $toy->StockPrice;

            // ── 1. DEPRECIATION WITH WILD SWINGS ──
            $totalChange = $this->calculateDepreciation($categoryId);
            $newPrice = max(1, round($currentPrice * (1 + $totalChange), 2));
            $priceChange = round($newPrice - $currentPrice, 2);

            // ── 2. MONTHLY ONGOING COSTS ──
            $monthlyCost = (float) ($toy->MonthlyCost ?? 0);
            if ($monthlyCost > 0) {
                $this->bankService->debit(
                    $playerId, $player->Turn,
                    "Upkeep - {$name}",
                    'TOY', $monthlyCost, $toy->StockID
                );
            }

            // ── 3. INSURANCE ──
            $monthlyInsurance = round(0.03 * $newPrice / 12, 2);
            if ($monthlyInsurance > 0) {
                $this->bankService->debit(
                    $playerId, $player->Turn,
                    "Insurance - {$name}",
                    'TOY', $monthlyInsurance, $toy->StockID
                );
            }

            // ── 4. RECORD PRICE HISTORY ──
            $this->stockModel->addPriceHistory($playerId, $toy->StockID, $newPrice, $player->Turn);

            // ── 5. UPDATE ALL-TIME HIGH / LOW ──
            $newHigh = max($toy->AllTimeHigh, $newPrice);
            $newLow = min($toy->AllTimeLow, $newPrice);

            $this->stockModel->update($playerId, $toy->StockID, [
                'StockPrice' => $newPrice,
                'StockPriceChange' => $priceChange,
                'AllTimeHigh' => $newHigh,
                'AllTimeLow' => $newLow,
            ]);

            // Generate message if notable swing
            $changePercent = $currentPrice > 0 ? round($priceChange / $currentPrice * 100, 1) : 0;
            if (abs($changePercent) >= 10) {
                $direction = $changePercent > 0 ? 'surged' : 'plummeted';
                $messages[] = [
                    'title' => "{$name} Value {$direction}!",
                    'body' => sprintf(
                        '<p>Your <b>%s</b> %s %s%% this month. New value: $%s</p>'
                        . '<p>%s</p>',
                        $name,
                        $direction,
                        number_format(abs($changePercent), 1),
                        number_format($newPrice, 2),
                        $changePercent > 0
                            ? "Don't get excited. It'll come back down."
                            : "At this rate, you'll be paying someone to take it."
                    ),
                ];
            }
        }

        return $messages;
    }

    /**
     * Buy a luxury toy at full price.
     * Because financing a depreciating asset would be even dumber.
     */
    public function buyToy(object $player, int $toyId): array
    {
        $playerId = $player->PlayerID;
        $toy = $this->stockModel->findById($playerId, $toyId);

        if (!$toy) return ['error' => 'Toy not found. Perhaps it already found a richer owner.'];
        if ($toy->NumberShares > 0) return ['error' => 'You already own this. One money pit at a time.'];

        $price = (float) $toy->StockPrice;
        $balance = $this->bankService->getBalance($playerId);

        if ($balance < $price) {
            return ['error' => sprintf(
                'Insufficient funds. Price: $%s, Balance: $%s. Even trust funds have limits.',
                number_format($price, 2),
                number_format($balance, 2)
            )];
        }

        // Debit full price
        $this->bankService->debit(
            $playerId, $player->Turn,
            "Purchased {$toy->StockSymbol}",
            'TOY', $price, $toyId
        );

        // Update toy to owned
        $this->stockModel->update($playerId, $toyId, [
            'NumberShares' => 1,
            'PurchasePrice' => $price,
        ]);

        // Advance turn
        $playerModel = new Player($this->db);
        $playerModel->update($playerId, ['TurnAction' => 0, 'Turn' => $player->Turn + 1]);

        // Pick a sardonic purchase message
        $categoryId = (int) $toy->IndustryID;
        $quips = self::PURCHASE_QUIPS[$categoryId] ?? self::PURCHASE_QUIPS[0];
        $quip = sprintf($quips[array_rand($quips)]);

        $categoryName = self::TOY_CATEGORIES[$categoryId] ?? 'Luxury Item';
        $monthlyCost = (float) ($toy->MonthlyCost ?? 0);

        return [
            'title' => "Purchased: {$toy->StockSymbol}",
            'body' => sprintf(
                '<p>You just dropped <b>$%s</b> on a <b>%s</b>.</p>'
                . '<p>Category: %s | Monthly Upkeep: $%s</p>'
                . '<p><em>%s</em></p>',
                number_format($price, 2),
                $toy->StockSymbol,
                $categoryName,
                number_format($monthlyCost, 2),
                $quip
            ),
        ];
    }

    /**
     * Sell a toy at current value minus a 15% commission.
     * Because even selling at a loss costs money.
     */
    public function sellToy(object $player, int $toyId): array
    {
        $playerId = $player->PlayerID;
        $toy = $this->stockModel->findById($playerId, $toyId);

        if (!$toy) return ['error' => 'Toy not found.'];
        if ($toy->NumberShares <= 0) return ['error' => "You don't own this. Can't sell what you've already lost."];

        $currentValue = (float) $toy->StockPrice;
        $purchasePrice = (float) $toy->PurchasePrice;
        $commission = round($currentValue * 0.15, 2);
        $netProceeds = round($currentValue - $commission, 2);
        $loss = round($purchasePrice - $netProceeds, 2);

        // Credit net proceeds
        $this->bankService->credit(
            $playerId, $player->Turn,
            "Sold {$toy->StockSymbol}",
            'TOY', $netProceeds, $toyId
        );

        // Mark as no longer owned
        $this->stockModel->update($playerId, $toyId, [
            'NumberShares' => 0,
        ]);

        // Pick a sardonic sell message
        $quip = self::SELL_QUIPS[array_rand(self::SELL_QUIPS)];

        $lossDisplay = $loss > 0
            ? sprintf('You lost <span class="text-red-600">$%s</span> on this adventure.', number_format($loss, 2))
            : sprintf('Miraculously, you made <span class="text-green-600">$%s</span>. Don\'t get used to it.', number_format(abs($loss), 2));

        return [
            'title' => "Sold: {$toy->StockSymbol}",
            'body' => sprintf(
                '<p>Sold <b>%s</b> for $%s (after 15%% commission of $%s).</p>'
                . '<p>Purchase Price: $%s | Net Proceeds: $%s</p>'
                . '<p>%s</p>'
                . '<p><em>%s</em></p>',
                $toy->StockSymbol,
                number_format($currentValue, 2),
                number_format($commission, 2),
                number_format($purchasePrice, 2),
                number_format($netProceeds, 2),
                $lossDisplay,
                $quip
            ),
        ];
    }

    /**
     * Get a summary of the player's toy portfolio.
     * A catalogue of depreciating dreams.
     */
    public function getPortfolioSummary(int $playerId): array
    {
        $toys = $this->stockModel->getOwned($playerId);
        $totalValue = 0;
        $totalCost = 0;
        $totalMonthlyCosts = 0;

        foreach ($toys as $toy) {
            $totalValue += (float) $toy->StockPrice;
            $totalCost += (float) $toy->PurchasePrice;
            $totalMonthlyCosts += (float) ($toy->MonthlyCost ?? 0);
        }

        $totalLoss = $totalCost - $totalValue;

        return [
            'count' => count($toys),
            'totalValue' => $totalValue,
            'totalCost' => $totalCost,
            'totalLoss' => $totalLoss,
            'totalMonthlyCosts' => $totalMonthlyCosts,
            'toys' => $toys,
        ];
    }

    /**
     * Get the category name for a given category ID.
     */
    public function getCategoryName(int $categoryId): string
    {
        return self::TOY_CATEGORIES[$categoryId] ?? 'Unknown';
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Generate a base price (pre-OneDollar scaling) for the given category.
     */
    private function getBasePriceForCategory(int $categoryId): float
    {
        return match ($categoryId) {
            0 => rand(500000, 5000000),           // Supercar: $500K-$5M
            1 => rand(20000000, 300000000),       // Megayacht: $20M-$300M
            2 => rand(30000000, 100000000),       // Private Jet: $30M-$100M
            3 => rand(1000000, 50000000),         // Fine Art: $1M-$50M
            4 => rand(500000, 10000000),          // Racehorse: $500K-$10M
            5 => rand(250000, 50000000),          // Space Tourism: $250K-$50M
            6 => rand(100000, 10000000),          // NFT Collection: $100K-$10M
            7 => rand(50000, 2000000),            // Designer Wardrobe: $50K-$2M
            8 => rand(2000000, 20000000),         // Exotic Car Fleet: $2M-$20M
            9 => rand(500000, 5000000),           // Wine Collection: $500K-$5M
            default => rand(500000, 5000000),
        };
    }

    /**
     * Generate a monthly ongoing cost for the given category.
     */
    private function getMonthlyCostForCategory(int $categoryId): float
    {
        return match ($categoryId) {
            0 => (float) rand(2000, 20000),        // Supercar: $2K-$20K (garage, insurance, detailing)
            1 => (float) rand(50000, 500000),      // Megayacht: $50K-$500K (crew, marina, fuel)
            2 => (float) rand(20000, 100000),      // Private Jet: $20K-$100K (hangar, crew, maintenance)
            3 => (float) rand(1000, 5000),         // Fine Art: $1K-$5K (climate control, insurance, security)
            4 => (float) rand(5000, 30000),        // Racehorse: $5K-$30K (stables, trainer, vet)
            5 => 0.0,                              // Space Tourism: $0 (they keep delaying the launch)
            6 => (float) rand(500, 2000),          // NFT Collection: $500-$2K (blockchain fees, digital storage lol)
            7 => (float) rand(5000, 20000),        // Designer Wardrobe: $5K-$20K (storage, dry cleaning, stylist)
            8 => (float) rand(2000, 20000),        // Exotic Car Fleet: $2K-$20K (garage, insurance, detailing)
            9 => (float) rand(2000, 10000),        // Wine Collection: $2K-$10K (cellar maintenance, sommelier)
            default => (float) rand(1000, 10000),
        };
    }

    /**
     * Calculate the total depreciation/appreciation change for a toy category.
     * Returns a multiplier to apply: newPrice = price * (1 + totalChange)
     */
    private function calculateDepreciation(int $categoryId): float
    {
        return match ($categoryId) {
            // NFTs: base -3% to -8%, volatile swing -50% to +30%
            6 => (rand(-800, -300) / 10000) + (rand(-5000, 3000) / 10000),

            // Fine Art: base -0.5% to -2%, but occasionally +5% to +20% (auction hype!)
            3 => (rand(-200, -50) / 10000) + $this->artSwing(),

            // Supercars: base -1% to -3%, rare +10% (becomes "collectible"!)
            0 => (rand(-300, -100) / 10000) + $this->supercarSwing(),

            // Everything else: base -1% to -5%, swing -30% to +15%
            default => (rand(-500, -100) / 10000) + (rand(-3000, 1500) / 10000),
        };
    }

    /**
     * Art has occasional auction hype that can spike value.
     */
    private function artSwing(): float
    {
        // 10% chance of auction hype: +5% to +20%
        if (rand(1, 10) === 1) {
            return rand(500, 2000) / 10000;
        }
        // Normal swing
        return rand(-3000, 1500) / 10000;
    }

    /**
     * Supercars occasionally become "collectible" and spike in value.
     */
    private function supercarSwing(): float
    {
        // 5% chance of becoming "collectible": +10% spike
        if (rand(1, 20) === 1) {
            return rand(800, 1200) / 10000;
        }
        // Normal swing
        return rand(-3000, 1500) / 10000;
    }
}
