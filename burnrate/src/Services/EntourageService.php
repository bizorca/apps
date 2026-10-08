<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Player;

class EntourageService
{
    private Database $db;
    private BankService $bankService;

    public const ENABLERS = [
        401 => ['field' => 'PersonalShopperRating', 'name' => 'Personal Shopper',
                'desc' => 'Finds increasingly "exclusive" and overpriced luxury items. Higher rating = higher markups on everything you buy.',
                'sardonic' => 'Because nothing says "I earned this" like paying triple the retail price.'],
        402 => ['field' => 'PartyPlannerRating', 'name' => 'Party Planner',
                'desc' => 'Makes your parties legendary. And legendarily expensive. Each upgrade adds ice sculptures and celebrity DJs.',
                'sardonic' => 'Your last party cost more than most countries\' GDP.'],
        403 => ['field' => 'YesManRating', 'name' => 'Yes Man',
                'desc' => 'Agrees with every terrible financial decision you make. Accelerates all spending across the board.',
                'sardonic' => '"That\'s a GREAT idea!" - Your Yes Man, on literally everything.'],
        404 => ['field' => 'CelebrityChefRating', 'name' => 'Celebrity Chef',
                'desc' => 'Your personal chef now requires high-end truffle oil and saffron for breakfast. Food costs skyrocket.',
                'sardonic' => 'Your scrambled eggs now cost $847. They are admittedly delicious.'],
        405 => ['field' => 'FashionConsultantRating', 'name' => 'Fashion Consultant',
                'desc' => 'Ensures you never wear the same outfit twice. Your wardrobe costs more than some hedge funds.',
                'sardonic' => 'You now own 340 pairs of shoes. You have two feet.'],
        406 => ['field' => 'ArtDealerRating', 'name' => 'Art Dealer',
                'desc' => 'Finds you increasingly questionable "masterpieces" at increasingly absurd prices.',
                'sardonic' => '"It\'s not a scribble, it\'s a statement." - Your Art Dealer, defending a $4M napkin doodle.'],
        407 => ['field' => 'InteriorDesignerRating', 'name' => 'Interior Designer',
                'desc' => 'Redecorates your mansions constantly. Nothing is ever finished. Bills never stop.',
                'sardonic' => 'They just suggested replacing all your doors with "statement portals." Cost: $2M.'],
        410 => ['field' => 'TravelAgentRating', 'name' => 'Travel Agent',
                'desc' => 'Books increasingly absurd and expensive trips. Next stop: a private submarine tour of the Titanic.',
                'sardonic' => 'Your last "quick getaway" required chartering three planes and a yacht.'],
        411 => ['field' => 'SocialMediaManagerRating', 'name' => 'Social Media Manager',
                'desc' => 'Creates pressure to spend for clout. Every purchase must be Instagram-worthy. Lifestyle costs increase.',
                'sardonic' => 'Your followers love watching you burn money. You have 47 followers.'],
        412 => ['field' => 'LifeCoachRating', 'name' => 'Life Coach',
                'desc' => '"You deserve it!" mentality applied to everything. All costs increase as self-care knows no budget.',
                'sardonic' => '"The universe wants you to buy that island." - Your Life Coach, who charges $50K/session.'],
        413 => ['field' => 'AstrologerRating', 'name' => 'Astrologer',
                'desc' => '"Mercury is in retrograde, so invest heavily in crypto." Makes bad investments even more expensive.',
                'sardonic' => 'Jupiter aligned with your credit card. Time to invest in moon cheese futures.'],
        414 => ['field' => 'ConciergeRating', 'name' => 'Concierge',
                'desc' => '24/7 access to everything expensive. They never say no and always upsell.',
                'sardonic' => '"Certainly, sir. Shall I also arrange for the dolphin masseuse?"'],
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->bankService = new BankService($db);
    }

