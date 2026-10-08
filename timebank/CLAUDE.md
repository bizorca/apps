# TimeBank — Project Context for Claude

## What this is
A multi-community timebank: neighbors exchange services and pay in time credits (hours) instead of money. Each community (a "tenant") has its own members, offers and requests, ledger, messages, groups, announcements, endorsements, reports, email templates and settings.

**Live:** https://tools.bizorca.com/timebank/ — one tool on the shared tools platform. Read `../CLAUDE.md` (repo root) first for the shared account, shared MySQL and deploy.
**Ported 2026-10-08** from `deprecated/timebank` (timebank.bizorca.com, SiteGround, never a git repo). The old subdomain is retired, not redirected.

The original had never worked for a signed-in member (see "Original bugs"), and the live database held only the install seed. So this port is effectively the first working version: parity was checked against a copy of the original with its systemic crashes patched, and most pages differ because the original rendered them empty.

## How it sits on the platform
- **Layout:** `public/` → `public_html/timebank/` (only `index.php`, `_bootstrap.php` and `assets/css/custom.css`); `includes/`, `src/`, `migrations/`, `bin/` → `private_html/timebank/`. `public/_bootstrap.php` defines `TM_ROOT` and loads `includes/config.php`, which loads the shared core and registers the `TimeBank\` autoloader (no Composer).
- **Routing:** front controller, query routes. A route's first segment is the community slug: `/timebank/?r=/demo/offers/5`. `src/Core/Url.php` is the only place that builds or reads URLs; views use `url('/offers/5')` (prefixes the current community), `url_abs()` for email, `community_url($slug, $path)` from the landing page, and `route_field('/members')` inside every GET form (a browser drops `?r=` from a GET form's action). Clean URLs are one constant away: set `TM_CLEAN_URLS = true` once Cloudways adds `try_files $uri $uri/ /timebank/index.php?$args`.
- **Site-level routes** (no community): `/` (landing page, lists communities) and `POST /payments/stripe/webhook`. Reserved first segments: `''`, `assets`, `payments`.
- **Tenancy:** `Tenant::resolve()` reads the slug from the route; every model is constructed with the tenant id and `BaseModel` scopes every query by it. Ids posted in forms are checked against the community (`BaseController::communityMember()`, category and group lookups).
- **Clock:** inside a community, `App::useCommunityClock()` sets PHP's and MySQL's timezone to the community's `timezone` setting, so displayed times, "today" and `CURDATE()` agree and are local. Outside one, everything is UTC.

## Accounts and membership
- Who you are is the shared tools account (`tl_user()`); sign-in, registration and password resets live at `/account/`. `src/Core/Auth.php` wraps it.
- A `tm_members` row is a **membership**: one person's profile, balance and role in one community, tied to `users` by `user_id` (unique per tenant). One account can belong to several communities. `email` on the membership is a copy of the account's email, re-synced on every request.
- **Joining:** `/<slug>/join` (`MembershipController`) creates the membership. Not signed in → sign in or create an account and come back. Communities can require approval (`settings.require_approval`): pending members see a notice until an admin approves. The old `/login`, `/register`, `/logout`, `/forgot-password` URLs hand off to `/account/`.
- **Roles** per membership: `member`, `admin`, `super_admin`. The site owner (`users.is_admin`) counts as a community admin in any community they have joined. Only a super admin may give the admin roles.
- **Admin "Add member"** attaches a membership to the account with that email, creating the account (with no usable password) if needed; that person sets a password with "Forgot your password?".
- **The session is lazy:** anonymous visitors (landing page, join page, unknown community) get no cookie. CSRF tokens and flash messages create the session only when needed.

## The ledger
- Provider earns the hours (balance +), receiver pays (balance −). Hours and balances are `DECIMAL`, unchanged from the original; balance updates use `CAST(? AS DECIMAL(8,2))` so MySQL never does float arithmetic, and every record/cancel runs in one DB transaction (`DB::transaction()`, re-entrant).
- **Recording** (`TransactionController::store`): quarter-hour increments from 0.25 to 24; provider and receiver must be different active members of this community; a non-admin must be one of the parties. One-to-one needs a receiver (nobody can credit themselves from nothing). A class ("one_to_many") is recorded as one ordinary transaction per participant, provider credited by each, so statements and reports need no special cases. "Group project" (many providers) is refused: the form had no way to name the providers.
- **Credits from the community fund** (no receiver): welcome credits on joining (or on approval, if the community requires approval) and admin grants via "Record hours". `Transaction::creditFromCommunityFund()` also lowers `tm_tenants.community_fund_balance`, so **sum of member balances = −fund** for each community. Admin grants do not touch the fund (as in the original).
- **Cancelling** reverses both sides exactly, once; only the recorder or a community admin may cancel.
- `max_balance` / `min_balance` / `allow_negative_balance` are stored in settings but were never enforced by the original, and are not enforced here.

## Uploads
Avatars (2 MB) and offer images (5 MB) live in `private_html/data/timebank/{avatars,offers}/<tenant id>/`, outside the web root and never touched by deploys. Type is checked from file content (JPEG, PNG, GIF, WebP), names are random. Served by `GET /<slug>/media/<kind>/<file>` (`MediaController`) to signed-in members of that community only. Stored paths keep the original shape (`uploads/avatars/<tenant>/<file>`), so imported rows resolve.

## Email
TimeBank sends HTML, so it keeps its own SMTP2GO sender (`src/Core/Mailer.php`) with the shared `SMTP2GO_API_KEY`, from `tools@bizorca.com`. Email templates (per community, editable by admins) are plain text: sent as the text body plus an escaped, line-broken HTML copy. No key → nothing is sent, the attempt is logged.

## Payments (community-fund donations)
Ported as-is but not linked from any page (neither was the original's). Keys are global in `private_html/.env.php`: `TM_STRIPE_SECRET_KEY`, `TM_STRIPE_WEBHOOK_SECRET`, `TM_PAYPAL_CLIENT_ID`, `TM_PAYPAL_CLIENT_SECRET`, `TM_PAYPAL_MODE` (`sandbox`|`live`). With none set every endpoint answers 503. Stripe is called over its REST API (no SDK); the webhook (`https://tools.bizorca.com/timebank/?r=/payments/stripe/webhook`) verifies `Stripe-Signature` itself: HMAC-SHA256 over `timestamp.payload`, 5-minute tolerance, constant-time compare. PayPal orders carry return/cancel URLs (the original set none, so a donor could never come back).

