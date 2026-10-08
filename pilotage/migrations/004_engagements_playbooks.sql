-- Pilotage — 004 engagements and the playbook engine
--
-- M4, plus the engagement record the whole product hangs off.
--
-- The central design call, FR-4.6: a playbook applied to an engagement is a
-- DEEP COPY, not a foreign key. Two parallel table families:
--
--   pl_playbook_*            the template a firm authors and versions
--   pl_engagement_playbook_* the frozen instance a client is actually running
--
-- Editing a template must never reach into a live engagement. A coach who
-- rewrites step 3 of their onboarding process in March cannot be allowed to
-- silently rewrite what a client agreed to in January. The copy is the point.
-- FR-4.7's drift diff is what lets a coach *choose* to pull changes forward.

-- ---------------------------------------------------------------- engagements

CREATE TABLE IF NOT EXISTS `pl_engagements` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `client_org_id` INT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `summary`       TEXT         NULL,
    `status`        ENUM('draft','active','paused','complete','cancelled') NOT NULL DEFAULT 'draft',
    `coach_user_id` INT UNSIGNED NULL,          -- the lead advisor
    `cadence`       ENUM('weekly','biweekly','monthly','quarterly','adhoc') NOT NULL DEFAULT 'biweekly',
    `starts_on`     DATE         NULL,
    `ends_on`       DATE         NULL,

    -- Ported from Foundry (FR-4.12). Optional per engagement.
    `scope_enabled`             TINYINT(1) NOT NULL DEFAULT 0,
    `scope_locked_at`           DATETIME   NULL,
    `client_accepted_scope_at`  DATETIME   NULL,

    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `completed_at`  DATETIME     NULL,

    KEY `idx_tenant_org`    (`tenant_id`, `client_org_id`),
    KEY `idx_tenant_coach`  (`tenant_id`, `coach_user_id`),
    KEY `idx_tenant_status` (`tenant_id`, `status`),

    CONSTRAINT `pl_fk_engagements_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_engagements_org`
        FOREIGN KEY (`client_org_id`) REFERENCES `pl_client_orgs`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_engagements_coach`
        FOREIGN KEY (`coach_user_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Who is attached, on both sides. Drives the 'assigned' qualifier.
CREATE TABLE IF NOT EXISTS `pl_engagement_members` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,
    `member_role`   ENUM('lead','associate','participant','observer') NOT NULL DEFAULT 'participant',
    `added_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_engagement_user` (`engagement_id`, `user_id`),
    KEY `idx_tenant_user` (`tenant_id`, `user_id`),

    CONSTRAINT `pl_fk_members_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_members_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_members_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------ playbooks (templates)

CREATE TABLE IF NOT EXISTS `pl_playbooks` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT         NULL,
    `status`      ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,  -- seeded starter (FR-4.9)
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `idx_tenant_status` (`tenant_id`, `status`),

    CONSTRAINT `pl_fk_playbooks_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_playbooks_creator`
        FOREIGN KEY (`created_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Editing happens on the draft version; publishing freezes it. An engagement
-- always records which frozen version it was instantiated from (FR-4.6).
CREATE TABLE IF NOT EXISTS `pl_playbook_versions` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`      INT UNSIGNED NOT NULL,
    `playbook_id`    INT UNSIGNED NOT NULL,
    `version_number` INT UNSIGNED NOT NULL,
    `state`          ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `notes`          VARCHAR(500) NULL,
    `published_at`   DATETIME     NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_playbook_version` (`playbook_id`, `version_number`),
    KEY `idx_tenant_state` (`tenant_id`, `state`),

    CONSTRAINT `pl_fk_versions_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_versions_playbook`
        FOREIGN KEY (`playbook_id`) REFERENCES `pl_playbooks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_playbook_phases` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `version_id`  INT UNSIGNED NOT NULL,
    `position`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `title`       VARCHAR(255) NOT NULL,
    `description` TEXT         NULL,

    KEY `idx_version_position` (`version_id`, `position`),

    CONSTRAINT `pl_fk_phases_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_phases_version`
        FOREIGN KEY (`version_id`) REFERENCES `pl_playbook_versions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- One unit of process guidance (FR-4.2).
--
-- coach_guidance is the "how to run this step" text and is NEVER rendered to a
-- client. client_guidance is what they see. Two columns rather than one with a
-- flag, for the same reason session notes are two tables: a single missed
-- WHERE clause on a flag is a confidentiality breach.
CREATE TABLE IF NOT EXISTS `pl_playbook_steps` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`         INT UNSIGNED NOT NULL,
    `version_id`        INT UNSIGNED NOT NULL,
    `phase_id`          INT UNSIGNED NOT NULL,
    `position`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `title`             VARCHAR(255) NOT NULL,
    `coach_guidance`    MEDIUMTEXT   NULL,
    `client_guidance`   MEDIUMTEXT   NULL,
    `estimated_minutes` SMALLINT UNSIGNED NULL,

    -- FR-4.4
    `gating`      ENUM('sequential','parallel','triggered') NOT NULL DEFAULT 'sequential',
    `gate_config` JSON NULL,   -- {"type":"date","offset_days":14} | {"type":"session","index":3} | {"type":"metric",...}

    -- FR-4.5
    `completion_rule` ENUM('coach_marks','artifacts_complete','client_attests') NOT NULL DEFAULT 'coach_marks',
    `is_required`     TINYINT(1) NOT NULL DEFAULT 1,

    KEY `idx_version_position` (`version_id`, `position`),
    KEY `idx_phase`            (`phase_id`, `position`),

    CONSTRAINT `pl_fk_steps_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_steps_version`
        FOREIGN KEY (`version_id`) REFERENCES `pl_playbook_versions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_steps_phase`
        FOREIGN KEY (`phase_id`) REFERENCES `pl_playbook_phases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- What a step spawns when it becomes available (FR-4.3). Assignees are stored
-- as a ROLE, never a named person — a template that names Dana cannot be
-- applied to a second client.
CREATE TABLE IF NOT EXISTS `pl_playbook_step_artifacts` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `step_id`       INT UNSIGNED NOT NULL,
    `position`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `artifact_type` ENUM('task','document','worksheet','session','metric','goal') NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `config`        JSON NULL,   -- {"assignee_role":"client_owner","due_offset_days":7,...}
    `is_required`   TINYINT(1) NOT NULL DEFAULT 1,

    KEY `idx_step` (`step_id`, `position`),

    CONSTRAINT `pl_fk_artifacts_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_artifacts_step`
        FOREIGN KEY (`step_id`) REFERENCES `pl_playbook_steps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------- instantiated (the live copy)

