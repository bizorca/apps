# Pilotage — Software Specification

**Name:** Pilotage — `pilotagehq.com`
**Type:** Multi-tenant SaaS for business coaches, business advisors, and management consultants
**Stack:** Vanilla PHP 8.2, MySQL/PDO, Tailwind CSS via CDN, Alpine.js — no framework, no build step
**Status:** Live in production · **free during beta, nothing is charged** · every module M1–M14 built, plus calendar sync and cohorts from Phase 3 · Phase 1.5 (first real engagement) still the gate
**Repo:** github.com/bizorca/pilotage (private)
**Spec written:** 2026-08-06 · **Last updated:** 2026-08-07

---

## 1. What this is

A shared workspace for a coaching or advisory engagement. The coach runs their methodology through it; the client business does the work in it; both sides see the same scoreboard.

Three things make it different from a generic project tool or a life-coaching app:

1. **The client is a company, not a person.** A business owner, a COO, and a controller may all need access to the same engagement with different visibility. Nearly every coaching platform on the market assumes one coach, one human client. Business advisory doesn't work that way.
2. **The methodology is a first-class object.** Coaches don't want a blank task list — they want their process (EOS, Scaling Up, Pinnacle, StratOp, or their own homegrown 12-week program) encoded once as a playbook and instantiated for every client. That's the "step-by-step process guidance" requirement, and it's the core of the product.
3. **Accountability is a closed loop, not a reminder email.** A commitment made in a session becomes a tracked task, becomes a nudge, becomes a check-in, becomes the first agenda item of the next session, and — if it's missed twice — becomes an issue to be solved rather than a quiet failure.

### 1.1 Positioning against what exists

CoachAccountable is the closest functional analogue: actions, metrics, session notes, worksheets, comment-on-anything. It's built for individual coaching and its business-advisory story is thin. Simply.Coach, Paperbell, Practice, CoachVantage and Delenta are scheduling-and-billing-first with light delivery. EOS-specific tools (Ninety, Traction Tools, Bloom, Strety) and Scaling Up tools (Align, Rhythm) are excellent but locked to one methodology and sold to the *company*, not to the *advisor* running a book of them.

The gap: a multi-client, multi-methodology console for the advisor, with a real client-side portal underneath it.

### 1.2 Relationship to Foundry

**Decided: Pilotage is Foundry's successor.** `foundry/` is single-tenant engagement management for Jassen's own consulting practice. Pilotage is the multi-tenant product version, sold to other advisors, and Jassen's practice becomes tenant #1. Foundry's concepts get ported rather than reinvented; `foundry.bizorca.com` retires or redirects once Pilotage carries the live engagements.

**What ports cleanly.** Foundry already has the shape of several Pilotage modules:

- `consulting_scope_items` → engagement scope, including the `is_original` flag distinguishing original scope from post-lock additions. Scope-lock is a real advisory need Pilotage's spec didn't originally cover; adopt it as an engagement property (`scope_locked_at`, `client_accepted_scope_at`).
- `consulting_change_requests` → the pending/approved/declined workflow with justification and review note. Port nearly as-is; it's the mechanism that keeps scope creep from eating an engagement, and it maps onto Pilotage's issues and deliverables cleanly.
- `consulting_onboarding_steps` → a proto-playbook. Keyed, ordered, completable steps per engagement, already doing in miniature what M4 generalizes. The playbook engine subsumes it; the onboarding sequence becomes a seeded starter playbook phase.
- `consulting_boards` / `columns` / `cards` / `card_steps` / `card_comments` → the kanban and its checklist and comment threads. Cards map to Pilotage tasks, card steps to the one-level subtasks in FR-6.4, and card comments to the polymorphic `comments` table.

**What has to change structurally.** Two things, and they touch everything:

1. **`consulting_engagements.client_id` points at a user.** Pilotage engagements point at a client *organization* with many contacts. This is the central premise of the product and it is not a migration detail — every downstream query, permission check, and view changes.
2. **`application_id` is NOT NULL**, so every Foundry engagement requires an inbound application. Pilotage coaches create engagements directly, from a prospect record, or from an import. The application becomes optional intake, not a prerequisite.

Also absent from Foundry and net-new here: the tenancy layer, the playbook engine, sessions and notes, goals and metrics, worksheets, document delivery and acknowledgment, and the entire client-side portal. Treat the port as harvesting four good ideas, not as a code migration.

**Data migration — none required.** Audited the live Foundry database on 2026-08-06 (read-only, against `dbhjebk3bzjbs5` on gcam1203). Every table is empty: zero engagements, zero users, zero scope items, zero change requests, zero boards, columns, cards, card steps, and card comments. The single row anywhere in the schema is a spam application submitted 2026-08-05 — randomized field values, a throwaway sender domain, a placeholder URL.

Foundry was built but never carried a live engagement. That removes the migration work entirely and simplifies the port: Foundry is a **design reference**, not a data source. Nothing needs preserving, `foundry.bizorca.com` can retire on whatever schedule suits, and the four harvested ideas above stand on their own merits rather than on compatibility with existing rows.

---

## 2. Research basis

The functional requirements below are drawn from these sources rather than invented:

- **EOS / Traction** — Rocks (3–7 quarterly priorities), the Scorecard (5–15 weekly numbers), the Level 10 meeting's fixed 7-part agenda, and the IDS track (Identify, Discuss, Solve) for issues. Pilotage generalizes these into quarterly goals, KPI scorecards, session templates, and an issues list.
- **Scaling Up / Rockefeller Habits** — the One-Page Strategic Plan, quarterly priorities on a 13-week cadence, and a visible KPI dashboard with trend lines. Drives the goal-and-metric model and the 13-week default engagement rhythm.
- **GROW / T-GROW** — session structure, and the rule that every session ends with specific, timed, trackable commitments, and that the *next* session opens by reviewing them. Drives the session template and the auto-generated agenda block.
- **ICF Code of Ethics and 2025 Core Competencies** — coaching agreements as an explicit artifact, the sponsor-versus-client confidentiality distinction, and the requirement to maintain, store, and dispose of records in a way that protects confidentiality and complies with applicable law. Drives the agreement module, the private-notes model, and the retention policy engine.
- **CoachAccountable** — actions, metrics, worksheets, session notes, and comment threads attached to every object. Directly informs the artifact model.

Sources are listed in §16.

---

## 3. Personas and roles

**Firm Owner** — buys the subscription, owns branding and billing, sees every engagement in the tenant, manages seats and playbooks.

**Coach / Advisor** — the working seat. Runs a book of 5–40 client organizations. Lives in the console: today's sessions, overdue commitments, at-risk clients.

**Associate / Analyst** — supports a coach. Can draft deliverables and see assigned engagements, but cannot see the coach's private notes or firm-wide financials.

**Client Owner** — the business owner or CEO at the client organization. Full visibility into their own engagement. Can invite their own team members.

**Client Team Member** — a COO, controller, or department head at the client organization. Sees only what's shared with them or assigned to them.

**Observer / Sponsor** — a paying third party (a PE firm, a parent company, an SBDC program manager) with read-only access to progress and completion, but *not* to session content. This is the ICF sponsor distinction, and it must be enforced in code, not by convention.

**Platform Admin** — Bizorca-side. Tenant provisioning, support impersonation (logged, consented), system playbook library.

---

## 4. Domain model

The spine of the system, in dependency order:

- **Tenant** — a coaching firm or solo practice. Owns branding, playbooks, users, billing.
- **User** — always belongs to exactly one tenant (whose world they live in). A `client_org_id` decides which side of the wall they stand on: unset is firm-side staff, set is a client-side contact, and the role must agree with the side.

  *Corrected during Phase 0.* This originally read "belongs to exactly one tenant **or** to one client organization, never both." Building the schema showed that to be wrong: a client organization already belongs to a tenant, so leaving `tenant_id` unset for client-side users would put every one of those rows outside the tenant-scoping mechanism that protects everything else — the one place you least want an exception. See `migrations/001_tenancy.sql`.
- **Client Organization** — the business being advised. Has an industry, size, fiscal year, and a set of contacts.
- **Engagement** — the unit of work. One coach (plus optional associates) × one client organization × one playbook instance × a date range and a commercial arrangement. Everything else hangs off an engagement.
- **Scope Item** — an agreed deliverable or workstream, original or added by change request. Optional per engagement.
- **Change Request** — a proposed scope addition with a justification and a review decision.
- **Playbook** — a versioned methodology template. Phases → Steps → attached artifact templates.
- **Playbook Instance** — a snapshot of a playbook applied to an engagement. Editing the template does not mutate live engagements.
- **Step** — one unit of process guidance, with completion criteria and gating.
- **Session** — a scheduled meeting with an agenda, shared notes, private notes, and outcomes.
- **Task** — a commitment. Owner (coach-side or client-side), due date, definition of done, status.
- **Goal** — a 90-day priority (a Rock), with milestones and an on-track/off-track status.
- **Metric** — a KPI definition plus a time series of periodic values. The scorecard is a set of metrics with targets.
- **Issue** — a problem parked for structured resolution.
- **Document** — a file, versioned, with a delivery and acknowledgment lifecycle.
- **Worksheet** — a structured form assigned to a client and submitted back.
- **Thread / Comment** — messaging, both standalone and attached to any object above.
- **Note** — coach-private or shared, attached to an engagement, session, or contact.

