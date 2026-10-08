# Bizorca Foundry — CLAUDE.md

## What this is
Engagement management for Jassen's consulting practice ("business systems architecture"). A public landing page and intake application; an admin reviews applications, accepts them into engagements, defines and locks a scope, and runs a kanban board; the client accepts the scope, follows the board, ticks checklist steps, comments, and submits change requests that the admin approves or declines (the "scope creep firewall").

**Live:** https://tools.bizorca.com/foundry/ — one tool on the shared tools platform. Read `../CLAUDE.md` (repo root) first: shared account, shared MySQL, deploy.
**Ported 2026-10-07** from foundry.bizorca.com (SiteGround, its `consulting_*` tables inside the login.bizorca.com database, Bizorca SSO). The original was not a git repo. The live server matched the local copy exactly (plus two stray duplicate files under `src/Views/`).

Business rules (from the original): $1,500/week, 1–12 weeks; budget tiers Under $5K / $5K–$10K / $10K–$18K; not for businesses under $250K revenue; scope must be locked and accepted before the board is active; cards added after the lock count as scope creep (`is_original_scope = 0`). `company_type` is a legacy column, no longer collected.

## Layout
```
public/                 -> public_html/foundry/
  _bootstrap.php        finds FD_ROOT (repo or private_html/foundry), loads includes/config.php
  index.php             front controller: security headers, every route
includes/config.php     -> private_html/foundry/   shared core, FD_BASE, FD_CLEAN_URLS, FD_TIMEZONE, autoloader
src/                    -> private_html/foundry/src/   namespace Bizorca\Consulting\ (the original's)
  Core/Url.php          every URL built and read here
  Core/Router.php       matches Url::path() against the route table ({id} params)
  Core/Database.php     static helpers over tl_db()
  Core/helpers.php      render(), u(), redirect(), require_auth/admin(), fd_user_is_admin(), fd_name_cols()
  Auth/Session.php      Foundry's view of the shared session (user(), flash)
  Auth/CSRF.php
  Controllers/ Models/ Views/
migrations/001_foundry.sql
bin/import-mysqldump.php
```

## Routing
This Cloudways app has no try_files fallback, so routes travel in the query string: `/foundry/?r=/admin/engagements/5`. **Every** link, form action and redirect goes through `u('/path')` (views) or `redirect('/path')` (controllers), both built on `Url::to()`. To switch to clean URLs once Cloudways adds `try_files $uri $uri/ /foundry/index.php?$args`, set `FD_CLEAN_URLS = true` in `includes/config.php`; nothing else changes. Same design as Dispatch.

`redirect()` treats `/account/...` and absolute URLs as already final; any other path starting with `/` is an app path.

## Accounts and roles
- Everyone is a shared tools account (`users`). Foundry's `consulting_users` (a mirror of SSO users) is gone, as are its SSO client and callback. Old `/login`, `/sso/redirect`, `/sso/callback` redirect to `/account/login.php` and back to the client dashboard; `GET /logout` goes to the shared confirm page; the dashboards' `POST /logout` (CSRF) signs out of every tool.
- **Admin** = site owner (`users.is_admin`) or a row in `fd_admins`. The original's `is_admin` mirrored login.bizorca.com's flag; it is imported as an `fd_admins` row, never as the site-wide flag.
- **Clients** are ordinary accounts. Accepting an application whose email has no account creates one with an unusable password (`User::createForClient`); the engagement page tells the admin to send the client to `/account/forgot.php`. The original tried to insert `sso_id = NULL` into a NOT NULL column here: a fatal error.
- Views print first/last names; the shared account has one `name`, split at the first space in `Session::user()` and in SQL via `fd_name_cols()`.
- Session keys are `fd_csrf` and `fd_flash` (one session for every tool). The session is lazy: the landing page and thank-you page set no cookie; the apply form does (it carries a CSRF token).

## Database
`fd_` tables, same columns as the original. User columns are signed `INT` → `users(id)`. Delete behaviour is the original's: an engagement or change request blocks deleting its client's account; reviewer/creator columns SET NULL; comments go with their author. PHP and MySQL both run on `America/Los_Angeles` (`FD_TIMEZONE`; `Database::pdo()` sets the session offset), so `date()` and `CURRENT_TIMESTAMP` agree.

## Data import (done 2026-10-07)
`bin/import-mysqldump.php <dump> [--dry-run]`: stages the eleven `consulting_*` tables as `fdimp_*` in the same database (the Cloudways user cannot create a database), copies into `fd_` in one transaction (users matched by email; no passwords existed, so new accounts must use Forgot your password; applications whose dropdown answers are not real choices are skipped unless an engagement came from them), always drops staging, refuses if any `fd_` table has rows. The live data was one bot application (random strings, "Select..." in every dropdown), so the live import brought in nothing.

## Fixed during the port (all reproduced on the original)
1. Accepting an applicant with no account was a fatal error (`sso_id` NULL into NOT NULL).
2. `toggleStep` toggled any step id: a client could tick steps on another client's board through their own card URL.
3. `moveCard` accepted any column id: an admin could move a card into another engagement's column, after which it vanished from both boards. `createCard` likewise; and the scope item now has to belong to the engagement.
4. `POST /boards/{id}/reorder` (not wired to any UI yet) had no CSRF check and accepted any column. It now needs `X-CSRF-Token` (or `_csrf` in the JSON body) and a column on that board.
5. An empty comment redirected to the literal URL `back`.
6. The home page's "Client Login" linked to `/login`, which had no route.
7. The apply form only checked "not empty", so a bot posting "Select..." was accepted. Dropdown answers are now checked against `Application::OPTIONS`, which the form also renders from.

## Local dev
From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, then http://127.0.0.1:8080/foundry/. Needs local MySQL `bizorca_tools` with migrations applied (`php private_html/bin/migrate.php`).

## Secrets
None of Foundry's own remain: the SSO secret, session secret and SMTP settings are unused (Foundry sends no mail). The original CLAUDE.md contained the login.bizorca.com database password and an SSH key passphrase in plain text; neither is carried here.
