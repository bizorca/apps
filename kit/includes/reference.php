<?php
declare(strict_types=1);

/**
 * Reference content from the framework, as data.
 *
 * Two rules govern everything in this file:
 *
 *  1. It gives orientation, never rules. Rates, thresholds, and requirements
 *     change, so nothing here states one as current fact. Every item carries
 *     the agency to verify with instead.
 *  2. Naming an issue is not advising on it. The advisor's job is to spot the
 *     problem, explain why it matters in plain terms, and refer.
 */

/** The Regulatory Intake Screen (Unit 1.1), grouped as the first meeting runs. */
function regulatoryItems(): array
{
    return [
        'Entity and filings' => [
            ['key' => 'entity',       'q' => 'What is the legal structure, and when was it formed?', 'verify' => 'Secretary of State'],
            ['key' => 'annual_report','q' => 'Is the Secretary of State annual report current?',     'verify' => 'Secretary of State'],
        ],
        'Licensing' => [
            ['key' => 'wa_license',   'q' => 'Does the business hold a Washington business license?', 'verify' => 'DOR Business Licensing Service'],
            ['key' => 'endorsements', 'q' => 'Does it have the city or county endorsements it needs?', 'verify' => 'City or county clerk'],
            ['key' => 'trade_license','q' => 'Does the work require a trade or professional license, and is it current?', 'verify' => 'L&I, Department of Health, or the county',
             'note' => 'Contractor registration, electrical or plumbing certification, massage therapist license, food handler or processor license, septic certification.'],
        ],
        'Tax registration and classification' => [
            ['key' => 'bo_registered','q' => 'Is the business registered and reporting for Washington excise taxes (B&O and sales tax)?', 'verify' => 'Department of Revenue',
             'note' => 'B&O applies to gross income, not profit — an unprofitable business can still owe it.'],
            ['key' => 'bo_class',     'q' => 'Do the reporting classifications match what the business actually does, rather than what it said at registration?', 'verify' => 'Department of Revenue'],
            ['key' => 'sales_tax',    'q' => 'Is retail sales tax being collected where it applies?', 'verify' => 'Department of Revenue',
             'note' => 'Which services count as "retail" in Washington is not intuitive — some construction, repair, and personal services are.'],
        ],
        'Workers' => [
            ['key' => 'has_workers',  'q' => 'Does anyone work for the business?', 'verify' => ''],
            ['key' => 'classification','q' => 'Are they employees or contractors, and does that classification hold up?', 'verify' => 'L&I and the IRS',
             'note' => 'The IRS and Washington apply different tests, and Washington\'s are generally stricter. Misclassification creates exposure for back taxes, workers\' comp premiums, and penalties.'],
            ['key' => 'workers_comp', 'q' => 'Is workers\' compensation coverage through L&I in place for employees?', 'verify' => 'Labor & Industries',
             'note' => 'Washington runs its own state fund. Coverage is required for most employees, part-time included. Private workers\' comp generally is not an option except for qualified self-insurers.'],
            ['key' => 'esd',          'q' => 'Is unemployment insurance in place through ESD?', 'verify' => 'Employment Security Department'],
            ['key' => 'pfml',         'q' => 'Are Paid Family & Medical Leave and WA Cares obligations being met?', 'verify' => 'Employment Security Department'],
        ],
        'Insurance' => [
            ['key' => 'gl',           'q' => 'General liability — matched to actual activities?',   'verify' => 'Insurance agent or broker'],
            ['key' => 'pl',           'q' => 'Professional liability, where the work calls for it?', 'verify' => 'Insurance agent or broker'],
            ['key' => 'auto',         'q' => 'Commercial auto, if vehicles are used for work?',      'verify' => 'Insurance agent or broker'],
            ['key' => 'tools',        'q' => 'Tools and equipment coverage?',                        'verify' => 'Insurance agent or broker'],
            ['key' => 'bond',         'q' => 'Bonding, where the trade requires it?',                'verify' => 'L&I or the insurance broker'],
        ],
        'Federal tax posture' => [
            ['key' => 'estimated',    'q' => 'Are federal estimated tax payments being made?', 'verify' => 'CPA or EA'],
            ['key' => 'unfiled',      'q' => 'Are any tax returns unfiled, or any balances owed?', 'verify' => 'CPA or EA'],
        ],
        'Local rules' => [
            ['key' => 'local',        'q' => 'Any county or city permits, zoning, or health department requirements?', 'verify' => 'County and city offices',
             'note' => 'Septic, food, and water system requirements are commonly county-level.'],
        ],
    ];
}

