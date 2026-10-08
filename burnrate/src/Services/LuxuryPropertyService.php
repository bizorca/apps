<?php

namespace App\Services;

use App\Core\Database;
use App\Models\RealEstate;
use App\Models\Player;

class LuxuryPropertyService
{
    private Database $db;
    private RealEstate $reModel;
    private BankService $bankService;

    /**
     * Luxury property types with base price ranges (in raw dollars, before OneDollar scaling).
     * These are the kind of properties trust fund babies blow fortunes on.
     */
    private const PROPERTY_CATALOG = [
        ['desc' => '12BR Malibu Oceanfront Mansion',     'min' => 20000000,  'max' => 80000000,   'type' => 'mansion'],
        ['desc' => 'Private Island in the Maldives',     'min' => 50000000,  'max' => 300000000,  'type' => 'island'],
        ['desc' => 'Manhattan Penthouse (92nd Floor)',    'min' => 15000000,  'max' => 60000000,   'type' => 'penthouse'],
        ['desc' => 'Swiss Alpine Chalet',                'min' => 10000000,  'max' => 40000000,   'type' => 'chalet'],
        ['desc' => 'Tokyo Sky Suite',                    'min' => 8000000,   'max' => 30000000,   'type' => 'penthouse'],
        ['desc' => 'Venetian Palazzo',                   'min' => 25000000,  'max' => 100000000,  'type' => 'estate'],
        ['desc' => 'Dubai Palm Mega-Villa',              'min' => 30000000,  'max' => 120000000,  'type' => 'mansion'],
        ['desc' => 'Beverly Hills Compound',             'min' => 40000000,  'max' => 150000000,  'type' => 'compound'],
        ['desc' => 'Caribbean Private Estate',           'min' => 20000000,  'max' => 80000000,   'type' => 'estate'],
        ['desc' => 'London Mayfair Townhouse',           'min' => 15000000,  'max' => 50000000,   'type' => 'mansion'],
        ['desc' => 'Monaco Harborfront Penthouse',       'min' => 30000000,  'max' => 90000000,   'type' => 'penthouse'],
        ['desc' => 'Aspen Mountain Lodge',               'min' => 10000000,  'max' => 40000000,   'type' => 'chalet'],
    ];

    /** Ultra-exclusive listings that only appear at Silver Spoon tier (level 16+). */
    private const PREMIUM_PROPERTY_CATALOG = [
        ['desc' => 'Entire Greek Island (with village)',         'min' => 200000000,  'max' => 800000000,  'type' => 'island'],
        ['desc' => 'Scottish Castle (1,200 acres)',              'min' => 80000000,   'max' => 250000000,  'type' => 'estate'],
        ['desc' => 'Private Undersea Habitat — Maldives',        'min' => 150000000,  'max' => 500000000,  'type' => 'estate'],
        ['desc' => 'Antarctic Research Villa (with penguins)',    'min' => 100000000,  'max' => 400000000,  'type' => 'compound'],
        ['desc' => 'Saharan Palace Complex',                     'min' => 120000000,  'max' => 350000000,  'type' => 'compound'],
        ['desc' => 'Floating Mega-Mansion (self-propelled)',     'min' => 250000000,  'max' => 700000000,  'type' => 'island'],
        ['desc' => 'Private Ski Mountain — Colorado',            'min' => 180000000,  'max' => 600000000,  'type' => 'estate'],
        ['desc' => 'Hong Kong Sky Residence (entire floor)',     'min' => 90000000,   'max' => 280000000,  'type' => 'penthouse'],
    ];

    /**
     * Sardonic purchase messages for trust fund babies who just blew millions on real estate.
     */
    private const PURCHASE_QUIPS = [
        "Your accountant just had a heart attack. Your interior designer just had an orgasm.",
        "Congratulations, you now own more square footage than some countries.",
        "Nothing says 'I earned this' like spending daddy's money on imported marble.",
        "The staff you'll need to maintain this place could populate a small town.",
        "Your carbon footprint just became visible from space. Well done.",
        "The property taxes alone could fund a public school. But why would you?",
        "Welcome to the 'I have more bathrooms than friends' club.",
        "At least when you're broke, the memories of this view will keep you warm.",
    ];

