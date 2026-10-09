# Pilotage — Phase 2 Feature Spec (Portfolio Harvest)

**Written:** 2026-08-07
**For:** the Claude Code session working in `Archipelago/businesscoach/`
**Status of this document:** a supplement to `SPEC.md`, not a replacement. Where the two
disagree, §A below says so explicitly and gives the reason.

## Why this exists

A cross-project survey of `kokoro/`, `taxcrm/`, `fathom/`, and `foundry/` was run against
Pilotage's Phase 1 codebase to find features worth harvesting. This document carries the
result: seven things to build, four amendments to `SPEC.md`, one architectural decision that
must be made before M12 or M13 starts, and a list of things deliberately assigned to Kokoro
instead of Pilotage.

**Sequencing caveat, read this first.** `SPEC.md` §11 puts Phase 1.5 next — run one real
engagement through Pilotage before Phase 2 begins. Nothing in this document should jump that
gate. Dogfooding is what tells you which of these is actually first, and the ranking in §B is
a considered guess, not evidence.

## Standing constraints (every item below is subject to these)

1. **No SQL touching a tenant-owned table outside a `Repository` subclass.** Every new table
   here is tenant-owned. Each gets a repository with a `writable()` allowlist and an entry in
   `tests/TenantIsolationTest.php`. The source implementations these ideas came from all scope
   in the controller layer; that is the pattern the base repository exists to prevent. Every
   port is a rewrite, not a copy.
2. **UTC on both clocks.** Anything with an expiry, window, or idle timeout uses SQL-side
   arithmetic (`DATE_SUB(NOW(), INTERVAL :secs SECOND)`), not a boundary computed in PHP.
3. **PHP 8.2 syntax ceiling.** SiteGround runs 8.2 regardless of local PHP.
4. **Zero Composer dependencies** — unless §D is resolved the other way, deliberately.
5. Next migration number is **013**. Migrations are re-runnable: `IF NOT EXISTS` on every
   statement.

---

# §A — Amendments to SPEC.md

## A1. FR-5.7 is overscoped. Cut self-booking and availability windows.

**Current text:** "Scheduling: coach availability windows, client self-booking links,
timezone handling, ICS attachments, reminders at 24h and 1h."

**Amend to:** "Scheduling: ICS attachments, reminders at 24h and 1h, and an optional external
booking URL per coach rendered as a link on the engagement and in session invitations. Pilotage
does not host availability or slot booking. Cadence enforcement (FR-5.8) is the scheduling
feature this product owns."

**Reason.** Kokoro built exactly this — `services`, `availability_rules`,
`availability_exceptions`, `appointments`, a slot generator, a public multi-step booking flow,
cancel-by-token. Its own `CLAUDE.md` opens with "What we are NOT building: a scheduling/calendar
system (they already have Acuity, Jane App, etc.)" and then ships it as v3 items 22–26. That is
a product that argued itself out of scheduling and built it anyway.

The domain logic also differs. Open availability with strangers self-booking into slots is a
practitioner's business model. A business coach runs a declared rhythm with named participants
across 5–40 client organizations — which is FR-5.8, already the correct advisory framing of the
same need, and separable from booking. Coaches already pay for Calendly or Acuity. Rebuilding
it is a multi-week detour that ends in a worse Calendly, and it would need per-participant
timezone handling that Kokoro's implementation does not have and that this codebase's UTC
invariant makes non-trivial.

**Keep:** `src/Services/Ics.php` (already built), the 24h/1h reminders (the five-minute tick
already carries nudges), and FR-5.8 cadence risk signalling.

**Add, cheaply:** `ptg_users.booking_url VARCHAR(500) NULL` — a coach pastes their Calendly
link once, and it renders on the engagement header and in session-related mail. Under 40 lines.

## A2. FR-10 — do not treat a flat form builder as most of the work.

The survey's first pass suggested Kokoro's intake builder was "60% of M10." That was arithmetic
on field types and it is wrong. Kokoro's forms are flat clinical capture: nine field types, a
JSON `fields` column, submit once, done.

