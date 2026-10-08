<?php
// Washington State Cooperative Structures
// Built for RCW 23.78 (Uniform Limited Cooperative Association Act)

define('STRUCTURES', [
    'worker_coop_llc' => [
        'name' => 'Worker Cooperative (LLC)',
        'short' => 'Worker Co-op LLC',
        'legal_basis' => 'RCW 25.15 (LLC Act) with cooperative operating agreement',
        'description' => 'An LLC structured as a worker cooperative, where employees are the members. Simple to form, flexible governance, ideal for smaller groups of 2–20 workers.',
        'pros' => ['Simple and low-cost to form', 'Flexible operating agreement', 'Pass-through taxation', 'Democratic governance built in'],
        'cons' => ['Limited outside investor participation', 'No preferred share classes', 'Less recognized cooperative brand'],
        'ideal_for' => 'Small businesses with 2–20 employees, seller-financed transitions, simple governance preferences',
        'filing_fee' => '$180',
        'typical_timeline' => '2–4 months',
        'requires_specialist' => false,
    ],
    'worker_coop_corp' => [
        'name' => 'Worker Cooperative Corporation',
        'short' => 'Worker Co-op Corp',
        'legal_basis' => 'RCW 23B (Business Corporation Act) with cooperative bylaws',
        'description' => 'A corporation structured as a worker cooperative. More formal than the LLC version, can issue stock to members, well-recognized legal form.',
        'pros' => ['Can issue membership stock certificates', 'Well-recognized legal form', 'Strong democratic governance tradition', 'Cleaner patronage dividend mechanics'],
        'cons' => ['More expensive and complex to form', 'Double taxation risk (unless S-corp election)', 'More administrative overhead'],
        'ideal_for' => 'Larger worker co-ops (15+), businesses with complex equity structures, those seeking ICA cooperative certification',
        'filing_fee' => '$180',
        'typical_timeline' => '3–6 months',
        'requires_specialist' => false,
    ],
    'ulca_multistakeholder' => [
        'name' => 'Multi-Stakeholder Cooperative (ULCA)',
        'short' => 'Multi-Stakeholder Co-op',
        'legal_basis' => 'RCW 23.78 (Uniform Limited Cooperative Association Act)',
        'description' => 'Washington\'s most flexible cooperative statute. Allows multiple membership classes — workers, investors, community members, customers — each with defined rights. Ideal for complex ownership structures.',
        'pros' => ['Multiple membership classes possible', 'Allows investor members with economic returns', 'Built-in cooperative protections', 'Ideal for community ownership models'],
        'cons' => ['More complex to structure and govern', 'Requires careful class definitions', 'Higher formation costs', 'Fewer attorneys familiar with RCW 23.78'],
        'ideal_for' => 'Businesses with mixed worker + community/investor ownership, community-owned groceries, neighborhood commercial buildings, complex multi-stakeholder models',
        'filing_fee' => '$180',
        'typical_timeline' => '4–8 months',
        'requires_specialist' => false,
    ],
    'consumer_coop' => [
        'name' => 'Consumer Cooperative',
        'short' => 'Consumer Co-op',
        'legal_basis' => 'RCW 23.86 (Cooperative Associations Act)',
        'description' => 'Owned and governed by customers rather than workers. Members join by purchasing a membership share and receive benefits based on purchases (patronage). Classic grocery co-op model.',
        'pros' => ['Clear consumer ownership model', 'Broad member base possible', 'Patronage refund structure understood by regulators', 'Strong brand recognition'],
        'cons' => ['Workers are employees, not owners', 'Governance can be complex with large membership', 'Less suited for business succession scenarios'],
        'ideal_for' => 'Retail businesses, food co-ops, buying clubs, businesses where customers are the primary stakeholders',
        'filing_fee' => '$30',
        'typical_timeline' => '3–5 months',
        'requires_specialist' => false,
    ],
    'esop' => [
        'name' => 'Employee Stock Ownership Plan (ESOP)',
        'short' => 'ESOP',
        'legal_basis' => 'ERISA (federal) + IRC §§ 401(a), 409, 1042',
        'description' => 'A federally regulated retirement plan that holds company stock for employees. Offers significant tax advantages for sellers and S-corp ESOPs pay no federal income tax. Requires specialized legal and financial advisors.',
        'pros' => ['Major tax advantages for seller (Section 1042)', 'S-corp ESOP pays no federal income tax', 'Employees build wealth through retirement accounts', 'Works well for larger businesses'],
        'cons' => ['Minimum ~$5M valuation to be cost-effective', 'Complex ERISA compliance ongoing', 'Requires specialized ESOP attorney and trustee', 'Not truly democratic governance'],
        'ideal_for' => 'Businesses with $5M+ valuation, owner wanting maximum tax deferral, larger employee bases (20+)',
        'filing_fee' => 'N/A (federal retirement plan)',
        'typical_timeline' => '6–18 months',
        'requires_specialist' => true,
    ],
]);

