# Lattice LMS — Project Context

Lite LMS modeled after Sophia Learning: courses → units → challenges (and milestones) → lessons, each lesson with one question slot holding up to 3 variants served in turn on wrong answers. Vanilla PHP, Tailwind via CDN, direct PHP file URLs (no front controller).

**Live:** https://tools.bizorca.com/lattice/ — one tool on the shared tools platform. Read `../CLAUDE.md` (the repo root) first: shared account, shared MySQL, deploy.
**Ported 2026-10-07** from the standalone lattice.bizorca.com (SiteGround, its own MySQL database and users table). That original was never a git repo; this folder is now the only copy of the code. Not to be confused with **Habit Stacks** at financialhypnosis.com/habits, a separate copy of the same app that has diverged.

## How it sits on the platform
- **Layout:** `public/` deploys to `public_html/lattice/`; `includes/`, `migrations/`, `bin/`, `docs/` deploy to `private_html/lattice/` (outside the web root). `public/_bootstrap.php` is the only file that knows the two layouts apart: it defines `LT_ROOT` and loads `includes/bootstrap.php` → `config.php`, which loads the shared core.
- **Pages** start with `require_once __DIR__ . '/_bootstrap.php'` (`dirname(__DIR__)` from `admin/` and `ajax/`), then include templates as `LT_ROOT . '/includes/header.php'`.
- **URLs:** `APP_URL` is `/lattice`, and every link and redirect already went through it, so the base path is that one constant.
- **Auth:** the shared tools account. `includes/auth.php` keeps the original function names (`is_logged_in()`, `current_user()`, `require_login()`, `require_admin()`) on top of `tl_user()`. Lattice's login, register and logout pages are gone; the header links to `/account/settings.php` and `/account/logout.php`.
- **Session is lazy:** the original `session_start()`ed on every request. Now nothing creates a session except `csrf_token()` (forms) and `flash_set()`; `flash_get()` reads without creating one. Session keys are `lt_csrf` and `lt_flash`.
- **Database:** shared MySQL via `db()` → `tl_db()` (native prepares, UTC). Tables are `lt_`-prefixed; `users` is the shared table. `maybe_complete_attempt()` stamps `completed_at` with `gmdate()` to match the UTC connection.

## Roles
Lattice had one flag, `users.is_admin` in its own users table, meaning "can edit courses". On the shared site `users.is_admin` means *site owner across every tool*, so:
- **Lattice course admin = a row in `lt_admins`.** A site admin (`users.is_admin = 1`) is always a Lattice admin too, with no row needed.
- `admin/members.php` lists only Lattice people (enrolled in a course, or in `lt_admins`), never every account on the site, and "Make admin" toggles `lt_admins` only. Site admins show as "Site admin" and can't be changed there; nobody can change their own role.
- `admin/users.php` (a duplicate of members.php kept only because SiteGround's WAF blocked that filename) is gone; links point at members.php.
- The admin dashboard's "Students" is now people enrolled in a Lattice course (was: every non-admin row in Lattice's users table).