Pilotage's M10 is a **scored assessment instrument**: per-answer weights (FR-10.4), category
subscores, band-based interpretive text the coach authors once, and the same instrument re-run
at month 0/6/12 with the delta charted (FR-10.5). The scoring and comparison engine *is* the
feature — FR-10.5 is correctly identified in the spec as the strongest ROI story a coach can
show a client. A flat builder is the easy 20% wearing the costume of the hard 80%.

**Amendment:** none to the requirements. This is a warning against a cheap start that would need
demolishing. The single thing worth taking from Kokoro is one design decision, recorded here so
nobody re-derives it: **put field definitions in a JSON column on the worksheet row, not in a
`worksheet_fields` table.** Field sets are read whole, written whole, never queried across, and
versioned with the parent. A child table buys nothing and costs a join on every render.
(Note this contradicts the `worksheet_fields` table listed in SPEC.md §10 — the JSON column is
the better call.)

## A3. FR-14.5 — reuse M7, do not build a parallel path.

FR-14.5 (coaching agreement required on every engagement, acknowledged by the client) reads
like a new module. It is not. `ptg_document_deliveries` and the acknowledgment flow shipped in
M7, and FR-7.2 already specifies timestamped, IP-logged client acknowledgment.

What is actually missing:
- `ptg_engagements.agreement_document_id INT UNSIGNED NULL` — FK to `ptg_documents`.
- A badge in the engagement header when it is null or unacknowledged. Visibly flagged, never
  gated (the spec is explicit on that).
- The document-type taxonomy gaining an `agreement` value so it is findable.

Roughly 120 lines. Do not build a consent-document/signature table pair; that is Kokoro's
liability-waiver shape and it would sit next to machinery that already does the job.

## A4. `ptg_announcements.segment` is too narrow, and the table is dead.

Migration 008 created `ptg_announcements` with `segment ENUM('all','active','prospects')`.
Nothing in `src/` references it — verified by grep. Meanwhile FR-8.5 asks for broadcast to
"all clients on a given playbook, all clients in a cohort," which that enum cannot express.

Fix comes free with §B2 (tags). Add `segment_tag_id INT UNSIGNED NULL`; when set, it wins over
the enum. Then build the send path, which does not exist yet.

---

# §B — Features to build

Ranked. Each is a real gap verified against the codebase, not a spec line item copied forward.

## B1. Notifications and preferences (M11 foundation) — build first

**Gap.** There is no notifications table and no preference check anywhere. `cron/tick.php`
composes and sends mail inline, per task, with no record that it happened and no way for a user
to turn it off. FR-11.1 and FR-11.2 have nothing under them. A coach running 20 engagements
currently gets one email per nudge, which is precisely the "40 emails instead of one digest"
failure FR-11.2 exists to prevent.

**Harvested from** Fathom's `notifications` table. The load-bearing column is `emailed_at`:
separating "this happened" from "this was mailed" is what lets a cron tick do immediate-vs-digest
batching without a queue worker, which matters because SiteGround kills daemons. Note Fathom has
the schema and a UI but nothing writes to it — `Notification::` appears only in relationship
definitions. Take the schema; the write side is yours.

```sql
-- migrations/013_notifications.sql

CREATE TABLE IF NOT EXISTS `ptg_notifications` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `event_type`  VARCHAR(60)  NOT NULL,   -- 'task.due', 'task.overdue', 'mention', ...
    `object_type` VARCHAR(40)  NULL,
    `object_id`   INT UNSIGNED NULL,
    `subject`     VARCHAR(255) NOT NULL,
    `body`        VARCHAR(1000) NULL,
    `url`         VARCHAR(500) NULL,       -- tenant-absolute, built by tenant_url()
    `read_at`     DATETIME NULL,
    `emailed_at`  DATETIME NULL,           -- immediate send happened
    `digested_at` DATETIME NULL,           -- included in a digest
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_inbox`   (`tenant_id`, `user_id`, `read_at`, `created_at`),
    KEY `idx_pending` (`tenant_id`, `emailed_at`, `digested_at`, `created_at`),

    CONSTRAINT `fk_notif_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ptg_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notif_user`   FOREIGN KEY (`user_id`)   REFERENCES `ptg_users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ptg_notification_preferences` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `event_type` VARCHAR(60) NOT NULL,
    `channel`    ENUM('immediate','digest','in_app','off') NOT NULL DEFAULT 'digest',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_user_event` (`tenant_id`, `user_id`, `event_type`),

    CONSTRAINT `fk_notifpref_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ptg_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notifpref_user`   FOREIGN KEY (`user_id`)   REFERENCES `ptg_users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Build:**
