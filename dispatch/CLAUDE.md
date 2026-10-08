# Dispatch — Claude Instructions

## What this project is
Event promotion workflow manager. Users create campaigns (events), select publicity venues, and the schedule engine calculates every submission deadline. Includes a flyer tracker, a document library, and an admin-curated venue library with a suggest-and-triage queue.

**Live:** https://tools.bizorca.com/dispatch/ — one tool on the shared tools platform. Read `../CLAUDE.md` (the repo root) first: shared account, shared MySQL, deploy.
**Ported 2026-10-07** from dispatch.bizorca.com (SiteGround, own users table, Bizorca SSO, Composer). The old GitHub history stays with the original `dispatch/` repo.

## How it sits on the platform
- **Layout:** `public/` (just `index.php` and `_bootstrap.php`) deploys to `public_html/dispatch/`; `includes/`, `src/`, `views/`, `migrations/`, `bin/` deploy to `private_html/dispatch/`, outside the web root. `public/_bootstrap.php` is the only file that knows the two layouts apart: it defines `DP_ROOT` and loads `includes/config.php`, which loads the shared core and registers the `Dispatch\` autoloader (Composer is gone; its only package was phpdotenv).
- **Routing is query-string based.** This Cloudways app has no nginx `try_files` fallback, so `/dispatch/campaigns/5` is a 404 before PHP runs. Every route travels as `/dispatch/?r=/campaigns/5`. `src/Core/Url.php` owns this:
  - `Url::prefix()` is what views get as `$_base`, so every `<?= $_base ?>/campaigns/<?= $id ?>` link works in either mode.
  - `Url::to()` builds a URL; `Response::redirect('/path')` uses it.
  - `Url::path()` is the current route; a path's own `?a=b` rides inside `r` and is folded back into `$_GET` (`/dispatch/?r=/campaigns/calendar?month=2026-11` works).
  - **To switch to clean URLs**: get Cloudways support to add a fallback to `/dispatch/index.php` for `/dispatch/*`, test `/dispatch/campaigns`, then set `DP_CLEAN_URLS = true` in `includes/config.php`. Nothing else changes.
  - Never write a relative `href="?x=1"`: it drops `r`. Always `<?= $_base ?>/route?x=1`.
- **Auth:** the shared tools account. Sign-in, registration, password reset and logout pages are `/account/*`; the old `/login`, `/register`, `/forgot-password`, `/sso/login` routes redirect there and come back to the dashboard. The sidebar's Log out still posts to `/logout` (CSRF-checked), which calls `tl_logout()` and signs out of every tool.
- **Roles** (`user` / `admin` / `sysop`) live in `dp_profiles`, keyed on the shared user id, with the digest settings. A site admin (`users.is_admin`) is always a Dispatch sysop. `Auth::user()` merges the shared row with the profile, and creates the profile (role `user`, daily digest, 3 days) on a signed-in account's first visit. Roles are read live on every request (the original cached them in the session until re-login, so demotions didn't take effect).
- **"Dispatch users"** = accounts with a `dp_profiles` row. The admin user list, the assignment dropdown and the digest are limited to them, never every tools account.
- **Name, email, password** belong to the shared account (`/account/settings.php`). The profile page only edits digest settings.
- **Session:** the shared lazy session. `Session` prefixes every key `dp_` and never creates a session to read; `View` only issues a CSRF token to signed-in pages, so marketing pages set no cookie.
- **Database:** `Database` wraps `tl_db()` and moves the MySQL session to `DP_TIMEZONE` (America/Los_Angeles) as an offset; PHP uses the same zone. The original ran PHP in UTC and MySQL in America/Chicago, so CURDATE()-based "overdue" and PHP's "today" disagreed for hours every night. Tables are `dp_`-prefixed; schema in `migrations/001_dispatch.sql`.
- **Documents** are stored in `private_html/data/dispatch/documents/` (`DP_UPLOADS`, server-only, never touched by deploy) and served only through `/documents/{id}/download` after an access check.
- **Email:** Dispatch keeps its own HTML sender (`src/Core/Email.php`) for the digest, using the shared `SMTP2GO_API_KEY`; from `Dispatch <jassen@bizorca.com>`. Links in emails use `Url::absolute()` (`DP_ORIGIN`, default https://tools.bizorca.com).
- **Security headers** are sent from `public/index.php` (X-Frame-Options, nosniff, Referrer-Policy). No CSP: the views use Tailwind/Google Fonts CDNs and inline scripts.

## Local dev
From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, then http://127.0.0.1:8080/dispatch/. Needs local MySQL `bizorca_tools` with migrations applied (`php private_html/bin/migrate.php`).

## Cron
```
php /home/master/applications/<app>/private_html/dispatch/bin/digest.php            # daily, e.g. 0 14 * * * (07:00 Pacific)
php .../dispatch/bin/digest.php --dry-run                                           # who would get one, sends nothing
```
Weekly-preference users are included on Mondays (Pacific). Each user's `remind_days_before` sets the look-ahead. Only Dispatch users are considered.

## Data import (one time)
`bin/import-mysqldump.php <dump.sql> [--dry-run]` stages a mysqldump of the old database as `dpimp_*` tables in the same database (the Cloudways DB user cannot create databases), copies into `dp_` tables in one transaction, and always drops staging. Users matched into shared `users` by email (bcrypt hash kept; accounts first created through SSO had random passwords and need "Forgot your password?"); an unverified account that owns nothing is skipped. Old `role` → `dp_profiles.role`; the old `is_admin` (an SSO mirror) is not carried into the site-wide flag. Copy `storage/uploads/documents/*` into `DP_UPLOADS` first; the script reports missing files. Refuses if any `dp_` table has rows.

## Adding a route
`src/Core/App.php::registerRoutes()`:
```php
$r->get('/my-path', [MyController::class, 'myMethod'], ['auth']);
$r->post('/my-path', [MyController::class, 'store'], ['auth']);
```
Middleware: `auth`, `admin`, `sysop`. Register static paths before `{param}` routes (`/campaigns/calendar` before `/campaigns/{id}`).

## Adding a page
1. Controller method in `src/Controllers/`
2. View in `views/section/page.php` (set `$title`)
3. Route in `src/Core/App.php`
4. `$this->render('section/page', [...])` — defaults to the `dashboard` layout. Layouts: `app` (marketing), `dashboard` (signed in), `print`.

## Database tables
`dp_profiles` · `dp_venues` · `dp_venue_submissions` · `dp_campaigns` · `dp_campaign_venues` · `dp_action_items` · `dp_flyer_locations` · `dp_notifications` · `dp_documents` (plus the shared `users`).

Foreign keys to `users`: what a person owns cascades with their account; references to someone else (assignee, reviewer, venue creator/suggester) are set NULL.

### Action item statuses
`pending` → `submitted` → `confirmed` / `rejected` / `no_response`; also `complete` and `skipped`. Overdue is derived at query time (`status='pending' AND due_date < CURDATE()`), never stored.

## Schedule Engine
`due_date = event_date - lead_time_days - buffer_days` (or from `target_publication_date` when set on an item). `src/Services/ScheduleEngine::generate()` runs after campaign create/edit only when the event date or venue list changed, and preserves `complete`, `confirmed`, `skipped`, `rejected` items. Recurrence: `weekly`, `monthly`, `monthly_weekday` (JSON `{"day":3,"weeks":[1,3]}`, PHP `N` day numbers, week 5 = last), `daily`.

## Forms & security
- Every POST form includes `<input type="hidden" name="_csrf" value="<?= \Dispatch\Core\View::e($_csrf) ?>">`; controllers check `Auth::verifyCsrf()` (returns bool).
- Ownership: verify `$resource['user_id'] === Auth::userId()` before reading or mutating; attaching a document to a campaign checks the campaign's owner too.
- `Response::redirectBack()` only follows a referer on this host under `/dispatch/`.

## Fixed during the port (all present on the live dispatch.bizorca.com)
- The Venue Library's group headings were blank: the view destructured `['icon' => …, 'label' => …]` by position (warnings hidden by `display_errors=Off`).
- Any user could attach a document to someone else's campaign (posted `campaign_id` never ownership-checked); it then showed on the owner's campaign page.
- Editing a campaign with an invalid date was a 500 (`store()` validated the date, `update()` did not).
- Downloads were saved as `Intro%20to%20timebanking.docx` (percent-encoded name in `filename=`).
- Role changes didn't apply until the affected user signed in again (role cached in session).
- Rendering a document on a campaign page raised an undefined `campaign_name` warning.
- Uploaded files kept whatever extension the client sent.

## Design conventions
- Cards: `bg-white rounded-2xl border border-slate-200`
- Primary buttons: `bg-indigo-600 text-white rounded-xl hover:bg-indigo-700`
- Status badges: red=overdue/rejected, amber=pending/submitted, emerald=complete/confirmed, slate=skipped, violet=assigned
- Flash messages via `Session::setFlash('success'|'error'|'warning'|'info', $msg)`