Two rules that shape everything: **every artifact belongs to exactly one engagement**, and **every artifact carries an explicit visibility flag**. No inference, no defaults that leak.

---

## 5. Functional requirements

### M1 — Tenancy, branding, onboarding

- **FR-1.1** Each tenant gets a subdomain: `firmname.pilotagehq.com`. Path-prefix tenancy is rejected because client-side users need a URL that looks like their coach's brand, not ours.
- **FR-1.2** Tenant branding: logo, primary color, accent color, sender name for email, optional custom domain via CNAME. Client-facing surfaces render tenant branding; the platform brand appears only in a small footer credit (removable on higher tiers).
- **FR-1.3** Firm onboarding wizard: firm profile → invite coaches → pick or import a starting playbook → add first client organization → send first invitation. Target: first client invited within 15 minutes.
- **FR-1.6** Public marketing site on the apex host, and **self-serve firm signup** — shipped 2026-08-08.

  Landing page, pricing grid, about and FAQ live at `pilotagehq.com` and **only** there. `MarketingController::apexOnly()` 404s every one of them on a tenant subdomain: a firm's client landing on `theirfirm.pilotagehq.com/pricing` and seeing Pilotage's own marketing would undo the point of subdomain tenancy. `robots.txt` is generated per origin for the same reason — the apex invites crawlers to the marketing pages, every tenant host disallows everything.

  Signup creates the tenant and its owner with no approval step. `Services\Provisioning` is the only code path that creates a tenant, and it does so in one transaction: a tenant row with no owner is unreachable *and* permanently burns a slug, so half a firm is worse than none. New firms start `status='trial'`, `plan='trial'`, `billing_status='trialing'` with the trial clock started, which is what M13 already reads — nothing about the gate changes.

  Identity crosses to the subdomain on a single-use magic link, because session cookies are scoped to the exact tenant host and an apex session is invisible where the firm actually lives. Reusing the audited sign-in path beats a bespoke handoff nobody has attacked.

  Email is deliberately **not** verified before the tenant exists. The owner sets a password and is signed in immediately, so a typo costs them magic-link recovery rather than access; blocking a new firm behind an inbox round-trip trades a real conversion cost against a hypothetical abuse. The rate limit (`signup`, keyed on IP because a squatter supplies a fresh email each time), the honeypot and the fill-time floor are what stand in front of it. If that stops being enough, verification goes in front of tenant *creation*, never in front of sign-in.

  Pricing is rendered from `Billing::PLANS` and the beta banner from `Entitlements::inBeta()` — never hardcoded. A marketing page that states a price the checkout disagrees with is the worst failure a pricing page has.
- **FR-1.4** Seat management — invite, deactivate, reassign a coach's book of engagements to another coach in bulk.
- **FR-1.5** Tenant data export (full JSON + files archive) available on demand to the Firm Owner. This is a trust requirement for a product holding a firm's entire client relationship.

### M2 — Identity, authentication, permissions

- **FR-2.1** Native email/password auth with bcrypt, plus magic-link login for client-side users (business owners will not remember a password for a portal they visit weekly).
- **FR-2.7** Emailed one-time links are consumed by a POST, never by a GET — shipped 2026-08-08.

  Mail security scanners (Outlook Safe Links, Mimecast, Proofpoint) issue a GET against every URL in incoming mail. A single-use token consumed by a scanner is a login that fails for its owner with no explanation and no remedy, and client-side users are businesses — exactly the population running that filtering. `MagicLink` and `PasswordReset` therefore split `preview()` from `consume()`: the GET renders a confirmation, the POST does the work, matching the shape `/unsubscribe` already used for the same reason.

  `MagicLink::DELIVERY_HANDOFF` is the sole exemption, used only by self-serve signup (FR-1.6), where the link is delivered in a `Location` header and followed by the same browser milliseconds later. It never enters a mailbox. `DELIVERY_EMAIL` is the default so that the safe behaviour is what a caller gets without deciding.

- **FR-2.9** Outbound sending throttle for new firms — shipped 2026-08-08.

  Every firm mails from the one sending domain (§7), so deliverability is shared: one account blasting invitations degrades every other firm's ability to have a sign-in link arrive, on a domain that is young and still at DMARC `p=none`. Self-serve signup (FR-1.6) means nobody vets the front door, so the control sits on mail leaving the building instead.

  `Services\SendingTrust` bands a firm by what it has actually done: **unproven** (no client organization — three invitations, lifetime), **new** (has a client, under seven days old — 25/day), **established** (250/day). `pl_tenants.vouched_at` promotes a firm by hand. The band is derived rather than stored, so it cannot go stale.

  **An approval queue was considered and rejected.** It would put a human in front of every genuine trial while filtering almost nothing — a practice name and an email address do not tell you whether a firm is real — and it would not stop the case that matters, a plausible-looking firm that spams after being approved. The throttle addresses the actual harm and costs a real customer nothing.

  Enforced in `Auth\Invitation::issue()` and nowhere else, because an invitation is the only way this product mails somebody who does not already have an account. Revoked invitations still count against the quota; refunding on revoke restores the loop being throttled.

- **FR-2.8** Password reset — shipped 2026-08-08.

  Request at `/password/forgot`, one-hour single-use token, generic response whether or not the address matched an account. Reset lives under `/password` so `Entitlements::ALWAYS_WRITABLE` lets a read-only firm recover the account it would pay from.

  **A reset token is not a sign-in credential.** Consuming one lets somebody set a password and then sends them to the sign-in page. A reset that started a session would be a two-factor bypass: anyone holding the mailbox could reset and never meet the TOTP prompt FR-2.2 makes mandatory for firm owners. That is why it is a separate table from magic links — one row type meaning both things is how the distinction gets "simplified" away later.

  The token is validated before the new password is checked, so a mistyped confirmation does not burn the link. A completed reset revokes every existing session for that user and any other outstanding reset, because someone resetting a password may be doing it precisely because another person has it.
- **FR-2.2** TOTP two-factor, mandatory for Firm Owner, optional-but-encouraged for everyone else. Client-side financial documents make this non-negotiable at the firm level.
- **FR-2.3** Invitation flow: single-use tokens, 7-day expiry, invited role baked into the token.
- **FR-2.4** Session security: HttpOnly + Secure + SameSite=Lax cookies, server-side session records, absolute timeout of 30 days with a 12-hour idle timeout for tenant-side users.
- **FR-2.5** Every authorization decision resolves against the permission matrix in §8. There is no "is admin, therefore yes" shortcut anywhere in the codebase.
- **FR-2.6** Support impersonation by Platform Admin requires an explicit reason string, is capped at 60 minutes, is written to the audit log, and displays a persistent banner to the impersonator.

### M3 — Client organizations and contacts

- **FR-3.1** Client organization record: legal name, DBA, industry (NAICS), employee count, revenue band, fiscal year end, entity type, website, address, and a free-text "situation" summary.
- **FR-3.2** Multiple contacts per organization, each with a role, title, and an access level (Client Owner / Client Team Member / no portal access).
- **FR-3.3** The Client Owner may invite their own team members, subject to a per-engagement cap the coach controls. Coaches should not be a bottleneck for their client's org chart.
- **FR-3.4** Organization-level timeline: every session, deliverable, task completion, and document delivery in one reverse-chronological feed. This is the "what's happened with this client" view a coach needs 90 seconds before a call.
- **FR-3.5** Prospects: an organization can exist in a pre-engagement state with intake forms and proposal documents attached, then convert to an active engagement without re-keying.
- **FR-3.6 Public intake.** A visitor with no advisor describes their situation on a public form and becomes a prospect of a firm. Two routes reach it: `firmslug.pilotagehq.com/apply` goes to that firm, and the bare apex `pilotagehq.com/apply` goes to a configured **house firm** — Jassen's own practice, which is tenant #1. Off by default per tenant: a firm that has not written its own copy should not have a public form quietly accepting strangers.

  Accepting a submission creates the prospect organization and its first contact in one step, carrying the enquirer's own words into the situation field. The contact gets **no** portal access — that is a decision for after a conversation, not a consequence of filling in a form. Declining keeps the record with its reason.

  The surface is unauthenticated, so spam is the design constraint rather than an afterthought: a honeypot field, a minimum fill time, and per-IP rate limiting, with rejected submissions stored and flagged rather than discarded so a pattern is visible later. A caught bot sees the same thank-you page a person does. There is deliberately no CAPTCHA — a real person is never asked to identify a traffic light. This is ported in spirit from Foundry's `consulting_applications`, the one Foundry feature Pilotage had no equivalent for.