- `src/Repositories/NotificationRepository.php`
- `src/Services/Notifier.php` — `Notifier::raise($userId, $eventType, $subject, $url, ...)`.
  Every existing mail send in `cron/tick.php` and `Messaging.php` routes through it.
- Channel resolution: explicit user preference, else a per-event-type default table in the
  service, else `digest`. **Transactional events are not preference-controlled** (FR-11.5):
  invitations, magic links, password changes, and 2FA always send. Encode that as an
  `ALWAYS_IMMEDIATE` const, not as a default.
- `cron/tick.php five-minute` — send `immediate` notifications, stamp `emailed_at`.
- `cron/tick.php nightly` — batch everything undigested into one coach morning digest and one
  client weekly digest (FR-11.3, tenant-configured day), stamp `digested_at`.
- `GET /notifications`, `POST /notifications/read`, `GET|POST /settings/notifications`.
- Unread count in the layout.

**Tests:** a user with `channel='off'` receives nothing; a transactional event ignores `off`;
a notification is never both emailed and digested; cross-tenant read is denied.

**Effort:** ~500 lines plus migration. This unblocks FR-11.1–11.5 and is a prerequisite for
the client-decay counterweight named in SPEC.md §13.

## B2. Tags and segments

**Gap.** No tagging of any kind. A coach with 40 client organizations cannot slice their book,
and FR-8.5's "all clients on a given playbook, all clients in a cohort" has no mechanism.
Also unblocks §A4.

**Harvested from** `taxcrm/lib/Tags.php` (247 lines) — polymorphic entity tagging with
categories, colours, pill rendering, seeded defaults. It is vanilla PHP 8 with a PDO singleton,
so this is the closest thing to a direct port in the survey. It still becomes a repository:
TaxCRM threads `$orgId` into each query by hand, which is the pattern to leave behind.

```sql
-- migrations/014_tags.sql

CREATE TABLE IF NOT EXISTS `ptg_tags` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `name`       VARCHAR(60) NOT NULL,
    `color`      CHAR(7) NULL,
    `category`   ENUM('cohort','methodology','priority','custom') NOT NULL DEFAULT 'custom',
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_tenant_name` (`tenant_id`, `name`),
    CONSTRAINT `fk_tags_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ptg_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ptg_taggables` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `tag_id`      INT UNSIGNED NOT NULL,
    `object_type` VARCHAR(40) NOT NULL,   -- 'client_org' | 'engagement' | 'task'
    `object_id`   INT UNSIGNED NOT NULL,
    `tagged_by`   INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_tag_object` (`tag_id`, `object_type`, `object_id`),
    KEY `idx_lookup` (`tenant_id`, `object_type`, `object_id`),

    CONSTRAINT `fk_taggables_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ptg_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_taggables_tag`    FOREIGN KEY (`tag_id`)    REFERENCES `ptg_tags`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ptg_announcements`
    ADD COLUMN `segment_tag_id` INT UNSIGNED NULL AFTER `segment`,
    ADD CONSTRAINT `fk_announce_tag` FOREIGN KEY (`segment_tag_id`) REFERENCES `ptg_tags`(`id`) ON DELETE SET NULL;