/** The Tax Exposure Flag List (Unit 1.2) — concepts to name, never to advise on. */
function taxFlags(): array
{
    return [
        ['key' => 'estimated', 'name' => 'Estimated tax payments',
         'plain' => 'Self-employed owners generally pay federal income tax and self-employment tax quarterly. Missing them creates penalties and a large balance at filing time.'],
        ['key' => 'se_tax', 'name' => 'Self-employment tax',
         'plain' => 'Social Security and Medicare tax on net self-employment earnings, generally 15.3% up to the Social Security wage base, with the Medicare portion continuing above it. The wage base changes yearly. This is the tax owners most often forget.'],
        ['key' => 's_corp', 'name' => 'S corporation election',
         'plain' => 'Can reduce self-employment tax at higher profit levels by splitting income between a reasonable salary and distributions, but adds payroll, bookkeeping, and filing costs. Whether it makes sense depends on facts a CPA or EA must evaluate. Never estimate the savings.'],
        ['key' => 'reasonable_comp', 'name' => 'Reasonable compensation',
         'plain' => 'The salary an S corporation owner-employee must pay themselves before taking distributions.'],
        ['key' => 'accountable_plan', 'name' => 'Accountable plans',
         'plain' => 'A written reimbursement arrangement that lets a business reimburse employees — including owner-employees of an S corporation — for business expenses without the reimbursement being taxed as wages, when substantiation and return-of-excess rules are met.'],
        ['key' => 'retirement', 'name' => 'Retirement plan options',
         'plain' => 'SEP-IRA, Solo 401(k), SIMPLE IRA. Each has different contribution rules and setup deadlines.'],
        ['key' => 'qbi', 'name' => 'Qualified business income (QBI) deduction',
         'plain' => 'A federal deduction for many pass-through businesses. Eligibility and limits are for a tax professional.'],
        ['key' => 'worker_class', 'name' => 'Worker classification',
         'plain' => 'Whether a worker is an employee or an independent contractor. The IRS and Washington apply different tests, and Washington\'s are generally stricter. Misclassification creates exposure for back taxes, workers\' comp premiums, and penalties.'],
    ];
}

/** The referral boundary (Part 4). Who to send the client to, and when. */
function referralBoundary(): array
{
    return [
        ['who' => 'CPA or Enrolled Agent (EA)',
         'when' => 'Tax elections, entity tax treatment, estimated payment amounts, unfiled returns, tax balances owed, notices, payroll tax questions, retirement plan setup, deductions and filing positions.'],
        ['who' => 'Attorney',
         'when' => 'Entity formation and restructuring, contracts and service agreements, partnership disputes, liability questions, employment law, collections litigation.'],
        ['who' => 'Insurance agent or broker',
         'when' => 'Coverage gaps, matching policies to actual activities, bonding.'],
        ['who' => 'Bookkeeper',
         'when' => "Books that aren't reconciled, catch-up bookkeeping, payroll processing."],
        ['who' => 'Licensed clinician or counselor',
         'when' => "When an owner's distress, health, or wellbeing is driving the business problem beyond ordinary stress."],
        ['who' => 'The agency directly',
         'when' => 'Licensing (DOR Business Licensing Service, trade boards, Department of Health), workers\' comp (L&I), unemployment insurance (ESD), entity filings (Secretary of State).'],
    ];
}

