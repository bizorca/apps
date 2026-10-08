# Burn Rate — CLAUDE.md

## What this is
A turn-based PHP game: inherit $1 billion and try to go broke. Bankruptcy is the win; the Hall of Shame ranks the fastest. Each finished game (bankrupt, or 611 turns still rich) moves the account up one membership tier, and tiers unlock content (player slots, vanity focus, premium properties and toys, Gold Digger-only investments).

**Live:** https://tools.bizorca.com/burnrate/ — one tool on the shared tools platform. Read `../CLAUDE.md` (the repo root) first: shared account, shared MySQL, deploy.
**Ported 2026-10-07** from burnrate.bizorca.com (SiteGround, its own MySQL database, Bizorca SSO), whose real app was the git repo `deprecated/burnrate/v2` (github.com/bizorca/burnrate). Everything else in `deprecated/burnrate/` was legacy and was not ported.

## How it sits on the platform
- **Layout:** `public/` deploys to `public_html/burnrate/`; `includes/`, `src/`, `migrations/`, `bin/`, `docs/` to `private_html/burnrate/`. `public/_bootstrap.php` defines `BR_ROOT` and loads `includes/config.php`, which loads the shared core and registers the `App\` autoloader (the original's namespace, kept).
- **Routing:** front controller `public/index.php`, routes as `/burnrate/?r=/game?tab=stocks`. Every link goes through `url()` (defined in `includes/config.php`) → `App\Core\Url`. `BR_CLEAN_URLS = true` switches to clean URLs once Cloudways adds a `try_files` fallback to `/burnrate/index.php`; test `/burnrate/game` first. GET forms need a hidden `r` in query mode (see `Views/admin/game_logs.php`).
- **Auth:** the shared tools account. `AuthMiddleware`/`GameMiddleware` call `tl_require_login()`; the old `/login`, `/register`, `/sso/callback` and `/logout` routes redirect to `/account/*`. There is no guest play: the marketing pages are public, the game needs an account (same as the original, which required SSO).
- **Owners:** `br_owners` (OwnerID = `users.id`) holds MemberLevel, RoundsCompleted, dates and `IsAdmin`. `App::owner()` creates the row on first visit; `App::memberLevel()` reads it every request (the original copied it into the session at login, so a tier earned mid-session didn't show until re-login). Email and name come from `users`; `Owner` splits `users.name` into FirstName/LastName for the views.
- **Admin:** `users.is_admin` OR `br_owners.IsAdmin` (`App::isAdmin()`). Not the tier: see "Fixed during the port".
- **Session:** the shared lazy session, keys prefixed `br_` (`player_id`, `player_name`, `game_message`, flash, `csrf_token`). Marketing pages set no cookie; the CSRF token is only minted for signed-in visitors.
- **Database:** `App\Core\Database` wraps `tl_db()` and fetches objects explicitly (`PDO::FETCH_OBJ`) without changing the shared handle's default; ints bind as ints. MySQL 8.4.
- **Secrets:** none required. `BR_ORIGIN` (default https://tools.bizorca.com) and optional `BR_STRIPE_PUBLIC_KEY` / `BR_STRIPE_SECRET_KEY` / `BR_STRIPE_WEBHOOK_SECRET` via `tl_env()`; no checkout code exists, so Stripe keys are unused. No mail, no cron.

## The per-player tables became shared tables
The original created six tables **per player** at game start — `Bank{id}`, `RE{id}`, `Stocks{id}` (toys), `StockData{id}` (toy value history), `Businesses{id}` (investments), `REMarketing{id}` (unused) — and dropped them on delete. They are now `br_bank`, `br_re`, `br_stocks`, `br_stockdata`, `br_businesses`, `br_remarketing`, each with a `PlayerID` column, an index led by it, and `ON DELETE CASCADE` from `br_players` (which cascades from `users`). Column names and types are unchanged, unsigned DECIMALs included.
- Every model query filters `PlayerID = {$playerId}` (the parameter is `int`-typed, so the interpolation is safe) and every update/delete adds `AND PlayerID = ?`.
- Ids are global now, not per-player sequences. Ordering by them within a player is unchanged (the balance is still "last row by BankID").
- Other tables: `br_players`, `br_gamelog` (now cascades; the original left orphans), `br_badinvestments` (the catalog, seeded by `002`), `br_site_settings`.

## Data import (done once; see bin/import-mysqldump.php)
Stages the mysqldump as `brimp_*` in the same database (case-twin names like `SiteSettings`/`site_settings` get a suffix), then one transaction: owners → shared users by email (bcrypt kept; SSO-only owners get an unusable hash → "Forgot your password?"), `br_owners`, players with their ids, per-player rows with their ids (stops if two players' ids would collide), game log, settings (Stripe keys skipped), catalog check. Refuses if `br_owners`/`br_players` hold rows. Live had 2 owners, 1 player (Piggy, turn 21), 456 ledger rows, 12 properties, 11 toys, 38 price points, 21 log rows; the import reproduced every row exactly.

The 15-row catalog in `002` is what production had. The original repo's `database/seeds/bad_investments.sql` (50 more) was never loaded on production and isn't here.

## Local dev
From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, then http://127.0.0.1:8080/burnrate/. Needs local MySQL (`bizorca_tools`) with `php private_html/bin/migrate.php` applied.

## Fixed during the port
- **Admin by playing.** Admin was `MemberLevel >= 65536`, and every finished game raises MemberLevel one tier, so five games made any player an admin (settings, user creation). Admin is now `users.is_admin` / `br_owners.IsAdmin`.
- **Tier gate bypass.** `MinTier` was only applied when picking a random deal; posting any `deal_id` to `/game/biz/start` bought a Gold Digger-only investment on a Freeloader account. `InvestmentService::makeInvestment()` now checks it.
- **CSRF on action links.** `/game/do/{code}` (party, airlift, browse, entourage upgrades) are GET links that spend money; any site could trigger them. They now carry the session token (`?t=`), checked in `GameController::doAction()`.
- **Admin "create user" was a fatal error** (it called an `Owner::hashPassword()` that never existed). It now creates a shared account (or attaches an existing one) plus `br_owners` with the chosen tier.
- **Dead links:** every "Start playing"/"Start Freeloading" CTA went to `/register`, and the game/admin middleware redirected to `/login`; neither route existed (404).
- Flash messages were echoed raw (a player name like `<b>x</b>` rendered as HTML); now escaped.
- `display_errors` was on in production; errors now go to the log. Security headers added in PHP.
- Stripe keys were saved in plain text in `site_settings` from the admin form; they come from `.env.php` now and the form fields are gone.
- The unverified-email banner was dropped: the shared account has no verification step.

## Game architecture (unchanged from the original)
- Turn processing: `Services/GameEngine::processTurn()` — vanity income/expenses, economy, crises, properties, toys, investments, new listings, bankruptcy check, game log. Viewing `/game` processes any unprocessed turns; actions advance `Turn`.
- Action codes: 1 browse properties, 2 browse toys, 101 random bad investment (MySQL `ORDER BY RAND()`), 201 party, 301 airlift friends, 401–414 entourage upgrades, 501 vanity focus (tier 1+).
- `docs/` holds the original's design notes, written for "Learn To Be Rich", the game Burn Rate was forked from; mechanics there are pre-pivot.
- Randomness is PHP's `rand()`/`array_rand()` (seedable) except action 101's SQL `RAND()`.