```

Then build the announcement send path — compose, resolve recipients (enum segment or tag),
preview the count, send through `Notifier`, stamp `sent_at` and `recipient_count`. The columns
are already there waiting.

**Effort:** ~350 lines including the announcement sender.

## B3. Plan gating with a non-destructive lapse (M13, part one — no Stripe yet)

**Gap.** No billing anything. `ptg_tenants.seat_limit` exists and nothing enforces it beyond
the firm settings screen.

**Harvested from** `taxcrm/lib/Subscription.php` (196 lines). The valuable part is not the
Stripe calls — it is the lifecycle `beta → active → grace (60 days) → expired` and
`requireActiveSubscription()`, which renders a banner and stops the request rather than hard
403-ing. That is FR-13.3's exact semantics, already written, in the right language: "a hard but
non-destructive limit at trial end (read-only, export still available)."

**Build this before the Stripe integration.** Gating and billing are separable, gating is the
part with product semantics in it, and having it in place means the Stripe work is only plumbing.

```sql
-- migrations/015_plans.sql
ALTER TABLE `ptg_tenants`
    ADD COLUMN `plan`           VARCHAR(40) NOT NULL DEFAULT 'trial' AFTER `status`,
    ADD COLUMN `trial_ends_at`  DATETIME NULL AFTER `plan`,
    ADD COLUMN `grace_ends_at`  DATETIME NULL AFTER `trial_ends_at`,
    ADD COLUMN `org_limit`      SMALLINT UNSIGNED NULL AFTER `seat_limit`;
```

- `src/Services/Plan.php` — a `PLANS` const (name, price in cents, seat limit, org limit,
  storage, custom domain, footer removal), `Plan::limits($tenant)`, `Plan::allows($tenant, $feature)`.
- Three states with distinct behaviour: **active** (everything), **grace** (banner, everything
  still works), **expired** (read-only — GET routes succeed, state-changing POSTs are refused
  with an upgrade prompt, `/firm/export` keeps working).
- Read-only enforcement belongs in one place in `public/index.php` next to the CSRF check, not
  sprinkled across controllers. `/firm/export`, `/login`, `/logout`, and billing routes are on
  the allowlist.
- 14-day trial, no card (FR-13.3).

**Tests:** an expired tenant can GET an engagement and cannot POST to it; an expired tenant can
still export; a grace tenant is unrestricted; seat and org limits refuse creation with a
specific message, not a 500.

**Effort:** ~400 lines. Stripe (Checkout, Customer Portal, webhook sync) is a separate follow-on
and depends on §D.

## B4. Bulk client-organization import with named batches

**Gap.** None exists. SPEC.md §13 names migration friction as the adoption killer and §14 sets
"signup to first client invited in under 15 minutes" as a success criterion. A coach with 30
existing clients in a spreadsheet currently types 30 forms.

**Harvested from** TaxCRM's `LeadLists` table plus the import tab in `data-transfer.php`. The
good idea is not CSV parsing — it is the **named batch**: every imported row carries the batch
id, so a bad column mapping is undone by deleting the batch instead of hand-picking 200 rows.
That is the difference between a coach trying the import and a coach fearing it.

```sql
-- migrations/016_imports.sql
CREATE TABLE IF NOT EXISTS `ptg_import_batches` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `label`        VARCHAR(160) NOT NULL,
    `kind`         ENUM('client_orgs','contacts') NOT NULL,
    `row_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `imported_by`  INT UNSIGNED NULL,
    `reverted_at`  DATETIME NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant` (`tenant_id`, `created_at`),
    CONSTRAINT `fk_import_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ptg_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ptg_client_orgs`   ADD COLUMN `import_batch_id` INT UNSIGNED NULL,
    ADD CONSTRAINT `fk_org_batch`     FOREIGN KEY (`import_batch_id`) REFERENCES `ptg_import_batches`(`id`) ON DELETE SET NULL;
ALTER TABLE `ptg_client_contacts` ADD COLUMN `import_batch_id` INT UNSIGNED NULL,
    ADD CONSTRAINT `fk_contact_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `ptg_import_batches`(`id`) ON DELETE SET NULL;
```

