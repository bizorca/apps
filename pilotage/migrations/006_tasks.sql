-- Pilotage — 006 tasks, comments, and issues
--
-- M6. The accountability loop (FR-6.5) is the product's actual mechanic:
--
--   commitment captured -> nudge before due -> overdue notice -> check-in or
--   completion -> completion rate updates -> next session opens with it ->
--   MISSED TWICE, it becomes an Issue
--
-- That last arrow is the design decision that separates accountability from
-- nagging. A task missed once is life. A task missed twice is not a reminder
-- problem, it is a problem problem — something is wrong with the commitment,
-- the capacity, or the priority — and it belongs on the table as a thing to
-- solve rather than as a bigger red number.
--
-- Issues get a minimal table here because the loop needs somewhere to escalate
-- TO. M9 builds the full issues workflow (priority, IDS resolution records,
-- the running list); this is only the target.

CREATE TABLE IF NOT EXISTS `pl_issues` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `detail`        TEXT NULL,
    `origin`        ENUM('session','missed_commitment','metric','ad_hoc') NOT NULL DEFAULT 'ad_hoc',
    `status`        ENUM('open','discussing','resolved','dropped') NOT NULL DEFAULT 'open',
    `raised_by`     INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at`   DATETIME NULL,

    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`, `status`),

    CONSTRAINT `pl_fk_issues_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_issues_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_issues_raiser`
        FOREIGN KEY (`raised_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_tasks` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,
    `parent_id`     INT UNSIGNED NULL,   -- one level of nesting ONLY (FR-6.4)

    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT NULL,

    -- FR-6.1: "definition of done" is a first-class field, not a convention.
    -- A commitment nobody can score is not a commitment.
    `definition_of_done` VARCHAR(500) NULL,

    -- FR-6.2: assignable in BOTH directions. A coach owing the client a
    -- deliverable is a first-class case, not an afterthought.
    `owner_user_id` INT UNSIGNED NULL,
    `assigned_by`   INT UNSIGNED NULL,

    `due_on`        DATE NULL,
    `priority`      ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    `status`        ENUM('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',

    -- Where it came from, so the session runner can show "what we promised".
    `source`             ENUM('session','step','recurring','ad_hoc') NOT NULL DEFAULT 'ad_hoc',
    `source_session_id`  INT UNSIGNED NULL,
    `source_step_id`     INT UNSIGNED NULL,
    `recurring_parent_id` INT UNSIGNED NULL,   -- the series this occurrence belongs to

    -- FR-6.6: what must be supplied to mark it done.
    `evidence_required` ENUM('none','note','file','metric') NOT NULL DEFAULT 'none',
    `evidence_note`     TEXT NULL,

    `client_visible` TINYINT(1) NOT NULL DEFAULT 1,

    -- The loop's memory (FR-6.5). miss_count deliberately does NOT reset when
    -- a due date is pushed — rescheduling a commitment you already missed is
    -- exactly the pattern worth surfacing.
    `miss_count`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_missed_on` DATE NULL,
    `escalated_issue_id` INT UNSIGNED NULL,

    `completed_at`  DATETIME NULL,
    `completed_by`  INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`, `status`),
    KEY `idx_tenant_owner`      (`tenant_id`, `owner_user_id`, `status`),
    KEY `idx_tenant_due`        (`tenant_id`, `due_on`, `status`),
    KEY `idx_parent`            (`parent_id`),
    KEY `idx_source_session`    (`source_session_id`),

    CONSTRAINT `pl_fk_tasks_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_tasks_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_tasks_parent`
        FOREIGN KEY (`parent_id`) REFERENCES `pl_tasks`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_tasks_owner`
        FOREIGN KEY (`owner_user_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_tasks_session`
        FOREIGN KEY (`source_session_id`) REFERENCES `pl_sessions`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_tasks_step`
        FOREIGN KEY (`source_step_id`) REFERENCES `pl_engagement_steps`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_tasks_issue`
        FOREIGN KEY (`escalated_issue_id`) REFERENCES `pl_issues`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Recurring series (FR-6.3). The series is a template; each occurrence is a
-- real task, so a missed week stays missed rather than being overwritten by
-- next week's reset.
CREATE TABLE IF NOT EXISTS `pl_task_series` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT NULL,
    `owner_user_id` INT UNSIGNED NULL,
    `frequency`     ENUM('weekly','biweekly','monthly') NOT NULL DEFAULT 'weekly',
    `next_due_on`   DATE NOT NULL,
    `active`        TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant_active` (`tenant_id`, `active`, `next_due_on`),

    CONSTRAINT `pl_fk_series_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_series_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_series_owner`
        FOREIGN KEY (`owner_user_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Comments, polymorphic (FR-6.7, SPEC.md §10).
--
-- One table rather than one per object type: FR-8.2 requires commenting on
-- tasks, documents, worksheets, metrics, goals, sessions and steps, and a
-- table each would be seven tables doing one job. Indexed on
-- (tenant_id, object_type, object_id), which is how it is always queried.
CREATE TABLE IF NOT EXISTS `pl_comments` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `object_type` VARCHAR(64)  NOT NULL,
    `object_id`   INT UNSIGNED NOT NULL,
    `author_id`   INT UNSIGNED NULL,
    `author_label` VARCHAR(255) NULL,   -- survives the author being deleted
    `body`        TEXT NOT NULL,
    `client_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME NULL,

    KEY `idx_object` (`tenant_id`, `object_type`, `object_id`, `created_at`),
    KEY `idx_author` (`tenant_id`, `author_id`),

    CONSTRAINT `pl_fk_comments_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_comments_author`
        FOREIGN KEY (`author_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