### M4 — Playbooks and the process engine

This is the differentiating module. Build it carefully.

- **FR-4.1 Structure.** Playbook → ordered Phases → ordered Steps. A phase is a chunk of the journey ("Discovery," "Quarter 1 Execution"). A step is one thing that happens.
- **FR-4.2 Step content.** Each step carries: a title, coach-facing guidance (rich text — the "how to run this step" instructions), optional separate client-facing guidance, an estimated duration, and a completion rule.
- **FR-4.3 Attached artifact templates.** A step may attach any number of: task templates (with a role-based assignee, not a named person), document templates to deliver, worksheets to assign, a session template with a prefilled agenda, KPI definitions to begin tracking, and goal templates.
- **FR-4.4 Gating.** Each step is sequential (blocked until prior steps complete), parallel (available any time within its phase), or triggered (unlocks on a date, on session N, or on a metric threshold).
- **FR-4.5 Completion criteria.** One of: coach marks complete; all required artifacts complete; or client attests completion. Steps can be skipped with a required reason, which is logged — coaches deviate from their own process constantly and the system should record it, not prevent it.
- **FR-4.6 Instantiation.** Applying a playbook to an engagement deep-copies it. Template edits never mutate live engagements. Each instance records the source playbook version.
- **FR-4.7 Version drift.** When a playbook's source version advances, affected engagements show a "changes available" diff, and the coach chooses per-step whether to pull the update.
- **FR-4.8 Authoring.** A drag-and-drop builder, plus duplicate-and-modify from any existing playbook, plus CSV/Markdown import for coaches migrating a process out of a Google Doc. Migration friction is the single biggest adoption barrier for this product.
- **FR-4.9 System library.** Ship neutral starter playbooks — a 90-day quarterly operating rhythm, a 12-week strategic planning engagement, a business valuation readiness track, an exit-planning track. Do not ship trademarked methodology content (EOS, Scaling Up, StratOp). Ship the *shape*; let coaches fill in their own IP.
- **FR-4.10 Client-side view.** The client sees a linear progress view: where we are, what's done, what's next, what's needed from them. They do not see coach-facing guidance text.

### M4B — Scope and change control

Ported from Foundry, which got this right. Consultants and management advisors need it; pure coaches can ignore it. Scope is optional per engagement, enabled by a flag.

- **FR-4.11** An engagement carries an ordered list of scope items (title, description). Each item is flagged as original scope or as added via an approved change request.
- **FR-4.12** Scope lock: the coach locks scope, the client accepts it, and both timestamps are recorded on the engagement. After lock, scope items become read-only except through the change-request path.
- **FR-4.13** Change requests: either side submits a title, description, and justification. Status is pending, approved, or declined, with a reviewer, review timestamp, and review note. Approval appends the item to scope and stamps it with the originating request.
- **FR-4.14** Declined and pending change requests remain visible to both sides. The value of this workflow is the paper trail, not the gatekeeping.
- **FR-4.15** Scope items may link to deliverables (M7), so "what we agreed to" and "what we've handed over" reconcile on one screen. This is the renewal and dispute conversation, and it should not require assembling anything by hand.

### M5 — Sessions and meeting cadence

- **FR-5.1** Session records: scheduled datetime, duration, attendees, location/video link, type (discovery, planning, weekly, quarterly, ad hoc), and linked step.
- **FR-5.2** Session templates define a timed agenda. Ship an L10-shaped default: check-in, scorecard review, goal review, headlines, commitment review, issues (IDS), conclude. The 90-minute time box is a template property.
- **FR-5.3** **Auto-generated agenda blocks.** When a session opens, the system injects current state: metrics that are off-target, goals that are off-track, commitments due or overdue since the last session, and open issues. The coach never has to assemble this by hand.
- **FR-5.4** Dual notes: shared notes (visible to client) and private notes (coach-side only, never rendered on any client-visible surface, excluded from client exports). This must be enforced at the query layer with separate columns or tables, not a display-time filter.
- **FR-5.5** In-session capture of commitments and issues, which become Tasks and Issues without leaving the page.
- **FR-5.6** Session recap: on session close, generate a client-facing summary (shared notes + new commitments + decisions) and send it. Draft-then-send, never auto-send.
- **FR-5.7** Scheduling: coach availability windows, client self-booking links, timezone handling, ICS attachments, reminders at 24h and 1h. Two-way Google/Microsoft calendar sync is v2; ICS and a booking link cover the MVP.
- **FR-5.8** Cadence enforcement: an engagement declares a meeting rhythm (weekly, biweekly, monthly, quarterly). Missed or unscheduled cadence surfaces on the coach dashboard as a risk signal.

### M6 — Tasks and the accountability loop

- **FR-6.1** Task fields: title, description, owner (any user on either side), due date, priority, definition of done, source (session, step, ad hoc), and visibility.
- **FR-6.2** Tasks are assignable in both directions. A coach owing a client a deliverable is a first-class case; too many tools assume only the client has homework.
- **FR-6.3** Recurring tasks (weekly metric entry, monthly close).
- **FR-6.4** Subtasks and dependencies — one level of nesting only. Deeper nesting turns a coaching tool into a project-management tool nobody asked for.
- **FR-6.5 The loop.** Commitment captured → reminder before due → overdue notice → client check-in or completion → completion rate updates → next session agenda auto-includes it → **second consecutive miss auto-creates an Issue.** That last step is the design decision that separates accountability from nagging: repeated failure becomes a thing to discuss, not a bigger red number.
- **FR-6.6** Completion evidence: optional note, file attachment, or metric value required to mark done, configurable per task.
- **FR-6.7** Comment thread on every task, with email reply-to-comment.
- **FR-6.8** Views: my tasks, engagement tasks, overdue across the whole book, kanban, and calendar. The board's columns are the task lifecycle (overdue, not started, in progress, done), **not** user-defined lists — letting a coach invent columns is the first step toward the worse-Asana outcome §11 rules out, and the value here is seeing where work is stuck. The calendar shows undated commitments in a separate list rather than dropping them, since a commitment with no date is the one most likely to be forgotten.
- **FR-6.9 Tags, spanning object types.** A tag is a row referenced from tasks, documents, issues, client organizations and engagements, not a comma-separated column on each. The value of a tag is precisely that it *crosses* things: "what is happening with bank financing across all my clients?" is only answerable if one tag is shared, and the answer spans a task in one engagement, two documents in another, and an issue in a third.

  Names are slugged on the way in, so "Cash flow", "cashflow" and "CASH FLOW" reconcile to one tag. A string column could not enumerate what tags exist (so no picker, no way to spot two spellings of one idea), could not match exactly (`LIKE '%bank%'` also catches "bankruptcy"), and could not be renamed or merged. All three are why this is a table.

  **Firm-side only.** A tag crosses every client in the book, so exposing one to a client-side user reveals the shape of engagements that are not theirs, even without the contents. The board's columns are the task lifecycle (overdue, not started, in progress, done), **not** user-defined lists — letting a coach invent columns is the first step toward the worse-Asana outcome §11 rules out, and the value here is seeing where work is stuck. The calendar shows undated commitments in a separate list rather than dropping them, since a commitment with no date is the one most likely to be forgotten.

### M7 — Documents and deliverables

- **FR-7.1** Three document contexts: the tenant's **template library** (reusable), the engagement's **deliverables** (produced by the coach), and the engagement's **client files** (produced by the client).
- **FR-7.2** Deliverable lifecycle: draft → internal review → delivered → acknowledged → superseded. Acknowledgment is a timestamped, IP-logged client action. Advisors get paid on delivery and need proof of it.
- **FR-7.3** Versioning: every upload creates a new version; prior versions remain accessible to the coach and are hidden from the client unless explicitly shared.
- **FR-7.4 Document requests.** The coach creates a checklist — "last three years of tax returns, trailing-12 P&L, current cap table" — with due dates. The client sees an upload checklist with progress. Requested items auto-nag. Every advisor doing financial work needs this and almost no coaching tool has it.
- **FR-7.5** Storage outside the web root, served only through an authenticated PHP handler that re-checks permissions per request. Never a guessable path under `public_html`.
- **FR-7.6** Optional per-document expiring share links for third parties (a banker, an attorney), with a hard expiry and a view log.
- **FR-7.7** Folders, tags, and full-text search over filenames and descriptions. Content indexing of PDFs is v2. **Tags are a first-class object, not a string column** — see FR-6.9.
- **FR-7.8** Size limits: 50 MB per file by default, 10 GB per tenant on the base tier. Virus scanning via ClamAV on upload if available on the host; MIME allowlist regardless.
- **FR-7.9** E-signature is explicitly out of scope for v1. Acknowledgment-with-audit-trail covers the coaching agreement case; real signature work belongs in a dedicated tool.

