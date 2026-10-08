-- Pilotage — 009 goals, metrics, and the issues workflow
--
-- M9. The scoreboard half of the product: what we said we would achieve this
-- quarter, the numbers we watch weekly, and the problems we are actually
-- solving.
--
-- pl_issues already exists (M6 created it as the target of the accountability
-- loop's escalation). This adds the resolution record that turns a list of
-- complaints into a record of decisions.

CREATE TABLE IF NOT EXISTS `pl_goals` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,

    `title`            VARCHAR(255) NOT NULL,
    `success_criteria` TEXT NULL,   -- how we will know. A goal nobody can score is a wish.
    `owner_user_id`    INT UNSIGNED NULL,

    -- The quarter this belongs to, as a date. Defaults to the end of the
    -- current quarter; 13 weeks is the rhythm the whole product assumes.
    `target_date`   DATE NULL,
    `quarter_label` VARCHAR(16) NULL,   -- "2026-Q3", for grouping and rollover

    `status`        ENUM('on_track','at_risk','off_track','done','dropped') NOT NULL DEFAULT 'on_track',
    `position`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    -- Set at rollover (FR-9.2): scored, then carried forward or closed.
    `carried_from_id` INT UNSIGNED NULL,
    `reviewed_at`     DATETIME NULL,
    `review_note`     VARCHAR(500) NULL,

    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`, `quarter_label`),
    KEY `idx_tenant_status`     (`tenant_id`, `status`),

    CONSTRAINT `pl_fk_goals_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_goals_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_goals_owner`
        FOREIGN KEY (`owner_user_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_goals_carried`
        FOREIGN KEY (`carried_from_id`) REFERENCES `pl_goals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_goal_milestones` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `goal_id`    INT UNSIGNED NOT NULL,
    `position`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `title`      VARCHAR(255) NOT NULL,
    `due_on`     DATE NULL,
    `done_at`    DATETIME NULL,

    KEY `idx_goal` (`goal_id`, `position`),

    CONSTRAINT `pl_fk_milestones_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_milestones_goal`
        FOREIGN KEY (`goal_id`) REFERENCES `pl_goals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- A metric definition. The VALUES live separately, because a definition is
-- edited rarely and read constantly while values are the opposite.
CREATE TABLE IF NOT EXISTS `pl_metrics` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,

    `name`          VARCHAR(255) NOT NULL,
    `unit`          VARCHAR(32) NULL,        -- $, %, hours, units
    `direction`     ENUM('higher','lower') NOT NULL DEFAULT 'higher',
    `target_value`  DECIMAL(18,4) NULL,
    `frequency`     ENUM('weekly','monthly','quarterly') NOT NULL DEFAULT 'weekly',

    -- Who enters it. A metric nobody owns stops being updated by week three.
    `owner_user_id` INT UNSIGNED NULL,

    `position`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `active`        TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`, `active`, `position`),

    CONSTRAINT `pl_fk_metrics_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_metrics_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_metrics_owner`
        FOREIGN KEY (`owner_user_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- One number for one period.
--
-- period_start is the FIRST day of the period, always. Storing a range or a
-- label would make "is this week's number in?" a parsing problem instead of a
-- comparison.
CREATE TABLE IF NOT EXISTS `pl_metric_values` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `metric_id`    INT UNSIGNED NOT NULL,
    `period_start` DATE NOT NULL,
    `value`        DECIMAL(18,4) NOT NULL,
    `note`         VARCHAR(500) NULL,

    `entered_by`   INT UNSIGNED NULL,
    -- FR-9.5: a coach may enter on the client's behalf, flagged as such. The
    -- flag matters — a scorecard the coach filled in is a different artifact
    -- from one the client filled in, and only one of them is evidence of
    -- engagement.
    `on_behalf`    TINYINT(1) NOT NULL DEFAULT 0,

    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_metric_period` (`metric_id`, `period_start`),
    KEY `idx_tenant_period` (`tenant_id`, `period_start`),

    CONSTRAINT `pl_fk_values_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_values_metric`
        FOREIGN KEY (`metric_id`) REFERENCES `pl_metrics`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_values_enterer`
        FOREIGN KEY (`entered_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- The resolution record (FR-9.7). An issues list without this is a list of
-- complaints; with it, it is a record of what was decided and why.
CREATE TABLE IF NOT EXISTS `pl_issue_resolutions` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `issue_id`   INT UNSIGNED NOT NULL,

    `identified` TEXT NULL,   -- what the problem actually is, once stated plainly
    `discussed`  TEXT NULL,   -- what was said
    `decided`    TEXT NULL,   -- what we are going to do

    `resolved_by` INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_issue` (`issue_id`),

    CONSTRAINT `pl_fk_resolutions_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_resolutions_issue`
        FOREIGN KEY (`issue_id`) REFERENCES `pl_issues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Issues gained a priority and a session link in M9.
ALTER TABLE `pl_issues`
    ADD COLUMN `priority` ENUM('low','normal','high') NOT NULL DEFAULT 'normal' AFTER `origin`,
    ADD COLUMN `owner_user_id` INT UNSIGNED NULL AFTER `raised_by`,
    ADD COLUMN `session_id` INT UNSIGNED NULL AFTER `owner_user_id`,
    ADD KEY `idx_tenant_priority` (`tenant_id`, `status`, `priority`),
    ADD CONSTRAINT `pl_fk_issues_owner`
        FOREIGN KEY (`owner_user_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL;
