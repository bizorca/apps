<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Player;
use App\Models\GameLog;

class GameEngine
{
    private Database $db;
    private Player $playerModel;
    private VanityIncomeService $vanityService;
    private EconomyService $economyService;
    private LuxuryPropertyService $propertyService;
    private ToyService $toyService;
    private InvestmentService $investmentService;
    private EntourageService $entourageService;
    private BankService $bankService;
    private CrisisService $crisisService;
    private QuotesService $quotesService;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->playerModel = new Player($db);
        $this->vanityService = new VanityIncomeService($db);
        $this->economyService = new EconomyService($db);
        $this->propertyService = new LuxuryPropertyService($db);
        $this->toyService = new ToyService($db);
        $this->investmentService = new InvestmentService($db);
        $this->entourageService = new EntourageService($db);
        $this->bankService = new BankService($db);
        $this->crisisService = new CrisisService($db);
        $this->quotesService = new QuotesService();
    }

    /**
     * Main turn processing - the grand hemorrhaging.
     */
    public function processTurn(object $player): array
    {
        $messages = [];

        // Check if already bankrupt (upgrade already granted on first detection)
        if ($player->BankruptTurn > 0) {
            return ['gameOver' => true, 'bankrupt' => true, 'messages' => $this->getEndGameData($player), 'tierUpgrade' => null];
        }

        // Check if game is over (611 turns and still rich = shame)
        if ($player->Turn > 611) {
            $tierUpgrade = null;
            if (!(int) ($player->UpgradeGranted ?? 0)) {
                $ownerModel = new \App\Models\Owner($this->db);
                $tierUpgrade = $ownerModel->upgradeToNextTier((int) $player->OwnerID);
                $this->playerModel->update($player->PlayerID, ['UpgradeGranted' => 1]);
            }
            return ['gameOver' => true, 'bankrupt' => false, 'messages' => $this->getEndGameData($player), 'tierUpgrade' => $tierUpgrade];
        }

        // Process all unprocessed turns
        $targetTurn = $player->Turn;
        while ($player->TurnProcessedThrough < $targetTurn) {
            $processingTurn = $player->TurnProcessedThrough + 1;
            $player = $this->playerModel->findById($player->PlayerID);
            $player->Turn = $processingTurn;

            // 1. Process vanity income and lifestyle expenses
            $vanityMessages = $this->vanityService->processVanityAndExpenses($player);
            $messages = array_merge($messages, $vanityMessages);

            // 2. Process economy changes
            $econMessages = $this->economyService->processEconomy($player);
            $messages = array_merge($messages, $econMessages);

            // 3. Check for economic crisis
            $crisisMessages = $this->crisisService->processCrisis($player);
            $messages = array_merge($messages, $crisisMessages);

            // Reload player after economy changes
            $player = $this->playerModel->findById($player->PlayerID);
            $player->Turn = $processingTurn;

            // 4. Process luxury properties (depreciation, staff, maintenance, taxes)
            $propMessages = $this->propertyService->processTurn($player);
            $messages = array_merge($messages, $propMessages);

            // 5. Process toys (depreciation, ongoing costs)
            $toyMessages = $this->toyService->processTurn($player);
            $messages = array_merge($messages, $toyMessages);

            // 6. Process investments (fees, wild value swings, catastrophes)
            $investMessages = $this->investmentService->processTurn($player);
            $messages = array_merge($messages, $investMessages);

            // 7. Generate new opportunities (pass member level for premium content gating)
            $memberLevel = $this->getOwnerMemberLevel((int) $player->OwnerID);
            $this->propertyService->generateProperties($player, $memberLevel);
            $this->toyService->generateToys($player, $memberLevel);

            // 8. BANKRUPTCY CHECK
            $player = $this->playerModel->findById($player->PlayerID);
            $player->Turn = $processingTurn;
            $netWorth = $this->calculateNetWorth($player);

            if ($netWorth <= 0) {
                // CELEBRATION! You're broke!
                $this->playerModel->update($player->PlayerID, [
                    'BankruptTurn'         => $processingTurn,
                    'TurnProcessedThrough' => $processingTurn,
                    'UpgradeGranted'       => 1,
                ]);
                $player->BankruptTurn = $processingTurn;

                $ownerModel = new \App\Models\Owner($this->db);
                $tierUpgrade = $ownerModel->upgradeToNextTier((int) $player->OwnerID);

                $messages[] = [
                    'title' => "YOU'RE BANKRUPT!",
                    'body' => '<p class="text-2xl font-bold text-green-500">CONGRATULATIONS!</p>'
                        . '<p>You burned through $1 BILLION in just ' . $processingTurn . ' turns!</p>'
                        . '<p class="italic text-gray-500">"' . $this->quotesService->getQuoteForContext('bankruptcy') . '"</p>',
                ];

                $this->logGameState($player);
                return ['gameOver' => true, 'bankrupt' => true, 'messages' => $this->getEndGameData($player), 'tierUpgrade' => $tierUpgrade];
            }

            // 9. Log game state
            $this->logGameState($player);

            // 10. Mark turn processed
            $this->playerModel->update($player->PlayerID, [
                'TurnProcessedThrough' => $processingTurn,
            ]);
            $player->TurnProcessedThrough = $processingTurn;
        }

        $player->Turn = $targetTurn;

        return ['gameOver' => false, 'bankrupt' => false, 'messages' => $messages];
    }

    /**
     * Process a player's chosen action.
     */
    public function processTurnAction(object $player, int $action): array
    {
        // Entourage upgrades (401-414)
        if ($action >= 401 && $action <= 414) {
            return $this->entourageService->processEnablerUpgrade($player, $action);
        }

        // Vanity project focus (501) - doubles vanity expenses for a turn
        if ($action === 501) {
            return $this->processVanityFocus($player);
        }

        // Browse luxury properties (1)
        if ($action === 1) {
            $memberLevel = $this->getOwnerMemberLevel((int) $player->OwnerID);
            $this->propertyService->generateProperties($player, $memberLevel);
            $this->playerModel->update($player->PlayerID, [
                'TurnAction' => 0,
                'Turn' => $player->Turn + 1,
            ]);
            $quote = $this->quotesService->getQuoteForContext('property');
            return [
                'title' => 'Browsed Luxury Properties',
                'body' => '<p>You spent the month touring mansions, islands, and penthouses with your Personal Shopper.</p>'
                    . '<p>New properties are available on the Mansions & Islands tab.</p>'
                    . '<p class="text-sm italic text-gray-400 mt-2">"' . $quote . '"</p>',
            ];
        }

        // Browse toys (2)
        if ($action === 2) {
            $memberLevel = $this->getOwnerMemberLevel((int) $player->OwnerID);
            $this->toyService->generateToys($player, $memberLevel);
            $this->playerModel->update($player->PlayerID, [
                'TurnAction' => 0,
                'Turn' => $player->Turn + 1,
            ]);
            $quote = $this->quotesService->getQuoteForContext('toy');
            return [
                'title' => 'Went Shopping For Toys',
                'body' => '<p>You spent the month test-driving supercars, touring yacht showrooms, and browsing private jet catalogs.</p>'
                    . '<p>New toys are available on the Toys & Luxuries tab.</p>'
                    . '<p class="text-sm italic text-gray-400 mt-2">"' . $quote . '"</p>',
            ];
        }

        // Make a bad investment (101)
        if ($action === 101) {
            $memberLevel = $this->getOwnerMemberLevel((int) $player->OwnerID);
            $deal = $this->investmentService->getRandomDeal($player, $memberLevel);
            if (!$deal) {
                $this->playerModel->update($player->PlayerID, [
                    'TurnAction' => 0,
                    'Turn' => $player->Turn + 1,
                ]);
                return ['title' => 'No Investments Available', 'body' => '<p>Even the scammers have run out of ideas. Try again next month.</p>'];
            }

            $bankBalance = $this->bankService->getBalance($player->PlayerID);
            $cost = $deal['adjustedCost'];

            if ($bankBalance < $cost) {
                $this->playerModel->update($player->PlayerID, [
                    'TurnAction' => 0,
                    'Turn' => $player->Turn + 1,
                ]);
                return [
                    'title' => 'Not Enough Cash (Yet)',
                    'body' => sprintf(
                        '<p>You considered investing in <b>%s</b> but don\'t have enough cash.</p><p>Investment requires: $%s | Your balance: $%s</p><p>Don\'t worry, you\'ll find other ways to lose money.</p>',
                        htmlspecialchars($deal['deal']->ShortDescription),
                        number_format($cost, 2),
                        number_format($bankBalance, 2)
                    ),
                ];
            }

            return $this->investmentService->makeInvestment($player, $deal['deal']->InvestmentID);
        }

        // Throw a party (201)
        if ($action === 201) {
            return $this->processThrowParty($player);
        }

        // Fly friends in on private jet (301)
        if ($action === 301) {
            return $this->processFlyFriendsIn($player);
        }

        return ['error' => 'Unknown action. Even your Yes Man is confused.'];
    }

    /**
     * Set the player's next action.
     */
    public function setAction(object $player, int $action): void
    {
        $this->playerModel->update($player->PlayerID, ['TurnAction' => $action]);
    }

    private function processVanityFocus(object $player): array
    {
        $extraCost = round($player->VanityExpenses * $player->OneDollar, 2);
        $this->bankService->debit(
            $player->PlayerID, $player->Turn,
            'Vanity Project Expansion (hired a film crew)',
            'VANITY', $extraCost
        );

        // Increase ongoing vanity expenses permanently by 10-25%
        $increase = rand(10, 25) / 100;
        $newExpenses = round($player->VanityExpenses * (1 + $increase), 2);
        $this->playerModel->update($player->PlayerID, [
            'VanityExpenses' => $newExpenses,
            'TurnAction' => 0,
            'Turn' => $player->Turn + 1,
        ]);

        $vanityMessages = [
            ["Podcast Pivot!", "<p>You decided to pivot your podcast from 'Wealth & Wellness' to 'Wealth & Whatever.' You hired an entire production team, built a new studio with Italian marble floors, and flew in a celebrity guest who didn't show up.</p><p>Extra cost this month: <b>\$" . number_format($extraCost, 2) . "</b></p><p>Your ongoing vanity expenses increased by " . round($increase * 100) . "%.</p>"],
            ["Documentary in Progress!", "<p>You hired a documentary crew to follow your 'journey of self-discovery.' The director insists on shooting everything on 70mm IMAX film. Nobody will ever watch this.</p><p>Extra cost: <b>\$" . number_format($extraCost, 2) . "</b></p>"],
            ["Fashion Line Launch!", "<p>You launched a fashion line called 'BROKE.' The irony is lost on everyone. You spent a fortune on a runway show attended by 12 people, 8 of whom were your staff.</p><p>Extra cost: <b>\$" . number_format($extraCost, 2) . "</b></p>"],
            ["Memoir Writing Retreat!", "<p>You flew to a private island to write your memoir. You wrote three sentences, then spent the rest of the month at the spa. The ghostwriter you hired charges per word.</p><p>Extra cost: <b>\$" . number_format($extraCost, 2) . "</b></p>"],
            ["Brand Collab!", "<p>You collaborated with a luxury brand to create a limited-edition product nobody asked for. The marketing campaign cost more than the product will ever earn.</p><p>Extra cost: <b>\$" . number_format($extraCost, 2) . "</b></p>"],
        ];

        $msg = $vanityMessages[array_rand($vanityMessages)];
        $quote = $this->quotesService->getQuoteForContext('general');

        return [
            'title' => $msg[0],
            'body' => $msg[1] . '<p class="text-sm italic text-gray-400 mt-2">"' . $quote . '"</p>',
        ];
    }

    private function processThrowParty(object $player): array
    {
        // Base party cost: $50K-$500K, scaled by Party Planner rating
        $baseCost = rand(50000, 500000) * $player->OneDollar;
        $partyPlannerMultiplier = 1 + (1.0 * $player->PartyPlannerRating / 100);
        $yesManMultiplier = 1 + (0.15 * $player->YesManRating / 100);
        $totalCost = round($baseCost * $partyPlannerMultiplier * $yesManMultiplier, 2);

        $this->bankService->debit(
            $player->PlayerID, $player->Turn,
            'Threw an Absolutely Legendary Party',
            'PARTY', $totalCost
        );

        $this->playerModel->update($player->PlayerID, [
            'TurnAction' => 0,
            'Turn' => $player->Turn + 1,
        ]);

        $partyDescriptions = [
            "You rented out an entire five-star hotel, flew in a famous DJ, and served champagne from bottles that cost more than most people's cars. The ice sculpture alone cost $47,000.",
            "You hosted a 'casual dinner' for 200 of your closest friends (you know maybe 30 of them). The menu featured endangered-species-adjacent cuisine and desserts covered in edible gold.",
            "You threw a costume party where the dress code was 'wear something worth more than a house.' Someone came as a stack of burning money. Points for honesty.",
            "You hosted a pool party on your yacht while it was docked. The pool was filled with champagne. Not metaphorically. Actual champagne. The environmental impact was... a topic of discussion.",
            "You rented a castle for the weekend and hired actors to pretend to be medieval servants. The jousting tournament cost extra because one of the horses was a thoroughbred you already owned.",
            "You threw a 'simple garden party' that required importing 10,000 rare orchids, building a temporary waterfall, and hiring Cirque du Soleil performers as 'ambient entertainment.'",
        ];

        $desc = $partyDescriptions[array_rand($partyDescriptions)];
        $quote = $this->quotesService->getQuoteForContext('party');

        return [
            'title' => 'What A Party!',
            'body' => "<p>{$desc}</p><p>Total cost: <b>\$" . number_format($totalCost, 2) . "</b></p>"
                . '<p class="text-sm italic text-gray-400 mt-2">"' . $quote . '"</p>',
        ];
    }

    private function processFlyFriendsIn(object $player): array
    {
        // Base cost: $100K-$1M
        $baseCost = rand(100000, 1000000) * $player->OneDollar;
        $travelMultiplier = 1 + (0.40 * $player->TravelAgentRating / 100);
        $totalCost = round($baseCost * $travelMultiplier, 2);

        $this->bankService->debit(
            $player->PlayerID, $player->Turn,
            'Private Jet Friends Airlift Operation',
            'TRAVEL', $totalCost
        );

        $this->playerModel->update($player->PlayerID, [
            'TurnAction' => 0,
            'Turn' => $player->Turn + 1,
        ]);

        $travelDescriptions = [
            "You flew 30 friends from 14 different countries to your Maldives island for a 'low-key weekend.' Each guest received a gift bag worth $5,000. The jet fuel bill alone could fund a small school.",
            "You chartered four private jets to bring your college friends to Aspen. Most of them don't ski. You also flew in the ski instructor from Switzerland. First class.",
            "You organized a 'friends reunion' that required coordinating private flights from New York, London, Tokyo, and Dubai. The in-flight catering featured lobster thermidor and Dom Perignon.",
            "You decided your friend's birthday needed to be 'special,' so you flew them (and 40 guests) to Monaco for the Grand Prix. You rented a mega-yacht just to watch from the harbor.",
            "You hosted a 'book club meeting' that required flying everyone to a chateau in the south of France. Nobody read the book. The wine bill was $280,000.",
        ];

        $desc = $travelDescriptions[array_rand($travelDescriptions)];
        $quote = $this->quotesService->getQuoteForContext('general');

        return [
            'title' => 'Friends Airlifted Successfully!',
            'body' => "<p>{$desc}</p><p>Total cost: <b>\$" . number_format($totalCost, 2) . "</b></p>"
                . '<p class="text-sm italic text-gray-400 mt-2">"' . $quote . '"</p>',
        ];
    }

    /**
     * Calculate total net worth.
     */
    public function calculateNetWorth(object $player): float
    {
        $playerId = $player->PlayerID;
        $bankBalance = $this->bankService->getBalance($playerId);
        $propertySummary = $this->propertyService->getPropertySummary($playerId);
        $toySummary = $this->toyService->getPortfolioSummary($playerId);
        $investSummary = $this->investmentService->getPortfolioSummary($playerId, $player->OneDollar);

        return $bankBalance
            + $propertySummary['totalEquity']
            + $toySummary['totalValue']
            + ($investSummary['totalCurrentValue'] ?? 0);
    }

    private function getOwnerMemberLevel(int $ownerId): int
    {
        $owner = $this->db->fetch("SELECT MemberLevel FROM br_owners WHERE OwnerID = ? LIMIT 1", [$ownerId]);
        return $owner ? (int) $owner->MemberLevel : 0;
    }

    private function logGameState(object $player): void
    {
        $playerId = $player->PlayerID;
        $bankBalance = $this->bankService->getBalance($playerId);
        $iaBankBalance = $player->OneDollar > 0 ? $bankBalance / $player->OneDollar : $bankBalance;

        $propertySummary = $this->propertyService->getPropertySummary($playerId);
        $propertyNetWorth = $propertySummary['totalEquity'];
        $iaPropertyNetWorth = $player->OneDollar > 0 ? $propertyNetWorth / $player->OneDollar : $propertyNetWorth;

        $toySummary = $this->toyService->getPortfolioSummary($playerId);
        $toyNetWorth = $toySummary['totalValue'];
        $iaToyNetWorth = $player->OneDollar > 0 ? $toyNetWorth / $player->OneDollar : $toyNetWorth;

        $investSummary = $this->investmentService->getPortfolioSummary($playerId, $player->OneDollar);

        $netWorth = $propertyNetWorth + $toyNetWorth + $bankBalance + ($investSummary['totalCurrentValue'] ?? 0);
        $iaNetWorth = $player->OneDollar > 0 ? $netWorth / $player->OneDollar : $netWorth;

        $gameLog = new GameLog($this->db);
        $gameLog->log([
            'Turn' => $player->Turn,
            'PlayerID' => $playerId,
            'BankBalance' => round($bankBalance, 2),
            'IABankBalance' => round($iaBankBalance, 2),
            'OneDollar' => $player->OneDollar,
            'WeekInRealTime' => date('W'),
            'YearInRealTime' => date('Y'),
            'NumProperties' => $propertySummary['count'],
            'NumToys' => $toySummary['count'],
            'NumInvestments' => $investSummary['count'] ?? 0,
            'NetWorth' => round($netWorth, 2),
            'IANetWorth' => round($iaNetWorth, 2),
            'PropertyNetWorth' => round($propertyNetWorth, 2),
            'PropertyIANetWorth' => round($iaPropertyNetWorth, 2),
            'ToyNetWorth' => round($toyNetWorth, 2),
            'ToyIANetWorth' => round($iaToyNetWorth, 2),
            'StockMarketRate' => $player->StockMarketRate,
            'InterestRate' => $player->InterestRate,
            'InflationRate' => $player->InflationRate,
        ]);

        $this->playerModel->update($playerId, [
            'GameLogProcessedThrough' => $player->Turn,
        ]);
    }

    public function getEndGameData(object $player): array
    {
        $playerId = $player->PlayerID;
        $bankBalance = $this->bankService->getBalance($playerId);
        $propertySummary = $this->propertyService->getPropertySummary($playerId);
        $toySummary = $this->toyService->getPortfolioSummary($playerId);
        $investSummary = $this->investmentService->getPortfolioSummary($playerId, $player->OneDollar);

        $netWorth = $bankBalance + $propertySummary['totalEquity'] + $toySummary['totalValue'] + ($investSummary['totalCurrentValue'] ?? 0);

        return [
            'netWorth' => $netWorth,
            'bankBalance' => $bankBalance,
            'propertySummary' => $propertySummary,
            'toySummary' => $toySummary,
            'investSummary' => $investSummary,
            'entourage' => $this->entourageService->getTeamRatings($player),
            'turns' => $player->Turn,
            'bankruptTurn' => $player->BankruptTurn,
            'totalBurned' => 1000000000 - $netWorth,
            'quote' => $this->quotesService->getQuoteForContext(
                $player->BankruptTurn > 0 ? 'bankruptcy' : 'general'
            ),
        ];
    }

    /**
     * Get full dashboard data for the game menu.
     */
    public function getDashboardData(object $player): array
    {
        $playerId = $player->PlayerID;
        $bankBalance = $this->bankService->getBalance($playerId);

        return [
            'player' => $player,
            'bankBalance' => $bankBalance,
            'propertySummary' => $this->propertyService->getPropertySummary($playerId),
            'toySummary' => $this->toyService->getPortfolioSummary($playerId),
            'investSummary' => $this->investmentService->getPortfolioSummary($playerId, $player->OneDollar),
            'entourage' => $this->entourageService->getTeamRatings($player),
            'economy' => $this->economyService->getEconomyData($player),
            'recentTransactions' => $this->bankService->getRecentTransactions($playerId, 15),
            'monthlyBurn' => $this->bankService->calculateMonthlyBurn($playerId, $player),
            'activeCrisis' => $this->crisisService->getActiveCrisis($player),
            'quote' => $this->quotesService->getQuoteForContext('general'),
        ];
    }
}