/** Washington orientation (Part 5). Orientation, not rules. */
function waLandscape(): array
{
    return [
        ['name' => 'No personal income tax',
         'text' => 'Washington has no personal income tax. Never reserve for Washington state income tax. (There is a capital gains tax on certain high-value gains — a CPA or EA question.)'],
        ['name' => 'Business & Occupation (B&O) tax',
         'text' => 'A gross receipts tax administered by the Department of Revenue. It applies to gross income, not profit, so an unprofitable business can still owe it. Rates vary by classification, and businesses must report under the classifications matching their actual activities. Small business credits and filing thresholds exist.'],
        ['name' => 'Retail sales tax',
         'text' => 'Collected on retail sales of goods and some services; state and local rates combine. Which services are "retail" in Washington is not intuitive — some construction, repair, and personal services are.'],
        ['name' => 'Business licensing',
         'text' => "Most businesses need a Washington business license through DOR's Business Licensing Service, plus city or county endorsements where required."],
        ['name' => 'Entity filings',
         'text' => 'LLCs and corporations are formed through the Secretary of State and file annual reports.'],
        ['name' => "Workers' compensation",
         'text' => 'Washington runs its own state fund through Labor & Industries. Coverage is required for most employees, part-time included. Private workers\' comp generally is not an option except for qualified self-insurers. Premiums are based on hours worked and risk class.'],
        ['name' => 'Contractor registration',
         'text' => 'Construction contractors generally must register with L&I and carry a bond and insurance.'],
        ['name' => 'Unemployment insurance',
         'text' => 'Administered by the Employment Security Department for employers.'],
        ['name' => 'Paid Family & Medical Leave and WA Cares',
         'text' => 'State programs with employer and employee obligations, administered through ESD.'],
        ['name' => 'Professional licensing',
         'text' => 'Many health and wellness professions, massage therapy included, are licensed through the Department of Health. Many trades are licensed or certified through L&I, and some septic-related work is certified at the county level.'],
        ['name' => 'Local rules',
         'text' => 'Counties and cities add permits, zoning rules, and health department requirements — septic, food, water systems.'],
    ];
}

/** Glossary (Part 14). */
function glossary(): array
{
    return [
        'Accountable plan'     => 'A reimbursement arrangement that keeps business expense reimbursements from being taxed as wages when IRS rules are met.',
        'Aging (receivables)'  => 'Grouping unpaid invoices by how long they have been outstanding.',
        'B&O tax'              => "Washington's Business & Occupation tax on gross receipts.",
        'Baseline owner pay'   => 'The minimum monthly amount the owner needs to live on, treated as a fixed cost.',
        'Behavioral avoidance' => 'The owner knows what to do and does not do it.',
        'Burn'                 => 'Monthly fixed costs, including baseline owner pay.',
        'EHR'                  => 'Effective hourly rate: real take-home divided by all hours worked.',
        'Exposure'             => 'How much harm an issue can do if it waits.',
        'Franchise prototype'  => "Gerber's model of a business designed to be replicable and run on documented systems.",
        'Informational gap'    => 'The owner does not know what to do.',
        'L&I'                  => "Washington State Department of Labor & Industries — workers' compensation, contractor registration, trade licensing.",
        'Pricing floor'        => "The minimum a billable hour must bring in to hit the owner's target take-home.",
        'Readiness'            => 'How likely the owner is to act on an issue this month.',
        'Residual pay'         => 'Paying the owner whatever is left, which hides unprofitability.',
        'Runway'               => 'Months the business can survive on cash with no new work.',
        'Task Library'         => "Documented, step-by-step protocols for the business's recurring work.",
        'TOWS'                 => 'Turning SWOT entries into paired strategic moves.',
        'Utilization'          => 'Billable hours as a share of all hours worked.',
    ];
}

