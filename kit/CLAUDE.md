# Advisor Field Kit

## What This Is
A free assessment toolkit for small business advisors, built for the Clallam County
(WA) Economic Development Council. Advisors create client records and work through the
instruments from the business advising framework: regulatory screen, tax exposure flags,
three-metric health check, effective hourly rate, capacity audit, SWOT/TOWS, and a
priority-and-next-action close. The progress record it produces doubles as funder-facing
activity reporting.

**Live:** https://tools.bizorca.com/kit/ — one tool on the shared tools platform. Read
`../CLAUDE.md` (the repo root) first: shared account, shared MySQL, deploy.
**Ported 2026-10-07** from the standalone kit.bizorca.com (SiteGround, SQLite, Bizorca
SSO). The old GitHub repo `bizorca/advisor-field-kit` keeps the pre-port history.
**Source of truth for content:** `Business Advising Framework_ AI Context Document.txt`
in this folder. Every instrument, threshold, and piece of reference copy comes from it.
Read it before changing anything an advisor sees.

Formerly BizBox; renamed Advisor Field Kit 2026-09-23.

## Three rules this app enforces (from the framework)

These are not stylistic preferences. They are why the app exists, and the framework
catalogs an AI breaking all of them on the same case data.

1. **Owner pay is a cost, never a leftover.** Baseline owner pay is a line in the burn
   and a band in the cash-flow reading. Cash that is positive only because the owner
   skipped their own pay reads **Watch**, not Healthy.
2. **Aged receivables are never liquidity.** Receivables past 60 days are collected in
   their own field and are never summed into "available".
3. **Regulation before revenue.** Instruments render in four-layer order, and naming a
   lower-layer priority while regulatory exposure is open triggers a warning
   (`priorityLayerWarning()`). It warns; it never blocks. The judgment stays the advisor's.

The app also never states a Washington or federal rate, threshold, or deadline as current
fact. Regulatory content points at the agency to verify with. Do not add a rate.

## How it sits on the platform
- **Folder layout:** `public/` deploys to `public_html/kit/`; `includes/`, `templates/`,
  `migrations/`, `bin/`, `tests/` and the build files deploy to `private_html/kit/`
  (outside the web root). `public/_bootstrap.php` is the only file that knows the two
  layouts apart: it defines `KIT_ROOT` and `KIT_PUBLIC` and loads `includes/bootstrap.php`.
- **Every page's first require** is `_bootstrap.php` (`dirname(__DIR__) . '/_bootstrap.php'`
  from `a/`). After that, paths are `KIT_ROOT . '/includes/...'`.
- **URLs:** every link goes through `url()`, which prefixes `KIT_BASE` (`/kit`). URLs end in
  `.php`; there are no rewrites.
- **Auth:** the shared tools account (`/account/...`). `includes/auth.php` is a thin wrapper:
  `requireAuth()` → `tl_require_login()`, `currentUser()` maps the shared `users` row
  (`name`, not first/last). `kitLoginUrl()` / `kitRegisterUrl()` send people to the shared
  pages and back to the dashboard. Kit's login, logout, SSO callback and SSO client are
  gone. `startSession()` only resumes a session and `flash()` reads without creating one,
  so anonymous visitors get no cookie.
- **Access:** any tools account can use Kit, the same as kit.bizorca.com, where any
  Bizorca account on the free plan got in. There is no allowlist and no admin page.
  Isolation is per advisor: every accessor filters on `user_id`, and another account
  gets 404 on a client it does not own (tested).
- **Database:** shared MySQL via `getDb()` → `tl_db()`. Tables `kit_clients`,
  `kit_assessments`, `kit_progress_entries`; schema in `migrations/001_kit.sql`, applied by
  `private_html/bin/migrate.php` on every deploy (`runSchema()` is gone).
  `kit_progress_entries.entry_date` is a real `DATE`: `addProgress()` turns anything that is
  not `YYYY-MM-DD` into today, because strict mode rejects it (SQLite stored whatever came).
  `deleteAssessment()` is a multi-table `DELETE ... JOIN`, because MySQL refuses a subquery
  on the table being deleted from. Nothing calls it today.
- **No secrets.** Kit needs no keys at all now; SSO was its only one.

## Build and test
```bash
./build-css.sh            # only when Tailwind utility classes change (npx tailwindcss@3.4.17)
php tests/calc_test.php   # 39 checks from the framework's worked cases; must pass
```
`public/assets/tailwind.css` is a committed build artifact; the server never needs Node.
Local dev: `php -S 127.0.0.1:8080 dev-router.php` from the repo root, then /kit/.

