<?php
declare(strict_types=1);

/**
 * Localrev rule definitions.
 *
 * Each rule is a flat array with:
 *   key              — unique string identifier
 *   title            — human-readable name
 *   description      — 2-3 sentence explanation of the opportunity
 *   category         — b2b | community | pricing | partnership
 *   business_types   — which business types this applies to
 *   conditions       — array of conditions that must ALL be met (AND logic)
 *                      keys: has_org_type (string|array), population_min, population_max
 *   population_tier  — 'any' | 'under_10k' | '10k_25k' | '25k_plus'
 *                      (shorthand — conditions can also express this via population_min/max)
 *   revenue_estimate — human-readable range string shown to user
 *   priority_base    — 1–100; higher = shown first. Boosted at runtime by community data.
 */
function getLocalrevRules(): array
{
    return [

        // ----------------------------------------------------------------
        // GYM / YOGA / DOJO — facility-based programs
        // ----------------------------------------------------------------

        [
            'key'             => 'school_youth_training',
            'title'           => 'Youth Sports Training Partnership',
            'description'     => 'School athletic programs need off-season conditioning and skill development. A facility that can host structured after-school or summer training gives coaches a trusted partner — and gives you a reliable block booking that fills off-peak hours.',
            'category'        => 'b2b',
            'business_types'  => ['gym', 'yoga', 'dojo'],
            'conditions'      => ['has_org_type' => 'school'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$500–$2,000/mo',
            'priority_base'   => 85,
        ],

        [
            'key'             => 'corporate_wellness_package',
            'title'           => 'Corporate Employee Wellness Program',
            'description'     => 'Local employers increasingly offer wellness benefits to attract and retain staff. A structured membership or class program — especially one with a group rate and a measurable outcome they can report to HR — converts a cold pitch into a multi-person contract.',
            'category'        => 'b2b',
            'business_types'  => ['gym', 'yoga', 'dojo'],
            'conditions'      => ['has_org_type' => 'corporate'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$800–$3,500/mo',
            'priority_base'   => 90,
        ],

        [
            'key'             => 'senior_living_classes',
            'title'           => 'Senior Living Facility Partnership',
            'description'     => 'Senior living facilities are constantly looking for programming that improves residents\' physical health and social engagement. Low-impact group classes — chair yoga, balance training, tai chi — are easy to transport off-site and virtually immune to competition.',
            'category'        => 'partnership',
            'business_types'  => ['gym', 'yoga', 'dojo'],
            'conditions'      => ['has_org_type' => 'senior_living'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$400–$1,200/mo',
            'priority_base'   => 75,
        ],

        [
            'key'             => 'church_space_rental',
            'title'           => 'Church / Community Space Rental',
            'description'     => 'Churches with fellowship halls or gyms often sit empty six days a week. A sublease or revenue-share arrangement lets you run satellite classes in a different neighborhood without a long-term lease commitment. Good for testing demand in parts of town you don\'t currently reach.',
            'category'        => 'partnership',
            'business_types'  => ['gym', 'yoga', 'dojo'],
            'conditions'      => ['has_org_type' => 'church'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$200–$800/mo',
            'priority_base'   => 60,
        ],

        [
            'key'             => 'pt_clinic_referral_pipeline',
            'title'           => 'Physical Therapy Referral Partnership',
            'description'     => 'Physical therapists need somewhere to send discharged patients who need to maintain progress. A formal referral program — even a handshake deal with a shared intake process — creates a steady stream of motivated, health-conscious clients who already understand the value of structured movement.',
            'category'        => 'partnership',
            'business_types'  => ['gym', 'yoga', 'dojo'],
            'conditions'      => ['has_org_type' => 'medical'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$300–$1,500/mo',
            'priority_base'   => 80,
        ],

        [
            'key'             => 'seasonal_outdoor_bootcamp',
            'title'           => 'Seasonal Outdoor Boot Camp',
            'description'     => 'Outdoor training programs require almost no additional overhead — no extra space, no equipment you don\'t already own. A six-week spring or fall boot camp in a local park attracts people who would never walk into a gym and creates a conversion funnel into your regular programming.',
            'category'        => 'community',
            'business_types'  => ['gym', 'yoga', 'dojo'],
            'conditions'      => [],
            'population_tier' => 'any',
            'revenue_estimate'=> '$300–$1,200/mo',
            'priority_base'   => 65,
        ],

        [
            'key'             => 'sports_league_conditioning',
            'title'           => 'Adult Sports League Conditioning',
            'description'     => 'Recreational sports leagues — softball, volleyball, basketball — are full of adults who take competition seriously but train informally. A conditioning program marketed directly to league organizers positions you as the official off-field training partner and gives you a ready-made audience with shared social connections.',
            'category'        => 'b2b',
            'business_types'  => ['gym', 'dojo'],
            'conditions'      => [],
            'population_tier' => 'any',
            'revenue_estimate'=> '$400–$1,500/mo',
            'priority_base'   => 70,
        ],

        [
            'key'             => 'small_town_anchor_strategy',
            'title'           => 'Small-Town Anchor: Be the Only Option',
            'description'     => 'When the market is under 10,000 people, the competitive strategy flips — you win by being essential, not by out-marketing. Expanding your service offering (add massage, nutrition coaching, sports performance testing) keeps existing members inside your walls for more of their health spending instead of leaving town.',
            'category'        => 'pricing',
            'business_types'  => ['gym', 'yoga', 'dojo'],
            'conditions'      => ['population_max' => 9999],
            'population_tier' => 'under_10k',
            'revenue_estimate'=> '$500–$2,000/mo',
            'priority_base'   => 80,
        ],

        [
            'key'             => 'digital_extension_online',
            'title'           => 'Online Class Extension for Regional Reach',
            'description'     => 'In a small market, your brand ceiling is local population. Streaming classes — even one or two per week to start — punches through the geography wall. Former clients who moved away, people in adjacent towns without a local option, and specialty-seekers who can\'t find your format anywhere else become recurring digital members without adding a square foot of space.',
            'category'        => 'pricing',
            'business_types'  => ['gym', 'yoga', 'dojo'],
            'conditions'      => ['population_max' => 24999],
            'population_tier' => 'any',
            'revenue_estimate'=> '$300–$1,500/mo',
            'priority_base'   => 72,
        ],

        // ----------------------------------------------------------------
        // YOGA-SPECIFIC
        // ----------------------------------------------------------------

        [
            'key'             => 'corporate_mindfulness_workshops',
            'title'           => 'Corporate Mindfulness & Stress Management',
            'description'     => 'Workplace stress and burnout are at record levels — employers know it and are actively looking for solutions they can point to. A 90-minute workshop or a monthly lunch-and-learn series is low-commitment for the employer, easy to price as a flat fee, and establishes you as a professional resource rather than just a studio.',
            'category'        => 'b2b',
            'business_types'  => ['yoga'],
            'conditions'      => ['has_org_type' => 'corporate'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$500–$2,500/mo',
            'priority_base'   => 82,
        ],

        [
            'key'             => 'prenatal_postnatal_specialty',
            'title'           => 'Prenatal & Postnatal Specialty Program',
            'description'     => 'Prenatal yoga is one of the few fitness categories where doctors actively recommend a specific format. A certified prenatal program creates a word-of-mouth machine through OB offices, midwifery practices, and new-parent communities — the very people who are most likely to become long-term members once their baby arrives.',
            'category'        => 'community',
            'business_types'  => ['yoga'],
            'conditions'      => ['population_min' => 5000],
            'population_tier' => 'any',
            'revenue_estimate'=> '$400–$1,800/mo',
            'priority_base'   => 70,
        ],

        // ----------------------------------------------------------------
        // DOJO-SPECIFIC
        // ----------------------------------------------------------------

        [
            'key'             => 'school_pe_enrichment',
            'title'           => 'School PE Enrichment / After-School Program',
            'description'     => 'Elementary and middle schools with limited PE budgets are natural partners for martial arts instructors. A structured after-school program — especially one that emphasizes discipline, focus, and anti-bullying themes — sells itself to administrators and gives you a direct funnel into your youth class enrollment.',
            'category'        => 'b2b',
            'business_types'  => ['dojo'],
            'conditions'      => ['has_org_type' => 'school'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$600–$2,000/mo',
            'priority_base'   => 88,
        ],

        // ----------------------------------------------------------------
        // THERAPIST-SPECIFIC
        // ----------------------------------------------------------------

        [
            'key'             => 'school_counseling_contract',
            'title'           => 'School-Based Mental Health Contract',
            'description'     => 'School districts are under enormous pressure to provide mental health services but chronically short-staffed. A contract to provide on-site counseling days — especially for rural districts without a full-time school counselor — is steady, predictable revenue that doesn\'t depend on insurance reimbursement cycles.',
            'category'        => 'b2b',
            'business_types'  => ['therapist'],
            'conditions'      => ['has_org_type' => 'school'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$1,500–$4,000/mo',
            'priority_base'   => 90,
        ],

        [
            'key'             => 'eap_corporate_provider',
            'title'           => 'EAP Panel: Corporate Employee Assistance',
            'description'     => 'Employee Assistance Programs pay a flat per-session or per-employee rate for access to local therapists. Getting on the EAP panel of even one or two local employers creates a stream of pre-authorized sessions that bypass insurance reimbursement entirely and often convert to private-pay clients afterward.',
            'category'        => 'b2b',
            'business_types'  => ['therapist'],
            'conditions'      => ['has_org_type' => 'corporate'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$1,000–$3,500/mo',
            'priority_base'   => 88,
        ],

        [
            'key'             => 'medical_behavioral_integration',
            'title'           => 'Primary Care Behavioral Health Integration',
            'description'     => 'Primary care practices are increasingly embedding behavioral health services through co-location or warm referral programs. A formal partnership with a local medical practice — even one afternoon a week on-site — creates a consistent referral pipeline and positions you ahead of the national telehealth chains moving into small markets.',
            'category'        => 'partnership',
            'business_types'  => ['therapist'],
            'conditions'      => ['has_org_type' => 'medical'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$1,200–$3,000/mo',
            'priority_base'   => 85,
        ],

        [
            'key'             => 'group_therapy_expansion',
            'title'           => 'Group Therapy Program Expansion',
            'description'     => 'Group therapy typically reimbursements at 40–60% of individual session rates but allows you to serve 6–10 clients simultaneously. In a market large enough to fill a group (generally 25K+ population or a strong referral network), adding one or two specialty groups dramatically improves hourly revenue without adding overhead.',
            'category'        => 'pricing',
            'business_types'  => ['therapist'],
            'conditions'      => ['population_min' => 25000],
            'population_tier' => '25k_plus',
            'revenue_estimate'=> '$800–$2,500/mo',
            'priority_base'   => 75,
        ],

        [
            'key'             => 'telehealth_rural_reach',
            'title'           => 'Telehealth Expansion for Rural Catchment Area',
            'description'     => 'In small markets, the population ceiling is real — but it\'s only the ceiling for in-person work. Rural counties surrounding a small town are chronically underserved for mental health services, and telehealth removes the distance barrier entirely. Licensing permitting, you can extend your reach to every adjacent county without opening a second location.',
            'category'        => 'pricing',
            'business_types'  => ['therapist'],
            'conditions'      => ['population_max' => 24999],
            'population_tier' => 'any',
            'revenue_estimate'=> '$1,500–$5,000/mo',
            'priority_base'   => 80,
        ],

        [
            'key'             => 'senior_grief_adjustment_groups',
            'title'           => 'Senior Living: Grief & Life Adjustment Groups',
            'description'     => 'Senior living facilities have built-in demand for mental health services — grief, adjustment to assisted living, cognitive decline anxiety — and almost universally lack on-site clinical support. A contracted group or individual service arrangement is often billable to Medicare Advantage plans and requires no additional marketing.',
            'category'        => 'partnership',
            'business_types'  => ['therapist'],
            'conditions'      => ['has_org_type' => 'senior_living'],
            'population_tier' => 'any',
            'revenue_estimate'=> '$800–$2,000/mo',
            'priority_base'   => 78,
        ],

    ];
}
