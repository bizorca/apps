<?php
/**
 * Starter boards, columns, tags and cards for a new workspace, by business
 * type. Copied from the original app/Services/OnboardingTemplates.php; the
 * only edits are the class header and FmUser in place of User, since the
 * models here expose the same static create(array) the original called.
 */

declare(strict_types=1);

function fm_onboarding_seed(Account $account, FmUser $user): void
{
    OnboardingTemplates::seed($account, $user);
}

final class OnboardingTemplates
{
    /**
     * Seed default boards, columns, and tags for the account's business type.
     * Called once immediately after account creation.
     */
    public static function seed(Account $account, FmUser $user): void
    {
        match ($account->business_type) {
            'financial_coach'  => static::seedFinancialCoach($account, $user),
            'tax_consultant'   => static::seedTaxConsultant($account, $user),
            'career_coach'     => static::seedCareerCoach($account, $user),
            'yoga_instructor'  => static::seedYogaInstructor($account, $user),
            'energy_healer'    => static::seedEnergyHealer($account, $user),
            default => null,
        };
    }

    // -------------------------------------------------------------------------
    // Financial Coach
    // -------------------------------------------------------------------------

    private static function seedFinancialCoach(Account $account, FmUser $user): void
    {
        static::createFinancialCoachTags($account);
        static::createClientPipelineBoard($account, $user);
        static::createActiveClientsBoard($account, $user);
        static::createOperationsBoard($account, $user);
    }

    private static function createFinancialCoachTags(Account $account): void
    {
        $tags = [
            ['name' => 'Referral',       'color' => '#10b981'], // emerald
            ['name' => 'High Priority',  'color' => '#ef4444'], // red
            ['name' => 'Budget Focus',   'color' => '#3b82f6'], // blue
            ['name' => 'Debt Payoff',    'color' => '#f97316'], // orange
            ['name' => 'Investing',      'color' => '#8b5cf6'], // violet
            ['name' => 'Credit Repair',  'color' => '#eab308'], // yellow
            ['name' => 'Retirement',     'color' => '#6366f1'], // indigo
        ];

        foreach ($tags as $tag) {
            Tag::create([
                'account_id' => $account->id,
                'name'       => $tag['name'],
                'color'      => $tag['color'],
            ]);
        }
    }

