# Fathom — tools.bizorca.com/fathom

Kanban boards for independent service businesses: boards, columns, cards with steps, comments, reactions, assignees, tags, templates; a client portal; public read-only sharing with QR codes; a sysop admin with impersonation.

**Rewritten 2026-10-07** from the Laravel 11 app at fathom.bizorca.com (SiteGround, SQLite, own magic-link + Bizorca SSO sign-in) into vanilla PHP on the shared tools platform. Read `../CLAUDE.md` (repo root) first for the platform: shared account, shared MySQL, deploy. The original repo `bizorca/fathom` keeps the Laravel history.

## How it sits on the platform
- **Layout:** `public/` deploys to `public_html/fathom/` (just `index.php` and `_bootstrap.php`); `includes/`, `templates/`, `migrations/`, `bin/` deploy to `private_html/fathom/`. `public/_bootstrap.php` defines `FM_ROOT` for either layout.
- **Front controller + query routes.** Everything goes through `public/index.php`. This server cannot rewrite `/fathom/boards/5` to it (nginx straight to PHP-FPM, no `.htaccess`), so URLs are `/fathom/?r=/boards/5`. **Every URL comes from `fm_url()` / `route()`; switching to clean URLs is one constant**: set `FM_CLEAN_URLS = true` in `includes/config.php` once Cloudways adds `try_files $uri $uri/ /fathom/index.php?$args` for this app. The router already accepts both forms. GET forms carry the route in a hidden `r` field (`fm_route_field()`), because browsers drop an action's query string on GET submit; JS builds URLs with `window.fmUrl()` (set in `layouts/app.php`).
- **Routes:** `includes/routes.php` lists every route of the original `routes/web.php`: same names, paths, methods. `route('boards.columns.edit', [$board, $column])` works as in Laravel. Handlers are plain functions in `includes/controllers/*.php`, named after the original controller methods.
- **Models:** `includes/models.php` is a small Eloquent stand-in: casts, lazy cached relations (`$card->board->name`, `$card->tags->isNotEmpty()`), soft deletes honoured everywhere, `update()` only writes when something changed (stalled/auto-postpone read `updated_at`), UUID ids, positions defaulting to max+1. `FmCollection` has the collection methods the templates call.
- **Templates:** `templates/` are the Blade views converted to plain PHP (one-off conversion, not maintained as Blade). A view sets `$__title`, buffers its body and calls `fm_layout()`. Escape with `e()`. Layouts: `app` (members), `portal` (clients), `public`, `marketing`.
- **Helpers:** `includes/helpers.php` has the Laravel-shaped helpers the templates use (`route`, `old`, `errors`, `session`, `request`, `csrf_field`, `method_field`, `Str::limit/plural`, `FmDate` with Carbon 3's `diffForHumans` wording) plus validation with Laravel's messages and Laravel's empty-string-to-null input handling.
- **Database:** shared MySQL, tables `fm_*` (`migrations/001_fathom.sql`). UUID string ids and the morph type strings (`App\Models\Card`) are kept so imported data is byte-for-byte the original.
- **Vendored libraries** (no Composer on the server): `includes/vendor/` holds bacon/bacon-qr-code 3.1.1 + dasprid/enum 1.0.7 (QR SVGs) and erusev/parsedown 1.8.0 (Markdown), with a tiny autoloader. Licenses alongside.

## Identity: members and clients
- **Members sign in with the shared tools account** (`/account/login.php`). Fathom's own login, magic links for members, signup form and SSO are gone; their routes redirect to the shared sign-in. `fm_users` is a person's **membership**: `user_id` (shared `users.id`, unique: one workspace per person, as email was unique before), `account_id`, `role` (admin/member), `is_sysop`, notification prefs, time zone, avatar. Name and email are joined in from `users`, so templates still read `$user->name` / `$user->email_address`.
- **Signup** now means "create a workspace" for a signed-in tools user (business type seeds starter boards via `includes/onboarding.php`, copied verbatim). **Invite links** (`/join/<code>`) add a membership; signed-out visitors are sent to register/sign in and come back.
- **Sysop** (Fathom platform admin) stays a Fathom flag, `fm_users.is_sysop`, not the site-wide `users.is_admin`. Impersonation swaps only the Fathom identity (`$_SESSION['fm_impersonating']`); the shared sign-in stays the sysop's.
- **Clients are not tools users.** They are a board owner's clients (`fm_clients`, per account, by email), signing in to `/fathom/?r=/portal` with a 15-minute single-use magic link (`fm_magic_links`), session key `fm_client_id`. Rate limit 10 requests/minute per IP (`fm_rate_limits`).
- **Profile:** name edits write the shared account's name; email is shown read-only (it is the sign-in for every tool); password lives at `/account/settings.php`.

## Files outside the web root
Avatars and exports go to `private_html/data/fathom/` (`FM_DATA`) and are served by authenticated routes (`/account/avatar/<member>`, `/account/export/<id>/download`), only within the same account.

## Scheduling
None needed. The original scheduled three Artisan commands (`fathom:send-notification-digests`, `fathom:cleanup-expired-magic-links`, `fathom:cleanup-expired-sessions`) that **did not exist** in its codebase, and nothing ever created notifications or activity events. Auto-postpone and stalled-card marking run on board page load, as before. Exports run in the request.

## Parity checklist (original routes/web.php and features)
- [x] Marketing: home (members go to boards), features, about, services; `/up` health
- [x] Boards: index (active + archived), create (default To Do / In Progress / Done), show, edit, update, delete (admin only), archive, unarchive, JSON export
- [x] Auto-postpone stale cards to the first column + flash notice; stalled flag (engaged, 14 days idle) without touching updated_at
- [x] Drag-and-drop reorder (`POST boards/{board}/cards/reorder`, JSON + X-CSRF-TOKEN)
- [x] Columns: create, edit, update, delete (cards unassigned), move left/right, WIP limit field
- [x] Board subscriptions (subscribe/unsubscribe); saved filters (store/update/destroy) and filter links on the board
- [x] Cards: index with board/assignee/tag/status filters + pagination (25), create (templates picker, tags, assignees, draft, due date), show, edit, update, delete (soft), duplicate (tags, assignees, steps), move, publish/unpublish, close (with reason) / reopen, change board
- [x] Steps: add, rename, delete, complete/incomplete
- [x] Comments: add (Markdown, @mentions, auto-watch), edit, delete; reactions on cards and comments (toggle) and delete
- [x] Assignments: add (once), remove; watch/unwatch; pin/unpin (pinned cards on Home)
- [x] Clients: index, create, edit, update, remove; grant/revoke card access
- [x] Client portal: magic-link sign-in, dashboard grouped by board, card view, complete/uncomplete steps, comment, sign out
- [x] Public board / public card (published, open cards only), QR code SVG for public boards and cards
- [x] Tags CRUD with card counts; card templates CRUD (default column, tags, checklist)
- [x] Notifications: list (paginated 30), mark read, mark all read, settings (email, digest flag)
- [x] Search (boards + cards, 2+ characters)
- [x] Home / activity feed (recent boards, pinned cards, events)
- [x] Account: overview, profile (name, time zone, avatar), members (remove: admin only), invite link + "send" (flashes the link, as before), data export, danger zone cancel (admin only)
- [x] Admin (sysop): dashboard stats, accounts list, account detail, cancel account, impersonate / stop
- [x] Keyboard shortcuts (n, /, b, ?), user menu, impersonation banner
- [~] Sign-in / signup / magic links for members / SSO: replaced by the shared tools account (by design)
- [ ] Not ported, never functional: Reverb broadcast channels (no client code subscribed), the Sanctum `/api/user` route, webhooks tables (no code used them), the three missing scheduled commands

## Bugs in the original, fixed here
Each verified against the original running locally on the live data, unless noted.
1. **Tagging a card was a 500** (pivot insert with no id/morph type, NOT NULL failure) on create and edit; the card was saved first. Live data has zero taggings.
2. **Granting a client access to a card was a 500** (same pivot bug on `client_cards`), so **the client portal could never show a client anything.**
3. **Saving a card template was a 500** after the write (redirect to undefined route `card_templates.index`).
4. **Exports never worked**: queued on a database queue nobody ran on SiteGround (stuck "Pending"), and when run, failed on undefined route `account.export.download`; the download link pointed at a private disk URL that 404s.
5. **Card descriptions were printed as raw HTML** (stored XSS, including on public pages and the client portal). Now rendered as Markdown with HTML escaped, like comments.
6. Public card page **crashed on a client's comment** (`null->initials()`), and showed client comments blank.
7. Board edit could not turn **public** or **auto-postpone** back off; card edit could not **un-golden** or **remove the last tag** (unticked checkboxes were ignored).
8. Due-date colouring: Carbon 3's signed `diffInDays` made **every future due date amber**; now amber only within 3 days.
9. Re-adding a **removed client** (or a duplicate **tag name**, or a repeated **workspace name**) hit a unique index: 500. Now restored / refused politely / given a unique slug.
10. `POST /boards/{b}/cards/reorder` accepted cards from **other boards** in the account; comment edit/delete routes accepted a comment from **another card**; card create accepted **tag/assignee ids from other accounts**. All scoped now.
11. Portal sign-in mailed only the **first** client row with an email; a client on two accounts could never reach the second. Client-ticked steps never got `completed_at`.
12. Composer refuses every Laravel 11 release over open security advisories; the live app runs one. (Moot once the old site is retired.)

## Local dev
From the repo root: `php -S 127.0.0.1:8080 dev-router.php`, open http://127.0.0.1:8080/fathom/. Needs the local `bizorca_tools` database with `migrations/001_fathom.sql` applied.

## Import (one-time)
`bin/import-sqlite.php <database.sqlite> [--dry-run]`: Fathom users become memberships tied to shared accounts by email (new tools accounts get an unusable password: "Forgot your password?"); an existing account's email-derived placeholder name (from the Proforma import) is replaced by the Fathom name; every other row keeps its UUID. One transaction; refuses if any `fm_` table has rows. There were no uploaded files (`storage/app/public` was empty), so nothing to copy.
