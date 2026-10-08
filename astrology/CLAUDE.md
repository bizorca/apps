# Ba Zi Astrology ("Meridian") — tools.bizorca.com/astrology

Dual-system astrology: Chinese Ba Zi / Four Pillars (with TCM) and Western sun
signs, plus the Star Seed lineage quiz, AI-generated readings and weekly
forecasts, five element meditations, and the "Work with Jillian" offer ladder
(the app is the free lead magnet for Jillian Ribbons' readings; prices on that
page are PLACEHOLDERS). A JSON API serves the Meridian iOS companion app.

**Ported 2026-10-08** from the original at `Archipelago/deprecated/astrology`
(astrology.bizorca.com, frozen 2026-10-01, no DNS since). Read `../CLAUDE.md`
first for the platform: shared account, shared MySQL, deploy. This is NOT the
financialhypnosis.com Five Element Money Wheel, which was rebuilt from the same
code and is a different product now.

## How it sits on the platform

- **Layout:** `public/` → `public_html/astrology/`; `includes/`, `templates/`,
  `migrations/`, `bin/` → `private_html/astrology/`. `public/_bootstrap.php`
  defines `AS_ROOT` and loads `includes/config.php`, which loads the shared core.
  Every page's first line is `require __DIR__ . '/_bootstrap.php';`
  (`dirname(__DIR__)` / `dirname(__DIR__, 2)` from `api/` and `api/*/`).
- **URLs:** page-per-file, real `.php` files. Every internal link goes through
  `url()`, which prefixes `BASE_PATH` (`/astrology`). Keep using it.
- **Accounts:** the shared tools account. Sign-in links use
  `authUrl('login'|'register', $redirect)`, which goes to `/account/...` and
  comes back through `auth-return.php`. That page claims what the visitor did
  before signing in: a pending star seed quiz (then shows its results) and an
  anonymous chart (which becomes their primary profile). The original only
  claimed the chart in its SSO callback, so password sign-ups lost it.
  `login.php`, `register.php`, `logout.php`, `forgot-password.php` are now
  one-line redirects for old links; `billing.php` and `stripe-checkout.php`
  redirect to pricing.
- **Per-person fields** that lived on the original `users` row
  (`primary_profile_id`, `timezone`) are in `as_members`, created on first visit
  or API login. `getCurrentUser()` returns the shared row merged with them, so
  pages read `$user['primary_profile_id']` as before; write it with
  `setPrimaryProfile()`. Name, email and password are edited on
  `/account/settings.php`; `settings.php` here keeps only the timezone.
- **Admin:** `isAdmin()` = site owner (`users.is_admin`) or a row in
  `as_admins`. The original's `ADMIN_EMAIL` account was imported into
  `as_admins`, not the site-wide flag. "Delete member" is now **Remove from
  Astrology**: it deletes the person's charts, readings, results, leads, tokens
  and `as_members` row, never the shared account (`removeAstrologyMember()`).
  Members lists show people with an `as_members` row, not every tools account.
- **Access:** `userHasAccess()` / `getEffectivePlan()` / `apiHasAccess()` still
  return true / 'premium' for everyone (free early access). They are the single
  seam if a paid plan ever returns. SSO entitlements, Stripe, `subscriptions`
  and Composer (`stripe/stripe-php` was its only package) are gone;
  `hasActiveSubscription()` returns false.
- **Sessions are lazy.** A GET resumes an existing session but never starts one,
  so reading the site sets no cookie (the original set one on every page). A
  POST may create one. Because an anonymous form has no session to hold a CSRF
  token, `csrfField()` renders an empty token when there is no session, and
  `verifyCSRFToken()` then accepts the POST only from this site (Origin, else
  Referer). With a session, the token is required as before. Session keys are
  `as_`-prefixed (`as_csrf_token`, `as_flash`, `as_pending_starseed`,
  `as_pending_profile_id`, `as_wf_generated_<week>`).
- **Database clock:** `getDB()` sets the session to America/Chicago's current
  offset. The live site's MySQL ran in America/Chicago with PHP in UTC (checked
  2026-10-08), so its pages showed TIMESTAMPs in Chicago time and its DATETIME
  columns (`as_api_tokens`, `as_weekly_forecasts.generated_at`) hold Chicago
  wall time. Neither the local nor the Cloudways MySQL has zone tables, so a
  named zone is impossible; the per-request offset means a timestamp from the
  other side of daylight saving displays one hour off from what live showed.