## Data import (done 2026-10-07)
`bin/import-sqlite.php <file> [--dry-run]` imports the old SQLite database: users matched
into shared `users` by email; Kit users signed in only through SSO and have no password,
so new accounts get an unusable random hash and set a password through "Forgot your
password?". Kit's `is_admin` (a mirror of login.bizorca.com's flag, which gated nothing
in Kit) is not carried into the site-wide `is_admin`. Refuses to run if any kit_ table has
rows.

## Tech
- Vanilla PHP 8.2 (never 8.3+ syntax), shared MySQL, Tailwind **vendored** (not CDN).
- No JavaScript beyond one `window.print()` call.
- There is **no AI in this app**, by decision. Deterministic arithmetic only. If that
  changes, the calculators stay pure PHP; a model may draft narrative, never a reading.

## The calculation engine
`includes/calc.php` is the product. Everything else is chrome around it.

- **Pure functions.** Worksheet inputs in, numbers plus a reading out. No I/O, no model.
- **Every function returns a `steps` array** of `[label, expression, value]`, rendered
  verbatim by `renderSteps()`. "Show your math so the advisor can check it" is a stated
  rule of the framework, not a nicety.
- **`tests/calc_test.php` is the guard.** Every expected figure is from the worked case
  studies, which the framework states agree across instruments — the only independent
  check available on this arithmetic. Seven of them are numbers the document records an
  AI getting wrong with confidence. `deploy.sh` refuses to ship if they fail.

```bash
php tests/calc_test.php
```

## Data model
Four tables. Clients are **private to the advisor who created them** — there is no
shared-pool path in the app, and every accessor in `db.php` takes `$userId` and filters
on it. Ownership is the query, not a check the page performs and then trusts.

Worksheet answers live in `assessments.data_json` as one JSON document per run, because
the rows inside them are variable-length (SWOT entries, capacity lines, candidate issues)
and nothing ever queries across worksheets. Instruments can be re-run: several rows may
share a `(client_id, instrument)`, and the client page shows the latest.

## Things that will bite you
- **Migrations end each statement with `;` at end of line.** `private_html/bin/migrate.php`
  splits on that and strips `--` comment lines per statement, so a comment block above a
  statement is safe, but a `;` inside a comment or string at a line end is not.
- **Colour tokens are RGB channel triplets, not hex.** `palette.php` authors them as hex
  and converts. Without the channel form, every opacity modifier in the app
  (`border-bad/30`, `bg-surface/95`) silently generates no rule at all.
- **`asset()` resolves against `KIT_PUBLIC`** (Kit's own public folder, set in
  `public/_bootstrap.php`). Under `/kit`, `DOCUMENT_ROOT` is the whole site's web root, so
  resolving against it pins `?v=1` and serves stale CSS after every deploy. The same bug
  happened once before on SiteGround with `public/` vs `public_html/`.
- **`fputcsv()` needs `escape: ''` passed explicitly.** Left at the default, PHP 8.4+
  writes a deprecation notice into the response body — i.e. into the middle of the file
  the advisor downloads.
- **The CSP and security headers are sent by PHP** (`includes/bootstrap.php`), not
  `.htaccess`: this Cloudways app never reads `.htaccess`. Cloudflare Web Analytics is on
  zone-wide for bizorca.com and appends a beacon at the edge; `script-src 'self'` blocks
  it deliberately, because client financial data does not need a third-party beacon.
  `curl` will not show the beacon; only a browser will.
- **The app mark lives in `palette.php`, not in the templates.** The header badge
  (inline SVG) and the favicon (a data: URI) use different encodings; defining the
  shapes once keeps them from drifting. It is not a letter mark on purpose — the
  initials of "Advisor Field Kit" spell AFK.
- **Checkboxes in repeating rows are indexed explicitly** (`row_only_me[3]`), not `[]`.
  Unchecked boxes submit nothing, so bare `[]` arrays fall out of alignment with the
  other columns and silently attach flags to the wrong activity.

## Not built yet
The framework's Part 7.6 instruments are described in one line each — not enough to
re-create faithfully. They are deliberately absent rather than guessed at:
Allocation Sequence (2.1), Collections Calendar (2.3), Modular Task Library (3.1),
Service Agreement (3.2), Delegation Decision Matrix (3.3), Alignment Matrix and
Fixed-Scope Offers (4.1), Micro-Workshop Framework (4.3), Referral Source Map (4.4).

The Collections Calendar is the one the worked case most wants next — the health check
already offers it as a "next instrument" pointer with nowhere to go.
