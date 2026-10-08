-- Pilotage — 005 sessions
--
-- M5. The session runner is the screen a coach drives during a live meeting,
-- so the schema is shaped around what that screen needs: a timed agenda, two
-- kinds of notes, attendance, and a recap that is drafted before it is sent.
--
-- The confidentiality decision, from SPEC.md §10 and FR-5.4: shared notes and
-- private notes live in SEPARATE TABLES, not one table with a visibility flag.
-- A single missed WHERE clause on a flag column is a breach of the coaching
-- relationship, and it is the kind of mistake that is invisible until it is
-- catastrophic. Two tables make the client-visible query structurally unable
-- to reach private content.

-- Reusable agenda shapes (FR-5.2).
CREATE TABLE IF NOT EXISTS `pl_session_templates` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `name`         VARCHAR(255) NOT NULL,
    `description`  VARCHAR(500) NULL,
    `session_type` ENUM('discovery','planning','working','review','adhoc') NOT NULL DEFAULT 'working',
    `time_box_minutes` SMALLINT UNSIGNED NULL,   -- the 90 in a 90-minute meeting
    `is_system`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant` (`tenant_id`),
    CONSTRAINT `pl_fk_stemplates_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- One line of a template agenda.
--
-- auto_block names a generated block (FR-5.3). When a session opens, the
-- runner fills these with current state — off-target metrics, off-track goals,
-- commitments due since last time — so the coach never assembles the agenda
-- by hand. A NULL auto_block is a plain heading the coach talks to.
CREATE TABLE IF NOT EXISTS `pl_session_template_items` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `template_id`  INT UNSIGNED NOT NULL,
    `position`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `title`        VARCHAR(255) NOT NULL,
    `minutes`      SMALLINT UNSIGNED NULL,
    `auto_block`   VARCHAR(64) NULL,   -- commitments | metrics | goals | issues | steps
    `prompt`       TEXT NULL,          -- coach-facing "what to actually do here"

    KEY `idx_template_position` (`template_id`, `position`),
    CONSTRAINT `pl_fk_stitems_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_stitems_template`
        FOREIGN KEY (`template_id`) REFERENCES `pl_session_templates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_sessions` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,
    `template_id`   INT UNSIGNED NULL,
    `engagement_step_id` INT UNSIGNED NULL,   -- the playbook step that spawned it

    `title`         VARCHAR(255) NOT NULL,
    `session_type`  ENUM('discovery','planning','working','review','adhoc') NOT NULL DEFAULT 'working',
    `scheduled_at`  DATETIME     NULL,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    `location`      VARCHAR(255) NULL,   -- room, or a video link
    `timezone`      VARCHAR(64)  NOT NULL DEFAULT 'UTC',  -- for display; storage is UTC

    `status`        ENUM('scheduled','in_progress','complete','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
    `started_at`    DATETIME     NULL,
    `ended_at`      DATETIME     NULL,

    `created_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`, `scheduled_at`),
    KEY `idx_tenant_scheduled`  (`tenant_id`, `scheduled_at`),
    KEY `idx_status`            (`tenant_id`, `status`),

    CONSTRAINT `pl_fk_sessions_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_sessions_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_sessions_template`
        FOREIGN KEY (`template_id`) REFERENCES `pl_session_templates`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_sessions_step`
        FOREIGN KEY (`engagement_step_id`) REFERENCES `pl_engagement_steps`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Who was invited and who actually turned up. Drives the 'attendee' qualifier
-- and the attendance half of the engagement health score.
CREATE TABLE IF NOT EXISTS `pl_session_attendees` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `session_id`  INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NULL,       -- null for a contact with no portal access
    `contact_id`  INT UNSIGNED NULL,
    `display_name` VARCHAR(255) NOT NULL,
    `invited`     TINYINT(1) NOT NULL DEFAULT 1,
    `attended`    TINYINT(1) NULL,         -- null until the session is run

    UNIQUE KEY `uq_session_user`    (`session_id`, `user_id`),
    KEY `idx_tenant_user` (`tenant_id`, `user_id`),

    CONSTRAINT `pl_fk_attendees_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_attendees_session`
        FOREIGN KEY (`session_id`) REFERENCES `pl_sessions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_attendees_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_attendees_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `pl_client_contacts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- The agenda as it exists for THIS session — copied from the template at
-- creation, so editing a template never rewrites a meeting that already ran.
-- Same reasoning as playbook instantiation.
CREATE TABLE IF NOT EXISTS `pl_session_agenda_items` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `session_id`  INT UNSIGNED NOT NULL,
    `position`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `title`       VARCHAR(255) NOT NULL,
    `minutes`     SMALLINT UNSIGNED NULL,
    `auto_block`  VARCHAR(64) NULL,
    `prompt`      TEXT NULL,
    `covered_at`  DATETIME NULL,    -- ticked off during the meeting

    KEY `idx_session_position` (`session_id`, `position`),

    CONSTRAINT `pl_fk_agenda_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_agenda_session`
        FOREIGN KEY (`session_id`) REFERENCES `pl_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Shared notes. The client sees these.
CREATE TABLE IF NOT EXISTS `pl_session_notes_shared` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `session_id` INT UNSIGNED NOT NULL,
    `body`       MEDIUMTEXT NOT NULL,
    `author_id`  INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_session` (`session_id`),

    CONSTRAINT `pl_fk_shared_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_shared_session`
        FOREIGN KEY (`session_id`) REFERENCES `pl_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Private notes. A SEPARATE TABLE, deliberately.
--
-- Nothing that renders to a client-side user may query this table. There is no
-- flag to forget, no join to get wrong. If you find yourself adding a
-- visibility column to the shared table and deleting this one, do not.
CREATE TABLE IF NOT EXISTS `pl_session_notes_private` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `session_id` INT UNSIGNED NOT NULL,
    `body`       MEDIUMTEXT NOT NULL,
    `author_id`  INT UNSIGNED NOT NULL,   -- private notes always have an owner
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_session_author` (`session_id`, `author_id`),

    CONSTRAINT `pl_fk_private_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_private_session`
        FOREIGN KEY (`session_id`) REFERENCES `pl_sessions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_private_author`
        FOREIGN KEY (`author_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- The recap (FR-5.6). Drafted on close, sent by hand — never auto-sent.
-- A recap that goes out before the coach has read it will eventually contain
-- something that should not have left the room.
CREATE TABLE IF NOT EXISTS `pl_session_recaps` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `session_id` INT UNSIGNED NOT NULL,
    `body`       MEDIUMTEXT NOT NULL,
    `status`     ENUM('draft','sent') NOT NULL DEFAULT 'draft',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `sent_at`    DATETIME NULL,
    `sent_by`    INT UNSIGNED NULL,

    UNIQUE KEY `uq_session` (`session_id`),

    CONSTRAINT `pl_fk_recaps_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_recaps_session`
        FOREIGN KEY (`session_id`) REFERENCES `pl_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
