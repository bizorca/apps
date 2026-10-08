# Pilotage — CLAUDE.md

## What this is

**Pilotage** — a multi-tenant workspace for business coaches, business advisors
and management consultants and their client businesses: documents, messaging,
two-way task accountability, and methodology-as-a-playbook. Lives at
**https://tools.bizorca.com/pilotage/** as one tool on the shared Bizorca Tools
platform. Read `../CLAUDE.md` (the repo root) first for the platform: shared
account, shared MySQL, deploy.

**Read `SPEC.md` before changing anything.** It carries the requirements, the
permission matrix, the delivery phases, and the decision log with rationale.
SPEC.md predates the move to tools.bizorca.com; where it talks about
subdomains, pilotagehq.com, per-firm passwords or emailed sign-in links, the
section "Ported to tools.bizorca.com" below is what is true now.

**Ported 2026-10-07** from pilotagehq.com (SiteGround, its own domain, a
subdomain per firm, its own login). That domain was abandoned; it had no users
besides the owner, so nothing was migrated and Pilotage started empty here. The
old GitHub repo keeps the pre-port history.

Feature state carried over unchanged: Phase 1 complete (M1–M14: tenancy,
branding, onboarding, seats, export, auth, clients, playbooks, scope, sessions,
tasks + the accountability loop, documents, messaging, goals/metrics/issues,
worksheets, notifications and digests, reporting and health, billing, audit,
retention, erasure), plus the marketing pages and self-serve firm creation.
**Phase 1.5 remains the only real gate: run one engagement through it.**
Billing still needs Stripe keys before it takes money (SPEC §M13).

## Stack

- **PHP 8.2** — the Cloudways server runs 8.2, so no 8.3+ syntax. Local dev may
  have a newer PHP; that does not license newer syntax.
- **MySQL** — the platform's one database, through the shared `tl_db()` handle
  (`Database::conn()` returns it). No ORM.
- **Tailwind CSS via CDN.** No npm, no build step. **Alpine.js was removed** —
  it was loaded on every page to power one accordion that `<details>` does
  natively, and it cost an `unsafe-eval` in the CSP. Do not add it back without
  reckoning with that.
- **Zero Composer dependencies.** The autoloader is hand-rolled in
  `src/Core/autoload.php` so deployment stays "rsync the files" with no
  `composer install` on the server. Keep it that way unless there's a very
  good reason.
