-- Pilotage — 017 administration, retention, erasure (M14)
--
-- The compliance layer. Three things: a retention policy the firm sets and a
-- job that honours it, a right-to-erasure workflow that does not quietly
-- destroy an engagement record, and the coaching agreement as a first-class
-- artifact.
--
-- The thread running through all of it: DESTRUCTION IS ANNOUNCED BEFORE IT
-- HAPPENS, AND RECORDED AFTER. Every path here that deletes something warns
-- first, waits, and leaves a receipt in the audit log. A compliance feature
-- that silently destroys client records is not a compliance feature; it is the
-- incident.


-- ---------------------------------------------------------------------------
-- Retention policy, per tenant (FR-14.2).
--
-- Jurisdiction-specific by nature — ICF wants records maintained, stored and
-- disposed of in a way that protects confidentiality and complies with
-- applicable law, and "applicable law" is different in Washington than in
-- Bavaria. So this is a per-firm setting with a conservative default rather
-- than a number we picked.
--
-- Default is 0, meaning KEEP FOREVER. A default that deletes would destroy a
-- firm's records because they never visited a settings page, which is the
-- single worst possible failure mode for this feature.
-- ---------------------------------------------------------------------------
ALTER TABLE `pl_tenants`
    ADD COLUMN `retention_months` SMALLINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'months to keep a CLOSED engagement before purge; 0 = keep forever'
        AFTER `client_digest_enabled`,
    ADD COLUMN `retention_confirmed_at` DATETIME NULL
        COMMENT 'when an owner last actively confirmed the policy'
        AFTER `retention_months`;


-- ---------------------------------------------------------------------------
-- Scheduled destruction, and its 30-day warning (FR-14.2).
--
-- Nothing is purged that has not sat on this table for the notice period with
-- a warning already sent. The row IS the warning: created by the sweep,
-- announced to the owner, and only acted on once `purge_after` passes.
--
-- `cancelled_at` matters more than it looks. A firm that reopens an engagement,
-- or simply says "not that one", must be able to stop the clock — and the
-- record of them stopping it is what answers "why is this still here" during
-- an audit.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_retention_notices` (
    `id`        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,

    `engagement_id` INT UNSIGNED NOT NULL,
    `closed_at`     DATETIME NOT NULL,   -- what started the clock
    `purge_after`   DATETIME NOT NULL,   -- nothing happens before this

    `notified_at`   DATETIME NULL,       -- when the owner was told
    `purged_at`     DATETIME NULL,
    `cancelled_at`  DATETIME NULL,
    `cancelled_by`  INT UNSIGNED NULL,
    `cancel_reason` VARCHAR(255) NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- One live notice per engagement. A sweep that ran twice must not schedule
    -- the same destruction twice.
    UNIQUE KEY `uq_engagement` (`tenant_id`, `engagement_id`),
    KEY `idx_due` (`tenant_id`, `purge_after`, `purged_at`, `cancelled_at`),

    CONSTRAINT `pl_fk_retention_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_retention_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_retention_canceller`
        FOREIGN KEY (`cancelled_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Right to erasure (FR-14.3).
--
-- The interesting part of this feature is not the deleting. It is the CONFLICT:
-- a client-side participant asks to be erased, and the commitments they made,
-- the sessions they attended and the documents they acknowledged are the
-- engagement record their coach may be professionally obliged to keep.
--
-- So a request is a workflow with a documented decision, not a button. It can
-- be fulfilled, or refused with a reason, or — most often — fulfilled by
-- PSEUDONYMISATION: the person's identifying details go, the shape of the
-- record stays. "Dana Owner completed 14 commitments" becomes "a former
-- participant completed 14 commitments", which erases the person without
-- rewriting history.
--
-- `conflicts` records what stood in the way, as JSON, at the moment the request
-- was assessed. Recomputing it later would answer a different question.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_erasure_requests` (
    `id`        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,

    `subject_user_id` INT UNSIGNED NULL,   -- nulled once erased; label survives
    `subject_label`   VARCHAR(255) NOT NULL,  -- who this was about, for the record
    `subject_email`   VARCHAR(255) NULL,      -- cleared on fulfilment

    `requested_by` INT UNSIGNED NULL,
    `reason`       VARCHAR(500) NULL,

    `status` ENUM('open','fulfilled','pseudonymised','refused') NOT NULL DEFAULT 'open',

    `conflicts`   JSON NULL,             -- what was in the way, when assessed
    `decision`    VARCHAR(1000) NULL,    -- what was decided, and why
    `decided_by`  INT UNSIGNED NULL,
    `decided_at`  DATETIME NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_open` (`tenant_id`, `status`, `created_at`),

    CONSTRAINT `pl_fk_erasure_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_erasure_subject`
        FOREIGN KEY (`subject_user_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_erasure_requester`
        FOREIGN KEY (`requested_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_erasure_decider`
        FOREIGN KEY (`decided_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- The coaching agreement (FR-14.5).
--
-- A pointer, not a copy: the agreement is an ordinary document that has already
-- been delivered and acknowledged through M7, and this marks WHICH one it is so
-- the engagement header can reference it and flag its absence.
--
-- Ungated but visibly flagged, per the FR. Blocking work until an agreement is
-- uploaded would mean a coach who signed on paper — which is most of them —
-- cannot use the product at all.
-- ---------------------------------------------------------------------------
ALTER TABLE `pl_engagements`
    ADD COLUMN `agreement_document_id` INT UNSIGNED NULL
        COMMENT 'FR-14.5: which delivered document is the coaching agreement'
        AFTER `scope_locked_at`,
    ADD COLUMN `agreement_waived_at` DATETIME NULL
        COMMENT 'signed on paper, elsewhere; the flag is dismissed but recorded'
        AFTER `agreement_document_id`,
    ADD COLUMN `agreement_waived_note` VARCHAR(255) NULL AFTER `agreement_waived_at`;

ALTER TABLE `pl_engagements`
    ADD CONSTRAINT `pl_fk_engagements_agreement`
        FOREIGN KEY (`agreement_document_id`) REFERENCES `pl_documents`(`id`) ON DELETE SET NULL;

-- FR-14.4 (archived engagements stop counting against active limits) needs no
-- schema of its own: the status enum already carries 'complete', and
-- pl_engagements already has idx_tenant_status covering the query. Archival is
-- a status transition plus the read paths honouring it.