### M8 — Communication

- **FR-8.1** Engagement-scoped threaded messaging between coach-side and client-side users. Asynchronous, email-notified, not real-time chat.
- **FR-8.2** Comments on every artifact — task, document, worksheet, metric, goal, session, step. This is the CoachAccountable pattern and it is the right one: conversation happens where the work is, not in a separate inbox.
- **FR-8.3** Email-in: replies to notification emails post back as comments, matched by a signed token in the reply-to address.
- **FR-8.4** @-mentions with notification, scoped to users who can already see the object.
- **FR-8.5** Announcements: a coach can broadcast to all client organizations or to a segment (all clients on a given playbook, all clients in a cohort).
- **FR-8.6** Message retention follows the tenant retention policy (§9). Deleted messages are soft-deleted with an audit record; nothing is silently destroyed while an engagement is live.
- **FR-8.7** SMS notification for high-priority items only, opt-in, via a pluggable provider. Email via SMTP2GO per house convention.

### M9 — Goals, scorecards, and issues

- **FR-9.1 Goals.** Title, owner, target date (default: end of current quarter), success criteria, status (on track / at risk / off track / done), and milestones. Default to 3–7 goals per quarter, warn above 7 — the constraint is the point.
- **FR-9.2** Quarterly rollover: at quarter end, a review screen scores each goal, carries forward or closes it, and seeds the next quarter.
- **FR-9.3 Metrics.** A metric definition has a name, unit, direction (higher/lower is better), target, frequency (weekly/monthly/quarterly), and an owner responsible for entering it.
- **FR-9.4** The scorecard is a grid: metrics down the side, periods across, actual-versus-target with on/off-target shading and a sparkline trend. 13 columns by default, matching the quarterly rhythm.
- **FR-9.5** Metric entry by the client, with a recurring reminder. Coach may enter on the client's behalf, flagged as such.
- **FR-9.6** Metrics may be computed (ratios, sums of other metrics) or manual. No accounting-system integrations in v1; a CSV import per metric covers the gap.
- **FR-9.7 Issues.** A running list per engagement, with priority, owner, and an IDS-style resolution record (what we identified, what we discussed, what we decided). Issues can originate from a session, a missed commitment, or either party ad hoc.
- **FR-9.8** A one-page engagement summary — vision, targets, quarterly goals, scorecard, top issues — renderable to PDF. This is the OPSP idea, and it's what the client pins to the wall.

### M10 — Worksheets, forms, and assessments — shipped 2026-08-07

- **FR-10.1** Form builder: short text, long text, number, currency, date, single/multi select, scale (1–10), file upload, section headers, and instructional text.
- **FR-10.2** Assignment to an individual or to every contact at a client organization, with a due date and reminders.
- **FR-10.3** Save-and-resume — business owners will not finish a 40-question assessment in one sitting.
- **FR-10.4** Scored assessments: per-answer weights, category subscores, and a results view with band-based interpretive text the coach writes once. This covers business health checks, readiness assessments, and 360-style inputs.
- **FR-10.5** Response comparison over time — assign the same assessment at month 0, 6, and 12 and chart the delta. This is the strongest ROI story a coach can show, and it should be one click.
- **FR-10.6** Anonymous mode for team-wide surveys, where individual responses are hidden even from the coach and only aggregates display above a minimum-response threshold.
- **FR-10.7** CSV export of all responses.

**As built.** Fields are rows in `pl_worksheet_fields`, not JSON in a column — a
scored assessment has to aggregate one question across many responses, and that
is a `GROUP BY`, not a document scan. Scoring normalises each scale answer to
`(value − min) / (max − min)`, weights it, and leaves unanswered optional
questions out of the denominator rather than counting them as zero.

**Anonymity is structural, not a flag.** FR-10.6 says individual responses are
hidden *even from the coach*. A visibility flag does not deliver that — anyone
with database access could still read who said what, and a coach asking their
developer nicely is exactly the pressure this has to withstand. An anonymous
response therefore stores no respondent id at all: the link is destroyed at
submission, not concealed. Consequences that follow from that choice, and are
not negotiable afterwards:

- Resume for an anonymous response rides a key held in the browser session.
  Lose the session, lose the draft. Nothing on the server can recover it.
- An anonymous worksheet cannot be assigned to one person. One respondent means
  the coach knows whose answers those are, so the assignment is refused.
- Free-text answers are reported as a count, never as words. The words identify
  people.
- Below the response threshold nothing displays at all — not even an average,
  since arithmetic over two responses de-anonymises both.

### M11 — Notifications and digests — shipped 2026-08-07

- **FR-11.1** Per-user, per-event-type channel preferences (in-app, email, SMS, off).
- **FR-11.2** Digest batching: a coach receives one morning digest, not 40 emails. Immediate delivery reserved for direct messages and @-mentions.
- **FR-11.3** Client weekly digest: what's due, what's overdue, what's new, one primary call to action. Sent on a tenant-configured day.
- **FR-11.4** All notification templates are tenant-editable with a documented variable set, and all carry tenant branding.
- **FR-11.5** Unsubscribe handling that distinguishes transactional (cannot opt out while engaged) from digest and marketing (can).

**As built.** One structural change carries the whole module: **nothing sends
mail at the moment it happens.** A module queues a row and returns; a tick
drains the queue, applies the reader's preference *at that moment*, and chooses
between sending now, holding for a digest, leaving it in the inbox, or
suppressing it. Batching, preferences and unsubscribe are all downstream of
that — none of them is possible for a message that has already left.

Auth mail is the deliberate exception and stays inline: magic links,
invitations, password confirmations, 2FA. Those answer something the person is
doing right now, they are worthless in tomorrow's digest, and no legitimate
preference turns them off.

Consequences worth not re-litigating:

- **Transactional is a property of the event, in code.** `Notifications::CATALOGUE`
  carries a `transactional` flag; the resolver returns email for those before it
  ever reads a stored preference, so neither a bug in the preferences screen nor
  a hand-edited row can silence a document a client must acknowledge. That is
  the whole of FR-11.5 and it is short on purpose.
- **The queue is the inbox.** One table, not two. A notification and the thing
  in your bell menu are the same fact, and splitting them means writing every
  event twice and reconciling read state across both.
- **A digest window is the idempotency key.** `pl_digests` is uniquely indexed
  on (tenant, user, kind, window_start), so a five-minute tick either claims the
  window or gets a duplicate-key error. Without that a coach receives their
  morning digest 288 times.
- **Windows are computed in SQL, never in PHP** — see the clock invariant in
  CLAUDE.md. A boundary computed on the wrong clock sends Monday's digest on
  Sunday evening and nobody can reproduce it.
- **Unsubscribe is a GET that shows a page and a POST that acts.** Mail clients
  and security scanners prefetch links; a one-click GET would silence people who
  never touched it.
- **No SMS**, despite FR-11.1 listing it. There is no SMS sender until Phase 3,
  and an option a user can select that then does nothing is worse than an option
  that is not offered.

### M12 — Reporting — shipped 2026-08-07

- **FR-12.1 Coach dashboard.** Today's sessions, commitments overdue across the book, clients with no session scheduled inside their declared cadence, at-risk engagements, and steps awaiting coach action.
- **FR-12.2 Engagement health score.** A composite of task completion rate, session attendance, metric entry consistency, message responsiveness, and days since last client login. Displayed as a band with the contributing factors shown — never a bare number the coach can't interrogate.
- **FR-12.3 Firm dashboard.** Active engagements, utilization by coach, playbook completion rates, revenue per engagement, churn and renewal signals.
- **FR-12.4 Client dashboard.** Progress through the playbook, goals with status, scorecard, what's due from them this week. One screen, no navigation required.
- **FR-12.5 Engagement report.** Coach-generated PDF covering a period: sessions held, deliverables produced, goals achieved, metric movement, assessment deltas. This is the renewal conversation in a document.
- **FR-12.6** All reports exportable to CSV and PDF (DOMPDF, per house convention).

**As built.** FR-12.1 and FR-12.4 shipped with Phase 1; this module added
health scoring, the firm roll-up, and the period report.

**The health score is never a bare number.** `Health::forEngagement()` returns
the score alongside its five factors — each with its own value, its own weight,
and a sentence saying what actually happened. Two rules make it trustworthy
enough to act on:

