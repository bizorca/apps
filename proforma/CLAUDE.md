# ProForma — Project Context for Claude

## What This Is
Pro forma modeling tool for small wellness/health businesses (yoga studios, gyms, dojos, therapy practices). Users enter costs, pricing and staffing; the app outputs break-even, owner affordability and sensitivity analysis.

**Live:** https://tools.bizorca.com/proforma/ — one tool on the shared tools platform. Read `../CLAUDE.md` (the repo root) first for the platform: shared account, shared MySQL, deploy.
**Ported 2026-10-07** from the standalone proforma.bizorca.com (SiteGround, SQLite, own login + Bizorca SSO). The old GitHub repo `bizorca/proforma` keeps the pre-port history.

## How it sits on the platform
- **Folder layout:** `public/` deploys to `public_html/proforma/`; `includes/`, `templates/`, `migrations/`, `bin/` deploy to `private_html/proforma/` (outside the web root). `public/_bootstrap.php` is the only file that knows the two layouts apart: it defines `PF_ROOT` and loads `includes/config.php`, which loads the shared core.
- **Every page's first require** is `_bootstrap.php` (`dirname(__DIR__) . '/_bootstrap.php'` from `wizard/` and `cron/`). After that, paths are `PF_ROOT . '/includes/...'`.
- **URLs:** the app lives under `PF_BASE` (`/proforma`). Markup uses `<?= PF_BASE ?>/page.php`; `redirect('/page.php')` adds the prefix itself; `getWizardSteps()` URLs include it; `APP_URL` is the absolute base for emails and share links. URLs end in `.php`: this Cloudways app ignores `.htaccess`, so there are no rewrites.
- **Auth:** the shared tools account (`/account/login.php` etc.). `includes/auth.php` is a thin wrapper: `requireAuth()` → `tl_require_login()`, `currentUser()` maps the shared `users` row (`name`, not first/last). Proforma's own login/register/logout/SSO pages are gone. `startSession()` only resumes an existing session, and `flash()` reads without creating one, so anonymous visitors (share links, landing) get no cookie.
- **Session keys are namespaced** because every tool shares one session: `$_SESSION['pf_business_id']` is the active business.
- **Database:** shared MySQL via `getDb()` → `tl_db()` (native prepares, UTC). Tables are `pf_`-prefixed; `users` is the shared table. Schema lives in `migrations/*.sql`, applied by `private_html/bin/migrate.php` on every deploy. MySQL 8.4, not MariaDB: upserts are `INSERT ... AS new ON DUPLICATE KEY UPDATE col = new.col`, and a bare column in that clause must be table-qualified (`COALESCE(new.lat, pf_market_profiles.lat)`), or MySQL calls it ambiguous. `LIMIT ?` needs `bindValue(..., PDO::PARAM_INT)`.
- **Keys** (`SMTP2GO_API_KEY`, `ANTHROPIC_API_KEY`, `GOOGLE_PLACES_API_KEY`, `PF_CRON_SECRET`) come from `private_html/.env.php` via `tl_env()`; config.php turns them into the old constant names.

## Local dev
From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, then http://127.0.0.1:8080/proforma/. Needs local MySQL with database `bizorca_tools` and `php private_html/bin/migrate.php`.

## Data import (done 2026-10-07)
`bin/import-sqlite.php <file> [--dry-run]` imported the old SQLite database: 2 users (matched into shared `users` by email, bcrypt hashes kept, so old passwords work; names derived from the email address since none were stored), 9 businesses and every child row with original ids. It refuses to run if any pf_ table has rows. Kept for reference; not needed again.

## Directory Layout
```
public/              -> public_html/proforma/
  _bootstrap.php     finds PF_ROOT, loads config
  index.php          landing (logged-in users go to dashboard)
  dashboard.php      business list + create/switch/clone/delete; net/revenue/break-even + opportunity count per card
  settings.php       per-user payroll tax rates
  actuals.php        monthly actual revenue/expense entry + history
  localrev.php       Market Intel — market profile, local orgs, recommendations with playbooks + feedback
  share.php          public read-only report (token auth, no login required)
  wizard/            setup, expenses, classes, revenue, instructors (class businesses) | insurance, providers (therapist) | report
  cron/digest.php    monthly email digest — secured by PF_CRON_SECRET, supports &dry_run=1
  assets/app.css
includes/            -> private_html/proforma/includes/
  config.php         loads shared core; BUSINESS_TYPES, getDb(), getWizardSteps(), getCalculator()
  db.php             all data-access helpers
  auth.php           wrapper over the shared account
  helpers.php        h(), money(), pct(), csrf(), verifyCsrf(), flash(), redirect()
  email.php          sendEmail(), sendMonthlyDigest(), sendLocalrevNotification()
  localrev/          rules, RecommendationEngine, PlaybookGenerator (Claude Haiku), CensusClient, PlacesClient
  calculations/      Calculator (abstract), YogaCalculator (yoga/gym/dojo), TherapistCalculator
templates/           header, footer, wizard_nav, report_yoga, report_therapist
migrations/001_proforma.sql   all pf_ tables
bin/import-sqlite.php         one-time import (above)
```