/** Ratio and metric definitions (Part 6). Diagnostic signals, not verdicts. */
function metricDefinitions(): array
{
    return [
        'Profitability' => [
            ['name' => 'Gross margin',           'formula' => '(Revenue − direct costs) ÷ Revenue', 'note' => 'For service businesses, direct costs include subcontractors, materials, and direct labor.'],
            ['name' => 'Net margin',             'formula' => 'Net profit ÷ Revenue', 'note' => 'Compare only after normalizing owner pay.'],
            ['name' => 'Owner-adjusted profit',  'formula' => 'Net profit − baseline owner pay', 'note' => 'Negative means the owner is subsidizing the business with unpaid labor.'],
            ['name' => 'Contribution margin',    'formula' => 'Price − variable costs of delivering it', 'note' => 'Tells you which services actually pay for the business.'],
            ['name' => 'Break-even revenue',     'formula' => 'Fixed costs ÷ Contribution margin ratio', 'note' => 'Fixed costs include baseline owner pay.'],
            ['name' => 'Markup vs. margin',      'formula' => 'Margin = Markup ÷ (1 + Markup)', 'note' => 'A 50% markup on cost produces a 33% margin, not 50%. Owners who confuse the two underprice.'],
        ],
        'Liquidity and cash' => [
            ['name' => 'Trailing 90-day cash flow', 'formula' => 'Cash in − cash out, last 90 days', 'note' => 'Exclude loans and transfers from cash in. Include owner draws in cash out.'],
            ['name' => 'Monthly fixed-cost burn',   'formula' => 'Sum of costs that continue with no work', 'note' => 'Including baseline owner pay.'],
            ['name' => 'Runway',                    'formula' => 'Cash on hand ÷ monthly burn', 'note' => '3+ months healthy, 1–3 watch, under 1 act.'],
            ['name' => 'Quick liquidity ratio',     'formula' => '(Cash + receivables due in 30 days) ÷ bills due in 30 days', 'note' => 'Exclude aged receivables. 1.5+ healthy, 1.0–1.5 watch, under 1.0 act.'],
            ['name' => 'Current ratio',             'formula' => 'Current assets ÷ current liabilities', 'note' => 'Less useful when books are thin.'],
        ],
        'Receivables and collections' => [
            ['name' => 'Days sales outstanding',  'formula' => 'Accounts receivable ÷ average daily revenue', 'note' => 'Average daily revenue = annual revenue ÷ 365. Compare with stated terms.'],
            ['name' => 'Receivables aging',       'formula' => 'Grouped: current, 31–60, 61–90, 90+ days', 'note' => 'Past 60 days deserves attention; past 90 is often behavioral.'],
            ['name' => 'Customer concentration',  'formula' => 'Revenue from top client (or top three) ÷ total revenue', 'note' => 'Above roughly 25–30% from one client is a dependency risk.'],
        ],
        'Debt' => [
            ['name' => 'Debt service coverage ratio', 'formula' => 'Cash available for debt service ÷ annual debt payments', 'note' => 'Lenders often want 1.25 or more. Under 1.0 means operations cannot cover the debt.'],
            ['name' => 'Debt-to-equity',              'formula' => "Total liabilities ÷ owner's equity", 'note' => 'Hard to interpret in micro-businesses with thin balance sheets.'],
            ['name' => 'Profitable leverage test',    'formula' => 'Does the borrowed money produce predictable, increased profit?', 'note' => 'Debt used for operating shortfalls is a warning sign, not a strategy.'],
        ],
        'Time, capacity, and pricing' => [
            ['name' => 'Effective hourly rate',    'formula' => 'Real take-home ÷ all hours worked', 'note' => 'The single most clarifying number for most owners.'],
            ['name' => 'Revenue per billable hour','formula' => 'Annual revenue ÷ annual billable hours', 'note' => 'The gap against the quoted rate reveals discounting, unbilled time, and write-offs.'],
            ['name' => 'Utilization rate',         'formula' => 'Billable hours ÷ total hours worked', 'note' => 'Many solo service businesses land between 50% and 70%.'],
            ['name' => 'Pricing floor',            'formula' => '(Target take-home ÷ (1 − tax reserve rate) + operating costs) ÷ billable hours', 'note' => 'The minimum an hour of billable work must bring in.'],
            ['name' => 'Repeat rate',              'formula' => 'Revenue or jobs from returning clients ÷ total', 'note' => 'Strong repeat rates support maintenance plans and referral systems.'],
            ['name' => 'Customer acquisition cost','formula' => 'Marketing and sales spend ÷ new clients', 'note' => 'Only useful when the owner tracks where clients came from.'],
            ['name' => 'Client lifetime value',    'formula' => 'Average revenue per client × average years retained', 'note' => 'Compare with CAC.'],
        ],
    ];
}