    /**
     * Sardonic sale messages for trust fund babies selling at a loss.
     */
    private const SALE_QUIPS = [
        "Another shrewd financial move. Your father would be so proud.",
        "Selling low is a time-honored tradition among the generationally wealthy.",
        "Don't worry, there are plenty more bad decisions where that came from.",
        "The new buyer thanks you for the generous discount on reality.",
        "You've successfully converted luxury real estate into a tax write-off.",
        "At this rate, you'll achieve bankruptcy in record time. Keep it up!",
        "Your financial advisor is updating their resume as we speak.",
        "Nothing depreciates faster than a rich person's judgment.",
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->reModel = new RealEstate($db);
        $this->bankService = new BankService($db);
    }

    /**
     * Generate luxury properties for sale.
     * If fewer than 3 properties are on the market, generate 1-3 more.
     * Personal Shopper finds "exclusive" deals (i.e., more expensive ones).
     */
    public function generateProperties(object $player, int $memberLevel = 0): void
    {
        $playerId = $player->PlayerID;
        $forSale = $this->reModel->getForSale($playerId);

        if (count($forSale) < 3) {
            $numToGenerate = rand(1, 3);
            for ($i = 0; $i < $numToGenerate; $i++) {
                $this->createRandomProperty($player, $memberLevel);
            }
        }
    }

    private function createRandomProperty(object $player, int $memberLevel = 0): void
    {
        $playerId = $player->PlayerID;
        $oneDollar = $player->OneDollar;

        // At Silver Spoon tier (16+), mix in ultra-exclusive premium listings ~30% of the time
        $catalog = self::PROPERTY_CATALOG;
        if ($memberLevel >= 16 && rand(1, 10) <= 3) {
            $catalog = self::PREMIUM_PROPERTY_CATALOG;
        }

        // Pick a random luxury property from the catalog
        $template = $catalog[array_rand($catalog)];

        // Base value within the template's range, scaled by inflation
        $baseValue = rand($template['min'], $template['max']);
        $currentValue = round($baseValue * $oneDollar, 2);

        // Personal Shopper effect: finds "exclusive" deals that cost UP TO 20% more
        $personalShopperMarkup = 1.0 + (0.20 * ($player->PersonalShopperRating / 100));
        $currentValue = round($currentValue * $personalShopperMarkup, 2);

        // Asking price (luxury properties are listed at or above value)
        $askingPrice = round($currentValue * (rand(100, 115) / 100), 2);

        // Comps (comparable properties)
        $lowComp = round($currentValue * (rand(85, 95) / 100), 2);
        $highComp = round($currentValue * (rand(105, 125) / 100), 2);

        // NEGATIVE appreciation rate: -2% to -8% annually (these things depreciate)
        $appreciationRate = rand(-800, -200) / 100;

        // Staff costs: $20K-$200K/month, scaled with property value
        // Higher-value properties need bigger staff
        $staffCostRatio = $currentValue / ($template['max'] * $oneDollar * $personalShopperMarkup);
        $staffCost = round(20000 + ($staffCostRatio * 180000), 2);
        $staffCost = round($staffCost * $oneDollar, 2);

        // Tax rate: 1.5% to 3.0% (luxury premium)
        $taxRate = round(rand(150, 300) / 100, 2);

        // Condition ratings: luxury properties start in good shape (70-100)
        $roofRating = rand(70, 100);
        $kitchenRating = rand(70, 100);
        $bathroomsRating = rand(70, 100);
        $flooringRating = rand(70, 100);
        $paintRating = rand(70, 100);
        $majorSystemsRating = rand(70, 100);

        $this->reModel->create($playerId, [
            'Description' => $template['desc'],
            'Turn' => $player->Turn,
            'PurchasePrice' => 0,
            'PurchaseTurn' => 0,
            'LoanBalance' => 0,
            'RentalRate' => 0,          // No rental income - you live here or it sits empty
            'SoldTurn' => 0,
            'CurrentValue' => $currentValue,
            'MinPayment' => 0,          // No mortgage - billionaires pay cash
            'CurrentPayment' => 0,
            'InterestRate' => 0,        // No loan, no interest
            'LoanType' => 0,
            'Status' => 1,              // For sale
            'AppreciationRate' => $appreciationRate,
            'AskingPrice' => $askingPrice,
            'LowComp' => $lowComp,
            'HighComp' => $highComp,
            'LowRental' => 0,           // No rental
            'HighRental' => 0,           // No rental
            'TaxRate' => $taxRate,
            'CashFlow' => 0,
            'SoldPrice' => 0,
            'RoofRating' => $roofRating,
            'KitchenRating' => $kitchenRating,
            'BathroomsRating' => $bathroomsRating,
            'FlooringRating' => $flooringRating,
            'PaintRating' => $paintRating,
            'MajorSystemsRating' => $majorSystemsRating,
            'RepairStatus' => 0,
            'StaffCost' => $staffCost,
            'PropertyType' => $template['type'],
        ]);
    }