## Business Types & Wizard Routing
- `yoga`, `gym`, `dojo` → 6-step wizard (setup/expenses/classes/revenue/instructors/report), use YogaCalculator
- `therapist` → 5-step wizard (setup/expenses/insurance/providers/report), use TherapistCalculator
- `getWizardSteps(string $type)` in config.php returns the correct ordered array
- `wizardStepStatus(int $bid, string $type)` is type-aware — different tables per type

## Calculator Architecture
```
Calculator (abstract)
  __construct($business, $expenses, $schedules, $streams, $instructors, $settings=[])
  employerBurdenMultiplier()   — 1 + (fed + state + wc) from $settings
  monthlyOwnerSalary()
  monthlyOperatingCost()       — overhead + staff, no owner
  monthlyNetBeforeOwner()
  monthlyTotalCost()           — includes owner draw
  monthlyNetIncome()

YogaCalculator extends Calculator
  — Burden multiplier applied per-instructor by worker_type
  — report() keys: gross_revenue, instructor_cost, operating_cost, owner_salary_monthly,
    net_before_owner, net_income, break_even_fill_rate, break_even_fill_rate_no_owner,
    current_fill_rate, blended_rev_per_visit, fill_scenarios, revenue_by_stream, ...

TherapistCalculator extends Calculator
  __construct(..., $extra=[])  — pulls $extra['settings'], payers/codes/rates/providers
  — Burden multiplier applied per-provider by worker_type
  — report() keys: gross_revenue, staff_cost, operating_cost, owner_salary_monthly,
    net_before_owner, net_income, break_even_sessions, break_even_sessions_no_owner, ...
```

**getCalculator() call pattern:**
```php
$extra = ['settings' => getUserSettings($userId)];
// therapist only:
$extra['payers']    = getInsurancePayers($bid);
$extra['codes']     = getCptCodes($bid);
$extra['rates']     = getPayerRates($bid);
$extra['providers'] = getStaffProviders($bid);
$calc = getCalculator($biz, $expenses, $schedules, $streams, $instructors, $extra);
```

## Report Templates
**report_yoga.php** expects in scope: `$r`, `$biz`, `$actuals`, `$isShared` (bool), `$bid` (int)
- Section 0: What-If Scenario Planner (fill rate, drop-in price, subscription price sliders)
- Section 1: Monthly P&L (revenue + operating costs + net before owner + owner draw + net after)
- Section 2: Break-even analysis with owner-salary toggle + industry benchmark chips (50–70% fill, $15–$25 blended $/visit)
- Section 3: Pricing sensitivity table (fill rate × price multiplier grid)
- Section 4: Instructor pay analysis + benchmark chips (<25%/25–35%/>35%, source: Mindbody/Yoga Alliance)
- Section 5: Intro offer funnel (conditional)
- Section 6: Online revenue breakdown (conditional)
- Section 7: Class pass cash vs. earned (conditional)
- Section 8: Expense waterfall
- Section 9: Annual projection
- Section 10: Actual vs. projected (uses `$actuals`)
- Section 11: Local Revenue Opportunities — top 3 recommendations; hidden when `$isShared = true` or no market profile
- Edit links and share button hidden when `$isShared = true`
- "Download CSV" button (client-side JS, `reportData` JSON constant, `downloadCsv()` function)

**report_therapist.php** — 8-section therapist report with benchmark chips throughout:
- Section 2: $111 insurance vs $159 cash per session (Heard 2024); insurance discount note
- Section 3: payer mix trend note (27% cash-only; 60/40 hybrid sustainability model)
- Section 4: break-even benchmark chips (20–25 sess/week full caseload; 5–8 overhead-only; 15–20 with owner salary; collection rate targets 95%+/80–85%)
- Section 5: staff cost % chips (W-2: 50–60%; 1099: 60–70%; >60% = sustainability risk)
- Section 8: panel size chips (full caseload 20–25/week; 50% of solos see ≤15/week; 65–75% utilization target)

**report.php** handles share token POST actions (generate/revoke) before rendering.

## Shareable Reports
- `share_token TEXT` column on `businesses` (migration in runSchema)
- `generateShareToken(int $bid, int $userId): string` — creates 48-char hex token
- `revokeShareToken(int $bid, int $userId): void` — nulls the token
- `getBusinessByToken(string $token): ?array` — no user auth required
- `/share.php?token=...` loads data by token, sets `$isShared = true`, includes same templates