- **Headers** that came from `.htaccess` (ignored on this server) are sent from
  `config.php`; the shared core adds `Cache-Control: private, no-store`.
- **Mail:** only the "Request a spot" lead notification remains
  (`includes/mail.php`), sent as plain text through the shared `tl_mail()` with
  Reply-To set to the person. Recipient is `AS_LEAD_EMAIL` (default
  `jassen.bowman@gmail.com`, the original's hard-coded placeholder until Jillian
  takes over follow-up).

## JSON API (Meridian iOS)

Base URL: **`https://tools.bizorca.com/astrology/api/`**, and every endpoint
now ends in `.php`. The original's `.htaccess` rewrote `/api/user` to
`/api/user.php`; nginx here does no rewriting, so the app must call the file:

| Method | Path |
|---|---|
| POST | `auth/login.php` (email, password) → token |
| POST | `auth/register.php` (name, email, password ≥ 10 chars) → token |
| DELETE | `auth/logout.php` |
| GET | `user.php`, `profile.php`, `reading.php?type=teaser\|full\|western_teaser\|western_full`, `tcm.php`, `starseed.php`, `forecast/weekly.php?week=YYYY-MM-DD`, `forecast/monthly.php?month=YYYY-MM` |
| POST | `profile.php`, `forecast/generate.php` |

Bearer tokens (`as_api_tokens`, 90 days), never cookies; every endpoint defines
`AS_API` before the bootstrap so no session is touched. Login and register work
against the shared users table. Changes from the original: `auth/callback.php`
(SSO) is gone; the register minimum is 10 characters (the shared account's
rule; was 8); `plan` is now `getEffectivePlan()` ('premium' for everyone,
matching the web; the original said 'free' for accounts without an SSO
entitlement even though nothing was gated); the login password is no longer
trimmed (the web never trimmed it, so a password with edge spaces could not log
in on iOS). Response shapes are otherwise identical (compared field by field).

## Claude API

`includes/claude-api.php`, key `ANTHROPIC_API_KEY` (shared `.env.php`), model
`AS_ANTHROPIC_MODEL`, default `claude-sonnet-5` — what the live site ran with;
it generated a reading as late as 2026-10-01. With no key every generator
returns null and the pages show their "not available" states. Generation is
on demand only (dashboard load, reading pages, admin batch pages,
`api/forecast/generate.php`); there is no cron.

## Data import (done once, 2026-10-08)

`bin/import-mysqldump.php <dump.sql> [--dry-run]` stages a mysqldump of the old
database as `asimp_*` tables in the same database, copies into `as_` tables in
one transaction (users matched by email; new accounts keep their bcrypt hash;
ids kept; anonymous charts come across with their session ids; expired API
tokens skipped; meditations verified against the seed), and always drops the
staging tables. It refuses to run if any people table already has rows.

Migrations: `001_astrology.sql` (schema), `002_astrology_content.sql` (the five
meditations and app settings from the live database, original ids).

## Astrology system (unchanged from the original)

- **Ba Zi:** `includes/astrology.php`. Year/month/day/hour pillars with graceful
  degradation, Li Chun year boundary (~Feb 4), Day Master, element balance; stored
  as `four_pillars_json` on `as_profiles`, lazily backfilled.
- **Western:** `includes/western-astrology.php`. Sun sign from month + day,
  aspect-based compatibility.
- **Readings:** `as_readings.reading_type` is `teaser|full|western_teaser|western_full`
  and the timestamp is `created_at` (the API maps them to `type` / `generated_at`).
- **Star seed quiz:** all data inline in `starseed-quiz.php`; one
  `as_starseed_results` row per person, replaced on retake; a missing AI reading
  shows a "Generate My Reading" retry.

## Fixed during the port (all reproduced on the original)

- "Delete member" crashed (`Unknown column 'user_id'` on `password_resets`)
  **after** deleting the person's readings and charts, with no transaction: the
  data was gone and the account stayed.
- The member page read `$member['email_verified']`, which does not exist, and
  printed a PHP warning into the page.
- Signing up or logging in with a password never claimed the chart made just
  before; only the SSO callback did.

## Local dev

From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, then
http://127.0.0.1:8080/astrology/. Needs the local `bizorca_tools` database with
the migrations applied.
