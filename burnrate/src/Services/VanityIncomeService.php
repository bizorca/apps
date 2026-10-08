<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Player;

class VanityIncomeService
{
    private Database $db;
    private BankService $bankService;

    /**
     * Sardonic reasons why lifestyle costs increased.
     * Displayed every 24 turns when cost of living adjustment kicks in.
     */
    private array $lifestyleIncreaseReasons = [
        "Your celebrity chef demanded a raise. You're now paying him more than most CEOs.",
        "Your security team insisted on armored golf carts. Monthly costs went up.",
        "You decided every bathroom needs fresh orchids daily. Naturally.",
        "Your dog's therapist recommended a companion dog. Who also needs a therapist.",
        "The yacht captain says the crew needs morale-boosting shore leave in Monaco. Every month.",
        "Your personal meteorologist quit and you had to hire one from the BBC. Premium talent.",
        "The new mansion wing requires its own HVAC system. And its own maintenance crew.",
        "Your astrologer says Mercury is in retrograde, so you need a backup astrologer for second opinions.",
        "You upgraded from a helicopter to a helicopter fleet. Someone has to maintain them.",
        "Your sommelier insists the wine cellar needs a dedicated climate scientist on retainer.",
        "The staff holiday party now costs more than most people's weddings. And it's quarterly.",
        "Your closet organizer demanded a team of three. Apparently one person can't handle that many shoes.",
        "You installed a bowling alley in the guest house. The pin-setter alone costs a fortune.",
        "Your personal shopper accidentally discovered a new tier of luxury. You're in too deep now.",
        "The koi pond consultant recommended upgrading to Japanese imperial-grade fish. $40K each.",
        "Your house manager hired a house manager manager. It's managers all the way down.",
    ];

    /**
     * Messages when the player chooses to focus on their vanity project (action 501).
     * This doubles vanity expenses for the turn because of course it does.
     */
    private array $vanityUpgradeMessages = [
        [
            'title' => 'Podcast Studio Upgrade!',
            'body' => '<p>You spent the month renovating your podcast studio with acoustic panels made from reclaimed Italian marble. Your three listeners did not notice.</p><p>Vanity project expenses <b>doubled</b> this month.</p>',
        ],
        [
            'title' => 'Influencer Retreat!',
            'body' => '<p>You flew to Bali for a "content creation retreat" with other influencers. You gained 4 followers and lost your luggage.</p><p>Vanity project expenses <b>doubled</b> this month.</p>',
        ],
        [
            'title' => 'Hired a Content Strategist!',
            'body' => '<p>Your new content strategist recommended posting more. Groundbreaking. They charge $8,000/month for this advice.</p><p>Vanity project expenses <b>doubled</b> this month.</p>',
        ],
        [
            'title' => 'Celebrity Guest Booking!',
            'body' => "<p>You paid a D-list celebrity to appear on your podcast. They cancelled last minute but kept the deposit. Your editor used AI to fake the interview. Nobody could tell.</p><p>Vanity project expenses <b>doubled</b> this month.</p>",
        ],
        [
            'title' => 'Rebrand Initiative!',
            'body' => '<p>You hired a branding agency to redesign your podcast logo. After 3 months and $50K, they changed the font from Helvetica to Helvetica Neue. Stunning work.</p><p>Vanity project expenses <b>doubled</b> this month.</p>',
        ],
        [
            'title' => 'Merch Line Launch!',
            'body' => '<p>You launched a merchandise line for your 3 listeners. You now own 2,000 unsold hoodies stored in a climate-controlled warehouse. Monthly storage fees apply.</p><p>Vanity project expenses <b>doubled</b> this month.</p>',
        ],
        [
            'title' => 'Live Show Attempt!',
            'body' => '<p>You rented a 500-seat venue for a live podcast recording. Seven people showed up, including your driver and your chef. You bought everyone front-row seats to make it look less empty.</p><p>Vanity project expenses <b>doubled</b> this month.</p>',
        ],
        [
            'title' => 'Equipment Splurge!',
            'body' => '<p>You replaced all your recording equipment with gear used by Joe Rogan\'s sound engineer\'s intern. The audio quality is imperceptibly different. Your three listeners remain loyal.</p><p>Vanity project expenses <b>doubled</b> this month.</p>',
        ],
        [
            'title' => 'Social Media Ad Blitz!',
            'body' => '<p>You spent a fortune on targeted ads for your podcast. You gained 12 followers, 11 of whom are bots. The remaining one unfollowed after listening to the first episode.</p><p>Vanity project expenses <b>doubled</b> this month.</p>',
        ],
        [
            'title' => 'Documentary About Your Journey!',
            'body' => '<p>You hired a film crew to document your "rise as a content creator." The crew is now more famous than you are. The documentary remains unfinished.</p><p>Vanity project expenses <b>doubled</b> this month.</p>',
        ],
        [
            'title' => 'Podcast Network Pitch!',
            'body' => '<p>You flew to LA to pitch your podcast to every major network. They all passed. Your Uber driver recognized your name from a negative Yelp review you left, not the podcast.</p><p>Vanity project expenses <b>doubled</b> this month.</p>',
        ],
        [
            'title' => 'Viral Moment (Almost)!',
            'body' => '<p>You went viral! Unfortunately, it was a clip of you mispronouncing "quinoa" on your cooking segment. Downloads went up by 2. Vanity project expenses <b>doubled</b> this month because you hired a crisis PR team.</p>',
        ],
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->bankService = new BankService($db);
    }