## CLI (bin/, deployed to private_html, 404 over HTTP)
- `php timebank/bin/create-community.php <slug> "<Name>" [admin-email] [--approval] [--welcome=1.00]` — the original had no way to create a community except SQL. Seeds the 13 standard categories and 7 email templates; the admin email must already be a tools account.
- `php timebank/bin/weekly-digest.php [--dry-run]` — weekly digest for every community (new offers and requests). The original had the builder but nothing called it. Cron (Mondays 8am Pacific): `0 15 * * 1 php /home/master/applications/qukjzcxeas/private_html/timebank/bin/weekly-digest.php`
- `php timebank/bin/import-mysqldump.php <dump.sql> [--dry-run]` — one-time import of the old database (below).

## Data import (live, 2026-10-08)
The live database was the install seed only: the `demo` community, 13 categories, 7 email templates and one placeholder super admin (`admin@timebank.bizorca.com`, password unknown) who owned nothing. The importer stages the dump as `tmimp_*` tables in the same database, copies into `tm_` keeping every id, maps members to shared accounts by email (keeping bcrypt hashes), skips `@timebank.bizorca.com` placeholders that own nothing, and then **checksums every shared column of every table, staged against imported**, rolling everything back on any difference (proven by importing into a deliberately lossy column). It refuses to run if `tm_tenants` or `tm_members` has rows. So `demo` arrives with no members; the site owner joins it like anyone (and is its admin by virtue of `users.is_admin`).