- **A factor with no evidence is excluded, not counted as zero.** An engagement
  with no metrics defined is not failing at metric entry; there is nothing to
  enter. Scoring absence as failure is how a health score becomes a number
  people learn to ignore, because the first thing it does with a new engagement
  is call it sick.
- **Enough to compute is not enough to pronounce on.** Below
  `MIN_WEIGHT_FOR_BAND` no score is published at all. Found by loading the page:
  an engagement whose only measurable signal was "somebody signed in today"
  rendered as *Healthy, 100* — arithmetically the weighted mean of everything
  measurable, and completely misleading.

Nothing is cached. A stored score goes stale silently and starts disagreeing
with the factors printed next to it, and then neither is trusted.

**PDF departs from house convention, deliberately.** DOMPDF arrives through
Composer, and this project carries zero Composer dependencies so that deploy
stays "rsync the files" with no `composer install` on the server (§7). A report
renders as HTML with a print stylesheet; the coach uses Print → Save as PDF.
Browser output is better than DOMPDF's — real fonts, correct page breaks,
working links, correct Unicode — and costs nothing to maintain. The one thing it
cannot do is generate a PDF unattended, which matters only if scheduled "email
the client their quarterly report" is ever built. Revisit then, and the answer
is headless Chrome somewhere that is not SiteGround, not DOMPDF. CSV is native
and unqualified.

**Two things FR-12.3 asks for are deliberately absent.** *Utilization by coach*
is reported as engagements and open commitments, not hours — Pilotage does not
track time, and a utilization figure invented from data that cannot support one
would have firms making staffing decisions on a fiction. *Revenue per
engagement* is absent because the platform bills the firm and never the firm's
clients (FR-13.4), so it genuinely does not know what an engagement is worth.
Both go in when a coach can enter a contract value, not before.

### M13 — Billing and subscription — shipped 2026-08-07 (needs Stripe keys to go live)

- **FR-13.1** Tenant subscription billed per active coach seat, monthly or annual, via Stripe.
- **FR-13.2** Tiers gated on seat count, storage, custom domain, white-label footer removal, and advanced assessment scoring.
- **FR-13.3** 14-day trial, no card required, with a hard but non-destructive limit at trial end (read-only, export still available).
- **FR-13.4** Client organizations are never billed by the platform and never see a platform payment screen.
- **FR-13.5 Coach-to-client invoicing is out of scope for v1.** Coaches already have QuickBooks or a billing tool, and building invoicing well is a product of its own. Revisit only if it becomes the top-requested item.

**As built.** Stripe over its REST API, not its PHP SDK — zero Composer
dependencies is what keeps deploy at "rsync the files", and the four endpoints
needed here are a curl call each. Checkout and the Billing Portal are hosted by
Stripe, so card details never touch this server.

**The limit is hard and non-destructive, and that is the load-bearing part.**
When a trial ends or a subscription lapses the workspace becomes read-only.
Nothing is deleted, nothing is hidden, clients keep the documents already
delivered to them, and **the export keeps working permanently, without asking**.
Three reasons, in order of how much they matter:

1. The export is what made trusting us with a year of client relationships
   rational (FR-1.5). Revoking it exactly when someone needs it most would
   retroactively make that a lie.
2. Holding a firm's client relationships hostage for a card number is the kind
   of thing people write about publicly, and rightly.
3. Firms come back. An intact workspace one click from writable is a renewal; an
   emptied one is a competitor's customer.

`past_due` stays writable on purpose — a card expires, a bank declines a
legitimate charge, and locking a firm out of live client work while Stripe is
still retrying produces a cancellation rather than a collection.

The gate is enforced once, in the front controller, against a short allowlist
(`Entitlements::ALWAYS_WRITABLE`). A gate with thirty call sites is a gate with a
hole in it.

**The webhook signature is the authentication.** That endpoint takes no session
and no CSRF token, so without a correct signature check it is a public "give my
firm a free subscription" API. Verification is timestamp-bound (replay), checked
with `hash_equals` (timing), and computed over the **raw** body — decoding and
re-encoding the JSON produces different bytes and a signature that can never
match. Every event is recorded before it is handled; the unique index on
`stripe_event_id` is the idempotency mechanism, because Stripe retries for three
days and will deliver the same event twice when a response is merely slow.

**`BILLING_ENFORCE` is separate from having a Stripe key**, deliberately. Wiring
Stripe up and turning on the read-only gate are two decisions, and taking them
in one step is how a live tenant gets locked out by accident.

**Currently free during beta, and Stripe is deliberately not wired up.**
`BILLING_BETA=true` is the single flag, read only by `Entitlements`, and while it
is on: nothing is charged, trials never expire, the read-only gate never fires,
seat and storage limits do not apply, and the billing screen says so instead of
showing buy buttons. Beta also wins over `BILLING_ENFORCE` if both are somehow
set — the safe reading is "do not lock anyone out of a product we are telling
them is free", and that must not depend on remembering to unset the other flag.

One flag rather than edited copy in three places, because the failure worth
avoiding is the product contradicting itself: a marketing page promising free
beta while the app tells the same firm their trial expired and they must choose
a plan. Anything that gates on a plan asks `Entitlements`; a structural test
enforces that no controller does the arithmetic itself, after the seat check on
the staff screen was found doing exactly that.

**The order when beta ends,** and it is three separate steps on purpose:
1. Create the products and prices in Stripe; put the price IDs and webhook
   secret in `.env`; register `/stripe/webhook`; walk a checkout in test mode.
2. `BILLING_BETA=false` — limits and trial expiry return, buy buttons appear.
3. `BILLING_ENFORCE=true` — the read-only gate goes live.

Doing 2 and 3 before 1 locks firms out of a product they cannot pay for.

### M14 — Administration, audit, compliance — shipped 2026-08-07

- **FR-14.1** Immutable audit log: authentication events, permission changes, document views and downloads, exports, impersonation, deletions, and visibility changes. Append-only, queryable by the Firm Owner for their own tenant.
- **FR-14.2** Per-tenant retention policy: configurable retention for engagement records after closure, with a scheduled purge job and a 30-day warning to the Firm Owner before anything is destroyed. This exists because ICF requires records to be maintained, stored, and disposed of in a manner that protects confidentiality and complies with applicable law, and because retention periods are jurisdiction-specific.
- **FR-14.3** Right-to-erasure workflow for individual users, with a documented conflict path when erasure would gut an active engagement record.
- **FR-14.4** Engagement archival: closed engagements go read-only, stay searchable, and stop counting against active limits.
- **FR-14.5** The coaching agreement is a required artifact on every engagement — uploaded or generated from a template, acknowledged by the client, and referenced from the engagement header. Ungated but visibly flagged when missing.

**As built.** One rule runs through the whole module: **destruction is announced
before it happens and recorded after.** A compliance feature that silently
deletes client records is not a compliance feature; it is the incident it was
supposed to prevent.

- **Retention defaults to keep-forever.** A default that deletes would destroy a
  firm's records because nobody visited a settings page — the single worst
  failure mode this feature has.
- **Scheduling and purging are two steps, thirty days apart.** The nightly sweep
  creates a notice and emails the owner; `purge()` acts a month later. It
  refuses to act on a notice the owner was never actually emailed about, so a
  mail outage on scheduling night cannot become a silent deletion later. That
  refusal is the safety property, and it is tested.
- **Stopping the clock requires a reason**, because "why is this record still
  here" is an auditor's question and "someone clicked cancel" is not an answer.
  Reopening an engagement cancels any pending destruction automatically.
- **Erasure defaults to pseudonymisation, not deletion (FR-14.3).** The conflict
  is the whole feature: a participant asks to be forgotten, and the commitments
  they made and sessions they attended *are* the engagement record their coach
  may be obliged to keep. Deleting the user row would cascade and take the
  record with it. So identifying details go — name, email, sign-in, and the
  denormalised author labels on every message — and the shape of what happened
  stays, attributed to "a former participant". A refusal is a legitimate outcome
  but requires a written reason; an unexplained one is indistinguishable from
  ignoring the request.
- **The coaching agreement is flagged, never gated (FR-14.5).** Blocking work
  until one is uploaded would lock out every coach who signed on paper, which is
  most of them. The waiver exists and is itself recorded.

`pl_billing_events` and `pl_audit_log` are the only tables exempt from the
tenant-cascade rule, both because the record must outlive what it records. The
isolation census asserts both exemptions rather than skipping them.

---

## 6. Non-functional requirements

**Security**
- All traffic HTTPS, HSTS on.
- CSRF tokens on every state-changing POST; `h()` escaping on all output; prepared statements only.
- Content Security Policy with no `unsafe-inline` on scripts. Note that Tailwind-via-CDN and Alpine require care here — plan for a nonce-based policy from day one rather than retrofitting.
- Rate limiting on login, magic-link issuance, invitation acceptance, and file download.
- Uploaded files stored outside the web root with randomized names; original filename kept in the database only.
- Secrets in server-side `.env`, never committed.
- Tenant isolation verified by an automated test suite that attempts cross-tenant access on every route. Multi-tenant leaks are the failure mode that ends this product; test for them mechanically.