    /**
     * Process monthly vanity income, vanity expenses, lifestyle burn, and bank interest.
     * Called once per turn. The player has no real job -- just a vanity project
     * (podcast/influencer gig) that earns almost nothing but costs a fortune.
     *
     * Mirrors the original JobService::processJobAndExpenses() flow.
     */
    public function processVanityAndExpenses(object $player): array
    {
        $messages = [];
        $playerId = $player->PlayerID;
        $turn = $player->Turn;

        // Update last played date
        $playerModel = new Player($this->db);
        $playerModel->update($playerId, [
            'LastPlayedDate' => date('Y-m-d H:i:s'),
        ]);

        // 1. Vanity "Income" (podcast revenue -- 3 listeners, essentially nothing)
        $balance = $this->bankService->credit(
            $playerId, $turn,
            'Podcast Revenue (3 listeners this month)',
            'VANITY',
            $player->VanityIncome
        );

        // 2. Vanity Expenses (studio, editor, gear -- nobody cares but you keep paying)
        $balance = $this->bankService->debit(
            $playerId, $turn,
            'Vanity Project Expenses (studio, editor, nobody cares)',
            'VANITY',
            $player->VanityExpenses
        );

        // 3. Lifestyle Burn (staff, chef, drivers -- the usual obscene overhead)
        $balance = $this->bankService->debit(
            $playerId, $turn,
            'Lifestyle Expenses (staff, chef, drivers, the usual)',
            'LIFE',
            $player->LifestyleBurn
        );

        // 4. Bank Interest on remaining balance (only if positive)
        if ($balance > 0) {
            $interestOnBalance = (0.35 * $player->InterestRate / 1200) * $balance;
            $balance = $this->bankService->credit(
                $playerId, $turn,
                'Bank Interest (enjoying it while it lasts)',
                'INT',
                $interestOnBalance
            );
        }

        // 5. Every 24 turns: lifestyle expenses INCREASE by 1-3x inflation rate
        //    The cost of living goes UP for the rich too. Actually, especially for the rich.
        if ($turn > 0 && ($turn % 24) == 0) {
            $inflationFactor = rand(100, 300) / 100; // 1x to 3x
            $increaseRate = $inflationFactor * $player->InflationRate;
            $newLifestyleBurn = $player->LifestyleBurn * (1 + ($increaseRate / 100));

            $playerModel->update($playerId, [
                'LifestyleBurn' => $newLifestyleBurn,
                'TurnAction' => 0,
            ]);

            $reason = $this->lifestyleIncreaseReasons[array_rand($this->lifestyleIncreaseReasons)];

            $messages[] = [
                'title' => 'Cost of Living Increase!',
                'body' => sprintf(
                    '<p>%s</p>
                     <p>Lifestyle expenses increased by %s%%.</p>
                     <p>New monthly burn: <b>$%s</b> (was $%s)</p>
                     <p><i>A dollar is now worth $%s in the game.</i></p>',
                    $reason,
                    number_format($increaseRate, 1),
                    number_format($newLifestyleBurn, 2),
                    number_format($player->LifestyleBurn, 2),
                    number_format($player->OneDollar, 2)
                ),
            ];
        }

        return $messages;
    }

    /**
     * Get sardonic messages for when the player focuses on their vanity project
     * (action 501). This doubles vanity expenses for the turn as a one-time cost,
     * because pouring more money into a podcast with 3 listeners is peak trust fund behavior.
     */
    public function getVanityUpgradeMessages(): array
    {
        return $this->vanityUpgradeMessages;
    }
}
