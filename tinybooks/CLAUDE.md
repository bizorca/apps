# TinyBooks — Claude Code Guide

## What this is
A simple bookkeeping web app for small service-based businesses and non-profits (built with Roots Fusion, a dance non-profit, in mind). Multi-company: each user sees only the companies they belong to.

**Lives at** https://tools.bizorca.com/tinybooks/, one tool on the shared tools platform. Read `../CLAUDE.md` (the repo root) first for the platform: shared account, shared MySQL, deploy.

**Ported 2026-10-07** from the standalone `Archipelago/tinybooks/` (now `deprecated/tinybooks/`) (SQLite, own login, never deployed: tinybooks.bizorca.com never had DNS and no server held an install). There were no live books to migrate.

## Stack
- PHP 8.2, vanilla, page-per-file, no build step; Tailwind from CDN
- **MySQL** via the shared `tl_db()` (`db()` is kept as the name the pages use); tables `tb_`-prefixed
- **Claude Haiku** (`claude-haiku-4-5-20251001`) via cURL in `includes/haiku.php`, keyed by `ANTHROPIC_API_KEY` in `private_html/.env.php` (the same key Proforma uses). With no key every AI feature degrades: no suggestion, no duplicate warning, a plain "AI help is unavailable" in reconciliation, and CSV import falls back to the first expense account.

## How it sits on the platform
- `public/` deploys to `public_html/tinybooks/`; `includes/`, `migrations/`, `bin/` to `private_html/tinybooks/`. `public/_bootstrap.php` finds `TB_ROOT` in either layout and loads `includes/config.php`, which loads the shared core. Every page's first line requires it.
- `APP_URL` is `/tinybooks`; most links are relative or go through it. URLs end in `.php` (nginx-only Cloudways app, no `.htaccess`).
- **Auth** is the shared tools account. `includes/auth.php` maps it: `auth_require()` → `tl_require_login()`, `auth_user()` returns `id, email, name, is_admin` from the shared `users` row. TinyBooks' login, logout, setup wizard, password form and "add user with a temp password" are gone.
- **Company access** is `tb_user_companies (user_id → users.id, company_id, role)`. `current_company()` re-checks membership on every request and, the first time, picks the user's first company (the old login did that). A first-timer with no company is sent to `companies/create.php`, which replaced `setup.php`. Settings → "Who can open <company>": owners add an existing tools account by email (role owner or member) and remove people; a company always keeps one owner. Roles grant nothing else: every member sees and edits the books, as before.
- **Session keys** are `tb_`-prefixed (`tb_company_id`, `tb_csrf_token`, `tb_flash`, `tb_csv_rows`): one session is shared by every tool. Flash reads never create a session, so anonymous visitors get no cookie.
- **Money** is `DECIMAL(15,2)` (was SQLite `REAL`). PDO returns it as a string like `"125.00"`; PHP arithmetic and `>`/`<` handle that, but never use `===` on an amount or treat `"0.00"` as falsy. Amounts are rounded to cents on input.
- **Dates** are MySQL `DATE`; `valid_date()` rejects anything that is not a real Y-m-d before it reaches MySQL (SQLite stored whatever it was given).

## Accounting model
- **Double-entry (default):** "Money came from" = credit side; "Money went to" = debit side. Jargon hidden from users.
- **Single-entry:** income/expense type + bank account + category, stored as the same two lines.
- `account_balance()` in functions.php applies normal balance: assets/expenses debit-normal; liabilities/equity/income credit-normal.
- Balance sheet rolls net income through the as-of date into equity (Net Assets for non-profits).

## File layout
```
public/              -> public_html/tinybooks/
  _bootstrap.php, index.php (to dashboard or sign-in), dashboard.php
  accounts/          chart of accounts CRUD (deactivate, never delete)
  companies/         create (seeds the chart of accounts), edit, switch, list
  transactions/      list/filter/search, create, edit/delete, CSV import (upload -> map columns -> import)
  reports/           pl.php, balance_sheet.php, budget_actuals.php (budget entry lives here)
  reconciliation/    index.php (start), view.php (tick lines, finish)
  settings/          company members, AI status, link to account settings
  api/               categorize.php, check_duplicate.php (JSON, signed-in only)
includes/            -> private_html/tinybooks/includes/
  config.php, db.php, auth.php, functions.php, haiku.php, chart_of_accounts.php, header/nav/footer.php
migrations/001_tinybooks.sql
bin/import-sqlite.php   one-time importer for a TinyBooks SQLite file (validates cents and dates, verifies totals)
```

## Reconciliation rules
- Ticking a line creates/updates a `tb_reconciliation_items` row and sets the transaction's `is_reconciled`; un-ticking sets both back.
- A transaction with a line still ticked (or `is_reconciled`) is **locked**: date, description, reference and memo stay editable; amount, accounts and delete are refused with a reason. Un-ticked item rows are cleared away when the lines are rewritten. The original rewrote the lines anyway and crashed on the foreign key.
- The toggle only accepts lines of that reconciliation's account in the current company.

## Fixed during the port (bugs in the original)
- `setup.php` called `h()` without loading functions.php: the first-run page fatal-errored on every render.
- CSV import never imported: the upload handler set `$step = 2` and the import branch, testing `$step`, ran in the same request with no column mapping and bank account 0, so every row was skipped and the upload discarded ("Imported 0 transactions"). Now it branches on the submitted step.
- Editing or deleting any transaction ticked in a reconciliation was a fatal error (foreign key on the line rewrite). See Reconciliation rules.
- No ownership checks on posted ids: a crafted form could post a transaction to another company's account, tick another company's line, start a reconciliation on another company's account, or budget another company's account. All now verified against the current company (`accounts_belong_to_company()`).
- The server allowed deleting a reconciled transaction (only the button was hidden).
- "Add user" created a login with no company, so the invited person could see nothing. Replaced by company members.
- An AI category suggestion was used even if it named an account outside the offered list.

## Security
- CSRF on every POST (`csrf_field()` / `csrf_verify()`, token in `$_SESSION['tb_csrf_token']`, 1 hour TTL)
- `h()` on all output; prepared statements throughout
- Every company-scoped page goes through `current_company()`; edit pages also scope their row lookups by `company_id`

## Conventions
- No Composer, no npm, no build tools
- New pages: standalone `.php` in the right `public/` subfolder, first line `require_once` of `_bootstrap.php`, then the standard header/nav/footer
- Flash via `flash_set()`, shown in nav.php
- `db()` everywhere; never a second connection

## Local dev
From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, then http://127.0.0.1:8080/tinybooks/. Needs local MySQL `bizorca_tools` and `php private_html/bin/migrate.php`.