## Schema
`migrations/001_timebank.sql`: the original's 15 tables, `tm_`-prefixed (constraint and index names too). Changes: `tm_members` gains `user_id` (FK → `users`, cascade) and loses `password_hash`, `reset_token`, `reset_token_expires`; `tm_tenants` loses the four per-community PayPal/Stripe key columns (never read, and keys do not belong in a table); `password_resets` is gone. Ledger foreign keys (`provider_id`, `receiver_id`, `recorded_by`, `created_by`, `author_id`) keep the original's `ON DELETE RESTRICT`, so deleting a shared account that has transactions is blocked: ledger history is never silently erased.

## Local dev
From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, then http://127.0.0.1:8080/timebank/. Local MySQL `bizorca_tools`; apply `migrations/001_timebank.sql`; create a community with `bin/create-community.php`.

## Original bugs (all fixed in the port)
The original crashed on every signed-in page, so none of these could have been seen in use:
1. `Auth::refreshUser()` filtered on `members.status`, which does not exist: **every signed-in page was a fatal error** (the nav calls it).
2. The router passed the Request plus named route parameters: **every route with an `{id}` or `{slug}` was a fatal error** ("Named parameter overwrites previous argument").
3. Redirects went to `/dashboard` with no community prefix: **sign-in itself 404'd** (the slug "dashboard" is not a community).
4. `Notification::create()` was incompatible with `BaseModel::create()`: fatal whenever notifications loaded (dashboard, recording, messaging).
5. Registration crashed: welcome credits used `provider_id = 0` (FK failure), and had it worked, the new member would have been **debited**.
6. Flash messages: `e()` received an array → every page with a flash was a TypeError; `e()` also rejected every NULL column.
7. Most forms posted to unrouted paths (`/offers/create`, `/requests/create`, `/transactions/record`, `/messages/compose`, `/groups/create`, `/announcements/create`, `/endorsements/create`, `/admin/members/create`, `/admin/categories/create`, email template edit): creating almost anything was a 405. Member approve, category update/toggle and admin group delete had forms but no handler at all.
8. Many views read data the controllers never passed (all rendered empty or zero): offers, requests, member directory, transactions, announcements, inbox, search, admin members list, admin transactions, every report, admin dashboard stats, admin member stats, endorsement form, offer prefill on the record form. Request detail links said "Offer not found".
9. **Ledger:** a member could credit themselves from nothing (no receiver), and could record for any member id of any community; a "class" ignored its participants and minted hours; nothing ran in a DB transaction; `min:0.25` was cast to `min:0`, so a 0-hour transaction passed.
10. **Cross-community:** a member's session counted as signed in to every other community; donation forgive/mark-paid, message recipients, announcement groups, endorsements and offer categories accepted another community's ids.
11. Profile save: the notification checkboxes were read under the wrong names, so **every save switched all email notifications off**; address, ZIP, country and display name were never saved; a digits-only phone number or a ZIP failed validation ("max:30" compared numbers).
12. Smaller: the group message page's view did not exist; adding a category inserted NULL into a NOT NULL column; deleting a category read a field the form never sent; settings never saved "currency name (plural)"; the "Transaction History" report had no view; `groups` (a MySQL 8 reserved word) unquoted broke the admin groups page; avatar/offer image deletion used `ltrim($path, 'uploads/')` (a character set, not a prefix) so files were never removed; image `src` paths were relative and broke under community URLs; old form input was cleared before `old()` could read it; the landing page multiplied each community's hours by its member count; the weekly digest skipped members who had never saved preferences and linked to subdomain URLs that never existed; `Request::ip()` trusted client-supplied headers.

## Security findings on the old server (not fixed there; the site is retired)
- `www/timebank.bizorca.com/public_html/setup.php` is publicly served at the origin (any client sending `Host: timebank.bizorca.com` to SiteGround gets it): it reports PHP version, extensions and whether the database connects. Read-only. `config.php`, `database.sql`, `src/` and `vendor/` are 403'd; `composer.json` is readable.
- The original's `config.php` held the live database password and the **old** SMTP2GO key in plain text. Neither is in this port. Rotate or drop that database when the old site is removed.
