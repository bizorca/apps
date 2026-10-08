<?php
declare(strict_types=1);

/*
 * Proforma, as a tool on tools.bizorca.com/proforma.
 *
 * PF_ROOT is set by public/_bootstrap.php. Account and database come from the
 * shared core in private_html/includes: one users table, one MySQL database,
 * Proforma's tables prefixed pf_. Keys come from private_html/.env.php.
 */

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([PF_ROOT . '/../includes', PF_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

define('APP_NAME', 'ProForma');
define('PF_BASE',  '/proforma');                              // URL prefix for every link and redirect
define('APP_URL',  'https://tools.bizorca.com' . PF_BASE);   // absolute base for emails/links

// ---------------------------------------------------------------------------
// Email — SMTP2Go
// ---------------------------------------------------------------------------
define('SMTP2GO_API_KEY', (string) tl_env('SMTP2GO_API_KEY', ''));
define('SMTP2GO_API_URL', 'https://api.smtp2go.com/v3/');
define('EMAIL_FROM_NAME', 'ProForma');
define('EMAIL_FROM_ADDR', 'noreply@bizorca.com');

// Monthly digest endpoint secret, matched by the Cloudways cron URL.
define('CRON_SECRET', (string) tl_env('PF_CRON_SECRET', ''));

// ---------------------------------------------------------------------------
// Anthropic API — used by Localrev PlaybookGenerator
// ---------------------------------------------------------------------------
define('ANTHROPIC_API_KEY', (string) tl_env('ANTHROPIC_API_KEY', ''));
define('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages');

// Google Places + Geocoding API key (optional; enables auto-geocoding)
define('GOOGLE_PLACES_API_KEY', (string) tl_env('GOOGLE_PLACES_API_KEY', ''));

/** The shared MySQL handle. Kept under its old name so the pages need no change. */
function getDb(): PDO {
    return tl_db();
}

// ---------------------------------------------------------------------------
// Business type configuration
// ---------------------------------------------------------------------------
const BUSINESS_TYPES = [

    // -------------------------------------------------------------------------
    // YOGA STUDIO
    // -------------------------------------------------------------------------
    'yoga' => [
        'label'                   => 'Yoga Studio',
        'has_classes'             => true,
        'has_private'             => true,
        'has_insurance_billing'   => false,
        'has_memberships'         => true,
        'owner_salary_default'    => 55000,
        'weeks_per_year_default'  => 50,
        'expense_defaults' => [
            ['category' => 'rent',      'label' => 'Studio Lease',                'amount_monthly' => 3500, 'is_variable' => 0],
            ['category' => 'utilities', 'label' => 'Utilities',                   'amount_monthly' => 300,  'is_variable' => 0],
            ['category' => 'insurance', 'label' => 'Liability Insurance',         'amount_monthly' => 150,  'is_variable' => 0],
            ['category' => 'software',  'label' => 'Studio Management Software',  'amount_monthly' => 129,  'is_variable' => 0],
            ['category' => 'marketing', 'label' => 'Marketing / Advertising',     'amount_monthly' => 200,  'is_variable' => 0],
            ['category' => 'cleaning',  'label' => 'Cleaning / Maintenance',      'amount_monthly' => 300,  'is_variable' => 0],
            ['category' => 'supplies',  'label' => 'Supplies (props, mats, etc.)','amount_monthly' => 100,  'is_variable' => 1],
        ],
        'revenue_stream_defaults' => [
            ['stream_type' => 'drop_in',      'label' => 'Drop-in Class',     'price' => 20,  'units_included' => null, 'estimated_monthly_units' => 35],
            ['stream_type' => 'class_pass',   'label' => '10-Class Pass',     'price' => 150, 'units_included' => 10,   'estimated_monthly_units' => 10],
            ['stream_type' => 'subscription', 'label' => 'Unlimited Monthly', 'price' => 99,  'units_included' => null, 'estimated_monthly_units' => 40],
            ['stream_type' => 'private',      'label' => 'Private Session',   'price' => 80,  'units_included' => null, 'estimated_monthly_units' => 4],
        ],
        'class_schedule_defaults' => [
            ['class_name' => 'General Class',      'class_type' => 'in_person', 'classes_per_week' => 10, 'room_capacity' => 20, 'avg_fill_rate' => 0.60],
            ['class_name' => 'Vinyasa Flow',       'class_type' => 'in_person', 'classes_per_week' => 5,  'room_capacity' => 18, 'avg_fill_rate' => 0.65],
        ],
        'instructor_defaults' => [
            ['name' => 'Lead Instructor', 'pay_type' => 'flat', 'pay_per_class' => 40, 'revenue_share_pct' => null, 'classes_per_week' => 10],
            ['name' => 'Sub Instructor',  'pay_type' => 'flat', 'pay_per_class' => 35, 'revenue_share_pct' => null, 'classes_per_week' => 5],
        ],
    ],

    // -------------------------------------------------------------------------
    // THERAPY PRACTICE
    // -------------------------------------------------------------------------
    'therapist' => [
        'label'                   => 'Therapy Practice',
        'has_classes'             => false,
        'has_private'             => true,
        'has_insurance_billing'   => true,
        'has_memberships'         => false,
        'owner_salary_default'    => 85000,
        'weeks_per_year_default'  => 48,
        'expense_defaults' => [
            ['category' => 'rent',      'label' => 'Office / Suite Lease',      'amount_monthly' => 1800, 'is_variable' => 0],
            ['category' => 'insurance', 'label' => 'Malpractice Insurance',     'amount_monthly' => 200,  'is_variable' => 0],
            ['category' => 'software',  'label' => 'EHR / Practice Software',   'amount_monthly' => 99,   'is_variable' => 0],
            ['category' => 'software',  'label' => 'Billing / Clearinghouse',   'amount_monthly' => 75,   'is_variable' => 0],
            ['category' => 'software',  'label' => 'Telehealth Platform',       'amount_monthly' => 40,   'is_variable' => 0],
            ['category' => 'marketing', 'label' => 'Psychology Today / Dirs.',  'amount_monthly' => 130,  'is_variable' => 0],
            ['category' => 'other',     'label' => 'Supervision / Consultation','amount_monthly' => 150,  'is_variable' => 0],
            ['category' => 'supplies',  'label' => 'Office Supplies',           'amount_monthly' => 50,   'is_variable' => 0],
        ],
        'revenue_stream_defaults' => [], // therapist uses insurance_payers + cpt_codes instead
        'class_schedule_defaults' => [],
        'instructor_defaults'     => [],

        // Default payer mix — Connecticut market estimates
        // Rates sourced from CT provider schedules (update with actual contract rates)
        'payer_defaults' => [
            ['payer_name' => 'Anthem BlueCross BlueShield',  'client_pct' => 0.30, 'is_cash_pay' => 0],
            ['payer_name' => 'Aetna',                        'client_pct' => 0.20, 'is_cash_pay' => 0],
            ['payer_name' => 'United / Optum',               'client_pct' => 0.20, 'is_cash_pay' => 0],
            ['payer_name' => 'Cigna',                        'client_pct' => 0.10, 'is_cash_pay' => 0],
            ['payer_name' => 'Husky / CT Medicaid',          'client_pct' => 0.05, 'is_cash_pay' => 0],
            ['payer_name' => 'Cash Pay / Self-Pay',          'client_pct' => 0.15, 'is_cash_pay' => 1],
        ],

        // Default CPT code set with sessions/month estimate
        'cpt_defaults' => [
            ['code' => '90791', 'description' => 'Psychiatric Diagnostic Eval (intake)', 'sessions_per_month' => 4],
            ['code' => '90837', 'description' => 'Individual Psychotherapy — 60 min',    'sessions_per_month' => 40],
            ['code' => '90834', 'description' => 'Individual Psychotherapy — 45 min',    'sessions_per_month' => 10],
            ['code' => '90847', 'description' => 'Family Therapy w/ Patient — 50 min',  'sessions_per_month' => 8],
            ['code' => '90853', 'description' => 'Group Psychotherapy — 60 min',         'sessions_per_month' => 15],
        ],

        // Reimbursement rate matrix [payer_index][cpt_code] — CT market estimates
        // Rows match payer_defaults order; columns match cpt_defaults order
        'rate_matrix_defaults' => [
            // Anthem   90791   90837   90834   90847   90853
            [            185,    138,    112,    125,     68],
            // Aetna
            [            195,    130,    108,    120,     62],
            // United/Optum
            [            180,    132,    110,    118,     65],
            // Cigna
            [            190,    135,    110,    122,     66],
            // Husky CT Medicaid
            [            145,     90,     75,     80,     42],
            // Cash Pay (self-pay rates)
            [            275,    175,    150,    190,    100],
        ],

        // Default staff provider
        'provider_defaults' => [
            ['name' => 'Associate Therapist', 'credential' => 'LCSW', 'sessions_per_week' => 20, 'hours_per_week' => 25, 'pay_type' => 'hourly', 'pay_rate' => 35, 'is_owner' => 0],
        ],
    ],

    // -------------------------------------------------------------------------
    // FITNESS GYM
    // -------------------------------------------------------------------------
    'gym' => [
        'label'                   => 'Fitness Gym',
        'has_classes'             => true,
        'has_private'             => true,
        'has_insurance_billing'   => false,
        'has_memberships'         => true,
        'owner_salary_default'    => 65000,
        'weeks_per_year_default'  => 51,
        'expense_defaults' => [
            ['category' => 'rent',      'label' => 'Facility Lease',            'amount_monthly' => 5500, 'is_variable' => 0],
            ['category' => 'utilities', 'label' => 'Utilities',                 'amount_monthly' => 900,  'is_variable' => 0],
            ['category' => 'other',     'label' => 'Equipment Maintenance',     'amount_monthly' => 400,  'is_variable' => 0],
            ['category' => 'insurance', 'label' => 'Liability Insurance',       'amount_monthly' => 250,  'is_variable' => 0],
            ['category' => 'software',  'label' => 'Gym Management Software',   'amount_monthly' => 149,  'is_variable' => 0],
            ['category' => 'marketing', 'label' => 'Marketing / Advertising',   'amount_monthly' => 400,  'is_variable' => 0],
            ['category' => 'cleaning',  'label' => 'Cleaning / Janitorial',     'amount_monthly' => 500,  'is_variable' => 0],
            ['category' => 'supplies',  'label' => 'Supplies & Consumables',    'amount_monthly' => 200,  'is_variable' => 1],
        ],
        'revenue_stream_defaults' => [
            ['stream_type' => 'subscription', 'label' => 'Standard Membership', 'price' => 49,  'units_included' => null, 'estimated_monthly_units' => 120],
            ['stream_type' => 'subscription', 'label' => 'Premium Membership',  'price' => 79,  'units_included' => null, 'estimated_monthly_units' => 45],
            ['stream_type' => 'drop_in',      'label' => 'Day Pass',            'price' => 15,  'units_included' => null, 'estimated_monthly_units' => 25],
            ['stream_type' => 'private',      'label' => 'Personal Training',   'price' => 75,  'units_included' => null, 'estimated_monthly_units' => 16],
        ],
        'class_schedule_defaults' => [
            ['class_name' => 'Group Fitness',   'class_type' => 'in_person', 'classes_per_week' => 20, 'room_capacity' => 25, 'avg_fill_rate' => 0.70],
            ['class_name' => 'Spin / Cycling',  'class_type' => 'in_person', 'classes_per_week' => 8,  'room_capacity' => 15, 'avg_fill_rate' => 0.80],
        ],
        'instructor_defaults' => [
            ['name' => 'Group Fitness Instructor', 'pay_type' => 'flat', 'pay_per_class' => 40, 'revenue_share_pct' => null, 'classes_per_week' => 20],
            ['name' => 'Spin Instructor',          'pay_type' => 'flat', 'pay_per_class' => 45, 'revenue_share_pct' => null, 'classes_per_week' => 8],
        ],
    ],

    // -------------------------------------------------------------------------
    // MARTIAL ARTS DOJO
    // -------------------------------------------------------------------------
    'dojo' => [
        'label'                   => 'Martial Arts Dojo',
        'has_classes'             => true,
        'has_private'             => true,
        'has_insurance_billing'   => false,
        'has_memberships'         => true,
        'owner_salary_default'    => 52000,
        'weeks_per_year_default'  => 50,
        'expense_defaults' => [
            ['category' => 'rent',      'label' => 'Dojo Lease',                'amount_monthly' => 2800, 'is_variable' => 0],
            ['category' => 'utilities', 'label' => 'Utilities',                 'amount_monthly' => 280,  'is_variable' => 0],
            ['category' => 'insurance', 'label' => 'Liability Insurance',       'amount_monthly' => 200,  'is_variable' => 0],
            ['category' => 'other',     'label' => 'Mats & Equipment Upkeep',   'amount_monthly' => 100,  'is_variable' => 0],
            ['category' => 'software',  'label' => 'Dojo Management Software',  'amount_monthly' => 79,   'is_variable' => 0],
            ['category' => 'marketing', 'label' => 'Marketing / Advertising',   'amount_monthly' => 200,  'is_variable' => 0],
            ['category' => 'cleaning',  'label' => 'Cleaning / Maintenance',    'amount_monthly' => 150,  'is_variable' => 0],
            ['category' => 'supplies',  'label' => 'Supplies & Uniforms',       'amount_monthly' => 75,   'is_variable' => 1],
        ],
        'revenue_stream_defaults' => [
            ['stream_type' => 'subscription', 'label' => 'Monthly Tuition',    'price' => 120, 'units_included' => null, 'estimated_monthly_units' => 65],
            ['stream_type' => 'subscription', 'label' => 'Family Plan',        'price' => 200, 'units_included' => null, 'estimated_monthly_units' => 12],
            ['stream_type' => 'private',      'label' => 'Private Lesson',     'price' => 65,  'units_included' => null, 'estimated_monthly_units' => 8],
            ['stream_type' => 'drop_in',      'label' => 'Drop-in / Trial',    'price' => 25,  'units_included' => null, 'estimated_monthly_units' => 6],
        ],
        'class_schedule_defaults' => [
            ['class_name' => 'Kids Class',   'class_type' => 'in_person', 'classes_per_week' => 10, 'room_capacity' => 15, 'avg_fill_rate' => 0.80],
            ['class_name' => 'Adult Class',  'class_type' => 'in_person', 'classes_per_week' => 8,  'room_capacity' => 20, 'avg_fill_rate' => 0.65],
        ],
        'instructor_defaults' => [
            ['name' => 'Head Instructor', 'pay_type' => 'flat', 'pay_per_class' => 50, 'revenue_share_pct' => null, 'classes_per_week' => 18],
        ],
    ],

];

// ---------------------------------------------------------------------------
// Wizard step definitions per business type
// ---------------------------------------------------------------------------
function getWizardSteps(string $type): array {
    if ($type === 'therapist') {
        return [
            1 => ['label' => 'Setup',      'url' => PF_BASE . '/wizard/setup.php'],
            2 => ['label' => 'Expenses',   'url' => PF_BASE . '/wizard/expenses.php'],
            3 => ['label' => 'Insurance',  'url' => PF_BASE . '/wizard/insurance.php'],
            4 => ['label' => 'Providers',  'url' => PF_BASE . '/wizard/providers.php'],
            5 => ['label' => 'Report',     'url' => PF_BASE . '/wizard/report.php'],
        ];
    }
    return [
        1 => ['label' => 'Setup',       'url' => PF_BASE . '/wizard/setup.php'],
        2 => ['label' => 'Expenses',    'url' => PF_BASE . '/wizard/expenses.php'],
        3 => ['label' => 'Classes',     'url' => PF_BASE . '/wizard/classes.php'],
        4 => ['label' => 'Revenue',     'url' => PF_BASE . '/wizard/revenue.php'],
        5 => ['label' => 'Instructors', 'url' => PF_BASE . '/wizard/instructors.php'],
        6 => ['label' => 'Report',      'url' => PF_BASE . '/wizard/report.php'],
    ];
}

// ---------------------------------------------------------------------------
// Calculator factory
// ---------------------------------------------------------------------------
function getCalculator(array $business, array $expenses, array $schedules, array $streams, array $instructors, array $extra = []): \Calculations\Calculator {
    require_once PF_ROOT . '/includes/calculations/Calculator.php';

    require_once PF_ROOT . '/includes/calculations/YogaCalculator.php';
    require_once PF_ROOT . '/includes/calculations/TherapistCalculator.php';

    return match($business['business_type']) {
        'yoga', 'gym', 'dojo' => new \Calculations\YogaCalculator($business, $expenses, $schedules, $streams, $instructors, $extra['settings'] ?? []),
        'therapist'            => new \Calculations\TherapistCalculator($business, $expenses, $schedules, $streams, $instructors, $extra),
        default                => throw new \InvalidArgumentException("Unknown business type: {$business['business_type']}"),
    };
}