**Privacy**
- Private coach notes and sponsor-visible content are separated at the schema level.
- No client content used for model training or analytics without explicit tenant opt-in.
- Support access requires impersonation with reason logging (FR-2.6).

**Performance**
- Coach dashboard renders under 500 ms at p95 with 40 active engagements.
- Scorecard grid renders under 300 ms for 15 metrics × 13 periods.
- Target hardware is SiteGround shared hosting. Design for it: index aggressively, avoid N+1 queries, cache computed health scores rather than recomputing per page load.

**Accessibility**
- WCAG 2.1 AA on all client-facing surfaces. Client-side users skew older and less technical than the coaches.
- Keyboard-navigable throughout; visible focus states; no color-only status encoding (on-track/off-track needs a shape or label, not just green and red).

**Browser and device**
- Last two versions of Chrome, Safari, Firefox, Edge.
- Fully responsive. The client-side portal must be genuinely usable on a phone — that's where a business owner checks their commitments. A native app is out of scope; a well-built responsive portal plus PWA install is enough.

**Reliability**
- Nightly database backup with 30-day retention; weekly restore verification.
- Documents backed up separately with the same schedule.

---

## 7. Technical architecture

Matching the Archipelago house style:

- **PHP 8.2** (SiteGround constraint — no 8.3+ syntax), front-controller MVC: `public/index.php` routes into `src/`.
- **MySQL** via raw PDO singleton. Numbered `migrations/*.sql`, idempotent `migrate.php`.
- **Tailwind CSS + Alpine.js via CDN.** No npm, no build step.
- **Namespace** `Bizorca\Pilotage\` → `src/`.
- **Table prefix** `pl_`.
- **Email** SMTP2GO. **PDF** DOMPDF. **Payments** Stripe.

**Tenancy model — decided: subdomain per firm.** Shared database, shared schema, `tenant_id` on every tenant-owned table. Enforcement lives in a base repository class that injects the tenant scope — not in individual queries, where it will eventually be forgotten. `firmname.pilotagehq.com` resolves to a tenant at bootstrap; a mismatch between session tenant and request tenant terminates the session.

This requires a wildcard DNS record and a wildcard SSL certificate on SiteGround before anything else works. Set that up in week one — it's the kind of infrastructure task that looks trivial until it blocks every other piece of testing. Per-tenant custom domains via CNAME are deferred to Phase 3 (FR-1.2), because automated certificate issuance per tenant on shared hosting is a genuinely painful problem and shouldn't gate launch.

**Domains — three owned, three jobs.** All three are registered. Each does one thing, and they must not drift into each other's roles.

- **`pilotagehq.com` — the product.** App, tenant subdomains (`firmname.pilotagehq.com`), all outbound mail, and every link inside the product. This is the only domain a client ever sees. Chosen over `getpilotage.com` because subdomain tenancy embeds the primary domain in every tenant URL permanently, and an imperative verb has no place in infrastructure a business owner visits weekly to check commitments — `acme.getpilotage.com` reads as a marketing funnel, `acme.pilotagehq.com` reads as a company. The same logic governs the sending domain: `notifications@getpilotage.com` reads promotional, which is the one bucket magic-link and reminder email cannot land in.
- **`getpilotage.com` — the spoken call to action.** Redirects to the marketing site. Exists because "go to get-pilotage-dot-com" flows from a stage, a podcast, or show notes better than spelling out H-Q. Marketing surface only; never used for product links, tenant subdomains, or email.
- **`pilotage.cc` — SMS short links.** For high-priority SMS notifications (FR-8.7) where characters are billable: `pilotage.cc/t/a8f3`. Also a defensive registration. Deliberately kept off the product and off the sending domain — .cc carries abuse history and adds avoidable spam-filter risk to a product whose core mechanic is email that must land.

Set `pilotagehq.com` as the canonical host and 301 the others. SPF, DKIM, and DMARC belong on `pilotagehq.com` only, since it's the sole sending domain.

**Directory layout**

```
public/
  index.php          front controller
  files.php          authenticated file handler
  .htaccess
src/
  Auth/              Session, CSRF, Password, MagicLink, TwoFactor
  Core/              Database, Router, Tenant, helpers
  Controllers/       one per resource
  Models/            one per entity
  Repositories/      tenant-scoped query layer
  Services/          Playbook, Accountability, Notification, Health, Export
  Views/             layout.php + per-controller templates
