# Commonweal (CoopConvert) — Project Context for Claude

## What this is
Washington State cooperative business conversion platform. Business owners explore converting to a worker co-op, ESOP or community-owned structure; volunteer coordinators guide them through intake → structure selection → deal modeling → document generation → review → complete. The UI still calls itself **CoopConvert**.

**Live:** https://tools.bizorca.com/commonweal/ — a tool on the shared platform. Read `../CLAUDE.md` (repo root) first for the shared account, database and deploy.
**Ported 2026-10-08** from `deprecated/commonweal` (was commonweal.app on SiteGround gcam1180, which now 301s to bizorca.com). Planning notes: `docs/coop-plan.txt`.

## How it sits on the platform
- **Layout:** `public/` → `public_html/commonweal/`; `includes/`, `templates/`, `migrations/`, `bin/` → `private_html/commonweal/`. `public/_bootstrap.php` defines `CW_ROOT` and loads `includes/config.php`, which loads the shared core. Every page's first line is `require __DIR__ . '/_bootstrap.php';`.
- **URLs:** page-per-file, so no query routes. Every link goes through `url()`, which prefixes `BASE_PATH` (`/commonweal`).
- **Accounts:** the shared tools account. Commonweal keeps only its own per-person fields in **`cw_members`** (`role` client/coordinator/admin, `coordinator_approved`, `coordinator_why`). The **`cw_users` view** joins shared `users` (id, email, name) to `cw_members`, so pages that read the old `users` table still read `cw_users` unchanged. Writes go to `cw_members` (join, approval).
- **Joining:** `register.php` is now "join Commonweal" for a signed-in account: business owner, or volunteer coordinator (with why-volunteer). `login.php`, `logout.php`, `forgot-password.php`, `reset-password.php` only forward to `/account/...`.
- **Roles:** `requireLogin()` = signed in AND a member; a signed-in non-member goes to the join page. A coordinator awaiting approval is held on a "pending" page (the original refused them at login). **Admin** = site owner (`users.is_admin`) OR `cw_members.role = 'admin'` — the original identified its admin by the `ADMIN_EMAIL` constant instead.
- **Sessions:** the shared session; Commonweal's keys are `cw_csrf` and `cw_flash`. Anonymous pages set no cookie (`getFlash()` and `isLoggedIn()` never create a session).
- **Database:** shared MySQL via `getDB()` → `tl_db()`. Tables `cw_businesses`, `cw_cases`, `cw_case_notes`, `cw_structure_assessments`, `cw_deal_models`, `cw_documents`, `cw_equity_ledger`, `cw_members`, view `cw_users`. Schema: `migrations/001_commonweal.sql` (same columns as the original 001–008).
- **Mail:** `includes/mail.php` posts HTML mail to the SMTP2GO API with the shared `SMTP2GO_API_KEY`; sender `CW_MAIL_FROM` (default `noreply@bizorca.com`, a verified domain). Only the coordinator-approval email is still sent; welcome and reset mail moved to the shared account.
- **Env keys (optional):** `CW_ORIGIN` (default `https://tools.bizorca.com`, used in email links), `CW_MAIL_FROM`.
- **Security headers** are set in `config.php` (nginx ignores the old `.htaccess`). No uploads: generated documents are HTML stored in `cw_documents.rendered_html`; `doc-view.php` prints it unescaped by design, and every user value in `includes/documents.php` is `htmlspecialchars`-escaped. No Composer packages (the original's composer.json only autoloaded an empty `src/`). No cron.

## Local dev
`php -S 127.0.0.1:8080 dev-router.php` from the repo root, then http://127.0.0.1:8080/commonweal/. Local MySQL `bizorca_tools` with `php private_html/bin/migrate.php`.

## Data import
`bin/import-mysqldump.php <dump.sql> [--admin-email=<the original ADMIN_EMAIL>] [--dry-run]` stages the dump as `cwimp_*`, then in one transaction matches users into shared `users` by email (a new account keeps its bcrypt hash; an existing tools account keeps its own password), writes `cw_members`, copies the seven data tables keeping ids, and round-trip-checks deal models, the equity ledger and documents. Refuses if any `cw_` table has rows. Proven on a dump of the original running locally; **not yet run on live data** — the SiteGround account refused the stored SSH key on 2026-10-08.

## Fixed during the port
- **Document generation never worked.** `documents.php` looped over `getAvailableDocuments()` values (labels) instead of keys and validated against labels, so every Generate button stored "Document type not found." Fixed to use the keys. Any old document rows hold that placeholder; regenerating replaces them.
- **Flash messages never displayed** and warned on every page view: `setFlash()` stores `{type, message}` but the header looped over it as `type => [messages]`. The header now normalises it.
- Pages that started with `require_once …/config.php` are bootstrapped like the rest.

## Known, not changed
- The nav and footer link to `about.php`, which never existed (404 on the original too).
- Clients reach their own case at `case.php` regardless of `?id=`; isolation was verified (another client's id shows nothing of theirs).