    private static function createClientPipelineBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id' => $account->id,
            'creator_id' => $user->id,
            'name'        => 'Client Pipeline',
            'description' => 'Track prospects from first contact through enrollment.',
            'color'       => '#6366f1',
        ]);

        $columns = [
            ['name' => 'New Leads',       'position' => 1],
            ['name' => 'Discovery Call',  'position' => 2],
            ['name' => 'Proposal Sent',   'position' => 3],
            ['name' => 'Enrolled',        'position' => 4],
            ['name' => 'Not a Fit',       'position' => 5],
        ];

        $firstColumn = null;
        foreach ($columns as $col) {
            $column = Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
            if ($col['position'] === 1) {
                $firstColumn = $column;
            }
        }

        // Seed a single starter card so the board doesn't feel empty
        if ($firstColumn) {
            Card::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'column_id'  => $firstColumn->id,
                'creator_id' => $user->id,
                'title'      => 'Example Lead — Jane Smith',
                'description' => "Use this card as a template.\n\n"
                    . "- Source: Instagram DM\n"
                    . "- Interest: Debt payoff + budget\n"
                    . "- Notes: Has $24k in credit card debt, ready to get serious.\n\n"
                    . "Move this card right as the conversation progresses. Delete it whenever you're ready.",
                'position'   => 1,
            ]);
        }

        return $board;
    }

    private static function createActiveClientsBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id' => $account->id,
            'creator_id' => $user->id,
            'name'        => 'Active Clients',
            'description' => 'Manage ongoing client relationships from onboarding through graduation.',
            'color'       => '#10b981',
        ]);

        $columns = [
            ['name' => 'Onboarding',      'position' => 1],
            ['name' => 'Building Plan',   'position' => 2],
            ['name' => 'Plan in Action',  'position' => 3],
            ['name' => 'Check-in Due',    'position' => 4],
            ['name' => 'Graduating Soon', 'position' => 5],
        ];

        foreach ($columns as $col) {
            Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
        }

        return $board;
    }

    private static function createOperationsBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id' => $account->id,
            'creator_id' => $user->id,
            'name'        => 'Operations',
            'description' => 'Back-office tasks, content creation, admin, and business maintenance.',
            'color'       => '#f97316',
        ]);

        $columns = [
            ['name' => 'Backlog',      'position' => 1],
            ['name' => 'This Week',    'position' => 2],
            ['name' => 'In Progress',  'position' => 3],
            ['name' => 'Done',         'position' => 4],
        ];

        $backlogColumn = null;
        foreach ($columns as $col) {
            $column = Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
            if ($col['position'] === 1) {
                $backlogColumn = $column;
            }
        }

        // A handful of realistic starter tasks
        $starterTasks = [
            'Set up client intake questionnaire',
            'Create onboarding welcome email sequence',
            'Draft coaching agreement / contract template',
            'Build out financial plan template (spreadsheet)',
            'Set up scheduling link (Calendly or similar)',
            'Write out your referral ask script',
        ];

        if ($backlogColumn) {
            foreach ($starterTasks as $i => $title) {
                Card::create([
                    'account_id' => $account->id,
                    'board_id'   => $board->id,
                    'column_id'  => $backlogColumn->id,
                    'creator_id' => $user->id,
                    'title'      => $title,
                    'position'   => $i + 1,
                ]);
            }
        }

        return $board;
    }

    // -------------------------------------------------------------------------
    // Tax Consultant
    // -------------------------------------------------------------------------

    private static function seedTaxConsultant(Account $account, FmUser $user): void
    {
        static::createTaxConsultantTags($account);
        static::createTaxClientPipelineBoard($account, $user);
        static::createTaxReturnsBoard($account, $user);
        static::createTaxOperationsBoard($account, $user);
    }

    private static function createTaxConsultantTags(Account $account): void
    {
        $tags = [
            ['name' => 'Individual',      'color' => '#3b82f6'], // blue
            ['name' => 'Business',        'color' => '#8b5cf6'], // violet
            ['name' => 'Partnership',     'color' => '#6366f1'], // indigo
            ['name' => 'Extension Filed', 'color' => '#f97316'], // orange
            ['name' => 'Amended Return',  'color' => '#ef4444'], // red
            ['name' => 'High Priority',   'color' => '#dc2626'], // red-600
            ['name' => 'Referral',        'color' => '#10b981'], // emerald
            ['name' => 'Audit',           'color' => '#b91c1c'], // red-700
        ];

        foreach ($tags as $tag) {
            Tag::create([
                'account_id' => $account->id,
                'name'       => $tag['name'],
                'color'      => $tag['color'],
            ]);
        }
    }

    private static function createTaxClientPipelineBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Client Pipeline',
            'description' => 'Track prospects from first contact through signed engagement.',
            'color'       => '#6366f1',
        ]);

        $columns = [
            ['name' => 'New Leads',             'position' => 1],
            ['name' => 'Discovery Call',         'position' => 2],
            ['name' => 'Engagement Letter Sent', 'position' => 3],
            ['name' => 'Active',                 'position' => 4],
            ['name' => 'Not a Fit',              'position' => 5],
        ];

        $firstColumn = null;
        foreach ($columns as $col) {
            $column = Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
            if ($col['position'] === 1) {
                $firstColumn = $column;
            }
        }

        if ($firstColumn) {
            Card::create([
                'account_id'  => $account->id,
                'board_id'    => $board->id,
                'column_id'   => $firstColumn->id,
                'creator_id'  => $user->id,
                'title'       => 'Example Lead — Robert Chen',
                'description' => "Use this card as a template.\n\n"
                    . "- Source: Referral from existing client\n"
                    . "- Return type: S-Corp + personal 1040\n"
                    . "- Notes: Two partners, first year filing. Needs prior year review.\n\n"
                    . "Move this card right as the engagement progresses. Delete it whenever you're ready.",
                'position'    => 1,
            ]);
        }

        return $board;
    }

    private static function createTaxReturnsBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Tax Returns',
            'description' => 'Track every return from info gathering through filing.',
            'color'       => '#10b981',
        ]);

        $columns = [
            ['name' => 'Not Started',    'position' => 1],
            ['name' => 'Info Gathering', 'position' => 2],
            ['name' => 'In Preparation', 'position' => 3],
            ['name' => 'Client Review',  'position' => 4],
            ['name' => 'Filed',          'position' => 5],
        ];

        foreach ($columns as $col) {
            Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
        }

        return $board;
    }

    private static function createTaxOperationsBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Operations',
            'description' => 'Admin, compliance, and practice management tasks.',
            'color'       => '#f97316',
        ]);

        $columns = [
            ['name' => 'Backlog',     'position' => 1],
            ['name' => 'This Week',   'position' => 2],
            ['name' => 'In Progress', 'position' => 3],
            ['name' => 'Done',        'position' => 4],
        ];

        $backlogColumn = null;
        foreach ($columns as $col) {
            $column = Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
            if ($col['position'] === 1) {
                $backlogColumn = $column;
            }
        }

        $starterTasks = [
            'Draft engagement letter template',
            'Set up client organizer / intake questionnaire',
            'Create IRS Form 2848 (Power of Attorney) template',
            'Set up e-file credentials with IRS (EFIN)',
            'Build document request checklist by return type',
            'Set up secure client document portal',
            'Write extension filing checklist',
        ];

        if ($backlogColumn) {
            foreach ($starterTasks as $i => $title) {
                Card::create([
                    'account_id' => $account->id,
                    'board_id'   => $board->id,
                    'column_id'  => $backlogColumn->id,
                    'creator_id' => $user->id,
                    'title'      => $title,
                    'position'   => $i + 1,
                ]);
            }
        }

        return $board;
    }

    // -------------------------------------------------------------------------
    // Career Coach
    // -------------------------------------------------------------------------

    private static function seedCareerCoach(Account $account, FmUser $user): void
    {
        static::createCareerCoachTags($account);
        static::createCareerClientPipelineBoard($account, $user);
        static::createCareerActiveClientsBoard($account, $user);
        static::createCareerOperationsBoard($account, $user);
    }

    private static function createCareerCoachTags(Account $account): void
    {
        $tags = [
            ['name' => 'Resume',        'color' => '#3b82f6'], // blue
            ['name' => 'LinkedIn',      'color' => '#0284c7'], // sky
            ['name' => 'Job Search',    'color' => '#10b981'], // emerald
            ['name' => 'Interview Prep','color' => '#8b5cf6'], // violet
            ['name' => 'Negotiation',   'color' => '#f97316'], // orange
            ['name' => 'Career Change', 'color' => '#6366f1'], // indigo
            ['name' => 'Recent Grad',   'color' => '#eab308'], // yellow
            ['name' => 'Executive',     'color' => '#1e293b'], // slate-800
            ['name' => 'High Priority', 'color' => '#ef4444'], // red
            ['name' => 'Referral',      'color' => '#10b981'], // emerald
        ];

        foreach ($tags as $tag) {
            Tag::create([
                'account_id' => $account->id,
                'name'       => $tag['name'],
                'color'      => $tag['color'],
            ]);
        }
    }

    private static function createCareerClientPipelineBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Client Pipeline',
            'description' => 'Track prospects from first contact through enrollment.',
            'color'       => '#6366f1',
        ]);

        $columns = [
            ['name' => 'New Leads',      'position' => 1],
            ['name' => 'Discovery Call', 'position' => 2],
            ['name' => 'Proposal Sent',  'position' => 3],
            ['name' => 'Enrolled',       'position' => 4],
            ['name' => 'Not a Fit',      'position' => 5],
        ];

        $firstColumn = null;
        foreach ($columns as $col) {
            $column = Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
            if ($col['position'] === 1) {
                $firstColumn = $column;
            }
        }

        if ($firstColumn) {
            Card::create([
                'account_id'  => $account->id,
                'board_id'    => $board->id,
                'column_id'   => $firstColumn->id,
                'creator_id'  => $user->id,
                'title'       => 'Example Lead — Maria Torres',
                'description' => "Use this card as a template.\n\n"
                    . "- Source: LinkedIn DM\n"
                    . "- Goal: Transition from teaching into instructional design\n"
                    . "- Notes: 8 years classroom experience, needs resume + LinkedIn overhaul.\n\n"
                    . "Move this card right as the conversation progresses. Delete it whenever you're ready.",
                'position'    => 1,
            ]);
        }

        return $board;
    }

    private static function createCareerActiveClientsBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Active Clients',
            'description' => 'Manage clients from kickoff through job offer.',
            'color'       => '#10b981',
        ]);

        $columns = [
            ['name' => 'Onboarding',      'position' => 1],
            ['name' => 'Resume & LinkedIn','position' => 2],
            ['name' => 'Job Search Active','position' => 3],
            ['name' => 'Interviewing',     'position' => 4],
            ['name' => 'Offer Stage',      'position' => 5],
            ['name' => 'Placed',           'position' => 6],
        ];

        foreach ($columns as $col) {
            Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
        }

        return $board;
    }

    private static function createCareerOperationsBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Operations',
            'description' => 'Content, admin, and business development tasks.',
            'color'       => '#f97316',
        ]);

        $columns = [
            ['name' => 'Backlog',     'position' => 1],
            ['name' => 'This Week',   'position' => 2],
            ['name' => 'In Progress', 'position' => 3],
            ['name' => 'Done',        'position' => 4],
        ];

        $backlogColumn = null;
        foreach ($columns as $col) {
            $column = Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
            if ($col['position'] === 1) {
                $backlogColumn = $column;
            }
        }

        $starterTasks = [
            'Create client intake questionnaire',
            'Build resume review checklist',
            'Build LinkedIn profile audit checklist',
            'Draft coaching agreement / contract template',
            'Write interview prep framework (STAR method guide)',
            'Create salary negotiation talking points template',
            'Set up scheduling link (Calendly or similar)',
        ];

        if ($backlogColumn) {
            foreach ($starterTasks as $i => $title) {
                Card::create([
                    'account_id' => $account->id,
                    'board_id'   => $board->id,
                    'column_id'  => $backlogColumn->id,
                    'creator_id' => $user->id,
                    'title'      => $title,
                    'position'   => $i + 1,
                ]);
            }
        }

        return $board;
    }

    // -------------------------------------------------------------------------
    // Yoga Instructor
    // -------------------------------------------------------------------------

    private static function seedYogaInstructor(Account $account, FmUser $user): void
    {
        static::createYogaInstructorTags($account);
        static::createYogaClientPipelineBoard($account, $user);
        static::createYogaClassesBoard($account, $user);
        static::createYogaOperationsBoard($account, $user);
    }

    private static function createYogaInstructorTags(Account $account): void
    {
        $tags = [
            ['name' => 'Group Class',       'color' => '#10b981'], // emerald
            ['name' => 'Private Session',   'color' => '#6366f1'], // indigo
            ['name' => 'Beginner',          'color' => '#3b82f6'], // blue
            ['name' => 'Intermediate',      'color' => '#8b5cf6'], // violet
            ['name' => 'Advanced',          'color' => '#1e293b'], // slate
            ['name' => 'Online',            'color' => '#0284c7'], // sky
            ['name' => 'In-Person',         'color' => '#f97316'], // orange
            ['name' => 'Referral',          'color' => '#10b981'], // emerald
            ['name' => 'Trial',             'color' => '#eab308'], // yellow
            ['name' => 'High Priority',     'color' => '#ef4444'], // red
        ];

        foreach ($tags as $tag) {
            Tag::create([
                'account_id' => $account->id,
                'name'       => $tag['name'],
                'color'      => $tag['color'],
            ]);
        }
    }

    private static function createYogaClientPipelineBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Client Pipeline',
            'description' => 'Track prospects from first contact through enrollment.',
            'color'       => '#6366f1',
        ]);

        $columns = [
            ['name' => 'New Leads',       'position' => 1],
            ['name' => 'Trial Booked',    'position' => 2],
            ['name' => 'Trial Complete',  'position' => 3],
            ['name' => 'Enrolled',        'position' => 4],
            ['name' => 'Not a Fit',       'position' => 5],
        ];

        $firstColumn = null;
        foreach ($columns as $col) {
            $column = Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
            if ($col['position'] === 1) {
                $firstColumn = $column;
            }
        }

        if ($firstColumn) {
            Card::create([
                'account_id'  => $account->id,
                'board_id'    => $board->id,
                'column_id'   => $firstColumn->id,
                'creator_id'  => $user->id,
                'title'       => 'Example Lead — Priya Nair',
                'description' => "Use this card as a template.\n\n"
                    . "- Source: Instagram\n"
                    . "- Interest: Weekly group vinyasa + occasional private sessions\n"
                    . "- Notes: Beginner, some lower back issues. Offered a free trial class.\n\n"
                    . "Move this card right as the conversation progresses. Delete it whenever you're ready.",
                'position'    => 1,
            ]);
        }

        return $board;
    }

    private static function createYogaClassesBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Classes & Schedule',
            'description' => 'Plan, launch, and manage recurring classes and workshops.',
            'color'       => '#10b981',
        ]);

        $columns = [
            ['name' => 'Planning',    'position' => 1],
            ['name' => 'Scheduled',   'position' => 2],
            ['name' => 'Recurring',   'position' => 3],
            ['name' => 'Completed',   'position' => 4],
            ['name' => 'Cancelled',   'position' => 5],
        ];

        foreach ($columns as $col) {
            Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
        }

        return $board;
    }

    private static function createYogaOperationsBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Operations',
            'description' => 'Admin, marketing, and studio management tasks.',
            'color'       => '#f97316',
        ]);

        $columns = [
            ['name' => 'Backlog',     'position' => 1],
            ['name' => 'This Week',   'position' => 2],
            ['name' => 'In Progress', 'position' => 3],
            ['name' => 'Done',        'position' => 4],
        ];

        $backlogColumn = null;
        foreach ($columns as $col) {
            $column = Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
            if ($col['position'] === 1) {
                $backlogColumn = $column;
            }
        }

        $starterTasks = [
            'Create client intake form (health history, goals)',
            'Set up scheduling and booking link',
            'Draft waiver / liability release template',
            'Build class description templates for each format',
            'Write welcome email for new students',
            'Set up payment / membership packages',
            'Create sub teacher contact list',
        ];

        if ($backlogColumn) {
            foreach ($starterTasks as $i => $title) {
                Card::create([
                    'account_id' => $account->id,
                    'board_id'   => $board->id,
                    'column_id'  => $backlogColumn->id,
                    'creator_id' => $user->id,
                    'title'      => $title,
                    'position'   => $i + 1,
                ]);
            }
        }

        return $board;
    }

    // -------------------------------------------------------------------------
    // Energy Healer
    // -------------------------------------------------------------------------

    private static function seedEnergyHealer(Account $account, FmUser $user): void
    {
        static::createEnergyHealerTags($account);
        static::createEnergyHealerClientPipelineBoard($account, $user);
        static::createEnergyHealerActiveClientsBoard($account, $user);
        static::createEnergyHealerOperationsBoard($account, $user);
    }

    private static function createEnergyHealerTags(Account $account): void
    {
        $tags = [
            ['name' => 'Reiki',            'color' => '#8b5cf6'], // violet
            ['name' => 'Sound Healing',    'color' => '#6366f1'], // indigo
            ['name' => 'Crystal Therapy',  'color' => '#ec4899'], // pink
            ['name' => 'Chakra Work',      'color' => '#f97316'], // orange
            ['name' => 'Distance Session', 'color' => '#0284c7'], // sky
            ['name' => 'In-Person',        'color' => '#10b981'], // emerald
            ['name' => 'New Client',       'color' => '#3b82f6'], // blue
            ['name' => 'Referral',         'color' => '#10b981'], // emerald
            ['name' => 'High Priority',    'color' => '#ef4444'], // red
        ];

        foreach ($tags as $tag) {
            Tag::create([
                'account_id' => $account->id,
                'name'       => $tag['name'],
                'color'      => $tag['color'],
            ]);
        }
    }

    private static function createEnergyHealerClientPipelineBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Client Pipeline',
            'description' => 'Track prospects from first contact through first booking.',
            'color'       => '#8b5cf6',
        ]);

        $columns = [
            ['name' => 'New Inquiries',    'position' => 1],
            ['name' => 'Discovery Call',   'position' => 2],
            ['name' => 'Session Booked',   'position' => 3],
            ['name' => 'Active Client',    'position' => 4],
            ['name' => 'Not a Fit',        'position' => 5],
        ];

        $firstColumn = null;
        foreach ($columns as $col) {
            $column = Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
            if ($col['position'] === 1) {
                $firstColumn = $column;
            }
        }

        if ($firstColumn) {
            Card::create([
                'account_id'  => $account->id,
                'board_id'    => $board->id,
                'column_id'   => $firstColumn->id,
                'creator_id'  => $user->id,
                'title'       => 'Example Inquiry — Dana Flores',
                'description' => "Use this card as a template.\n\n"
                    . "- Source: Website contact form\n"
                    . "- Interest: Reiki for stress and sleep issues\n"
                    . "- Notes: First time trying energy work, open to in-person or distance session.\n\n"
                    . "Move this card right as the conversation progresses. Delete it whenever you're ready.",
                'position'    => 1,
            ]);
        }

        return $board;
    }

    private static function createEnergyHealerActiveClientsBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Active Clients',
            'description' => 'Manage ongoing client care from intake through completion.',
            'color'       => '#10b981',
        ]);

        $columns = [
            ['name' => 'Intake',            'position' => 1],
            ['name' => 'Session Scheduled', 'position' => 2],
            ['name' => 'Session Complete',  'position' => 3],
            ['name' => 'Follow-Up Due',     'position' => 4],
            ['name' => 'Package Complete',  'position' => 5],
        ];

        foreach ($columns as $col) {
            Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
        }

        return $board;
    }

    private static function createEnergyHealerOperationsBoard(Account $account, FmUser $user): Board
    {
        $board = Board::create([
            'account_id'  => $account->id,
            'creator_id'  => $user->id,
            'name'        => 'Operations',
            'description' => 'Admin, content, and practice management tasks.',
            'color'       => '#f97316',
        ]);

        $columns = [
            ['name' => 'Backlog',     'position' => 1],
            ['name' => 'This Week',   'position' => 2],
            ['name' => 'In Progress', 'position' => 3],
            ['name' => 'Done',        'position' => 4],
        ];

        $backlogColumn = null;
        foreach ($columns as $col) {
            $column = Column::create([
                'account_id' => $account->id,
                'board_id'   => $board->id,
                'name'       => $col['name'],
                'position'   => $col['position'],
            ]);
            if ($col['position'] === 1) {
                $backlogColumn = $column;
            }
        }

        $starterTasks = [
            'Create client intake form (health history, intentions)',
            'Draft session notes template',
            'Write post-session follow-up email template',
            'Set up scheduling and booking link',
            'Build service menu with pricing',
            'Create package options (3-session, 6-session)',
            'Write intake waiver / consent form',
        ];

        if ($backlogColumn) {
            foreach ($starterTasks as $i => $title) {
                Card::create([
                    'account_id' => $account->id,
                    'board_id'   => $board->id,
                    'column_id'  => $backlogColumn->id,
                    'creator_id' => $user->id,
                    'title'      => $title,
                    'position'   => $i + 1,
                ]);
            }
        }

        return $board;
    }
}
