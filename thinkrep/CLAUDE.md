# ThinkRep — Cognitive Training (tools.bizorca.com/thinkrep)

## What this is
Scenario-based mental model training. Users face real-world decisions, identify which cognitive framework applies, write their reasoning, and get scored on both answer accuracy and thinking quality. Surfaces blind spots over time. B2B features: company accounts, team mode, onboarding cohorts, custom scenario packs, role/industry benchmarking.

**Live:** https://tools.bizorca.com/thinkrep/ — one tool on the shared tools platform. Read `../CLAUDE.md` first (shared account, shared MySQL, deploy).
**Ported 2026-10-07** from the original thinkrep.bizorca.com (SiteGround, own MySQL db, Bizorca SSO only), source `Archipelago/deprecated/thinkrep/`. The **full original feature set** came across, including teams, companies, cohorts, packs and benchmarks. This is NOT the financialhypnosis.com copy (`hypnosis/website/apps/thinkrep/`), which dropped the B2B features; the two are separate and not kept in sync.

## How it sits on the platform
- **Layout:** `public/` → `public_html/thinkrep/`; `includes/`, `templates/`, `migrations/`, `bin/` → `private_html/thinkrep/`. `public/_bootstrap.php` finds `TR_ROOT` in either layout and loads `includes/config.php`, which loads the shared core. Every page's first line is `require_once __DIR__ . '/_bootstrap.php';`.
- **URLs:** every link and redirect already went through `url()`; `BASE_PATH` is `/thinkrep`. Files end in `.php` (no rewrites on this server).
- **Auth:** the shared tools account. `config.php` keeps ThinkRep's helper names (`isLoggedIn()`, `requireLogin()`, `requireOnboarded()`, `getCurrentUser()`, `getCurrentUserId()`) on top of `tl_user()`. Sign in/out/register are `/account/*.php`; the SSO client, `login.php`, `logout.php` and `sso-callback.php` are gone. Session keys are `tr_csrf_token` and `tr_flash` (one session is shared by every tool). `getFlash()` never creates a session, so anonymous visitors get no cookie.
- **Profiles:** what the old `users` row held beyond the account lives in `tr_profiles` (role_title, industry, onboarded_at, timezone, is_admin), one row per user, created by `getCurrentUser()` on first visit. Writes go through `saveProfile()`. Queries that need role/industry `LEFT JOIN tr_profiles up ON up.user_id = u.id` and read `up.role_title` / `up.industry`.
- **Admin:** reviews scenario submissions (`admin-submissions.php`). `isThinkrepAdmin()` = `tr_profiles.is_admin` OR the site owner's shared `users.is_admin`. The site-wide flag counts because the original's only admin was a placeholder account that could never sign in.
- **Timezone:** the pages mix SQL dates (`CURDATE()`, `DATE(created_at)`, `NOW()`) with PHP `date('Y-m-d')`, and streaks / the daily challenge / today's confidence log depend on them agreeing. `config.php` sets PHP's zone to the user's and then moves the MySQL session to the same offset (`SET time_zone`). Stored DATETIMEs are therefore the writer's wall clock, as the original intended.
- **Database:** `tr_`-prefixed tables in the shared MySQL db; user ids are signed `INT` to match `users.id`. `getDB()` → `tl_db()`. Upserts use `INSERT ... AS new ON DUPLICATE KEY UPDATE col = new.col`.

## Migrations
- `001_thinkrep.sql` — all tables (original migrations 001/004/005/007 folded together) plus `tr_profiles`. `tr_cohorts.created_by` is nullable so the built-in template exists before any user.
- `002_thinkrep_content.sql` — content dumped from the live database 2026-10-07, ids kept: 10 models, 36 scenarios (30 + 6 mashups), 180 choices, 12 mashup correct-model rows, and the 20-day cohort template as cohort 1. Verified byte-identical to live with `CHECKSUM TABLE`. Live held the template twice (a broken 17-day copy whose mashup days never seeded, and the complete one); only the complete one is seeded.

## Data import (one-time)
`bin/import-mysqldump.php <dump.sql> [--dry-run]` — stages the dump as `trimp_*` tables in the same database (a Cloudways db user cannot CREATE DATABASE), copies into `tr_` in one transaction, drops the staging tables. Users matched into shared `users` by email. The original stored **no passwords** (SSO only), so new accounts get an unusable random hash and must use "Forgot your password?". A user with no SSO link and no data (live's "System" placeholder) is skipped. Seeded content is not re-copied; only scenarios the seed lacks (approved submissions, packs) are, and a seeded scenario whose live text differs is reported, not overwritten. Template cohorts map to the seeded template by name. Refuses if any tr_ table already holds user data.

Live data at port time: 2 users (1 real, SSO-only; 1 placeholder), no responses, teams, companies or enrollments.

## Content model notes (from hypnosis/website/THINKREP.md, still true)
- `scenarios.ideal_reasoning` does double duty: its first line is a `KEY: a|b|c` header that `includes/scoring.php` parses for the key-concepts point; everything after the first newline is the model answer. Editing it changes the marking scheme.
- The reasoning score is gameable (length, "because", "however", a dollar figure). Model-selection accuracy is the real signal.
- One scenario a day exhausts the 36 in about five weeks.

## Scoring
**Standard (0-10):** model selection (5) + reasoning quality (0-5: length, key concepts, causal language, tradeoff awareness, specificity). Wrong model caps reasoning at 2/5.
**Mashup (0-10):** primary model (5) + secondary found (2) + reasoning (0-3). Wrong primary caps reasoning at 1/3.

## Scenario selection priority
0. Unseen company pack scenarios (company members) · 1. Spaced-repetition reviews due (wrong → 1/3/7 days, weak reasoning → 7/14, strong → 30) · 2. Unseen, preferring tag matches and weak models · 3. Not seen in 30 days · 4. Any active.

## Fixed during the port
- `cohort-detail.php` never enforced access: any signed-in user could read any cohort's roster, and its manager check used the viewer's own company, so a manager anywhere could enroll people into another company's cohort. Now: manager of the cohort's company, or enrolled.
- `company-cohorts.php` `enroll` accepted any posted `cohort_id`. Now scoped to the manager's company.
- Lists ordered only by `created_at` (journal, recent activity, submissions, packs, teams) got an `id DESC` tiebreak; rows written in the same second came back in arbitrary order, and the journal paginates.
- `confidence.php` used the deprecated `VALUES()` upsert form.

## Conventions
- PHP 8.2 only. Procedural, one file per page. `h()` for output, `url()` for links, CSRF on every POST via `csrfField()` / `verifyCSRFToken()`.
- Tailwind + Alpine.js via CDN (Alpine powers the mashup picker and copy buttons). Brand classes `tr-600` etc.; mashup pages use purple.

## Local dev
From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, then `/thinkrep/`. Needs local MySQL `bizorca_tools` and `php private_html/bin/migrate.php`.