config/
migrations/
cron/                digests, reminders, health recompute, retention purge
```

**Background jobs — triggered by page loads, not cron (changed 2026-08-08).** SiteGround kills long-running processes, so the work was always short ticks rather than daemons or queue workers. It is now triggered by traffic rather than by a scheduler: a request that finds work due claims the slot, fires a detached request at `/_tick/{token}/{mode}`, and returns. That second request does the work with its own execution budget. The page load pays a few milliseconds normally, and roughly a quarter of a second on the one request per interval that finds work due.

A second HTTP request rather than continuing in-process because this host runs `apache2handler` — there is no `fastcgi_finish_request()`, flushing and continuing would hold an Apache worker for the whole tick, and backgrounded `exec` is killed.

**The honest cost: a site with no traffic never ticks.** Two things make that survivable. `/_tick` is reachable from outside, so any uptime monitor keeps a quiet installation alive — the URL is shown on the firm settings screen. And everything the tick does is idempotent and window-based, so a late tick does the right thing once rather than the wrong thing repeatedly: digest windows are claimed by (user, kind, window_start), `queueOnce` will not re-notify, and retention needs its notice period to have elapsed. Irregular ticking was already tolerated by design.

`/_health` and the firm settings screen both report when each mode last finished. That is not decoration: the background layer was dormant in production for a day and the only symptom was email that never arrived.

**Authentication — decided: native auth on both sides.** Coach-side accounts *could* have run through `login.bizorca.com` SSO for entitlement gating and centralized billing. They won't. Client-side accounts belong to the coach's customers, not to Bizorca, and routing a business owner through a Bizorca login page breaks the white-label promise the subdomain decision exists to protect. Kokoro set this precedent.

Consequences to build against: Pilotage owns its own password hashing, magic links, TOTP, sessions, and invitations (M2), and integrates Stripe directly rather than through `billing.bizorca.com`. Note that Foundry's `SsoController.php` and `BizorcaSSO.php` do **not** port — the identity module is net-new. If cross-selling into the rest of the Bizorca suite ever matters, add SSO later as an optional login method on the coach side only, never on the client side.

---

## 8. Permission matrix

C = create, R = read, U = update, D = delete, — = no access. "Own" means records where the user is the assignee, author, or a participant.

| Object | Firm Owner | Coach | Associate | Client Owner | Client Team | Sponsor |
|---|---|---|---|---|---|---|
| Tenant settings | CRUD | — | — | — | — | — |
| Firm seats (users) | CRUD | — | — | — | — | — |
| Client portal access | CRUD | CRUD | CR | C own org | — | — |
| Playbook templates | CRUD | CRU | R | — | — | — |
| Engagement | CRUD | CRUD own | R assigned | R own | R own | R own |
| Playbook instance / steps | CRUD | CRUD | RU assigned | R client-facing | R client-facing | R progress only |
| Scope items | CRUD until lock | CRUD until lock | CRU until lock | R + accept | R | R |
| Change request | CRU + review | CRU + review | CR | CR | — | — |
| Session (shared notes) | CRUD | CRUD | RU assigned | R | R if attendee | — |
| Session (private notes) | — | CRUD own | — | — | — | — |
| Task | CRUD | CRUD | CRU assigned | CRU own org | RU own | — |
| Document (deliverable) | CRUD | CRUD | CRU | R delivered | R if shared | R if shared |
| Document (client file) | R | R | R | CRUD | CRU own | — |
| Document request | CRUD | CRUD | CRU | R + fulfill | R + fulfill | — |
| Message thread | CRUD | CRUD | CRU assigned | CRUD | CRU own | — |
| Goal | CRUD | CRUD | CRU | CRU | R | R |
| Metric definition | CRUD | CRUD | CRU | R | R | R |
| Metric value | CRU | CRU | CRU | CRU | CRU if owner | R |
| Issue | CRUD | CRUD | CRU | CRU | CRU own | — |
| Worksheet template | CRUD | CRUD | CRU | — | — | — |
| Worksheet response | R | R | R | CRU own org | CRU own | — |
| Audit log | R | — | — | — | — | — |
| Billing | CRUD | — | — | — | — | — |

**The firm owner column was read-only until Phase 1 closed, and that was wrong.** The original reading gave a firm owner `R` on every engagement-level object — scope, sessions, tasks, documents, goals, metrics, issues — and no access to threads at all. Defensible in a multi-coach firm; fatal in a solo one, where the owner *is* the coach. It left a one-person practice unable to run an engagement at all, which was found by logging in as an owner and watching every button return 403. Firm owners now hold every operational permission a coach does.

The one exception that survives is `Session (private notes)`: an owner still cannot read them. Owning the subscription is not the same as being party to a particular coaching conversation, and a coach's private read on a client should not be visible to their boss.

**Firm seats vs client portal access were one row until M3.** Building the contact workflow showed that merging them makes a coach unable to invite their own client's CFO to the portal — the most ordinary act in the product — while merging them the other way would let any coach mint billable firm seats. They are different decisions with different blast radius, so they are different objects. This is the kind of thing only wiring the real screen surfaces.

**`Worksheet response` R does not mean what it looks like.** On an anonymous
worksheet, the coach's read grants aggregates and nothing else — and that limit
is not enforced by the matrix. The response simply does not record who wrote it
(FR-10.6), so there is no permission that could be widened to reveal it. Read
the row as "R, and on anonymous worksheets there is nothing else to read."

The Sponsor row is the one to get right. A sponsor sees that the engagement is progressing and that goals are being hit. A sponsor never sees session notes, messages, or issues.

---

## 9. Screen inventory

**Coach side**
Dashboard · Client organization list · Organization detail with timeline · Engagement workspace (tabbed: overview, playbook, sessions, tasks, documents, scorecard, goals, issues, messages, notes) · Session runner (the live in-meeting screen) · Playbook builder · Worksheet builder · Document library · Reports · Firm settings · Audit log

**Client side**
Portal home (progress, what's due, next session) · Journey view (playbook progress) · My tasks · Documents (received and requested) · Scorecard entry · Goals · Messages · Sessions (upcoming, past recaps) · Worksheets · Profile and team

**Shared**
Login · Magic link · Invitation acceptance · Two-factor setup · Notification preferences

The session runner deserves special attention. It's a single screen the coach drives during a live meeting: agenda with a timer, auto-populated review blocks, note-taking that produces tasks and issues inline, and a one-click close that generates the recap. If that screen is good, the product is good.

---

## 10. Data model sketch

Core tables, `pl_` prefixed. Every tenant-owned table carries `tenant_id`, `created_at`, `updated_at`.

- `tenants`, `tenant_settings`, `tenant_branding`
- `users` (`tenant_id` nullable, `client_org_id` nullable, exactly one set), `user_sessions`, `user_2fa`, `invitations`, `magic_links`
- `client_orgs`, `client_contacts`
- `engagements`, `engagement_members`, `engagement_cadence`
- `scope_items`, `change_requests` (ported from Foundry)
- `playbooks`, `playbook_versions`, `playbook_phases`, `playbook_steps`, `playbook_step_artifacts`
- `engagement_playbooks`, `engagement_phases`, `engagement_steps` (instantiated snapshots)
- `sessions`, `session_attendees`, `session_agenda_items`, `session_notes_shared`, `session_notes_private`
- `tasks`, `task_subtasks`, `task_reminders`
- `goals`, `goal_milestones`, `goal_reviews`
- `metrics`, `metric_values`
- `issues`, `issue_resolutions`
- `documents`, `document_versions`, `document_deliveries`, `document_acknowledgments`, `document_requests`, `document_request_items`, `share_links`
- `worksheets`, `worksheet_fields`, `worksheet_bands`, `worksheet_assignments`, `worksheet_responses`, `worksheet_answers`, `worksheet_subscores`
- `notifications`, `notification_prefs`, `notification_templates`, `digests`, `unsubscribe_tokens`
- `threads`, `messages`, `comments` (polymorphic: `object_type` + `object_id`)
- `notifications`, `notification_preferences`, `email_log`
- `health_scores` (materialized per engagement, recomputed nightly)
- `audit_log`, `retention_policies`
- `subscriptions`, `stripe_events`

Two notes. `comments` is polymorphic by design — FR-8.2 requires commenting on everything, and a table per object type is worse. Index it on `(tenant_id, object_type, object_id)`. And session notes live in two tables rather than one table with a flag, because a single missed `WHERE` clause on a flag column is a confidentiality breach.

---

## 11. Delivery phases

**Phase 0 — Infrastructure (days, not weeks) — code complete 2026-08-06, infrastructure pending**
Wildcard DNS and wildcard SSL on `pilotagehq.com` · 301s from `getpilotage.com` and `pilotage.cc` · SPF, DKIM, and DMARC on the sending domain · tenant bootstrap and resolution · the tenant-scoped base repository · the cross-tenant access test suite.

Nothing else can be tested honestly until subdomain routing works, and the isolation tests need to exist before there's a codebase large enough to hide a leak in. Get mail authentication right at the same time rather than later — a sending domain that starts out landing in spam takes months to rehabilitate, and this product cannot function if its email doesn't arrive.

**Phase 1 — MVP (the thing worth using alone)**
Tenancy and branding · auth for both sides · client organizations and contacts · engagements · scope and change control (ported, §1.2) · playbook builder and instantiation · sessions with dual notes and the session runner · tasks with the full accountability loop · documents with delivery, acknowledgment, and requests · engagement-scoped messaging with comments · coach and client dashboards.

That's the smallest set where a coach can run a real engagement end to end. Everything before that is a demo.

**Phase 1.5 — First real engagement (was: Foundry migration)**
The migration half is cancelled — the Foundry audit found no data to move (§1.2). What remains is the part that actually mattered: run a real engagement through Pilotage as tenant #1 before Phase 2 starts. Set up the firm, add a real client organization, encode an actual process as a playbook, hold real sessions, assign real commitments, and deliver a real document.

Keep this gate. Dogfooding a coaching product with an actual client surfaces workflow gaps no spec catches, and Foundry is the cautionary tale sitting right there — a tool built to completion and never run in anger, whose gaps therefore never surfaced.

**Phase 2 — The advisory layer — COMPLETE 2026-08-07**
Goals and quarterly rollover · metrics and the scorecard · issues with IDS · worksheets and scored assessments · engagement health scoring · reporting and exports · digests and notification preferences · billing · administration, audit and compliance. Every module M1–M14 in §5 is built.

Note that "complete" means the code exists and is tested, not that it has been used. Billing takes no money until Stripe keys are in place and `BILLING_ENFORCE` is turned on, and Phase 1.5 is still unrun.

**Phase 3 — Scale and polish**
Shipped: ~~firm dashboard and utilization~~ (M12) · ~~assessment comparison over time~~ (M10) · ~~anonymous team surveys~~ (M10) · ~~playbook version drift~~ (M4) · ~~calendar two-way sync~~ (2026-08-07) · ~~announcements and cohorts~~ (2026-08-07).

**Calendar two-way sync — shipped 2026-08-07.** Google and Microsoft, OAuth and
REST over curl, no SDK. Runs on the five-minute tick, per connected user. Four
rules, each tested on its own, and all four are decisions rather than accidents:
last writer wins and the overwrite is recorded; only the *time* comes back from
the provider, never the title; a remote delete unlinks rather than cancelling,
and does not recreate the event on the next tick; echoes are suppressed by
digesting what was last pushed. Refresh tokens are encrypted at rest — sync
refuses to offer itself at all when no key is configured. Setup, and the honest
cost of exact redirect-URI matching against subdomain tenancy, are in
`docs/calendar-sync.md`.

**Cohorts — shipped 2026-08-07.** Group coaching: several client organizations
running one playbook together, meeting together.

The design rests on one sentence. **A cohort is a coordinating layer above
engagements, not a container.** Every member keeps its own engagement, and
membership grants access to exactly three things: the shared sessions, material
the coach deliberately published, and the roster if the coach turned it on.
Everything else about a member — commitments, documents, metrics, goals, issues,
messages — is as invisible to its peers as it was before the cohort existed.

That shape was chosen over a container precisely because a container would mean
every existing scoped query needing a new "…or shared with my cohort" branch,
and one missed branch is a cross-client leak. Here there are no branches to
miss, and `tests/CohortTest.php` attacks the wall directly rather than assuming
it holds.

Consequences worth not re-litigating:

- **The roster is hidden by default.** In a peer mastermind it is the point; in
  a group assembled from clients who do not know each other, publishing it is a
  breach of confidence. That must never be what happens when a coach does
  nothing.
- **Only library documents can be published to a cohort.** A deliverable belongs
  to one client and a client file belongs to one client; either becoming cohort
  material would hand one member's document to its peers.
- **Cohort sessions reuse `pl_sessions`** with a nullable `engagement_id` and a
  CHECK that exactly one of engagement/cohort is set. The alternative was four
  duplicated tables and a second session runner. The safety property that makes
  it comfortable: every existing engagement query INNER JOINs the engagement, so
  a cohort session drops out of all of them automatically.
- **Leaving is recorded, not deleted** — "who was in the room in March" has to
  stay answerable, because a session's notes were shared with whoever was a
  member at the time.
- A coach still has to be careful about one thing software cannot fix: shared
  notes on a cohort session are read by every member. The screens say so.

**Custom domains — will not be built.** Decided 2026-08-07. Every firm lives on
a subdomain of `pilotagehq.com`, permanently. Custom domains would need
per-tenant certificate provisioning, which is a manual step on the current host,
and they would multiply the OAuth redirect-URI problem that calendar sync
already has. The plan feature flag that anticipated them has been removed rather
than left dangling — advertising a capability that will not exist is worse than
not having it. Moved to the list below.

**SMS — deferred, not abandoned.** Wanted; not now. The groundwork is in place
and was built deliberately: `pilotage.cc` is registered and reserved for SMS
short links alone, `short_url()` exists, and SPEC §7 already explains why the
sending domain and the short-link domain must differ. What is missing is a
sender (Twilio or SMS2GO), phone-number capture and verification, and an opt-in
record.

The one thing NOT to do in the meantime: SMS is deliberately absent from the
notification channel enum in `pl_notification_prefs`. An option a user can
select that then silently does nothing is worse than an option that is not
offered. It goes into the enum in the same change that adds the sender, and not
before.

**Deliberately later or never** (§11 below) is a different list, and none of Phase 3 belongs on it.

**Deliberately later or never**
Coach-to-client invoicing · e-signature · real-time chat · video conferencing (link out to Zoom/Meet) · native mobile apps · accounting-system integrations · a marketplace connecting coaches to clients · **custom domains** (decided against 2026-08-07, see above).

**Still wanted, just not yet:** SMS (see above) · public API and webhooks.

---

## 12. Decisions

### 12.1 Decided (2026-08-06)

1. **Foundry relationship — successor.** Pilotage is the multi-tenant product version of Foundry. Port the four good ideas (§1.2), rebuild the rest. Jassen's practice becomes tenant #1; `foundry.bizorca.com` retires or redirects once live engagements have moved.
2. **Authentication — native on both sides.** No Bizorca SSO. Pilotage owns identity end to end and integrates Stripe directly. Rationale and consequences in §7.
3. **Tenancy — subdomain per firm.** `firmname.pilotagehq.com`, shared schema with `tenant_id` scoping in a base repository. Wildcard DNS and wildcard SSL are week-one infrastructure. Custom domains deferred to Phase 3.
4. **Name — Pilotage, at `pilotagehq.com`.** A harbor pilot boards a ship at the harbor mouth, guides it through water only they know, and steps off once it's berthed. That's an advisory engagement, including the exit. Sets the namespace (`Bizorca\Pilotage\`), the table prefix (`pl_`), the tenant subdomain pattern, and the deploy paths.

   Chosen over Trimtab, which had the better visual metaphor but four incumbents already using the name in leadership coaching and consulting — and "the trim tab principle" is a commonplace in that vertical, not a differentiator. Cadence was eliminated earlier: 44 of 46 candidate domains taken, and Cadence Design Systems holds cadence.com, .io, and .co with every reason to defend a software mark.

   Clearance: a USPTO search shows Pilotage apparently clear. Three companies operate under the name — Pilotage Software Inc (Toronto IT services), Pilotage Inspired Innovation (Toronto IT consulting), and Pilotage Group LLC (pharma leadership training) — none a coaching platform or a software product with mindshare.

   Domains registered: `pilotagehq.com` (product, tenant subdomains, all mail), `getpilotage.com` (spoken CTA, redirects to marketing), `pilotage.cc` (SMS short links, defensive). Roles and rationale in §7.

   Known weakness to design around: in B2B software "pilot" connotes a trial deployment, which is the wrong frame for permanent practice infrastructure. Handle it in the tagline and positioning, not the name.

### 12.1b Decided (2026-08-08)

5. **Signup is self-serve, with no approval queue.** Anyone can create a firm and be working inside a minute. An approval step was considered and rejected: it puts a human in the path of every trial, and the abuses it would catch (squatting, junk tenants) are better handled by rate limiting and reserved slugs than by making real customers wait for a reply. Consequences and the deliberate absence of email verification are in FR-1.6.

6. **The marketing site is apex-only, enforced per action.** Not a router concern — a route table that silently means different things on different hosts is worse than an explicit check in each action. See FR-1.6.

### 12.2 Still open

7. **Starter playbook content.** Shipping generic shapes (FR-4.9) avoids trademark exposure on EOS, Scaling Up, and StratOp. Confirm that's the posture, or budget for licensing conversations. Low urgency — it doesn't block architecture, but it does block the seeded library.
8. **Pricing.** Per-coach-seat is assumed. The market runs roughly $20/mo for solo schedulers to $150–$400/mo for all-in-one platforms. A per-seat price in the $79–$149 range with unlimited client organizations positions against CoachAccountable and under the EOS-specific tools. The published grid (Solo $79, Practice $129, Firm $249) now sits on the pricing page, so changing it means changing `Billing::PLANS` and telling anyone already signed up.
None of items 7–8 block starting Phase 1.

---

## 13. Risks

- **Migration friction is the adoption killer.** Coaches have their process in a Google Doc, their clients in a spreadsheet, and their files in Dropbox. FR-4.8 import and a bulk client import are not nice-to-haves; they're the thing that determines whether anyone ever gets to value.
- **Client-side engagement decay.** Business owners stop logging in. The weekly digest, magic links, and a mobile-usable portal are the counterweights, and the health score is the early warning.
- **Scope creep toward project management.** Every request to add Gantt charts, time tracking, or resource allocation moves this toward a worse Asana. The one-level subtask limit in FR-6.4 is a deliberate wall.
- **Multi-tenant leakage.** Covered in §6, but worth repeating: this is the risk that ends the product rather than merely damaging it.
- **Shared-hosting ceiling.** SiteGround is fine for the first hundred tenants. Plan the exit to a VPS before it's urgent.

---

## 14. Success criteria

- A coach can go from signup to first client invited in under 15 minutes.
- A coach can encode an existing 12-week process as a playbook in under an hour.
- Client-side weekly active rate above 60% of invited Client Owners at 90 days.
- Task completion rate above 70% across active engagements.
- A coach running 20 engagements spends under 10 minutes a day on administrative overhead inside the tool.

---

## 15. Out of scope for this specification

Visual design system, copy and microcopy, marketing site, onboarding email sequences, support documentation, and the terms/privacy policy. All needed; none belong here.

---

## 16. Sources

- [What Is a Level 10 Meeting? — EOS Worldwide](https://www.eosworldwide.com/level-10-meeting)
- [Level 10 Meeting Agenda: How to Run Effective Meetings — EOS Worldwide](https://www.eosworldwide.com/blog/the-level-10-meeting)
- [Mastering the Rockefeller Habits and One-Page Strategic Plan — Rhythm Systems](https://www.rhythmsystems.com/blog/mastering-the-rockefeller-habits-one-page-strategic-plan-opsp)
- [10 Rockefeller Habits Checklist — Growth Institute](https://blog.growthinstitute.com/scale-up-blueprint/10-rockefeller-habits-checklist)
- [The GROW Model of Coaching and Mentoring — Mind Tools](https://www.mindtools.com/an0fzpz/the-grow-model-of-coaching-and-mentoring/)
- [How Coaches Use the GROW Model for Effective Training — Simply.Coach](https://simply.coach/blog/grow-model-effective-coaching-2)
- [ICF Code of Ethics — International Coaching Federation](https://coachingfederation.org/credentialing/coaching-ethics/icf-code-of-ethics/)
- [2025 ICF Core Competencies — International Coaching Federation](https://coachingfederation.org/credentialing/coaching-competencies/icf-core-competencies/)
- [Insights and Considerations for Ethics — ICF (PDF)](https://coachingfederation.org/wp-content/uploads/2025/03/icf-ethics-ethical-insights-and-considerations.pdf)
- [CoachAccountable — product overview](https://www.coachaccountable.com/)
- [CoachAccountable Worksheets — knowledge base](https://www.coachaccountable.com/knowledgeBase/worksheets)
- [Online Coaching Software: Features Every Coach Needs in 2026 — CoachingPortal](https://coachingportal.io/blog/online-coaching-software-features-every-coach-needs-in-2026)
- [10 Best Coaching Business Software Platforms — EasyWebinar](https://easywebinar.com/blog/coaching-business-software/)
- [EOS Implementation Wiki — Strety](https://strety.com/blog/stretys-eos-implementation-wiki/)