- Namespace `Bizorca\Pilotage\` → `src/`. Table prefix `pl_`.

## The one rule that matters

**No SQL touching a tenant-owned table is written outside a `Repository`
subclass.**

`src/Core/Repository.php` injects `tenant_id` into every read and write. That
scoping is the only thing standing between this product and a cross-tenant
data leak, which is the failure mode that ends the business (SPEC.md §6). If
you need a query the base class can't express, add a method to a repository
that builds it *with the scope applied* — do not reach for the PDO handle.

Corollaries:
- Repositories declare a `writable()` allowlist. Columns not on it are dropped,
  so adding a sensitive column later is safe by default.
- `tenant_id` and `id` can never be written by a caller, and `tenant_id` can
  never be passed as a query condition.
- Column names are validated before they touch SQL — they are concatenated,
  not bound.

When you add a tenant-owned table, add it to the isolation suite. A table that
isn't attacked in `tests/TenantIsolationTest.php` is a table nobody has checked.
That suite now ends with a census: it asks the schema which tables carry a
`tenant_id` and holds every one of them to the cascade rule, so a new table is
covered the moment it exists. `pl_audit_log` is the one exemption, and the
test asserts the exemption too — an audit trail that vanishes with the thing it
audits is not an audit trail.

Services may hold raw SQL when the base class can't express the query (see
`Worksheets`, `Timeline`, `AccountabilityLoop`), but every such statement
carries `tenant_id` in its WHERE, without exception — including reads of a row
whose id came straight from `lastInsertId()`. The point is that grepping for an
unscoped `pl_` statement should come back empty, so a real one stands out.

## Invariants — do not remove these

**Both PHP and MySQL are pinned to UTC.** `Database::conn()` runs
`SET time_zone = '+00:00'`; `autoload.php` calls `date_default_timezone_set('UTC')`.

This is not tidiness. PHP's `date()` and MySQL's `NOW()` otherwise run on
independent clocks, and a window computed in one compared against a timestamp
written by the other is off by the UTC offset. That exact bug silently disabled
**all** rate limiting — six consecutive failed logins produced no lockout — and
the same fault line ran under session idle timeouts, magic-link expiry, and
invitation expiry.

A PHP-only test cannot catch it: the assertion and the bug share the same wrong
clock and agree perfectly. It was found by running the flow over real HTTP.

Consequences for anything you build with an expiry, window, or idle timeout:

- Prefer SQL-side arithmetic (`DATE_SUB(NOW(), INTERVAL :secs SECOND)`) over
  computing a boundary in PHP. `RateLimiter` does this deliberately.
- `tests/ClockAndRateLimitTest.php` asserts the two clocks agree within two
  seconds. **If that group fails, assume every time-windowed feature is broken**
  — do not dismiss it as flaky.
- New time-windowed features want a test that writes with one clock and reads
  with the other. `tests/http/worksheets.sh` is the worked example: it drives
  the flow over HTTP, then reads `submitted_at` back through a MySQL session
  pinned to UTC. Pin that session. Reading a correctly-stored UTC datetime
  through the shell's local zone reports the app as seven hours wrong and
  sends you hunting a bug that isn't there.

## Known debt — check here before assuming an oversight

**Unregistered permission qualifiers.** `Policy::unresolvedQualifiers()` lists
qualifiers in the §8 matrix that have no resolver yet. They **deny** until the
owning module registers one — that fail-closed default is intentional. A
permission that unexpectedly denies is usually this, not a bug. Retiring them is
a running measure of how much of the matrix is live. **The list is now empty** —
every qualifier has a resolver as of Phase 1. `PolicyTest` carries the single
global assertion; module tests assert only their own, so shipping a new module
never turns an older module's suite red.

**CSP vs inline scripts.** SPEC.md §6 wants a nonce-based CSP with no
`unsafe-inline`. `src/Views/auth/two_factor_setup.php` uses an inline script to
render the TOTP QR, and the layout pulls Tailwind and Alpine from CDNs. Plan
nonces when the CSP goes in rather than retrofitting. The QR stays client-side
regardless — the TOTP secret must never reach a third-party chart service.

**Anonymous worksheet drafts are unrecoverable, on purpose.** An anonymous
response stores no respondent id, so the only way back to a half-finished one
is a resume key held in the browser session (`_worksheet_resume`). Clear the
session and the draft is orphaned — permanently, by anyone, including support.
That is the cost of the guarantee in FR-10.6 and it is not a bug to fix. If a
"recover my draft" request ever arrives, the answer is that the feature cannot
exist without breaking the promise the module is built on.

**Nothing sends mail inline any more, except auth.** Since M11 a module that
wants to tell someone something calls `Notifications::queue()` and returns; the
tick drains the queue and decides. Reaching for `Mailer::send` in a new feature
is almost always wrong — it bypasses preferences, batching and unsubscribe in
one move. The exception is auth mail (invitations and 2FA confirmations; password
resets are the shared account's now), which is time-critical, worthless in a
digest, and has no legitimate opt-out.

`Notifications::CATALOGUE` is the contract. A `queue()` for an event that is not
in it throws, so a typo fails in test rather than going silent in production.
Events marked `transactional` cannot be switched off by any stored preference —
that check runs before the preference is read, so neither a UI bug nor a
hand-edited row can silence a document a client must acknowledge.

**No DOMPDF, despite the house convention.** M12 renders reports as HTML with a
print stylesheet and lets the browser make the PDF. DOMPDF needs Composer, and
zero Composer dependencies is what keeps deploy at "rsync the files". Reach for
headless Chrome elsewhere before reaching for DOMPDF here. The reasoning is in
`src/Services/Reports.php`.

**The health score withholds itself on purpose.** `Health` excludes unmeasured
factors from the score rather than counting them as zero, and publishes no band
at all below `MIN_WEIGHT_FOR_BAND`. If a score reads as missing when you expect
one, that is usually this rather than a bug — check `measured` and `possible` on
the returned array.

**Free during beta — `PL_BILLING_BETA=true`, and it is the only flag.** While it is
on nothing is charged, trials never expire, the gate never fires, and seat and
storage limits do not apply. `Entitlements` is the only thing that reads it, so
the banner, the billing screen and the write gate cannot drift apart and tell one
firm three different stories. **Anything that gates on a plan asks
`Entitlements`** — a structural test in `BillingTest` fails any controller that
compares seat counts itself, which is how the staff screen was found still
enforcing a cap during beta. Beta beats `PL_BILLING_ENFORCE` deliberately.

Ending beta is three steps in order: wire Stripe, then `PL_BILLING_BETA=false`, then
`PL_BILLING_ENFORCE=true` (all in `private_html/.env.php`). Doing the last two first locks firms out of a product
they have no way to pay for.

**The read-only gate lives in `public/index.php`, not in controllers.** M13
blocks writes for a lapsed firm in one place, against
`Entitlements::ALWAYS_WRITABLE`. Adding a route that a locked-out firm must be
able to POST to means adding it to that list — and each entry is a hole in the
gate, so it has to earn its place. Reading is never blocked, deletion is blocked
too (read-only cuts both ways), and **the export must never be gated**: it is
the reason trusting us was rational in the first place.

`PL_BILLING_ENFORCE` defaults to false. Turning it on is a separate decision from
configuring Stripe, so that wiring up keys cannot lock a live tenant out by
accident.

**Destruction is announced before it happens and recorded after.** M14's
retention sweep schedules and emails on one night and purges thirty days later,
and `Compliance::purge()` refuses to act on a notice whose `notified_at` is null
— so a mail outage cannot turn into a silent deletion a month on. Do not
"simplify" those two steps into one. Retention defaults to keep-forever for the
same reason.

**Erasure pseudonymises; it does not delete.** Deleting a user row cascades
through commitments and attendance and takes the engagement record with it,
which is the opposite of what a right-to-erasure workflow should do. If you add
a table that stores a person's name denormalised, add it to
`Compliance::pseudonymise()` — otherwise an erased name stays printed on every
row of it.

**No public API yet, and that is a decision.** Wanted; deliberately not started
until a real engagement or prospect asks for a specific integration. An API is
the hardest thing here to change once someone depends on it, and nothing so far
has said what a coach would integrate with. When it is built, most of the
groundwork exists already — `Core\Token`, `Auth\Policy`, `Core\Repository` and
the `Notifications` catalogue. See SPEC §11.

**The CSP carries a per-request nonce. Every `<script>` needs `Csp::attr()`.**
A script tag without it silently will not run, which is the correct failure —
noticing it in development is the system working. `script-src` has no
`unsafe-inline` and no `unsafe-eval`; `style-src` keeps `unsafe-inline` because
the Tailwind CDN injects style elements it creates itself, and removing that
would mean a build step. Alpine was dropped to avoid `unsafe-eval` — it was
loaded on every page to power one accordion that `<details>` does natively. Do
not add it back without reckoning with that.

**Background work: Cloudways cron first, page loads as backup.** `Services\Tick`
owns what a tick does and is the only implementation of it; `Core\Heartbeat`
owns scheduling. There are two triggers and they cannot double-run, because
both claim the mode through `Heartbeat::claim()` (same row, same window):

- **Cron (primary).** `bin/tick.php five-minute` and `bin/tick.php nightly`,
  CLI-only, in `private_html/pilotage/bin` on the server. Cron lines are under
  Deploy below. On SiteGround there was no usable cron, which is why the page
  load trigger exists at all.
- **Page loads (backup).** A request that finds work due claims the slot and
  fires a short self-request at the routed tick URL
  (`/pilotage/?r=/_tick/{token}/{mode}`, built with `pl_route()`), then the
  second request does the work. `/_tick` stays reachable from outside so an
  uptime monitor can ping it; the URL is on the firm settings screen.
- **Locally, the built-in server needs `PHP_CLI_SERVER_WORKERS=4`.** It is
  single-threaded by default, so the self-request deadlocks against the request
  that made it and the tick silently never runs.
- `pl_tick_state` is seeded by migration 020 (`five-minute`, `nightly`). An
  empty table means nothing can claim and every tick says "not due" — that is
  what wiping it does.

`/_health` and the firm settings screen both report when the tick last finished.
That exists because the background layer was dormant in production for a day and
the only symptom was mail that never arrived — anything that can go quiet has to
be able to say so.

**The landing area is the no-firm part of the route, and `Provisioning` is the
only place a tenant is born.** Landing, pricing, about, FAQ, signup and "your
workspaces" are routes with no `/f/<slug>` prefix and 404 inside a firm —
`MarketingController::apexOnly()` is called first in every action, deliberately
per-action rather than in the router. A firm's client seeing Pilotage's own
pricing inside their advisor's workspace undoes the white-label promise.

Signup on tools.bizorca.com:

- **The owner is the signed-in Bizorca Tools account.** Signed out, `/signup`
  explains that and links to `/account/register.php` / `login.php` with a
  `next` back. Signed in, the form asks only for the practice name and address
  (`/f/<slug>`); name, email and password are the account's.
- **It is one transaction, and it must stay one.** A tenant row with no owner is
  unreachable *and* burns its slug permanently. `SignupTest` asserts a failed
  create leaves no orphan.
- **No handoff link.** The subdomain days needed a one-time magic link to carry
  identity from the apex to the firm's host. Same host now: the controller
  starts the Pilotage session directly and sends the owner to `/2fa/setup`.
- **Prices come from `Billing::PLANS` and the beta banner from
  `Entitlements::inBeta()`.** Never hardcode either.

**Signing in is the shared account; the firm adds the second step.** Who a
person is comes from the Bizorca Tools account (`/account/*`, the shared core).
Whether they may act in THIS firm, and as whom, is Pilotage's:
`GET /f/<slug>/login` (`AuthController::showLogin`) sends a signed-out visitor
to `/account/login.php?next=…`, looks up this account's membership
(`UserRepository::findByAccountId`), then runs the original second step
unchanged — TOTP challenge if enrolled, forced enrolment for firm owners — and
starts a Pilotage session. Gone with the port, because the shared account does
them for every tool: per-firm passwords, `MagicLink` (emailed sign-in links)
and `PasswordReset` (per-firm resets). Their old addresses forward to the
shared pages. The 12-character `Password::problems()` rule still applies when an
invitation creates a tools account. The `pl_magic_links` / `pl_password_resets`
tables remain in the schema, unused.

**A Pilotage session is bound to the account.** `Auth\Session` keeps its
DB-backed rows (idle timeout, revocation, impersonation cap, audit), stored per
firm under `$_SESSION['pl_sid'][tenant_id]` inside the one shared PHP session.
`Session::current()` only accepts a row whose membership's `account_id` (or the
impersonator's, during impersonation) is the signed-in tools account, and
revokes it otherwise. So signing out of the tools account, a different account
signing in, or an admin re-pointing a membership ends that firm session at
once. Pilotage's own "Sign out" revokes every firm session and then signs out
of the site (`tl_logout()`), because on a shared site that is what signing out
means.

**Invitations attach a membership to an account.** `Invitation::accept()` now
takes the accepting tools account id and binds the new `pl_users` row through
`UserRepository::attachAccount()`, the one write path for `account_id` (it is
deliberately not in `writable()`). The accept page has three states: signed in
as the invited address (one button), signed in as someone else (refused, 403
on POST), signed out (create the tools account here, or "sign in to accept" if
the address already has one — it never attaches an existing account without
its password). Client-side contacts used to live on emailed links with no
password; they now get a tools account like everyone else.

**A new firm cannot mail strangers, and the gate is `Invitation::issue()`.**
Every firm sends from the one domain (SPEC §7), so deliverability is *shared* —
one account blasting invitations degrades every other firm's ability to have a
sign-in link arrive, on a young domain still at DMARC `p=none`. Self-serve
signup means nobody is watching the front door, so the limit is structural.

`Services\SendingTrust` bands a firm as `unproven` (no client organization yet:
three invitations, lifetime), `new` (has a client, under a week old: 25/day) or
`established` (250/day). `vouched_at` on `pl_tenants` is the manual override.
The band is derived, not stored, so there is no second copy to go stale.

Why invitations are the only place this is enforced: an invitation is the sole
way this product mails somebody who does not already have an account. Every
other message — digests, mentions, document deliveries — needs a user, and a
user needs an invitation. Gate that and the whole path from "signed up two
minutes ago" to "mail in a stranger's inbox" is closed in one place. Controllers
re-check first so a refusal arrives before a half-created record, but those are
courtesies; the binding check is in `Invitation::issue()`.

Two details that are load-bearing:
- **It throws `HttpException` (429), which is deliberately not a
  `RuntimeException` in this codebase.** Both call sites wrap `issue()` in
  `catch (\RuntimeException)`, and `ClientOrgController` swallows that one
  *silently* to mean "already has an account". A throttle on that branch would
  make a blocked firm add contacts all day and quietly never invite anyone.
- **A revoked invitation still counts.** The resource spent is a message that
  already left the building; refunding on revoke restores the exact loop being
  throttled.

The one path left open on purpose: a firm with public intake enabled causes one
acknowledgement email per submission to an attacker-chosen address. Already rate
limited, needs the form switched on, and sends one message rather than a list.
Named in `SendingTrust` so it is a decision rather than an oversight.

**`Tenant::RESERVED_SLUGS` can only grow, and growing it is not free.** A
reserved label 404s, so adding one a live firm already holds takes that firm off
the internet with no undo. **Check the list against real tenants before
extending it** — `HostResolutionTest` asserts hygiene (no duplicates, all
lowercase, all reachable, and that the known live slugs are absent) but cannot
see production. Grown on 2026-08-08 to ~270 entries covering infrastructure,
auth, commerce, legal, environments, methodology trademarks (`eos`, `traction`,
`stratop`) and generics no single firm should own (`coaching`, `advisors`).

**A cohort is a coordinating layer, not a container.** Cohort membership grants
access to the shared sessions, deliberately published material, and the roster —
and nothing else. Every member keeps its own engagement and all existing scoping
applies unchanged. If you ever find yourself adding "…or shared with my cohort"
to a scoped query, stop: that is the design going wrong, and one missed branch
is a cross-client leak. `tests/CohortTest.php` attacks the wall directly and is
the group to keep if cohorts are ever reworked.

**A digest is an event in the catalogue, and must stay one.** `digest.weekly`
and `digest.daily` exist so that unsubscribing actually stops them. Before that,
`Digest` wrote to every active client-side reader without consulting a
preference — so a person could switch off every individual notice and the weekly
summary kept arriving, which is precisely what FR-11.5 forbids. Found in
production, not in a test.

**Demo contacts must be silenced, not just have their queue drained.**
`tools/seed-demo.php` sets every optional preference to off for the accounts it
creates. Marking the queue `in_app` is not sufficient: the client weekly digest
is assembled from LIVE open commitments rather than the queue, so it went out to
`@harbourline.example` — a reserved TLD that hard-bounces — and hard bounces
damage a young sending domain.

**SMS is deferred, and must stay out of the channel enum until it is real.**
`pilotage.cc` and `short_url()` exist for it. What does not exist is a sender,
phone capture, or an opt-in record. Adding `sms` to `pl_notification_prefs`
before the sender exists gives users a setting that silently does nothing, which
is worse than not offering it. Both go in together or neither does. See SPEC §11.

**Custom domains will not be built** (decided 2026-08-07). Every firm lives at
its `/f/<slug>` address on tools.bizorca.com, permanently.

**Out of scope on purpose** (SPEC.md §11), so it is not re-litigated casually:
coach-to-client invoicing, e-signature, real-time chat, video conferencing,
native mobile apps, accounting integrations, marketplace.

## URLs — one host, a firm is a route prefix

Every URL is built by the helpers in `src/Core/helpers.php`; never hardcode a
host or a path shape, especially not in an email template.

- `url()` / `tenant_url($path, $slug)` — inside a firm:
  `https://tools.bizorca.com/pilotage/?r=/f/<slug>/<path>`
- `app_url($path)` — the landing area (no firm): `/pilotage/?r=/<path>`
- `pl_route()` — the site-relative form of either; `pl_parse_route()` splits a
  route into `[slug, path]`; `pl_request_path()` is this request's path below
  the firm prefix (what `?redirect=` must carry); `pl_route_field()` is the
  hidden `r` input every `method="get"` form needs, because a GET form replaces
  its action's query string.
- `pl_origin()` — scheme+host from the request, or `PL_ORIGIN` (default
  `https://tools.bizorca.com`) for CLI work like the tick, so mail links are
  right from cron.
- **Clean URLs are one constant.** `PL_CLEAN_URLS` in helpers.php switches every
  link to `/pilotage/f/<slug>/<path>`. Only flip it after Cloudways adds an
  nginx fallback for this app (`try_files $uri $uri/ /pilotage/index.php?$args`
  under `/pilotage/`); until then such paths 404 at nginx.
- `marketing_url()` and `short_url()` remain as names (getpilotage.com and
  pilotage.cc went with pilotagehq.com) and point at the landing area.

## Layout

```
public/                -> public_html/pilotage/   index.php (front controller), _bootstrap.php
src/                   -> private_html/pilotage/src/
  Core/                Database, Config, Router, Tenant, Repository, helpers, Heartbeat, Csp, Mailer
  Auth/                Session, Policy, Qualifiers, Invitation, TwoFactor, Csrf, RateLimiter, tokens
  Repositories/        one per tenant-owned table
  Services/            playbooks, sessions, tasks, timeline, notifications, billing, compliance, ...
  Controllers/         one per resource
  Views/               plain PHP templates; marketing/ is the landing area
config/app.php         every value from private_html/.env.php via tl_env()
migrations/            001–024 *.sql, applied by private_html/bin/migrate.php on deploy
bin/tick.php           cron entry point for the tick (CLI only)
tests/                 run.php + *Test.php (no PHPUnit), schema.php builds the test schema
tools/seed-demo.php    one realistic engagement, for looking at (--remove undoes it)
docs/                  calendar-sync.md, phase-0-infrastructure.md (pilotagehq.com era)
```

`public/_bootstrap.php` is the only file that knows the repo layout from the
server layout; it defines `PL_ROOT` and loads the shared core first. Uploads and
the local mail log live in `private_html/data/pilotage/` (`app.storage`), which
deploys never touch. Front-controller MVC, not page-per-file. Match it.

## Commands

```bash
# tests: a scratch schema of their own (pilotage_tools_test), never the shared DB
php tests/schema.php               # (re)build it: core users + every pl_ migration
php tests/run.php                  # everything
php tests/run.php Route            # filter by filename

# local dev, from the repo root (apps/)
PHP_CLI_SERVER_WORKERS=4 PL_ENV=development PL_SECRET_KEY=$(openssl rand -hex 32) \
    php -S 127.0.0.1:8091 dev-router.php
open http://127.0.0.1:8091/pilotage/

# the tick by hand
php pilotage/bin/tick.php five-minute
php pilotage/bin/tick.php nightly --force

# demo data inside an existing firm
php pilotage/tools/seed-demo.php --tenant=<slug>
php pilotage/tools/seed-demo.php --tenant=<slug> --remove
```

## Tenancy notes

- A firm is the route prefix `/f/<slug>`. `pl_parse_route()` splits it off and
  `Tenant::resolveSlug()` validates and loads it — `Tenant::parseSlug()` is
  pure and strict, like `parseHost()` was. `RouteResolutionTest` covers it,
  including a round trip through every URL helper.
- `Tenant::RESERVED_SLUGS` can only grow. Reclaiming a slug after a firm has
  taken it breaks their URLs.
- An unknown, reserved, malformed or suspended slug renders the same plain 404.
  Never leak whether a slug exists.
- Per-firm session state lives under pl_-prefixed keys in the one shared PHP
  session (`pl_sid`, `pl_pending_2fa`, `pl_csrf`), keyed by tenant where it
  matters, and `Session::current()` re-checks the tenant in SQL.

## Users

`pl_users` is a **firm membership**: `tenant_id` is always set, `client_org_id`
decides which side of the wall (NULL firm-side, set client-side, tied to `role`
by a CHECK constraint), and since the port `account_id` points at the shared
`users` table — the Bizorca Tools account that signs in as this member. One
person in three firms is three rows sharing an `account_id`;
`uq_tenant_account` allows one membership per account per firm. `account_id` is
NULL only for a row whose account was deleted (ON DELETE SET NULL keeps the
engagement record, like erasure). `password_hash` is no longer written; the
export still strips it.

`Provisioning::memberships($accountId)` (used by "your workspaces") is the one
read of pl_users scoped to an account rather than a tenant: it can only ever
return the caller's own memberships.

## Deploy

Deployed with the rest of the platform: `./deploy.sh` at the repo root (`pilotage`
is in `TOOLS`), which rsyncs `public/` to `public_html/pilotage/`, everything else
to `private_html/pilotage/`, and runs pending migrations. Never edit on the server.

**Server keys** (`private_html/.env.php`, read through `tl_env()`): shared
`DB_*` and `SMTP2GO_API_KEY`; Pilotage's own `PL_SECRET_KEY` (**required**: it
encrypts calendar tokens and signs the tick), and optionally `PL_ORIGIN`,
`PL_MAIL_FROM` (default `pilotage@bizorca.com`, on the verified bizorca.com
SMTP2GO domain), `PL_MAIL_FROM_NAME`, `PL_INTAKE_HOUSE_TENANT` (default
`bizorca`), `PL_BILLING_BETA`, `PL_BILLING_ENFORCE`, `PL_STRIPE_*`,
`PL_GOOGLE_CLIENT_*`, `PL_MICROSOFT_CLIENT_*`, `PL_ENV`, `PL_DEBUG`.

**Cloudways cron** (Application → Cron Job Management):

```
*/5 * * * * php /home/1676077.cloudwaysapps.com/qukjzcxeas/private_html/pilotage/bin/tick.php five-minute
15 3 * * *  php /home/1676077.cloudwaysapps.com/qukjzcxeas/private_html/pilotage/bin/tick.php nightly
```

**Calendar sync** (not configured yet): the OAuth callback is the routed
`/pilotage/?r=/calendar/callback/{provider}`. Providers match redirect URIs
character for character, and some refuse URIs with a query string — if Google
or Microsoft rejects it, calendar sync needs the clean-URL nginx rule first.


**Cloudflare sits in front.** `REMOTE_ADDR` is a Cloudflare IP; never read it
directly, go through `ClientIp::resolve()`, which trusts `CF-Connecting-IP`
only when the request really came from a Cloudflare range. Security headers
(X-Frame-Options, nosniff, Referrer-Policy, the nonce CSP) are sent from PHP:
this Cloudways app ignores `.htaccess`.

## Ported to tools.bizorca.com (2026-10-07) — record and parity checklist

**What changed, all of it in service of one host and one account:**
tenancy by route prefix instead of subdomain (`Tenant::resolveSlug`, URL
helpers); sign-in by the shared Bizorca Tools account with Pilotage's 2FA as the
second step (`AuthController`, `Auth\Session` bound to `account_id`);
`MagicLink` and `PasswordReset` removed; invitations and signup create/attach a
tools account; table prefix `ptg_` → `pl_` and constraint names `pl_fk_*` /
`pl_chk_*` (MySQL constraint names are global per schema, and Pilotage's
`fk_password_resets_user` collided with the core's); config from `.env.php`;
uploads to `private_html/data/pilotage`; the CSP's `form-action` is just
`'self'`; the tick has a cron entry point; Cloudways' nginx means query-string
routes and no `.htaccess`. Migrations 001–023 are the original's (the same
statements, re-split-safe for the platform runner); 024 adds `account_id`.

**Bugs found in the original while porting (all fixed, all pinned by tests):**
1. `/firm/dashboard` rendered entirely from nulls: the controller passed the
   report as `data`, which `View::capture()` (EXTR_SKIP) can never hand to a
   template. Every section of the firm report was empty, with PHP warnings.
2. Every new-message notification linked to `/engagements/{id}/messages/{thread}`,
   which matches no route: the email button and the in-app notification 404'd.
3. The read-only billing gate allowlisted `/two-factor`, which matches no route;
   the enrolment POST is `/2fa/setup`. With `BILLING_ENFORCE` on, an owner of a
   lapsed firm who had not enrolled could never enrol, so could never reach the
   billing page that fixes it.
4. Not a bug on 8.2, but noise: `finfo_close()` and `curl_close()` are no-ops
   since PHP 8.1 / 8.0 and deprecated on 8.5; removed so logs stay clean.

**Verification at port time** (local, against the original running side by side
from a scratch copy on its own database):
- Unit suite: **2105 passed, 0 failed**, including `TenantIsolationTest`
  131/131 (its schema census covers every `pl_` table). Per file the counts
  match the original's 2141 exactly, except the auth files: `HostResolution`
  (60) → `RouteResolution` (67), `InvitationMagicLink` (44) → `Invitation` (44),
  `PasswordReset` (45) removed with the class, `Billing` and `Signup` adjusted,
  `PortRegression` (5) added.
- One scripted scenario, run identically on both apps over HTTP: two firms,
  owner + coach + client in one, owner in the other; signup, 2FA enrolment,
  onboarding, client create/convert/edit, engagement, playbook apply, contact
  and staff invitations accepted, branding, session schedule/start/notes, task
  create/tag/comment/complete (client), document upload/share/deliver,
  document request, threads (both sides), goal/metric/issue, worksheet, cohort,
  notification preferences, scope, retention, email wording, public intake
  submit and queue. Afterwards **all 50 non-empty tables hold the same row
  counts in both apps** (the only differences: no `pl_magic_links` rows and two
  fewer audit rows, the magic-link sign-ins that no longer exist).
- A crawl of every in-firm link for each of the four people: **146 of 158
  shared pages render identical visible text**; the rest differ only where
  intended (firm address shown as a URL, no `magic_link.used` audit event, the
  fixed firm dashboard, `.ics` UIDs on the site host, the export's `account_id`
  column). The firm export matches table by table across all 36 tables and
  carries no credential field.
- 61 HTTP probes: cross-firm reads and writes with real ids (all 404, nothing
  written), client-side users on firm-side pages (403/404), invitation hijack
  and reuse, an existing account never attached without its password, sign-out
  of the tools account ending every firm session, 2FA challenge (and TOTP
  replay refusal) on signing back in, Pilotage sign-out signing out of the
  site, no cookie for anonymous visitors. Plus: the read-only gate over HTTP
  (7 checks), membership re-pointed at another account revoking the live
  session, GET search forms keeping their route, the tick from cron and from a
  page load, and `seed-demo.php` creating and removing its accounts.
- No PHP warning, notice or deprecation in the port's server log across all of it.

**Not done / known gaps:**
- Clean URLs need the Cloudways nginx rule (then flip `PL_CLEAN_URLS`).
- Calendar sync and Stripe were not exercised (no keys); calendar OAuth may
  need clean URLs (see Deploy).
- The impersonation banner's "end" form posts to `/admin/impersonation/end`,
  which has no route — inherited: impersonation has no route to start either,
  so the banner cannot appear.
- `tests/http/*.sh` (curl walks against subdomain hosts and magic links) were
  removed with what they tested; the verification above used a scratch harness
  that is not in the repo.