    /**
     * Purchase a luxury property. Billionaires pay cash - no mortgages, no down payments.
     * Just a grotesque transfer of wealth for a place you'll visit twice a year.
     */
    public function purchaseProperty(object $player, object $property, float $price): array
    {
        $playerId = $player->PlayerID;
        $reId = $property->REID;

        // Check bank balance - even billionaires have limits (theoretically)
        $balance = $this->bankService->getBalance($playerId);
        if ($balance < $price) {
            return [
                'error' => sprintf(
                    'Insufficient funds. Price: $%s, Bank balance: $%s. Perhaps sell one of your other mansions?',
                    number_format($price, 2),
                    number_format($balance, 2)
                ),
            ];
        }

        // Debit full purchase price - no mortgage, just raw cash hemorrhage
        $this->bankService->debit(
            $playerId, $player->Turn,
            "Purchase - {$property->Description}",
            'PROP', $price, $reId
        );

        // Update property to owned status
        $this->reModel->update($playerId, $reId, [
            'Status' => 10, // Owned
            'PurchasePrice' => $price,
            'PurchaseTurn' => $player->Turn,
            'LoanBalance' => 0,    // No mortgage
            'InterestRate' => 0,
            'MinPayment' => 0,
            'CurrentPayment' => 0,
            'LoanType' => 0,
        ]);

        // Reset turn action and advance turn
        $playerModel = new Player($this->db);
        $playerModel->update($playerId, ['TurnAction' => 0]);
        $playerModel->update($playerId, ['Turn' => $player->Turn + 1]);

        $quip = self::PURCHASE_QUIPS[array_rand(self::PURCHASE_QUIPS)];

        return [
            'title' => 'Property Acquired!',
            'body' => sprintf(
                '<p>You paid <b>$%s</b> in cash for <b>%s</b>.</p>
                 <p>No mortgage. No negotiations. Just pure, unfiltered wealth evaporation.</p>
                 <p>Monthly staff costs: $%s | Annual depreciation rate: %s%%</p>
                 <p class="text-sm text-gray-500 italic">%s</p>',
                number_format($price, 2),
                $property->Description,
                number_format($property->StaffCost, 2),
                number_format($property->AppreciationRate, 2),
                $quip
            ),
        ];
    }

