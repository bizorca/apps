<?php

/*
 * Game settings. Database, session and sign-in come from the shared tools
 * core now; the original's 'database', 'session' and 'sso' blocks are gone.
 */
return [
    'app' => [
        'name' => 'Burn Rate',
        'url' => (string) tl_env('BR_ORIGIN', 'https://tools.bizorca.com'),
    ],

    'game' => [
        'max_turns' => 611,
        'starting_balance' => 1000000000, // $1 billion inheritance
        'vanity_income' => 2500, // Monthly podcast/influencer pittance
        'vanity_expenses' => 15000, // Monthly cost to maintain vanity project
        'lifestyle_burn' => 500000, // Base monthly lifestyle (staff, food, travel)
        'bank_interest_multiplier' => 0.35,
        'default_interest_rate' => 6.0,
        'default_inflation_rate' => 4.0, // Slightly higher - everything costs more
        'toy_commission_rate' => 0.15, // 15% dealer commission on toy sales
        'entourage_upgrade_cost' => 50000, // Base cost per enabler upgrade
    ],

    'mail' => [
        'from_email' => 'noreply@bizorca.com',
        'from_name' => 'Burn Rate',
    ],


    // Never wired to anything in the original (no checkout code exists); kept
    // so a future integration reads keys from .env.php, not the database.
    'stripe' => [
        'public_key' => (string) tl_env('BR_STRIPE_PUBLIC_KEY', ''),
        'secret_key' => (string) tl_env('BR_STRIPE_SECRET_KEY', ''),
        'webhook_secret' => (string) tl_env('BR_STRIPE_WEBHOOK_SECRET', ''),
    ],

    'membership' => [
        0 => ['name' => 'Freeloader', 'initial' => 0, 'monthly' => 0],
        1 => ['name' => 'Trust Fund Baby', 'initial' => 24.99, 'monthly' => 4.95],
        16 => ['name' => 'Silver Spoon', 'initial' => 24.99, 'monthly' => 24.99],
        256 => ['name' => 'Gold Digger', 'initial' => 49.99, 'monthly' => 39.99],
        4096 => ['name' => 'Platinum Spender', 'initial' => 149, 'monthly' => 97],
        65536 => ['name' => 'Diamond Destroyer', 'initial' => 297, 'monthly' => 197],
        1048576 => ['name' => 'Inner Circle of Ruin', 'initial' => 997, 'monthly' => 497],
    ],
];