CREATE TABLE IF NOT EXISTS `pl_engagement_playbooks` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`         INT UNSIGNED NOT NULL,
    `engagement_id`     INT UNSIGNED NOT NULL,
    `playbook_id`       INT UNSIGNED NULL,   -- nullable: the template may be deleted later
    `source_version_id` INT UNSIGNED NULL,
    `source_version_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `name`              VARCHAR(255) NOT NULL,
    `applied_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `applied_by`        INT UNSIGNED NULL,

    UNIQUE KEY `uq_engagement` (`engagement_id`),   -- one running playbook per engagement
    KEY `idx_tenant` (`tenant_id`),

    CONSTRAINT `pl_fk_ep_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_ep_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_ep_playbook`
        FOREIGN KEY (`playbook_id`) REFERENCES `pl_playbooks`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_ep_version`
        FOREIGN KEY (`source_version_id`) REFERENCES `pl_playbook_versions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_engagement_phases` (
    `id`                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`             INT UNSIGNED NOT NULL,
    `engagement_playbook_id` INT UNSIGNED NOT NULL,
    `source_phase_id`       INT UNSIGNED NULL,
    `position`              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `title`                 VARCHAR(255) NOT NULL,
    `description`           TEXT         NULL,

    KEY `idx_ep_position` (`engagement_playbook_id`, `position`),

    CONSTRAINT `pl_fk_ephase_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_ephase_ep`
        FOREIGN KEY (`engagement_playbook_id`) REFERENCES `pl_engagement_playbooks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_engagement_steps` (
    `id`                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`              INT UNSIGNED NOT NULL,
    `engagement_playbook_id` INT UNSIGNED NOT NULL,
    `engagement_phase_id`    INT UNSIGNED NOT NULL,
    `source_step_id`         INT UNSIGNED NULL,   -- what it was copied from, for drift (FR-4.7)
    `position`               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `title`                  VARCHAR(255) NOT NULL,
    `coach_guidance`         MEDIUMTEXT   NULL,
    `client_guidance`        MEDIUMTEXT   NULL,
    `estimated_minutes`      SMALLINT UNSIGNED NULL,
    `gating`                 ENUM('sequential','parallel','triggered') NOT NULL DEFAULT 'sequential',
    `gate_config`            JSON NULL,
    `completion_rule`        ENUM('coach_marks','artifacts_complete','client_attests') NOT NULL DEFAULT 'coach_marks',
    `is_required`            TINYINT(1) NOT NULL DEFAULT 1,

    -- live state
    `status`         ENUM('locked','available','in_progress','complete','skipped') NOT NULL DEFAULT 'locked',
    `started_at`     DATETIME     NULL,
    `completed_at`   DATETIME     NULL,
    `completed_by`   INT UNSIGNED NULL,
    `skipped_reason` VARCHAR(500) NULL,   -- FR-4.5: skipping requires a reason, and it is kept

    KEY `idx_ep_position` (`engagement_playbook_id`, `position`),
    KEY `idx_phase`       (`engagement_phase_id`, `position`),
    KEY `idx_status`      (`tenant_id`, `status`),

    CONSTRAINT `pl_fk_estep_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_estep_ep`
        FOREIGN KEY (`engagement_playbook_id`) REFERENCES `pl_engagement_playbooks`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_estep_phase`
        FOREIGN KEY (`engagement_phase_id`) REFERENCES `pl_engagement_phases`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_estep_completer`
        FOREIGN KEY (`completed_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL,

    -- A skipped step must say why. Enforced here so no future code path can
    -- skip silently.
    CONSTRAINT `pl_chk_estep_skip_reason`
        CHECK (`status` <> 'skipped' OR `skipped_reason` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_engagement_step_artifacts` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`           INT UNSIGNED NOT NULL,
    `engagement_step_id`  INT UNSIGNED NOT NULL,
    `source_artifact_id`  INT UNSIGNED NULL,
    `position`            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `artifact_type`       ENUM('task','document','worksheet','session','metric','goal') NOT NULL,
    `title`               VARCHAR(255) NOT NULL,
    `config`              JSON NULL,
    `is_required`         TINYINT(1) NOT NULL DEFAULT 1,

    -- Filled once the artifact is actually spawned by its owning module.
    `spawned_object_type` VARCHAR(64)  NULL,
    `spawned_object_id`   INT UNSIGNED NULL,
    `completed_at`        DATETIME     NULL,

    KEY `idx_step` (`engagement_step_id`, `position`),

    CONSTRAINT `pl_fk_eartifact_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_eartifact_step`
        FOREIGN KEY (`engagement_step_id`) REFERENCES `pl_engagement_steps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
