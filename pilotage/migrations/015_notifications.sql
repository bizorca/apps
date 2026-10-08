-- Pilotage — 015 notifications and digests (M11)
--
-- THE ONE STRUCTURAL CHANGE: nothing sends mail at the moment it happens.
--
-- Until now a module that wanted to tell someone something called Mailer::send
-- inline. That works fine until a coach marks eight tasks reviewed, uploads
-- three documents and closes two issues in one sitting, and their client gets
-- thirteen emails in four minutes. FR-11.2 says a coach gets one morning
-- digest, not forty emails — and you cannot batch what has already been sent.
--
-- So every module now QUEUES a row here and returns. A tick drains the queue,
-- applies the reader's preference, and decides between sending now, holding it
-- for a digest, or dropping it. Batching, preferences and unsubscribe are all
-- downstream of that one decision.
--
-- Auth mail is the deliberate exception and does NOT come through here: magic
-- links, invitations, password-change confirmations, 2FA. Those are answers to
-- something the person is doing right now, they are worthless four hours later
-- in a digest, and there is no legitimate preference that turns them off. They
-- stay inline in the controller that mints the token.


-- ---------------------------------------------------------------------------
-- The queue, which is also the in-app inbox.
--
-- One table, not two. A notification and "the thing that appears in your bell
-- menu" are the same fact, and splitting them means writing every event twice
-- and reconciling read state across both. `delivery` records the decision the
-- dispatcher made; `read_at` is the in-app half, independent of it.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_notifications` (
    `id`        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `user_id`   INT UNSIGNED NOT NULL,      -- who reads it

    `event_type` VARCHAR(64) NOT NULL,      -- key into the catalogue in Notifications.php

    -- Rendered at queue time, not at send time. The task may be renamed or
    -- deleted before the digest goes out; what the reader is told should be
    -- what was true when it happened.
    `title`   VARCHAR(255) NOT NULL,
    `body`    TEXT NULL,
    `link`    VARCHAR(500) NULL,            -- path only, never absolute: the host is the tenant's

    `client_org_id` INT UNSIGNED NULL,      -- context, for grouping a digest by client
    `object_type`   VARCHAR(64) NULL,
    `object_id`     INT UNSIGNED NULL,

    -- The dispatcher's decision, written when the row is drained rather than
    -- when it is queued: preferences can change in between, and the reader's
    -- preference at send time is the one that should win.
    --
    --   pending    undecided; the dispatcher has not reached it yet
    --   immediate  emailed on its own
    --   digest     held for, or carried by, a digest
    --   in_app     decided: inbox only, no mail will ever be sent for it
    --   suppressed the reader switched this event off; hidden everywhere
    --
    -- in_app and suppressed are both "no email", and they are still different.
    -- One means "I read these in the app", the other means "I do not want this
    -- at all", and collapsing them would put things a reader explicitly
    -- silenced back in front of them.
    `delivery` ENUM('pending','immediate','digest','in_app','suppressed') NOT NULL DEFAULT 'pending',

    `emailed_at` DATETIME NULL,             -- when it actually left, digest or not
    `digest_id`  BIGINT UNSIGNED NULL,      -- which digest carried it
    `read_at`    DATETIME NULL,             -- in-app read state

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- The dispatcher's hot query: undecided rows, oldest first.
    KEY `idx_pending`   (`tenant_id`, `delivery`, `created_at`),
    -- The digest assembler's query: everything held for one reader.
    KEY `idx_held`      (`tenant_id`, `user_id`, `delivery`, `emailed_at`),
    -- The bell menu's query.
    KEY `idx_inbox`     (`tenant_id`, `user_id`, `read_at`, `created_at`),

    CONSTRAINT `pl_fk_notifications_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Preferences — SPARSE. A row exists only where a user diverged from the
