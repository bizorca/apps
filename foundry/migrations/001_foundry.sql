-- Bizorca Foundry, ported 2026-10-07 from foundry.bizorca.com, where its
-- tables (consulting_*) lived inside the login.bizorca.com database.
--
-- Same tables and columns as the original's 001_schema.sql, renamed fd_.
-- Every person is a shared tools account: consulting_users is gone, and each
-- column that pointed at it now points at users(id), which is a signed INT
-- (a foreign key across INT UNSIGNED -> INT is rejected). Delete behaviour is
-- the original's: an engagement or change request blocks deleting its client's
-- account (business records); reviewer/creator columns are SET NULL; a
-- comment goes with its author.

-- Foundry admins who are not site-wide admins. users.is_admin (the site owner)
-- is always a Foundry admin as well; see fd_user_is_admin().
CREATE TABLE IF NOT EXISTS `fd_admins` (
    `user_id`    INT      NOT NULL PRIMARY KEY,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Applications ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fd_applications` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT          NULL,  -- set if applicant logged in; null if anonymous
    `first_name`      VARCHAR(100) NOT NULL,
    `last_name`       VARCHAR(100) NOT NULL,
    `email`           VARCHAR(255) NOT NULL,
    `company_name`    VARCHAR(255) NOT NULL,
    `company_type`    VARCHAR(100) NOT NULL DEFAULT '',  -- legacy, no longer collected
    `website`         VARCHAR(255) NOT NULL DEFAULT '',
    `revenue_range`   VARCHAR(50)  NOT NULL,  -- Under $250K, $250K-$500K, $500K-$1M, $1M-$3M, $3M+
    `employee_count`  VARCHAR(20)  NOT NULL,  -- Just me, 2-5, 6-15, 16+
    `proc_location`   VARCHAR(255) NOT NULL DEFAULT '',  -- where operating procedures live
    `two_weeks`       TEXT         NULL,       -- what breaks if owner steps away 2 weeks
    `core_problem`    TEXT         NOT NULL,   -- primary operational bottleneck
    `desired_outcome` TEXT         NOT NULL,   -- what they will permanently stop doing
    `timeline`        VARCHAR(100) NOT NULL,  -- Immediately, 30-60 days, Next quarter, Just exploring
    `budget_range`    VARCHAR(50)  NOT NULL,  -- Under $5K, $5K-$10K, $10K-$18K
    `referral_source` VARCHAR(255) NOT NULL DEFAULT '',
    `notes`           TEXT         NULL,
    `status`          ENUM('pending','accepted','declined','waitlisted') NOT NULL DEFAULT 'pending',
    `admin_notes`     TEXT         NULL,
    `reviewed_by`     INT          NULL,
    `reviewed_at`     DATETIME     NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_email`  (`email`),
    FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`reviewed_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Engagements ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fd_engagements` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT UNSIGNED NOT NULL,
    `client_id`      INT          NOT NULL,
    `title`          VARCHAR(255) NOT NULL,
    `description`    TEXT         NULL,
    `status`         ENUM('onboarding','active','paused','complete','cancelled') NOT NULL DEFAULT 'onboarding',
    `scope_locked_at` DATETIME    NULL,  -- null = scope not yet locked/accepted by client
    `client_accepted_scope_at` DATETIME NULL,
    `started_at`     DATETIME     NULL,
    `completed_at`   DATETIME     NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_client`  (`client_id`),
    INDEX `idx_status`  (`status`),
    FOREIGN KEY (`application_id`) REFERENCES `fd_applications`(`id`),
    FOREIGN KEY (`client_id`)      REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Onboarding steps ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fd_onboarding_steps` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `engagement_id`  INT UNSIGNED NOT NULL,
    `step_key`       VARCHAR(100) NOT NULL,  -- agreement_signed, questionnaire_complete, kickoff_scheduled, scope_accepted
    `label`          VARCHAR(255) NOT NULL,
    `position`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `completed_at`   DATETIME     NULL,
    `completed_by`   INT          NULL,
    UNIQUE KEY `uq_engagement_step` (`engagement_id`, `step_key`),
    FOREIGN KEY (`engagement_id`) REFERENCES `fd_engagements`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`completed_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Scope items ────────────────────────────────────────────────────────────
-- The frozen deliverables list defined at engagement start
CREATE TABLE IF NOT EXISTS `fd_scope_items` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `engagement_id`   INT UNSIGNED NOT NULL,
    `title`           VARCHAR(255) NOT NULL,
    `description`     TEXT         NULL,
    `position`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_original`     TINYINT(1)   NOT NULL DEFAULT 1,  -- 0 = added via approved change request
    `change_request_id` INT UNSIGNED NULL,              -- if added post-lock
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`engagement_id`) REFERENCES `fd_engagements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Boards ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fd_boards` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `engagement_id` INT UNSIGNED NOT NULL,
    `name`          VARCHAR(255) NOT NULL,
    `description`   TEXT         NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`engagement_id`) REFERENCES `fd_engagements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Columns ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fd_columns` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `board_id`   INT UNSIGNED NOT NULL,
    `name`       VARCHAR(100) NOT NULL,
    `position`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `color`      VARCHAR(20)  NOT NULL DEFAULT 'slate',
    FOREIGN KEY (`board_id`) REFERENCES `fd_boards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Cards ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fd_cards` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `board_id`          INT UNSIGNED NOT NULL,
    `column_id`         INT UNSIGNED NOT NULL,
    `scope_item_id`     INT UNSIGNED NULL,  -- linked to a scope item (the deliverable it represents)
    `change_request_id` INT UNSIGNED NULL,  -- non-null = added via change request
    `title`             VARCHAR(255) NOT NULL,
    `description`       TEXT         NULL,
    `position`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_original_scope` TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by`        INT          NULL,
    `closed_at`         DATETIME     NULL,
    `due_at`            DATE         NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_board`  (`board_id`),
    INDEX `idx_column` (`column_id`),
    FOREIGN KEY (`board_id`)  REFERENCES `fd_boards`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`column_id`) REFERENCES `fd_columns`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Card steps (checklist) ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fd_card_steps` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `card_id`      INT UNSIGNED NOT NULL,
    `title`        VARCHAR(255) NOT NULL,
    `position`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `completed`    TINYINT(1)   NOT NULL DEFAULT 0,
    `completed_at` DATETIME     NULL,
    `completed_by` INT          NULL,
    FOREIGN KEY (`card_id`)      REFERENCES `fd_cards`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Card comments ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fd_card_comments` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `card_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT          NOT NULL,
    `body`       TEXT         NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_card` (`card_id`),
    FOREIGN KEY (`card_id`)  REFERENCES `fd_cards`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Change requests ────────────────────────────────────────────────────────
-- The scope creep firewall. Clients submit; admin approves/declines.
CREATE TABLE IF NOT EXISTS `fd_change_requests` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `engagement_id`   INT UNSIGNED NOT NULL,
    `submitted_by`    INT          NOT NULL,
    `title`           VARCHAR(255) NOT NULL,
    `description`     TEXT         NOT NULL,
    `justification`   TEXT         NULL,
    `status`          ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
    `reviewed_by`     INT          NULL,
    `reviewed_at`     DATETIME     NULL,
    `review_note`     TEXT         NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_engagement` (`engagement_id`),
    INDEX `idx_status`     (`status`),
    FOREIGN KEY (`engagement_id`) REFERENCES `fd_engagements`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`submitted_by`)  REFERENCES `users`(`id`),
    FOREIGN KEY (`reviewed_by`)   REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