    /**
     * Process all owned luxury properties each turn.
     * Staff, maintenance, insurance, taxes, depreciation, interior designer redecorating.
     * Everything costs money. Nothing generates income. This is the way.
     */
    public function processTurn(object $player): array
    {
        $messages = [];
        $playerId = $player->PlayerID;
        $properties = $this->reModel->getOwned($playerId);

        foreach ($properties as $prop) {
            $totalCosts = 0;

            // 1. Staff costs (butlers, maids, groundskeepers, pool boys, personal chefs...)
            if ($prop->StaffCost > 0) {
                $this->bankService->debit(
                    $playerId, $player->Turn,
                    "Staff - {$prop->Description}",
                    'PROP', round($prop->StaffCost, 2), $prop->REID
                );
                $totalCosts += $prop->StaffCost;
            }

            // 2. Maintenance: (100 - avgRating) / 100 * CurrentValue * 0.003 (3x original rate)
            $avgRating = ($prop->RoofRating + $prop->KitchenRating + $prop->BathroomsRating
                        + $prop->FlooringRating + $prop->PaintRating + $prop->MajorSystemsRating) / 6;
            $maintenanceCost = round((100 - $avgRating) / 100 * $prop->CurrentValue * 0.003, 2);
            if ($maintenanceCost > 0) {
                $this->bankService->debit(
                    $playerId, $player->Turn,
                    "Maintenance - {$prop->Description}",
                    'PROP', $maintenanceCost, $prop->REID
                );
                $totalCosts += $maintenanceCost;
            }

            // 3. Insurance: 2% of CurrentValue annually (luxury premium), paid monthly
            $monthlyInsurance = round((0.02 * $prop->CurrentValue) / 12, 2);
            $this->bankService->debit(
                $playerId, $player->Turn,
                "Insurance - {$prop->Description}",
                'PROP', $monthlyInsurance, $prop->REID
            );
            $totalCosts += $monthlyInsurance;

            // 4. Property taxes: (TaxRate/100 * CurrentValue) / 12 monthly
            $monthlyTaxes = round(($prop->TaxRate / 100 * $prop->CurrentValue) / 12, 2);
            $this->bankService->debit(
                $playerId, $player->Turn,
                "Property Tax - {$prop->Description}",
                'PROP', $monthlyTaxes, $prop->REID
            );
            $totalCosts += $monthlyTaxes;

            // 5. Depreciation: apply negative appreciation rate
            // newValue = CurrentValue * (1 + AppreciationRate/12/100)
            $monthlyDepreciation = $prop->AppreciationRate / 12 / 100;
            $newValue = round($prop->CurrentValue * (1 + $monthlyDepreciation), 2);
            $this->reModel->update($playerId, $prop->REID, [
                'CurrentValue' => $newValue,
            ]);

            // 6. Degrade condition ratings faster: rand(0, 50) / 100 per turn
            $this->degradeCondition($playerId, $prop);

            // 7. Interior Designer effect: redecorating costs
            // Because nothing says "burning money" like redecorating a mansion every month
            $redecorationCost = 0;
            if ($player->InteriorDesignerRating > 0) {
                $redecorationCost = round(
                    ($player->InteriorDesignerRating / 100) * $prop->CurrentValue * 0.001,
                    2
                );
                $this->bankService->debit(
                    $playerId, $player->Turn,
                    "Redecorating - {$prop->Description}",
                    'PROP', $redecorationCost, $prop->REID
                );
                $totalCosts += $redecorationCost;
            }

            // 8. Calculate cash flow (all negative since no income)
            $cashFlow = -$totalCosts;
            $this->reModel->update($playerId, $prop->REID, [
                'CashFlow' => round($cashFlow, 2),
            ]);
        }

        return $messages;
    }

