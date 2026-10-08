<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Player;

class CrisisService
{
    private Database $db;

    private const CRISES = [
        [
            'name' => 'The Crypto Crater',
            'description' => 'Some guy named Satoshi just tweeted "lol jk" and the entire blockchain evaporated. Your crypto portfolio now has the purchasing power of a Blockbuster gift card.',
            'duration' => 8,
            'propertyEffect' => 1.0,
            'toyEffect' => 1.0,
            'investmentEffect' => 3.5,
            'lifestyleEffect' => 1.0,
            'categories' => ['crypto', 'defi', 'blockchain'],
        ],
        [
            'name' => 'The NFT Apocalypse',
            'description' => 'Turns out a JPEG of a bored ape was not, in fact, a store of value. Every digital asset you own is now worth less than the electricity used to mint it.',
            'duration' => 6,
            'propertyEffect' => 1.0,
            'toyEffect' => 1.2,
            'investmentEffect' => 4.0,
            'lifestyleEffect' => 1.0,
            'categories' => ['nft', 'digital_art', 'metaverse'],
        ],
        [
            'name' => 'The Subprime Yacht Crisis',
            'description' => 'Banks were handing out yacht loans to people who couldn\'t even afford a canoe. Now the marina looks like a floating foreclosure auction and your vessel is worth scrap metal.',
            'duration' => 10,
            'propertyEffect' => 2.0,
            'toyEffect' => 2.5,
            'investmentEffect' => 1.5,
            'lifestyleEffect' => 1.3,
            'categories' => ['luxury', 'marine', 'vehicles'],
        ],
        [
            'name' => 'The Avocado Toast Recession',
            'description' => 'Millennials finally stopped buying avocado toast and the entire luxury food economy collapsed. Your personal chef just quit to become a survivalist. Lifestyle costs are through the roof.',
            'duration' => 5,
            'propertyEffect' => 1.0,
            'toyEffect' => 1.0,
            'investmentEffect' => 1.2,
            'lifestyleEffect' => 3.0,
            'categories' => ['food', 'lifestyle', 'hospitality'],
        ],
        [
            'name' => 'The Influencer Bubble Burst',
            'description' => 'Instagram changed its algorithm and now nobody sees your sponsored content. Every influencer-backed venture you invested in just posted a crying selfie captioned "pivoting to authenticity."',
            'duration' => 4,
            'propertyEffect' => 1.0,
            'toyEffect' => 1.5,
            'investmentEffect' => 2.5,
            'lifestyleEffect' => 1.8,
            'categories' => ['social_media', 'marketing', 'entertainment'],
        ],
        [
            'name' => 'The Private Jet Fuel Crisis',
            'description' => 'OPEC decided to "quiet quit" and jet fuel now costs more per gallon than vintage champagne. Your fleet of private jets has become the world\'s most expensive lawn ornaments.',
            'duration' => 7,
            'propertyEffect' => 1.0,
            'toyEffect' => 3.0,
            'investmentEffect' => 1.5,
            'lifestyleEffect' => 2.0,
            'categories' => ['vehicles', 'energy', 'travel'],
        ],
        [
            'name' => 'The Art Market Meltdown',
            'description' => 'A renowned art critic declared "everything after 1850 is just vibes" and the contemporary art market vaporized overnight. Your Basquiat is now valued at the price of the frame.',
            'duration' => 6,
            'propertyEffect' => 1.0,
            'toyEffect' => 2.0,
            'investmentEffect' => 3.0,
            'lifestyleEffect' => 1.0,
            'categories' => ['art', 'collectibles', 'luxury'],
        ],
        [
            'name' => 'The Celebrity Chef Scandal',
            'description' => 'Your Michelin-starred personal chef was caught microwaving everything. The ensuing scandal tanked the entire premium dining industry. Your $500 truffle risotto was actually Uncle Ben\'s.',
            'duration' => 3,
            'propertyEffect' => 1.0,
            'toyEffect' => 1.0,
            'investmentEffect' => 1.5,
            'lifestyleEffect' => 2.5,
            'categories' => ['food', 'hospitality', 'entertainment'],
        ],
        [
            'name' => 'The Space Tourism Disaster',
            'description' => 'The first civilian space hotel accidentally launched itself into a decaying orbit. Your deposit is now literally burning up in the atmosphere, along with everyone\'s confidence in billionaire vanity projects.',
            'duration' => 9,
            'propertyEffect' => 1.0,
            'toyEffect' => 1.5,
            'investmentEffect' => 4.0,
            'lifestyleEffect' => 1.0,
            'categories' => ['space', 'tech', 'venture_capital'],
        ],
        [
            'name' => 'The AI Hype Collapse',
            'description' => 'Turns out AI couldn\'t actually replace everyone, and investors realized they\'d been funding very expensive autocomplete. Your tech portfolio just had a "hallucination" about being worth something.',
            'duration' => 7,
            'propertyEffect' => 1.0,
            'toyEffect' => 1.2,
            'investmentEffect' => 3.0,
            'lifestyleEffect' => 1.0,
            'categories' => ['tech', 'ai', 'venture_capital'],
        ],
        [
            'name' => 'The Metaverse Implosion',
            'description' => 'Someone finally asked "but why would I want to attend a meeting as a legless cartoon?" and the entire virtual real estate market collapsed. Your digital penthouse is now a 404 error.',
            'duration' => 5,
            'propertyEffect' => 1.0,
            'toyEffect' => 1.0,
            'investmentEffect' => 3.5,
            'lifestyleEffect' => 1.0,
            'categories' => ['metaverse', 'vr', 'digital_art', 'tech'],
        ],
        [
            'name' => 'The Wine Cellar Catastrophe',
            'description' => 'A master sommelier revealed that 80% of "rare vintage" wines in circulation are actually Two Buck Chuck with fancy labels. Your cellar full of "1945 Bordeaux" is worth approximately $11.50.',
            'duration' => 4,
            'propertyEffect' => 1.0,
            'toyEffect' => 2.5,
            'investmentEffect' => 2.0,
            'lifestyleEffect' => 1.5,
            'categories' => ['collectibles', 'luxury', 'food'],
        ],
        [
            'name' => 'The Great Hedge Fund Unraveling',
            'description' => 'Your hedge fund manager\'s "proprietary algorithm" turned out to be a Magic 8-Ball taped to a Bloomberg terminal. The SEC is involved and your returns have un-returned.',
            'duration' => 12,
            'propertyEffect' => 1.2,
            'toyEffect' => 1.0,
            'investmentEffect' => 4.0,
            'lifestyleEffect' => 1.0,
            'categories' => ['hedge_fund', 'finance', 'stocks'],
        ],
        [
            'name' => 'The Social Media Reckoning',
            'description' => 'Your old tweets resurfaced and now every brand is distancing themselves from you faster than a crypto bro from tax obligations. Reputation damage is costing you a fortune in crisis PR.',
            'duration' => 6,
            'propertyEffect' => 1.0,
            'toyEffect' => 1.0,
            'investmentEffect' => 1.5,
            'lifestyleEffect' => 2.5,
            'categories' => ['social_media', 'marketing', 'entertainment'],
        ],
        [
            'name' => 'The Supply Chain of Fools',
            'description' => 'A single cargo ship got stuck sideways again, but this time it was carrying literally everything you need. Your imported Italian marble countertops are now floating somewhere near Djibouti.',
            'duration' => 8,
            'propertyEffect' => 1.8,
            'toyEffect' => 1.8,
            'investmentEffect' => 1.5,
            'lifestyleEffect' => 2.0,
            'categories' => ['logistics', 'retail', 'luxury'],
        ],
        [
            'name' => 'The Wellness Industry Detox',
            'description' => 'A study revealed that your $400/month adaptogenic mushroom supplement is literally just dirt. The entire wellness-industrial complex is imploding and your spa membership is worthless.',
            'duration' => 4,
            'propertyEffect' => 1.0,
            'toyEffect' => 1.5,
            'investmentEffect' => 2.0,
            'lifestyleEffect' => 2.5,
            'categories' => ['wellness', 'lifestyle', 'hospitality'],
        ],
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Check if a new crisis should fire this turn.
     * ~4% chance per turn. Only one active crisis at a time.
     * Returns crisis data array or null.
     */
    public function checkForCrisis(object $player): ?array
    {
        // If player already has an active crisis, skip
        $activeCrisis = $this->getActiveCrisis($player);
        if ($activeCrisis !== null) {
            return null;
        }

        // ~4% chance of a crisis firing
        if (rand(1, 100) > 4) {
            return null;
        }

        // Pick a random crisis from the catalog
        $crisis = self::CRISES[array_rand(self::CRISES)];

        // Vary the duration slightly (±1 turn)
        $duration = max(2, $crisis['duration'] + rand(-1, 1));

        $crisisData = [
            'name' => $crisis['name'],
            'description' => $crisis['description'],
            'duration' => $duration,
            'turnsRemaining' => $duration,
            'propertyEffect' => $crisis['propertyEffect'],
            'toyEffect' => $crisis['toyEffect'],
            'investmentEffect' => $crisis['investmentEffect'],
            'lifestyleEffect' => $crisis['lifestyleEffect'],
            'categories' => $crisis['categories'],
            'startedOnTurn' => $player->Turn,
        ];

        // Store the crisis on the player record
        $playerModel = new Player($this->db);
        $playerModel->update($player->PlayerID, [
            'ActiveCrisis' => json_encode($crisisData),
            'CrisisTurnsRemaining' => $duration,
        ]);

        return $crisisData;
    }

    /**
     * Get the currently active crisis for a player.
     * Returns the crisis data array or null if no crisis is active.
     */
    public function getActiveCrisis(object $player): ?array
    {
        if (empty($player->ActiveCrisis)) {
            return null;
        }

        $crisis = json_decode($player->ActiveCrisis, true);
        if (!is_array($crisis) || empty($crisis['name'])) {
            return null;
        }

        return $crisis;
    }

    /**
     * Process an active crisis: decrement remaining turns and clear when done.
     * Returns messages about the crisis status.
     */
    public function processCrisis(object $player): array
    {
        $messages = [];
        $crisis = $this->getActiveCrisis($player);

        if ($crisis === null) {
            return $messages;
        }

        $playerModel = new Player($this->db);
        $turnsRemaining = (int) $player->CrisisTurnsRemaining;
        $turnsRemaining--;

        if ($turnsRemaining <= 0) {
            // Crisis is over
            $playerModel->update($player->PlayerID, [
                'ActiveCrisis' => null,
                'CrisisTurnsRemaining' => 0,
            ]);

            $messages[] = [
                'title' => 'Crisis Over: ' . $crisis['name'],
                'body' => sprintf(
                    '<p><b>%s</b> has finally ended.</p><p>Markets are stabilizing. '
                    . 'Your accountant has stopped crying. For now.</p>',
                    $crisis['name']
                ),
            ];
        } else {
            // Crisis continues - update remaining turns in both fields
            $crisis['turnsRemaining'] = $turnsRemaining;

            $playerModel->update($player->PlayerID, [
                'ActiveCrisis' => json_encode($crisis),
                'CrisisTurnsRemaining' => $turnsRemaining,
            ]);

            $messages[] = [
                'title' => 'Crisis Ongoing: ' . $crisis['name'],
                'body' => sprintf(
                    '<p><b>%s</b> continues to wreak havoc.</p>'
                    . '<p>%s</p>'
                    . '<p><em>%d turn%s remaining.</em></p>',
                    $crisis['name'],
                    $crisis['description'],
                    $turnsRemaining,
                    $turnsRemaining === 1 ? '' : 's'
                ),
            ];
        }

        return $messages;
    }

    /**
     * Get the current effect multipliers based on any active crisis.
     * Returns an array of multipliers (all 1.0 if no crisis is active).
     */
    public function applyCrisisEffects(object $player): array
    {
        $defaults = [
            'propertyEffect' => 1.0,
            'toyEffect' => 1.0,
            'investmentEffect' => 1.0,
            'lifestyleEffect' => 1.0,
            'categories' => [],
            'crisisName' => null,
        ];

        $crisis = $this->getActiveCrisis($player);

        if ($crisis === null) {
            return $defaults;
        }

        return [
            'propertyEffect' => (float) $crisis['propertyEffect'],
            'toyEffect' => (float) $crisis['toyEffect'],
            'investmentEffect' => (float) $crisis['investmentEffect'],
            'lifestyleEffect' => (float) $crisis['lifestyleEffect'],
            'categories' => $crisis['categories'] ?? [],
            'crisisName' => $crisis['name'],
        ];
    }
}
