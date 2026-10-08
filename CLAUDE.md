# tools.bizorca.com — Project Instructions

## Overview
**Bizorca Tools**: tools for business owners and coaches, all on one domain with **one shared login and one MySQL database**. Each tool lives at `tools.bizorca.com/<tool>/`. The landing page (`public_html/index.html`) lists the tools, has a "Request a tool" form, and carries the services pitch and TidyCal booking.

Formerly apps.bizorca.com (static portfolio on SiteGround); moved to Cloudways and renamed 2026-10-07. apps.bizorca.com redirects here via a Cloudflare redirect rule (wildcard `https://apps.bizorca.com/*` → `https://tools.bizorca.com/`).

## Porting a tool in
Each tool gets **its own folder at the repo root holding everything it needs** (`proforma/` is the reference). The original project directory is deleted once the port is verified, so nothing may be left behind in it. Read `proforma/CLAUDE.md` for the worked example; the recipe:

1. Copy the app into `<tool>/`: `public/` (web files), `includes/`, `templates/`, `migrations/`, `bin/`.
2. Add `<tool>/public/_bootstrap.php` (copy Proforma's): finds the tool's private root in both layouts and loads its config, which loads the shared core.
3. Prefix every table (`pf_` for Proforma), point user ids at the shared `users` table, write `<tool>/migrations/001_<tool>.sql`. Drop the tool's own users table, login/register/logout pages and any Bizorca SSO client.
4. Base path: every link and redirect goes under `/<tool>`. Session keys get the tool prefix (one session is shared by every tool).
5. Secrets move out of committed config into `private_html/.env.php` (`tl_env()`), and `private_html/.env.example.php` lists them.
6. Add the tool to `TOOLS` in `deploy.sh`, link its card on the landing page.
7. Write an import script if it has users/data (`proforma/bin/import-sqlite.php`), test locally against a copy of the live data, compare output against the original, then import on the server.
8. Cut over: the old subdomain is simply retired. No redirect is added (decided 2026-10-07: no SEO value and no real traffic). Tell the tool's few real users where it moved instead.

## Layout (repo mirrors the server)
```
public_html/                 -> public_html/            index.html, request.php, account/
private_html/                -> private_html/           shared core (never web-served)
  includes/bootstrap.php     session config, Cache-Control, loads db + auth
  includes/db.php            tl_env(), tl_db()  (MySQL, native prepares, UTC)
  includes/auth.php          lazy-session shared account: tl_user(), tl_require_login(), tl_login(), tl_register(), resets, CSRF
  includes/mailer.php        tl_mail() via SMTP2GO; logs to data/mail.log with no key
  includes/chrome.php        page frame for /account pages
  migrations/                shared schema (users, password_resets)
  bin/migrate.php            applies core + every <tool>/migrations, tracked in schema_migrations
  .env.php                   SERVER-ONLY secrets, mode 640, gitignored (local one points at local MySQL)
  data/                      SERVER-ONLY: tool-requests.jsonl, mail.log, ratelimit.json
<tool>/public/               -> public_html/<tool>/
<tool>/{includes,...}        -> private_html/<tool>/
dev-router.php               local only: php -S 127.0.0.1:8080 dev-router.php
```
Everything shared is `tl_`-prefixed so it never collides with a tool's own helpers (Proforma has its own `h()`, `csrf()`, `redirect()`).

## Accounts
Adapted from financialhypnosis.com's shared account (`hypnosis/website/ACCOUNTS.md`). One `users` table, session cookie `tools_session` at path `/`, so signing in once covers every tool. **The session is lazy**: `tl_user()` never creates one, so anonymous visitors get no cookie; only sign-in, registration and form CSRF tokens create a session. Pages: `/account/login.php`, `register.php`, `logout.php` (POST + CSRF; a GET shows a confirm button), `forgot.php`, `reset.php`, `settings.php`. URLs end in `.php` because there are no rewrites (below). `tl_safe_next()` guards every `?next=` redirect.

## Billing (membership)
Ported from billing.bizorca.com 2026-10-08 as **one membership** ($33/mo, $330/yr, live Stripe prices) that unlocks every paid tool. There is no per-tool plan matrix.
- **Every tool is free right now**: `TL_BILLING_ENFORCE` is false and `TL_PAID_TOOLS` is empty (both in the server `.env.php`). A tool is gated only when enforcement is on AND it is listed.
- Tools ask `tl_has_access('<tool>')` / `tl_require_access('<tool>')` (`private_html/includes/billing.php`). Site admins always pass. No tool calls it yet; wire it into a tool's own access seam when that tool becomes paid.
- Membership = a `tl_subscriptions` row in active/trialing/past_due/unpaid (Stripe's own status) or an unexpired `tl_comps` row. `users.is_paid` is a synced cache.
- Pages: `/account/billing.php` (subscribe via Stripe Checkout, manage via the Stripe portal), `/account/billing-admin.php` (site admins: subscribers, comps, MRR), `/account/stripe-webhook.php`.
- Stripe over REST with curl, `Stripe-Version: 2024-06-20`. Webhook endpoint `we_1UO8i1KQmTeMaePs7io89zSd` (checkout.session.completed, customer.subscription.created/updated/deleted), registered by API 2026-10-08; signatures verified with a 5-minute window, each event id handled once (`tl_stripe_events`).
- The old billing.bizorca.com webhook endpoint still exists on the Stripe account and points at a dead host; delete it in the Stripe dashboard once nothing needs it.

## Request-a-tool form
`public_html/request.php`: same-origin check instead of a CSRF token (the landing page is static), honeypot + 3s minimum + 5/hour per IP (CF-Connecting-IP). Every request is appended to `private_html/data/tool-requests.jsonl` before mailing, then mailed to jassen@bizorca.com with Reply-To set to the requester.

## Hosting
- **Cloudways**, server 143.198.64.127, app folder **`qukjzcxeas`** (= MySQL db and user). The `phpstack-1676077-6715614.cloudwaysapps.com` test URL stopped working over HTTPS once the Cloudflare Origin CA cert was installed.
- nginx → PHP-FPM directly: **`.htaccess` is ignored**, so no rewrites, no `php_value`, no `Require all denied`. Anything under `public_html/` is reachable; CLI scripts live in `private_html/`.
- **Varnish** caches at the origin. Pages that load the core send `Cache-Control: private, no-store`; `deploy.sh` PURGEs the static landing page after each deploy.
- Cloudflare proxied, SSL Full (strict), Origin CA cert on the app.
- Repo: github.com/bizorca/apps

## Deploy
```bash
./deploy.sh --dry-run   # what would change
./deploy.sh             # committed HEAD only; rsync, run migrations, purge Varnish
```
SSH is `ssh cloudways-bizorca` (see the Archipelago root CLAUDE.md). The preflight refuses to deploy if `public_html/` on the server holds a directory that is not a tool, a `SUBAPPS` entry or in the repo, because `--delete` would remove it. `.env.php` and `data/` are never touched.

## TidyCal
Booking link: https://tidycal.com/jassen/app-consult

## Editing the landing page
`public_html/index.html` directly: Tailwind via CDN, no build step. Commit, then `./deploy.sh`.