Flow: upload → column mapper against `ptg_client_orgs` fields → **preview first 10 rows parsed**
→ confirm → import → batch listed with a revert button. Revert only deletes rows that have no
engagement attached; anything in use is reported and skipped rather than cascading.

Do not send portal invitations as part of import. Importing 200 orgs must never become 200
emails; invitations stay a separate deliberate act.

**Effort:** ~400 lines.

## B5. Asynchronous tenant export

**Gap and live risk.** `FirmController::export()` assembles 34 tables into one JSON structure
synchronously inside a web request, on shared hosting. For a firm with 40 engagements and two
years of messages that hits `max_execution_time` and returns a broken file, and FR-1.5 calls
this a trust requirement. It is also the thing that has to keep working when a tenant is expired
(§B3), which is exactly when it will be a large, angry export.

**Harvested from** Fathom's `Export` model — status row (`pending/processing/completed/failed`),
`file_path`, `error_message`, mail when ready. Do **not** take the `ShouldQueue` job; SiteGround
kills daemons. Write the row, let the existing tick pick it up.

```sql
-- migrations/017_exports.sql
CREATE TABLE IF NOT EXISTS `ptg_exports` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `requested_by`  INT UNSIGNED NULL,
    `status`        ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `file_path`     VARCHAR(500) NULL,      -- outside the web root, per FR-7.5
    `byte_size`     BIGINT UNSIGNED NULL,
    `error_message` VARCHAR(500) NULL,
    `expires_at`    DATETIME NULL,          -- purge after 7 days
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at`  DATETIME NULL,

    KEY `idx_pending` (`status`, `created_at`),
    CONSTRAINT `fk_export_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ptg_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Serve the finished file through `FileController`, never a guessable path. Log the download to
`ptg_audit_log` — FR-14.1 lists exports as an audited event. Purge expired files on the nightly
tick. Keep the table allowlist in one place; it is already hardcoded in `FirmController` and
should move to the service so it cannot drift.

**Effort:** ~250 lines, mostly moving code that exists.

## B6. Saved filters

Small. A coach with 40 engagements wants "my overdue, client-side, this quarter" as one click.
Fathom's `filters` table is the whole idea: `user_id`, scope, `name`, JSON `params`.

```sql
CREATE TABLE IF NOT EXISTS `ptg_saved_views` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `surface`    VARCHAR(40) NOT NULL,      -- 'tasks' | 'orgs' | 'documents'
    `name`       VARCHAR(80) NOT NULL,
    `params`     JSON NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_surface_name` (`tenant_id`, `user_id`, `surface`, `name`),
    CONSTRAINT `fk_savedview_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ptg_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Validate `params` against an allowlist on read, not just on write.** A saved filter is stored
user input that later becomes query conditions; the repository's column validation must see it.

**Effort:** ~150 lines.

## B7. A real navigation component

**Gap.** There is no nav component — grep for `sidebar` in `src/Views/` returns nothing. SPEC.md
§9 lists roughly 24 coach-side and client-side screens. That does not survive without one.

**Harvested from** `taxcrm/templates/partials/nav-crm.php`: a collapsible accordion grouped by
functional area, with per-link role guards, active section auto-opened, other sections restored
from `localStorage`. Small and boring and the screen inventory needs it.

Two Pilotage-specific requirements the source does not have:
- Every link is filtered through `Policy::can()`, not a role name list. SPEC.md §8 is explicit
  that there is no "is admin, therefore yes" shortcut, and a nav built on role strings
  reintroduces one in the one place users judge the product's coherence.
- Coach-side and client-side get separate navs, not one nav with hidden items. A client-side
  user must never receive markup describing screens they cannot reach.

**Effort:** ~200 lines.

---

# §C — Deliberately not built here (assigned to Kokoro or dropped)

Recorded with reasons so these are not re-litigated. See `KOKORO-SOFTWARE-SPEC.md` for the ones
that went to Kokoro.