    /**
     * Sell a luxury property at its current (depreciated) value minus a 10% realtor fee.
     * Show the loss from purchase price, because there's always a loss.
     */
    public function sellProperty(object $player, int $reId): array
    {
        $playerId = $player->PlayerID;
        $property = $this->reModel->findById($playerId, $reId);

        if (!$property || $property->Status !== 10) {
            return ['error' => 'Property cannot be sold. Perhaps it never existed, much like your financial judgment.'];
        }

        // Sell at current (depreciated) value minus 10% realtor fee
        $sellPrice = round($property->CurrentValue * 0.90, 2);
        $loss = $property->PurchasePrice - $sellPrice;

        // Credit net proceeds to bank
        $this->bankService->credit(
            $playerId, $player->Turn,
            "Sold {$property->Description}",
            'PROP', $sellPrice, $reId
        );

        // Update property status
        $this->reModel->update($playerId, $reId, [
            'Status' => 98, // Sold
            'SoldTurn' => $player->Turn,
            'SoldPrice' => $sellPrice,
            'LoanBalance' => 0,
        ]);

        $quip = self::SALE_QUIPS[array_rand(self::SALE_QUIPS)];

        return [
            'title' => 'Property Sold!',
            'body' => sprintf(
                '<p>You sold <b>%s</b> for $%s.</p>
                 <p>Original Price: $%s | Realtor Fee (10%%): $%s</p>
                 <p>Total Loss: <span class="text-red-600">-$%s</span></p>
                 <p class="text-sm text-gray-500 italic">%s</p>',
                $property->Description,
                number_format($sellPrice, 2),
                number_format($property->PurchasePrice, 2),
                number_format($property->CurrentValue * 0.10, 2),
                number_format($loss, 2),
                $quip
            ),
        ];
    }

    /**
     * Make an offer on a luxury property.
     * Billionaires don't negotiate - they overpay.
     * Offers >= 90% of asking are accepted immediately.
     * Offers < 90% are an insult.
     */
    public function makeOffer(object $player, int $reId, float $offerPrice): array
    {
        $playerId = $player->PlayerID;
        $property = $this->reModel->findById($playerId, $reId);

        if (!$property || $property->Status !== 1) {
            return ['error' => 'Property not available for offers.'];
        }

        $ratio = $offerPrice / $property->AskingPrice;

        if ($ratio >= 0.90) {
            // Accepted immediately - billionaires don't haggle
            return $this->purchaseProperty($player, $property, $offerPrice);
        } else {
            // The audacity of lowballing
            return [
                'title' => 'Offer Rejected',
                'body' => sprintf(
                    '<p>The seller is insulted you\'d lowball a property this magnificent.</p>
                     <p>Your offer of $%s on a $%s property? How embarrassingly middle-class.</p>
                     <p>Offer at least 90%% of the asking price, like a proper billionaire.</p>',
                    number_format($offerPrice, 2),
                    number_format($property->AskingPrice, 2)
                ),
            ];
        }
    }

    /**
     * Get a summary of all owned luxury properties.
     * Tracks totalDepreciation instead of equity (since there's no debt, equity = value).
     */
    public function getPropertySummary(int $playerId): array
    {
        $owned = $this->reModel->getOwned($playerId);
        $totalValue = 0;
        $totalCashFlow = 0;
        $totalDepreciation = 0;

        foreach ($owned as $prop) {
            $totalValue += $prop->CurrentValue;
            $totalCashFlow += $prop->CashFlow;
            // Depreciation = how much value has been lost since purchase
            $totalDepreciation += ($prop->PurchasePrice - $prop->CurrentValue);
        }

        return [
            'count' => count($owned),
            'totalValue' => $totalValue,
            'totalDebt' => 0,                   // Always 0 - billionaires pay cash
            'totalEquity' => $totalValue,        // Equity = value (no debt)
            'totalCashFlow' => $totalCashFlow,
            'totalDepreciation' => $totalDepreciation,
            'properties' => $owned,
        ];
    }

    /**
     * Degrade property condition faster than regular real estate.
     * Luxury properties are high-maintenance - marble cracks, infinity pools leak,
     * helicopter pads develop potholes.
     * Degradation: rand(0, 50) / 100 per turn (up from 30 in original).
     */
    public function degradeCondition(int $playerId, object $prop): void
    {
        $degradation = rand(0, 50) / 100; // 0 to 0.5% per turn
        $updates = [];

        $ratings = ['RoofRating', 'KitchenRating', 'BathroomsRating',
                    'FlooringRating', 'PaintRating', 'MajorSystemsRating'];

        foreach ($ratings as $rating) {
            $current = (float) $prop->$rating;
            if ($current > 0) {
                $updates[$rating] = max(0, round($current - $degradation, 1));
            }
        }

        if (!empty($updates)) {
            $this->reModel->update($playerId, $prop->REID, $updates);
        }
    }
}
