# Anglerfish Press — Project Context for Claude

## What this is
Jassen's private content production desk: ingests books, extracts structured knowledge, generates images, composes posts (Bizorca Press, Hypnologue), video scripts. Single operator, used daily.

**Live:** https://tools.bizorca.com/anglerfish/ — a tool on the shared tools platform, **private**. Read `../CLAUDE.md` (repo root) first for the platform.
**Ported 2026-10-08** from anglerfish.bizorca.com (SiteGround; repo `writer/press`, which still holds the Mac worker). The full original instructions are in `docs/press-CLAUDE.md` and the spec in `docs/SPEC.md`; both still apply to everything except hosting, auth and paths, which are below.

## Private: rules
- **Every page requires a site admin** (`tl_require_admin()` in `public/index.php`, before routing, so outsiders do not even get a 404). Non-admins get 403, strangers go to `/account/login.php`.
- **No landing-page card, no link from anything public.** Never add it to `public_html/index.html`. Pages send `X-Robots-Tag: noindex` and the meta tag.
- The one exception to the admin gate is the **worker API** (`?r=/api/worker/*`): the Mac worker is a daemon with no session, so it authenticates with a bearer token (`AF_WORKER_TOKEN`, checked with `hash_equals` in `Core/Api.php`).

## How it sits on the platform
- **Layout:** `public/index.php` → `public_html/anglerfish/`; everything else (`src/ templates/ config/ includes/ vendor/ worker/prompts/ migrations/ bin/ cli.php`) → `private_html/anglerfish/`. `includes/boot.php` loads the shared core, defines `AF_ROOT`, `AF_BASE` (`/anglerfish`), `AF_STORAGE`, the autoloader and the Composer vendor dir (Anthropic SDK, committed: no Composer on the server).
- **Routes ride in `?r=`** (`/anglerfish/?r=/library/some-slug`): tools.bizorca.com has no try_files fallback. `url()` builds every link and `redirect()` every Location; JS `fetch` URLs are echoed through `url()`. **GET forms must carry `<input type="hidden" name="r" value="/route">`**, because a browser drops the action's query string on a GET submit.
- **Auth:** the shared tools account. `Core/Auth.php` is a wrapper over `tl_user()` / `tl_require_admin()`; SSO, `User` model and the users table are gone. Sign out posts to `/account/logout.php`.
- **Session:** the shared `tools_session`; Anglerfish's keys are `af_`-prefixed (`af_csrf`, `af_flash`). `/tick` closes the session before it works, so a 70s tick does not lock every other page in the browser.
- **Database:** shared MySQL, own PDO in `Core/Database.php` (keeps its gone-away reconnect). **Tables `af_`-prefixed**, constraint names too. Schema is `migrations/001_anglerfish.sql`, generated from the live SiteGround schema (= original migrations 001–019, kept in `docs/original-migrations/`). New schema changes go in `002_*.sql` etc., applied by `private_html/bin/migrate.php` on deploy.
- **Time zone: the MySQL session runs in America/Chicago** (`AF_DB_TIMEZONE`), sent as the current offset because this MySQL has no named-zone tables. SiteGround's MySQL ran in Chicago, so every imported DATETIME written by `NOW()` is Chicago wall time; keeping the zone keeps old and new rows comparable. PHP runs in UTC as before (the original already mixed the two).
- **Files:** what was the app's `storage/` is now `private_html/data/anglerfish/` (`AF_STORAGE`; deploy never touches `data/`): `assets/` (generated images, `assets/web` derivatives), `covers/`, `logs/` (Gemini batch logs), `backups/`, `incoming/` (push drop folder), lock files. The database still stores paths as `storage/assets/x.png`; `af_file()` maps that prefix to `AF_STORAGE`, and `cli.php` maps a `--from=storage/...` the same way.
- **Prompts:** the server reads `worker/prompts/*.md` (Compose, audiobook script). They are a copy of `writer/press/worker/prompts/`; when a prompt changes, change both. **deploy.sh must ship them** despite its `*.md` exclude (see the root CLAUDE.md / deploy.sh).
- **Keys** in `private_html/.env.php`: `AF_ANTHROPIC_API_KEY`, `AF_ANTHROPIC_MODEL`, `AF_GEMINI_API_KEY`, `AF_WORKER_TOKEN` (copied from the SiteGround `.env.php`; the worker's `.env` holds the same token).

## Server jobs
- `php cli.php work` drains queued server-side jobs (art direction, Gemini batches, compose). **Cloudways cron, every minute:** `cd /home/master/applications/qukjzcxeas/private_html/anglerfish && php cli.php work`. Page loads also tick via `/tick`.
- `php cli.php status`, `publications`, `batches`, `weekly-import`, `video-import`, `hypno-import`, `qa-export`... see `docs/press-CLAUDE.md`. Run from `private_html/anglerfish` as `ssh cloudways-bizorca 'cd applications/qukjzcxeas/private_html/anglerfish && php cli.php status'`.
- `bin/smoke.php` runs the front controller for every GET route as the first site admin (needs `public/index.php` reachable: on the server it reads `public_html/anglerfish/index.php`).
- `bin/import_*.php`, `bin/batch_probe.php`: the original one-off importers, now booted through the shared core.

## The Mac worker (`writer/press/worker`, not in this repo)
Runs from launchd (`com.bizorca.anglerfish.*.plist`), polls the server over HTTPS and pushes files over SSH. Its target is set in `worker/.env`:
```
PRESS_TARGET=cloudways
PRESS_BASE_URL=https://tools.bizorca.com/anglerfish/?r=
```
`worker/press_server.py` holds both servers' SSH details (default `siteground` until cutover); `weekly_push.py`, `video_push.py`, `hypno_push.py`, `image_qa.py` and `drain.sh` use it. Do not move `writer/press` to `deprecated/` while the worker runs from it.

## Cutover from SiteGround
`docs/cutover-sync.sh` (repo-only, not deployed) copies the database (replacing every af_ table's rows) and the files, and `--verify` compares row counts, `CHECKSUM TABLE` and every file's MD5. Final run: stop the worker and pushes, run it, flip the worker's `.env`, restart the worker.

## Local dev
From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, sign in as a site admin, open http://127.0.0.1:8080/anglerfish/. Local MySQL `bizorca_tools`, `php private_html/bin/migrate.php`.
