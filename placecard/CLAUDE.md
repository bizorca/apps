# Placecard — tools.bizorca.com/placecard

Social dining for strangers: group dinners at partner restaurants in Chiang Mai
and Port Townsend. No algorithm — just show up and eat. Read the repo root
`../CLAUDE.md` first for the platform (shared account, shared MySQL, deploy).

**Ported 2026-10-08** from `deprecated/Placecard` (placecard.bizorca.com on
SiteGround: `web/` page-per-file site + `api/` JSON API for the SwiftUI iOS
app, own users table, HS256 JWTs). The old GitHub repo `bizorca/placecard`
keeps the pre-port history. The iOS app (`Placecard/Placecard/`, gitignored,
local only) was **not** copied; it stays in the original folder.

## Layout

```
public/                  -> public_html/placecard/
  _bootstrap.php         finds PC_ROOT in both layouts, loads includes/config.php
  index, about, features public marketing pages (no session, no cookie)
  register.php           "Join placecard": first name, last name, age 18+ on a
                         signed-in tools account (creates pc_profiles)
  login.php / logout.php forward to /account/login.php / /account/logout.php
  onboarding, setup, dashboard, events, event, my-dinners, profile,
  edit-profile, privacy-settings, create-event   — the member pages
  api/index.php          JSON API front controller (iOS)
includes/                -> private_html/placecard/includes/
  config.php             shared core, PC_BASE, pcUrl()/pcRedirect(), INTERESTS/DINING_PREFS/CITIES, headers, PHP in UTC
  session.php            requireAuth()/requireAccount()/currentUser()/flash, CSRF on every POST
  api.php                api(): calls the API controllers IN-PROCESS (no curl)
  head, public-nav, app-nav, event-card   templates
src/                     Response (throws PcResponse), Auth (bearer tokens), Profile, Router (route table), controllers/
migrations/              001_placecard.sql (schema), 002_placecard_seed.sql (cities + restaurants)
bin/import-mysqldump.php one-time import of the old database
docs/                    plan.txt, logo-1.png, logo-2.png (repo only, never deployed)
```

## How the two halves share one implementation

The original web pages called the API over HTTP (`curl` + JWT in the session).
Now `src/Response.php` **throws** a `PcResponse` carrying the same
`{success, message, data}` envelope instead of printing and exiting, so:

- `public/api/index.php` catches it and prints JSON (for the iOS app)
- `includes/api.php`'s `api($method, $endpoint, $data, $token)` catches it and
  returns the array to the page — same route table, same controllers, no
  network hop. A non-null `$token` means "as the signed-in shared account"
  (`getToken()` returns a placeholder); `Auth::$webUserId` carries the id.

Controllers read the body via `PcRequest::json()` (php://input for the API, the
passed array for web).

## Accounts

- People are the shared `users` table. Being a Placecard member = having a
  `pc_profiles` row (first/last name, age, bio, interests, dining prefs,
  privacy flags, completeness, member_since).
- `pc_profiles.public_id` is the id the API exposes (`id`, `user_id`,
  `host_user_id`): imported people keep their original UUID; new members get a
  new UUID. `users.id` never leaves the server.
- Web: signed out → `/account/login.php`; signed in but not a member →
  `register.php` (join form, prefilled from the account name); member without a
  bio → onboarding/setup, as before.
- API: `POST /auth/register` with a brand-new email creates the shared account
  and profile. With an email that already has a tools account **and the right
  password**, it joins that account to Placecard (201); wrong password or an
  existing member → the original 409. `POST /auth/login` works for members only;
  a tools account that hasn't joined gets 403 telling them to sign up with the
  same credentials.
- API password minimum stays 8 (the iOS app's rule); the shared /account pages
  require 10. No admin or restaurant-partner role exists in Placecard.

## API (iOS) — base URL changed, contract kept

```
https://tools.bizorca.com/placecard/api/?r=/events?city_id=chiang-mai
```

The route rides in `?r=` (this server can't rewrite `/placecard/api/events` to
index.php); a route's own query may sit inside `r` and is folded into `$_GET`.
Clean paths also work unchanged if Cloudways ever adds a `try_files` fallback.

Endpoints, methods, request bodies, status codes, messages and the envelope are
the original's (verified side by side, see Testing). Differences, all additive
or fixes:
- `GET /events` and `GET /users/me/events` rows now include `host_user_id`
  (and `/users/me/events` also `restaurant_id`). The iOS `APIEvent` decoder
  requires both, so **the original lists could never be decoded by the app**.
- `GET/PUT /users/me` also return `show_last_name` (ignored by the iOS decoder).
- Tokens are opaque 64-hex strings stored hashed in `pc_api_tokens` (30 days),
  not JWTs. Old JWTs from placecard.bizorca.com don't carry over; the app signs
  in again.
- The API never reads or sets a cookie.

**What the iOS app must change** (`Services/APIService.swift`): only URL
construction. `URL(string: cleanPath, relativeTo: base)` with base
`https://placecard.bizorca.com/api/` cannot express `?r=` (relative resolution
replaces the query). Build it by concatenation instead:

```swift
private let base = "https://tools.bizorca.com/placecard/api/?r=/"
// in request(): cleanPath is e.g. "events?city_id=chiang-mai" or "events/\(id)/rsvp"
guard let url = URL(string: base + cleanPath) else { throw APIError.decoding }
```

Nothing else in the app changes.

## Data

`bin/import-mysqldump.php <dump.sql> [--dry-run]` stages the old database as
`pcimp_*` in the same DB (only DROP/CREATE TABLE/INSERT run; FK constraints and
mysqldump's SET SQL_MODE/TIME_ZONE preamble are stripped), checks cities and
restaurants byte-for-byte against the seed, then in one transaction: users by
email into shared `users` (bcrypt kept; no usable hash → unusable one + Forgot
password), profiles with `public_id` = old UUID, events and RSVPs with their
UUIDs and remapped user ids. Refuses if pc_profiles/pc_events/pc_rsvps has
rows; staging always dropped.

Live data at port time (2026-10-08): 2 cities, 10 restaurants, 1 member
(jassen.bowman@gmail.com, "Mike"), 1 dinner (2026-03-29, past), 1 RSVP. No
uploads (the original had none).

## Times

PHP runs in UTC here (as it did on SiteGround) and `tl_db()` keeps MySQL in UTC.
Event times are stored the way the original stored them: the web form's
wall-clock time as typed (19:00 shows as 7:00 PM); the iOS app sends ISO 8601
with `Z`, stored as UTC.

## Behaviour fixed in the port

1. Turning a privacy toggle **off** (`show_exact_age` / `show_last_name` false)
   was a 500 "Database error": PHP `false` bound as `''` into a TINYINT under
   strict MySQL. Stored as 0/1 now.
2. iOS event lists were undecodable (missing `host_user_id`/`restaurant_id`).
3. `show_last_name` was saved but never returned, so the web toggle always
   rendered off.
4. Web forms had no CSRF protection; every POST now needs the token.
5. The web session cached the user forever; `currentUser()` reads fresh.
6. Login always ran `password_verify` only when the email existed (a timing
   oracle for registered emails); it now always verifies.

Unchanged on purpose: RSVPs to past dinners are allowed (the original allowed
them); hosts can't cancel their own RSVP; deleting a dinner is a soft delete.

## Local dev

`php -S 127.0.0.1:8080 dev-router.php` from the repo root, then
http://127.0.0.1:8080/placecard/ and http://127.0.0.1:8080/placecard/api/?r=/cities.
Needs local MySQL `bizorca_tools` with the core + placecard migrations.