## Uploads (course thumbnails)
Stored in `private_html/data/lattice/uploads/` (`LT_UPLOADS`), never in the web root: `deploy.sh --delete` would erase them from public_html, and this nginx-only server would execute any `.php` that landed in an uploads folder (the original's `assets/uploads/.htaccess` "php_flag engine off" means nothing here). Uploads must pass `getimagesize()` as well as the extension check. `public/thumb.php?f=` serves them: filename must match `thumb_[a-z0-9]+.<image ext>`, content type by extension, 404 otherwise. `thumb_url()` in helpers builds the link. The live site had no uploaded thumbnails.

## Schema and content
- `migrations/001_lattice.sql` — all `lt_` tables plus `lt_admins`. Ids are signed INT (users.id is signed). `lt_question_responses` now CASCADEs on slot/variant delete and SET NULLs the selected answer; in the original those RESTRICTed and deleting any lesson a student had answered was a fatal SQLSTATE 23000 (reproduced). `retire_answer()` still keeps an answer a student picked rather than deleting it.
- `migrations/002_lattice_content.sql` — the live course ("Personal Finance Fundamentals": 1 unit, 1 challenge, 2 lessons, 2 slots, 2 variants, 8 answers), generated from a read-only mysqldump with original ids. One INSERT per line, so `bin/migrate.php` splits it correctly; regenerate the same way (`mysqldump --set-gtid-purged=OFF --no-create-info --skip-extended-insert --complete-insert --compact`, INSERT lines only, tables renamed) rather than hand-editing.
- **Never DELETE answers rows a student has picked** — `retire_answer()` sets `is_active = 0`. Student-facing reads filter on `is_active = 1`. Admin `questions.php` upserts answers by `(variant_id, sort_order)`.

## Data import (one-time)
`bin/import-mysqldump.php <dump.sql> [--dry-run]` — loads a mysqldump of the old database into staging tables `ltimp_*` inside the target DB (the Cloudways DB user can't create a scratch database), copies in one transaction, and always drops staging. Users are matched into shared `users` by email (bcrypt hashes kept, so old passwords work); a Lattice admin becomes an `lt_admins` row. If migration 002 already seeded the content, every staged content row must match it byte-for-byte by id or the import stops; if `lt_courses` is empty the content is copied instead. Refuses to run if any people-data `lt_` table has rows.

Live data at port time: 1 user (the admin, jassen@bizorca.com), 1 enrollment, 1 completed attempt (50%), 2 responses.

## Scoring (unchanged from the original)
- Attempt score = distinct slots answered correctly / lessons in the challenge × 100, set when every lesson is complete (correct, or all variants exhausted).
- Course score = weighted mean of best completed scores; milestones and the final milestone weigh `MILESTONE_WEIGHT` (3), practice milestones don't count. Pass = `PASS_THRESHOLD` (70).
- Milestone unlocks when every `challenge` in its unit is complete; the final milestone when every unit `milestone` is.
- `max_attempts` and `MAX_CHALLENGE_ATTEMPTS` exist but nothing enforces them: there is no retake path, and `get_or_create_attempt()` always returns the latest attempt. Left as the original behaves.

## Fixed during the port
- `ajax/submit_answer.php` accepted any `variant_id` with a matching answer, so a student could submit a different lesson's variant (e.g. one already seen) and have the current slot marked correct. Now the variant must be the one the slot is serving next. Reproduced on the original: C2 scored 100% that way.
- `challenge.php` checked enrollment against `?course_id=` but served any `?id=`, so a challenge in an unenrolled or unpublished course was open; the course is now derived from the challenge and must be published.
- Locked milestones and the final milestone were only a hidden link; the URL served them. Now enforced in `challenge.php`, with a flash explaining why.
- The `info` flash ("This challenge has no content yet.") was set but never displayed; header.php shows it.
- Course and score-report titles were HTML-escaped twice.
- Deleting a lesson with student responses crashed (FK RESTRICT); see Schema.
- Login's `?next=` accepted `//evil.example` (open redirect); gone with the page, the shared login uses `tl_safe_next()`.
- Admin delete forms are POST + CSRF as before; a GET with `action=delete` still 403s at `verify_csrf()`.

## Local dev
From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, then http://127.0.0.1:8080/lattice/. Needs local MySQL database `bizorca_tools` and `php private_html/bin/migrate.php`.

## Files
- `includes/helpers.php` — all business logic (scoring, lesson status, variant serving, milestone locks, `retire_answer()`, `thumb_url()`)
- `includes/auth.php` — role check, flash, CSRF over the shared account
- `public/ajax/submit_answer.php` — JSON answer submission
- `public/admin/members.php` — Lattice people and course-admin role
- `docs/student-guide.pdf` — kept from the original folder; not linked by any page and not web-served