// Structure selector questions
function getStructureQuestions(): array {
    return [
        [
            'id' => 'ownership_type',
            'question' => 'Who do you want to own this business after the transition?',
            'help' => 'This is the most important question. Think about who should benefit from the business\'s success.',
            'options' => [
                ['value' => 'workers_only', 'label' => 'The workers and employees only', 'scores' => ['worker_coop_llc' => 3, 'worker_coop_corp' => 3]],
                ['value' => 'community_customers', 'label' => 'Community members or customers', 'scores' => ['consumer_coop' => 3, 'ulca_multistakeholder' => 1]],
                ['value' => 'workers_and_investors', 'label' => 'Workers plus outside investors or community members', 'scores' => ['ulca_multistakeholder' => 3]],
                ['value' => 'employees_retirement', 'label' => 'Employees, through a retirement plan structure', 'scores' => ['esop' => 4]],
            ],
        ],
        [
            'id' => 'employee_count',
            'question' => 'How many employees or potential member-owners are there?',
            'help' => 'Including full-time and part-time employees who would likely become members.',
            'options' => [
                ['value' => '1_5', 'label' => '1–5 people', 'scores' => ['worker_coop_llc' => 2, 'worker_coop_corp' => -1]],
                ['value' => '6_20', 'label' => '6–20 people', 'scores' => ['worker_coop_llc' => 1, 'worker_coop_corp' => 1]],
                ['value' => '21_50', 'label' => '21–50 people', 'scores' => ['worker_coop_corp' => 2, 'ulca_multistakeholder' => 1, 'esop' => 1]],
                ['value' => '50_plus', 'label' => 'More than 50 people', 'scores' => ['worker_coop_corp' => 1, 'esop' => 3, 'ulca_multistakeholder' => 1]],
            ],
        ],
        [
            'id' => 'financing',
            'question' => 'How do you expect the transition to be financed?',
            'help' => 'Most small business conversions use seller financing. CDFIs like Craft3 or Community Capital Development can supplement.',
            'options' => [
                ['value' => 'seller_note', 'label' => 'Seller carries the note (owner-financed buyout)', 'scores' => ['worker_coop_llc' => 2, 'worker_coop_corp' => 1]],
                ['value' => 'cdfi_loan', 'label' => 'CDFI or bank loan', 'scores' => ['worker_coop_llc' => 1, 'worker_coop_corp' => 1]],
                ['value' => 'community_investment', 'label' => 'Community investment or crowdfunding', 'scores' => ['ulca_multistakeholder' => 3, 'consumer_coop' => 2]],
                ['value' => 'esop_financing', 'label' => 'ESOP-specific financing (SBA, seller notes to ESOP trust)', 'scores' => ['esop' => 4]],
            ],
        ],
        [
            'id' => 'community_involvement',
            'question' => 'How important is it that people outside the business (community members, customers) have ownership stakes?',
            'options' => [
                ['value' => 'not_important', 'label' => 'Not important — this should be worker-owned only', 'scores' => ['worker_coop_llc' => 2, 'worker_coop_corp' => 1]],
                ['value' => 'somewhat', 'label' => 'Somewhat — maybe a small investor or community class', 'scores' => ['ulca_multistakeholder' => 2]],
                ['value' => 'very_important', 'label' => 'Very important — community ownership is central to the mission', 'scores' => ['ulca_multistakeholder' => 3, 'consumer_coop' => 2]],
            ],
        ],
        [
            'id' => 'governance_complexity',
            'question' => 'How comfortable are you with complex governance structures?',
            'help' => 'Democratic governance is core to cooperatives. More complex structures allow more flexibility but require more administrative work.',
            'options' => [
                ['value' => 'simple', 'label' => 'Keep it simple — one member, one vote, flat structure', 'scores' => ['worker_coop_llc' => 2, 'worker_coop_corp' => 1]],
                ['value' => 'moderate', 'label' => 'Moderate complexity is fine — different membership classes are OK', 'scores' => ['ulca_multistakeholder' => 2, 'worker_coop_corp' => 1]],
                ['value' => 'complex', 'label' => 'We can handle complexity — multiple classes with different rights', 'scores' => ['ulca_multistakeholder' => 3]],
            ],
        ],
        [
            'id' => 'business_valuation',
            'question' => 'What is the approximate value of the business?',
            'help' => 'Rough estimate is fine. This affects which structures are economically viable.',
            'options' => [
                ['value' => 'under_500k', 'label' => 'Under $500,000', 'scores' => ['worker_coop_llc' => 2]],
                ['value' => '500k_2m', 'label' => '$500,000–$2 million', 'scores' => ['worker_coop_llc' => 1, 'worker_coop_corp' => 1]],
                ['value' => '2m_5m', 'label' => '$2 million–$5 million', 'scores' => ['worker_coop_corp' => 2, 'ulca_multistakeholder' => 1]],
                ['value' => 'over_5m', 'label' => 'Over $5 million', 'scores' => ['esop' => 3, 'worker_coop_corp' => 1, 'ulca_multistakeholder' => 1]],
            ],
        ],
    ];
}

function scoreStructures(array $answers): array {
    $scores = [
        'worker_coop_llc'      => 0,
        'worker_coop_corp'     => 0,
        'ulca_multistakeholder' => 0,
        'consumer_coop'        => 0,
        'esop'                 => 0,
    ];
    $questions = getStructureQuestions();
    foreach ($questions as $q) {
        $answer = $answers[$q['id']] ?? null;
        if (!$answer) continue;
        foreach ($q['options'] as $opt) {
            if ($opt['value'] === $answer) {
                foreach ($opt['scores'] as $structure => $points) {
                    $scores[$structure] = ($scores[$structure] ?? 0) + $points;
                }
                break;
            }
        }
    }
    // Ensure no negative scores
    foreach ($scores as $k => $v) {
        $scores[$k] = max(0, $v);
    }
    arsort($scores);
    return $scores;
}

function getRecommendedStructure(array $scores): string {
    return array_key_first($scores);
}