- **Availability, slot generation, self-service booking, appointment records.** → Kokoro. See §A1.
- **Coach-to-client invoicing, payment links, packages, session credits.** FR-13.5 already rules
  invoicing out. Kokoro has all four and they are the right shape for a per-session practice,
  not for a per-engagement one.
- **Clinical anything** — SOAP notes, superbills, consent waivers, PHI encryption casts. Wrong
  threat model. Pilotage holds commercially sensitive material, not protected health information,
  and encrypting columns at rest would cost the searchability that FR-7.7 requires while
  defending against a threat this product does not face.
- **TaxCRM's `WorkflowEngine`** (event → conditions → actions). It would be a second, weaker
  rules engine sitting beside the playbook engine, which is the product's whole differentiator.
  Extend playbook gating (FR-4.4) instead.
- **E-signature.** FR-7.9 rules it out and the acknowledgment path in §A3 covers the agreement
  case. TaxCRM's `ESignature` class exists and is tempting; the token/expiry/view-log mechanics
  it uses are already what `ptg_share_links` does.
- **Bizorca SSO.** SPEC.md §12.1 decision 2. Both Foundry and Fathom have working clients;
  neither ports. Client-side users belong to the coach, not to Bizorca.
- **Fathom's reactions, pins, QR codes, public share tokens.** Not in the spec, and share links
  already exist.
- **Kokoro's Sanctum REST API.** Phase 3 (FR public API). Its route shape is a useful reference
  when that starts; nothing to do now.

**Foundry is fully harvested and can retire.** Verified against the codebase: scope items, change
requests, the onboarding-step proto-playbook, and boards/cards are all present in Pilotage, and
`consulting_applications` shipped as `ptg_intake_submissions` in migration 012. Two scraps remain,
both cheap:

- **Intake qualification floor.** Foundry's landing page refuses businesses under $250K. Pilotage
  collects `revenue_band` including `under_250k` and does nothing with it. Add a per-tenant
  threshold that auto-flags below-floor enquiries in the queue (flag, never auto-decline — the
  coach decides). ~40 lines.
- **Scope creep as a visible count.** Foundry flagged `is_original_scope=0` on cards and counted
  it in the UI. Pilotage has `is_original` on scope items but no reconciliation view. FR-4.15
  wants "what we agreed to" and "what we've handed over" on one screen; that screen is the
  renewal and dispute conversation and it should not require assembling anything by hand.

---

# §D — One decision that must be made before M12 or M13

`CLAUDE.md` says zero Composer dependencies so deploy stays "rsync the files." `SPEC.md` §7 says
PDF via DOMPDF, and FR-12.6 requires PDF export. A Stripe integration conventionally wants
`stripe/stripe-php`. Those cannot all be true.

**Option 1 — hold the line at zero dependencies.** Stripe over cURL: Checkout Session create,
Billing Portal session create, and webhook verification via `hash_hmac('sha256', ...)` against
the `Stripe-Signature` header with a timestamp tolerance. That is roughly 250 lines and is
genuinely fine; the API surface actually used is small. PDF becomes server-rendered HTML with a
print stylesheet and a "Save as PDF" instruction, or a one-page HTML artifact the client prints.

**Option 2 — accept `composer install` on deploy** for `dompdf/dompdf` and `stripe/stripe-php`,
and update `deploy.sh` and `CLAUDE.md` accordingly.

**Recommendation: Option 1 for Stripe, Option 2 only if FR-12.5's engagement report proves to
need real PDF.** Stripe's REST surface is stable and small, and hand-rolling the HMAC check is a
one-afternoon job with a clear test. PDF is the weaker case for purity — FR-12.5 describes the
renewal conversation as a document, and a print stylesheet does not survive being emailed. Decide
per-dependency rather than as a blanket policy, and write the decision into `SPEC.md` §12.1 when
you make it.

One implementation note harvested from Kokoro, worth more than any code: their
`customer.subscription.updated` handler silently failed to sync plans on upgrade and downgrade
until they retrieved the subscription with `expand: items.data.price`. The webhook is the sole
source of truth for plan state — never set it from the checkout success redirect.