-- default.
--
-- The alternative, a row per user per event type, means a backfill every time
-- a module adds an event, and a user who signed up before the backfill silently
-- gets nothing. Defaults live in code (Notifications::CATALOGUE) where they can
-- be read alongside the event that uses them.
--
-- No SMS in the enum, deliberately, even though FR-11.1 lists it. There is no
-- SMS sender yet (Phase 3), and an option a user can select that then silently
-- does nothing is worse than an option that is not offered. It goes in when the
-- sender does.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_notification_prefs` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `event_type` VARCHAR(64) NOT NULL,

    --   email  — send it on its own, as it happens
    --   digest — hold it for the next digest
    --   in_app — inbox only, no mail
    --   off    — do not record it at all
    `channel` ENUM('email','digest','in_app','off') NOT NULL,

    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_user_event` (`tenant_id`, `user_id`, `event_type`),

    CONSTRAINT `pl_fk_prefs_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_prefs_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- One row per digest actually sent.
--
-- This is the idempotency record, and it is why the tick can run every five
-- minutes without sending Tuesday's digest twice. `window_start` is the key:
-- a digest for a given reader and window either exists or does not, and the
-- unique index makes the second attempt a duplicate-key error rather than a
-- second email.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_digests` (
    `id`        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `user_id`   INT UNSIGNED NOT NULL,

    `kind` ENUM('firm_daily','client_weekly') NOT NULL,

    `window_start` DATETIME NOT NULL,
    `window_end`   DATETIME NOT NULL,
    `item_count`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `sent_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_window` (`tenant_id`, `user_id`, `kind`, `window_start`),
    KEY `idx_recent` (`tenant_id`, `user_id`, `sent_at`),

    CONSTRAINT `pl_fk_digests_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_digests_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Tenant-editable templates (FR-11.4).
--
-- An override, never a replacement: a tenant row supplies a subject and body
-- for one event type, and anything absent falls back to the built-in copy. So
-- a firm that edits one template does not inherit responsibility for the other
-- thirty, and a new event type ships working copy to everyone on day one.
--
-- Variables are {{double_braced}} and validated against a per-event allowlist
-- when the template is SAVED, not when it is sent. A coach who mistypes should
-- find out in the editor, not by a client receiving "Hello {{naem}}".
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_notification_templates` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `event_type` VARCHAR(64) NOT NULL,

    `subject` VARCHAR(255) NULL,
    `body`    TEXT NULL,

    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_tenant_event` (`tenant_id`, `event_type`),

    CONSTRAINT `pl_fk_notiftpl_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_notiftpl_editor`
        FOREIGN KEY (`updated_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- One-click unsubscribe (FR-11.5).
--
-- Selector/verifier like every other token here: the selector is indexed so
-- lookup is not a timing oracle, and the verifier is stored only as a hash so
-- a database leak does not hand anyone a working unsubscribe link.
--
-- Durable rather than expiring, which is the opposite of every other token in
-- this codebase and is correct here — an unsubscribe link in a nine-month-old
-- email must still work. An expired unsubscribe is a spam complaint.
--
-- It turns off DIGESTS. It cannot turn off transactional mail: while someone is
-- an active participant in an engagement, "your coach delivered a document you
-- must acknowledge" is not marketing and there is no opt-out from it short of
-- leaving the engagement. That distinction is the whole of FR-11.5, and it is
-- enforced in code by the `transactional` flag on the event, not by data anyone
-- can edit through a support request.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_unsubscribe_tokens` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `user_id`   INT UNSIGNED NOT NULL,

    `selector`      CHAR(32) NOT NULL,
    `verifier_hash` CHAR(64) NOT NULL,

    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at`     DATETIME NULL,     -- last time it was exercised, not a lock
    `revoked_at`  DATETIME NULL,

    UNIQUE KEY `uq_selector` (`selector`),
    UNIQUE KEY `uq_user`     (`tenant_id`, `user_id`),

    CONSTRAINT `pl_fk_unsub_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_unsub_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- When the digests go out (FR-11.3: "a tenant-configured day").
--
-- Stored on the tenant, in UTC hours, because the whole system is pinned to UTC
-- and a local-time column here would reintroduce exactly the two-clock problem
-- documented in CLAUDE.md. A firm in Denver wanting 8am picks 15.
-- ---------------------------------------------------------------------------
-- Plain ADD COLUMN, matching 012. MySQL 8 has no ADD COLUMN IF NOT EXISTS
-- (that is MariaDB), and the re-runnability rule in CLAUDE.md is really about
-- one failure mode: DDL auto-commits, so a file that dies halfway must survive
-- being run again. The CREATE TABLEs above cover that. This ALTER is last, so
-- reaching it means everything before it succeeded, and a re-run that trips on
-- a duplicate column is a re-run of a migration that had already finished.
ALTER TABLE `pl_tenants`
    ADD COLUMN `digest_hour` TINYINT UNSIGNED NOT NULL DEFAULT 13
        COMMENT 'UTC hour the firm daily digest is sent'
        AFTER `intake_assign_to`,
    ADD COLUMN `client_digest_day` TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT '0=Sunday .. 6=Saturday, day the client weekly digest is sent'
        AFTER `digest_hour`,
    ADD COLUMN `client_digest_enabled` TINYINT(1) NOT NULL DEFAULT 1
        AFTER `client_digest_day`;
