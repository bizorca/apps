# Pod — Claude Instructions

## What this project is
Bizorca Pod, the community for Bizorca users: video courses with progress tracking, a forum (categories, posts, replies, reactions, @mentions, pin/lock), support tickets (staff replies, assignment, status), an events calendar with optional Zoom meetings and RSVPs, a member directory and profiles, in-app notifications (also emailed), and admin pages for all of it plus member notes and bulk email.

**Lives at** https://tools.bizorca.com/pod/ — one tool on the shared tools platform. Read `../CLAUDE.md` (the repo root) first: shared account, shared MySQL, deploy.
**Ported 2026-10-08** from pod.bizorca.com (SiteGround, tables unprefixed inside the shared login.bizorca.com database, Bizorca SSO). The port was built from **the server's code, not git**: the server had 12 modified and 14 untracked files on top of git HEAD 44f6231 (members directory, profiles, notifications, bulk email, event RSVP list, forum search, admin user editing and notes, the Mailer). The original `pod/` repo still has only what git has.

## How it sits on the platform
- **Layout:** `public/` (`index.php`, `_bootstrap.php`) deploys to `public_html/pod/`; `includes/`, `src/`, `migrations/`, `bin/` deploy to `private_html/pod/`. `public/_bootstrap.php` defines `PD_ROOT` and loads `includes/config.php`, which loads the shared core (plus `mailer.php`) and registers the `Bizorca\Pod\` autoloader. Composer is gone: it supplied nothing but that autoloader.
- **Routing is query-string based** (no nginx fallback on this app): every route is `/pod/?r=/forum/post/5`. `url()` in `src/Core/helpers.php` builds every link, form action and redirect; `current_path()` reads the route and folds a path's own `?a=b` back into `$_GET`. **To switch to clean URLs**: get Cloudways to fall back to `/pod/index.php` for `/pod/*`, test `/pod/forum`, then set `PD_CLEAN_URLS = true` in `includes/config.php`. Rules: never write a relative `href="?x=1"`; a GET form needs `<?= route_field('path') ?>` because the browser drops the action's query string.
- **Auth:** the shared tools account. Every route requires sign-in (`tl_require_login()` in `public/index.php`, which never creates a session, so anonymous visitors get no cookie). The old `/sso/redirect` and `/logout` routes redirect to `/account/login.php` and `/account/logout.php`.
- **Members and roles** live in `pd_profiles` (keyed on `users.id`): `is_admin` (Pod-only), `is_staff`, `is_active`, `bio`, `avatar_url`. A profile is created on a signed-in account's first visit (Pod was open to every Bizorca account). The `pd_users` **view** joins it to `users` and splits the shared `name` into `first_name`/`last_name`, so the original's queries read almost unchanged. Read live on every request by `Session::user()`.
  - **Admin** = site admin (`users.is_admin`) or `pd_profiles.is_admin`. **Staff** = `is_staff` or admin.
  - Name and email belong to the shared account. A member editing their own profile updates `users.name`; changing *someone else's* name or email on `/admin/users/{id}/edit` is limited to site admins.
  - "Disable" (`is_active = 0`) blocks Pod only, on the person's next request; it does not touch their tools account.
- **Session:** the shared lazy session; Pod's only key is `pd_flash`. CSRF is the shared `tl_csrf_*` (field `_csrf`).
- **Database:** `Database` wraps `tl_db()` and moves the MySQL session to `PD_TIMEZONE` (America/Los_Angeles) as an offset; PHP uses the same zone. Event start/end are Pacific wall-clock DATETIMEs as typed. Schema `migrations/001_pod.sql`; `002_pod_seed.sql` adds the four default forum categories.
- **Mail:** `tl_mail()` (shared SMTP2GO key, plain text, from `MAIL_FROM`). Notification emails carry `absolute_url()` links (`PD_ORIGIN`, default https://tools.bizorca.com). `pd_notifications.url` stores an app path (`/forum/post/5`) and is turned into a link when shown.
- **Zoom:** Server-to-Server OAuth, keys `PD_ZOOM_ACCOUNT_ID`, `PD_ZOOM_CLIENT_ID`, `PD_ZOOM_CLIENT_SECRET` in `private_html/.env.php`. Unset = the admin "Create Zoom meeting" box says Zoom is not configured and nothing calls Zoom (the live server had none set). Token cached in `pd_zoom_token_cache`. Meetings are created with `timezone` = `PD_TIMEZONE`. `php pod/bin/zoom-check.php` is a read-only credential check (token + GET /users/me).
- **No uploads, no cron.** Avatars and course thumbnails are URLs; nothing is stored on disk.
- **Security headers** are sent from `public/index.php` (X-Frame-Options, nosniff, Referrer-Policy). No CSP: Tailwind and Alpine come from CDNs and lessons embed YouTube/Vimeo iframes.

## Local dev
From the repo root: `php -S 127.0.0.1:8099 dev-router.php`, then http://127.0.0.1:8099/pod/. Needs local MySQL `bizorca_tools` with migrations applied (`php private_html/bin/migrate.php`).

## Data import (one time)
`bin/import-mysqldump.php <dump.sql> [--dry-run]`. The dump is Pod's tables plus `users` from the login database:
```
mysqldump --single-transaction --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF <logindb> \
  courses lessons lesson_progress forum_categories forum_category_reads forum_posts forum_replies \
  forum_reactions tickets ticket_messages events event_rsvps notifications user_notes \
  zoom_token_cache user_app_admins users > pod.sql
```
Stages every dumped table as `pdimp_*` (foreign keys stripped, always dropped), copies in one transaction keeping ids, then carries each table's AUTO_INCREMENT over. `users` there is the whole Bizorca SSO user base: only people who **used Pod** are imported (signed in to it, i.e. `sso_id` set, which only Pod's callback wrote; own a Pod row; or have staff/disabled/bio/photo). Matched to tools accounts by email; new accounts keep their bcrypt hash (the login DB was the identity store, so it is their real password); a non-hash becomes unusable ("Forgot your password?"). Old `is_admin` → `pd_profiles.is_admin`, never the site-wide flag. Old DATETIME columns were America/Chicago (the old MySQL `time_zone`) and are converted; `POD_IMPORT_OLD_TZ` overrides that. Rows pointing at a missing post/category (the old schema had no foreign key there) are skipped and counted. Refuses if any `pd_` table has data, except the seed categories (replaced) and untouched profiles.

## Adding a route
`public/index.php`: `$router->get('/my-path/{id}', [new MyController(), 'method']);`. Every route is behind sign-in already; admin methods call `$this->requireAdmin()`. Views go in `src/Views/` and set `$pageTitle` **unescaped** (the layout escapes it).

## Database tables
`pd_profiles` · `pd_users` (view) · `pd_courses` · `pd_lessons` · `pd_lesson_progress` · `pd_forum_categories` · `pd_forum_posts` · `pd_forum_replies` · `pd_forum_reactions` · `pd_forum_category_reads` · `pd_tickets` · `pd_ticket_messages` · `pd_events` · `pd_event_rsvps` · `pd_notifications` · `pd_user_notes` · `pd_zoom_token_cache` (plus the shared `users`). What a person owns cascades with their account; ticket assignee and note author are set NULL.

## Fixed during the port (all present on the live pod.bizorca.com)
- Every relative time read about 5 hours old ("5h ago" right after posting): PHP ran in UTC while MySQL returned America/Chicago times. Events' "past"/"upcoming" disagreed between pages for the same reason.
- Admins who were not also flagged staff (both live admins) got "Ticket not found" for every ticket, including the ones linked from their own dashboard, and could never be made staff (toggle skips admins). Admins now count as staff.
- The Members directory and bulk email used `user_app_admins` (the login server's per-app *admin* table, empty), so the directory was always empty and bulk email always reached 0 members.
- Staff (non-admin) got a nav link to `/admin`, which is admin-only (403). It now goes to the ticket queue.
- Role and access changes were cached in the session at sign-in: disabling a member did nothing until their 7-day session expired.
- New tickets notified no one; staff heard about a ticket only once its owner replied.
- Duplicate course, lesson or category slug and an unparseable event date were uncaught exceptions (blank 500 on the server).
- Deleting an admin note redirected to whatever `Referer` said (open redirect).
- Reactions could be stored against posts that do not exist; ticket assignment accepted any user id on any ticket id.
- Profile photo URLs and the lesson-video fallback link accepted `javascript:` URLs.
- Page titles were escaped twice (`Intro &amp;amp; Basics` in the tab).
- The member search box broke on names with an apostrophe (a name inside a hand-quoted JS string).
- Marking a lesson complete worked on unpublished lessons and courses.