## Actual vs. Projected Tracking
- `monthly_actuals` table: business_id, year, month (UNIQUE), gross_revenue, student_visits, total_expenses, notes
- `saveMonthlyActual(int $bid, int $year, int $month, array $data): void` — upsert
- `getMonthlyActuals(int $bid, int $limit = 12): array` — newest first
- `deleteMonthlyActual(int $id, int $businessId): void` — verifies business_id before deleting
- `/actuals.php` — entry form (month/year picker pre-fills existing data) + history table with Delete per row

## Clone Business
- `cloneBusiness(int $businessId, int $userId): int` — returns new business ID
- Deep-copies: expenses, class_schedules, revenue_streams, instructors
- Therapist: also copies insurance_payers, cpt_codes, payer_rates (with ID remapping), staff_providers
- Market Intel: also copies market_profiles + local_organizations (recommendations regenerate fresh)
- Dashboard "Clone" button → POST action=clone → switches to new business → redirects to setup

## Email (SMTP2Go)
- Key and cron secret from `.env.php`; sender and APP_URL in config.php
- Sender: ProForma <jassen@bizorca.com>; domain verified
- `sendEmail(string $toAddr, string $toName, string $subject, string $html, string $text): bool`
- `sendMonthlyDigest(string $email, array $summaries, string $monthLabel): void`
- `sendLocalrevNotification(string $email, string $bizName, array $recommendations): void` — sent after market profile save generates new recommendations
- Cron digest: `GET /proforma/cron/digest.php?secret=PF_CRON_SECRET[&dry_run=1]` — mails only users who have at least one Proforma business (the account is shared). An empty secret locks the endpoint.
- Schedule: Cloudways → Application → Cron Job Management, `0 8 1 * *`, curl the URL above.

## Employee vs. Contractor
- `worker_type` column on `instructors` and `staff_providers` (default: `contractor`)
- Employees: `employerBurdenMultiplier()` applied to base cost
- Rates at `/settings.php` → `user_settings` table. Defaults: FICA 7.65%, state 3.0%, WC 2.0%

## P&L Structure (both report types)
1. Revenue breakdown → Gross Revenue
2. Operating Costs (expenses + staff, **no owner draw**)
3. **Net Operating Income** — green/red banner
4. **Owner's Draw** block — indigo
5. **Net Income After Owner Draw** — green/red banner

## Break-Even Toggle
Radio `be_mode` / `be_mode_t` swaps JS between pre-calculated server-side values.
Yoga: `break_even_fill_rate` / `break_even_fill_rate_no_owner`
Therapist: `break_even_sessions` / `break_even_sessions_no_owner`

## Key Conventions
- All POST forms: `<?= csrf() ?>` + `verifyCsrf()` on handler
- `h()` all HTML output, `money()` currency, `pct()` percentages
- Dynamic rows: vanilla JS DOM manipulation, no framework
- Flash: `flashSuccess()` / `flashError()` → `renderFlash()` in header
- PRG pattern: `redirect('/path')` after every POST (PF_BASE is added automatically)
- Never assume `yoga` — always check `$biz['business_type']`

## Database Tables (16, all pf_-prefixed; users is shared)
businesses (+ share_token), expenses, class_schedules, revenue_streams, instructors,
insurance_payers, cpt_codes, payer_rates, staff_providers, user_settings, monthly_actuals,
market_profiles, local_organizations, recommendations, recommendation_feedback, api_cache


## Market Intel (Local Revenue Intelligence)
- `/localrev.php` — standalone page: market profile form, local org management, recommendations with playbooks + feedback
- "Market Intel" nav link in header; opportunity count shown inline on dashboard cards; Section 11 on yoga report
- **Market profile** — town, state, ZIP, population. ZIP triggers auto Census ACS lookup (population, median income) + geocoding
- **Geocoding** — Census Geocoder primary, Nominatim (OSM) fallback; Google Geocoding optional if GOOGLE_PLACES_API_KEY set
- **Org scan** — "Scan nearby organizations" button → Overpass API (OSM, free, no key) → populates local_organizations; cached 30 days
- **Recommendation engine** — `generateRecommendations()` in RecommendationEngine.php evaluates 19 rules (conditions: has_org_type, population_min/max); rules in rules.php
- **Playbooks** — Claude Haiku (ANTHROPIC_API_KEY in config.php) generates contextual playbook per recommendation: who to contact, what to offer, sample pricing, outreach template, timeline, success signals
- **Feedback loop** — status buttons per recommendation: Considering / Tried it / Working / Not relevant → recommendation_feedback table
- **Email notification** — `sendLocalrevNotification()` fires after market profile save when recommendations are generated
- Shared reports (`$isShared = true`) never show Market Intel data
- `cloneBusiness()` copies market_profiles + local_organizations; recommendations regenerate fresh

## Fixed during the port
- Dashboard crashed (`pct(null)`) for every therapist business: it showed a fill-rate break-even, which only class businesses have. Now therapist cards show break-even sessions/month. The bug was live on proforma.bizorca.com (3 fatals in its error log).