    /**
     * Process an enabler upgrade action.
     * Unlike advisors who save money, enablers help you SPEND more.
     * The rating increases by a random amount boosted by Life Coach and Yes Man.
     * Higher-rated enablers cost more to upgrade.
     */
    public function processEnablerUpgrade(object $player, int $actionCode): array
    {
        if (!isset(self::ENABLERS[$actionCode])) {
            return ['error' => 'Invalid enabler action.'];
        }

        $enabler = self::ENABLERS[$actionCode];
        $field = $enabler['field'];
        $name = $enabler['name'];
        $currentRating = (float) $player->$field;

        if ($currentRating >= 100) {
            return [
                'title' => "Already Maximally Enabled",
                'body' => "<p>Your <b>{$name}</b> is already maximally enabling your destruction. Rating: 100%</p>",
            ];
        }

        // Calculate improvement boosted by Life Coach (replaces PDC) and Yes Man (replaces Mentor)
        $lifeCoachBonus = $player->LifeCoachRating / 100;
        $baseImprovement = rand(1, 10) + rand(1, 5);
        $yesManBonus = ($player->YesManRating / 100) * 5;
        $improvement = $baseImprovement * (1 + $lifeCoachBonus) + $yesManBonus;
        $newRating = min(100, $currentRating + $improvement);

        // Charge for the upgrade - higher rated enablers cost more
        $upgradeCost = round(50000 * $player->OneDollar * (1 + $currentRating / 100), 2);
        $this->bankService->debit(
            $player->PlayerID,
            $player->Turn,
            "Enabler Upgrade - {$name}",
            'ENTOURAGE',
            $upgradeCost
        );

        // Update the enabler rating
        $playerModel = new Player($this->db);
        $playerModel->update($player->PlayerID, [
            $field => round($newRating, 2),
            'TurnAction' => 0,
        ]);

        // Advance turn
        $this->advanceTurn($player);

        return [
            'title' => "Upgraded {$name}!",
            'body' => sprintf(
                '<p>You threw money at your <b>%s</b> to make them even more effective at draining your fortune.</p>
                 <p>Your %s rating improved from <b>%s%%</b> to <b>%s%%</b> (+%s%%).</p>
                 <p>Upgrade cost: <b>$%s</b></p>
                 <p class="text-sm text-gray-500 italic">"%s"</p>',
                $name, $name,
                number_format($currentRating, 1),
                number_format($newRating, 1),
                number_format($improvement, 1),
                number_format($upgradeCost, 2),
                $enabler['sardonic']
            ),
        ];
    }

    /**
     * Get all enabler ratings for the entourage display.
     */
    public function getTeamRatings(object $player): array
    {
        $team = [];
        foreach (self::ENABLERS as $code => $enabler) {
            $field = $enabler['field'];
            $team[] = [
                'code' => $code,
                'name' => $enabler['name'],
                'description' => $enabler['desc'],
                'sardonic' => $enabler['sardonic'],
                'rating' => (float) $player->$field,
                'maxed' => $player->$field >= 100,
            ];
        }
        return $team;
    }

    /**
     * Get calculated effect multipliers based on all enabler ratings.
     * These multipliers make everything MORE expensive - the whole point of the entourage.
     */
    public function getEnablerEffects(object $player): array
    {
        return [
            'toyMarkup'         => 1 + 0.20 * $player->PersonalShopperRating / 100,
            'partyCost'         => 1 + 1.0  * $player->PartyPlannerRating / 100,
            'allSpending'       => 1 + 0.15 * $player->YesManRating / 100,
            'foodCost'          => 1 + 0.5  * $player->CelebrityChefRating / 100,
            'wardrobeCost'      => 1 + 0.3  * $player->FashionConsultantRating / 100,
            'artMarkup'         => 1 + 0.25 * $player->ArtDealerRating / 100,
            'renovationCost'    => 1 + 0.3  * $player->InteriorDesignerRating / 100,
            'travelCost'        => 1 + 0.4  * $player->TravelAgentRating / 100,
            'lifestyleIncrease' => 1 + 0.2  * $player->SocialMediaManagerRating / 100,
            'selfCareCost'      => 1 + 0.3  * $player->LifeCoachRating / 100,
            'investmentMarkup'  => 1 + 0.15 * $player->AstrologerRating / 100,
            'conciergeSurcharge' => 1 + 0.1 * $player->ConciergeRating / 100,
        ];
    }

    private function advanceTurn(object $player): void
    {
        $playerModel = new Player($this->db);
        $playerModel->update($player->PlayerID, [
            'Turn' => $player->Turn + 1,
        ]);
    }
}
