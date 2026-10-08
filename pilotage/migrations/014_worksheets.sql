-- Pilotage — 014 worksheets, forms, and scored assessments
--
-- M10. Three things wearing one coat: an intake questionnaire, a reflection
-- worksheet, and a scored business-health assessment are the same machinery
-- with different settings.
--
-- Two design calls carry the module.
--
-- 1. FIELDS ARE ROWS, NOT JSON. Same argument as playbook steps: a field needs
--    to be referenced by an answer, weighted for scoring, and reordered
--    without rewriting a blob. A JSON column would make "which question did
--    this answer belong to" a parsing problem.
--
-- 2. ANONYMITY IS STRUCTURAL. FR-10.6 says individual responses in an
--    anonymous survey are hidden EVEN FROM THE COACH. A visibility flag would
--    not deliver that — anyone with database access could still read who said
--    what, and a coach asking their developer nicely is exactly the pressure
--    this is meant to withstand. So an anonymous response stores NO respondent
--    id at all. The link is destroyed at submission, not concealed.

CREATE TABLE IF NOT EXISTS `pl_worksheets` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,

    `title`       VARCHAR(255) NOT NULL,
    `description` TEXT NULL,

    -- form: answers are read individually.
    -- assessment: answers are scored, banded, and comparable over time.
    -- survey: run anonymously across a team; only aggregates are ever shown.
    `kind` ENUM('form','assessment','survey') NOT NULL DEFAULT 'form',

    -- FR-10.6. Set at the TEMPLATE, never per assignment: a worksheet that is
    -- sometimes anonymous is a worksheet nobody trusts.
    `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,

    -- Aggregates stay hidden until this many responses are in, so a team of
    -- six cannot be de-anonymised by arithmetic.
    `min_responses` TINYINT UNSIGNED NOT NULL DEFAULT 5,

    `status`     ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `idx_tenant_status` (`tenant_id`, `status`),

    CONSTRAINT `pl_fk_worksheets_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_worksheet_fields` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `worksheet_id` INT UNSIGNED NOT NULL,
    `position`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    -- FR-10.1. `section` and `note` carry no answer — they are structure and
    -- instruction, which a long assessment needs as much as it needs inputs.
    `field_type` ENUM('short_text','long_text','number','currency','date',
                      'select_one','select_many','scale','file','section','note') NOT NULL,

    `label`    VARCHAR(500) NOT NULL,
    `help`     VARCHAR(500) NULL,
    `required` TINYINT(1) NOT NULL DEFAULT 0,

    -- Choices for select_one / select_many, one per line.
    `options`  TEXT NULL,

    -- Scale bounds. Defaults suit the 1-10 the spec names.
    `scale_min` TINYINT NULL,
    `scale_max` TINYINT NULL,
    `scale_min_label` VARCHAR(60) NULL,
    `scale_max_label` VARCHAR(60) NULL,

    -- FR-10.4 scoring. `category` groups fields into subscores; `weight`
    -- lets one question matter more than another.
    `category` VARCHAR(60) NULL,
    `weight`   DECIMAL(6,2) NOT NULL DEFAULT 1.00,

    KEY `idx_worksheet_position` (`worksheet_id`, `position`),

    CONSTRAINT `pl_fk_wfields_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wfields_worksheet`
        FOREIGN KEY (`worksheet_id`) REFERENCES `pl_worksheets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- FR-10.4: what a score MEANS. A number with no interpretation is a number
-- the client will read wrongly, so the coach writes the reading once.
CREATE TABLE IF NOT EXISTS `pl_worksheet_bands` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `worksheet_id` INT UNSIGNED NOT NULL,
    `category`     VARCHAR(60) NULL,   -- null = the overall score
    `min_percent`  TINYINT UNSIGNED NOT NULL,
    `max_percent`  TINYINT UNSIGNED NOT NULL,
    `label`        VARCHAR(120) NOT NULL,
    `interpretation` TEXT NULL,

    KEY `idx_worksheet` (`worksheet_id`, `category`, `min_percent`),

    CONSTRAINT `pl_fk_wbands_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wbands_worksheet`
        FOREIGN KEY (`worksheet_id`) REFERENCES `pl_worksheets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- An assignment is the worksheet given to someone, at a point in time.
-- FR-10.5 compares responses across assignments: the same assessment at month
-- 0, 6 and 12 is three assignments of one worksheet.
CREATE TABLE IF NOT EXISTS `pl_worksheet_assignments` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `worksheet_id`  INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,

    `label`   VARCHAR(120) NULL,   -- "Baseline", "Six months"
    `due_on`  DATE NULL,

    -- Null = everyone with portal access at the client organization, which is
    -- how a team survey is run.
    `assigned_user_id` INT UNSIGNED NULL,

    `assigned_by` INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at`   DATETIME NULL,

    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`),
    KEY `idx_worksheet`         (`worksheet_id`),

    CONSTRAINT `pl_fk_wassign_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wassign_worksheet`
        FOREIGN KEY (`worksheet_id`) REFERENCES `pl_worksheets`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wassign_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wassign_user`
        FOREIGN KEY (`assigned_user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- One person's attempt.
--
-- respondent_user_id is NULL for anonymous worksheets and that is the whole
-- mechanism. `resume_key` lets a part-finished anonymous response be returned
-- to (FR-10.3) without naming who owns it — a random token held in the
-- respondent's session, not an identity.
CREATE TABLE IF NOT EXISTS `pl_worksheet_responses` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `assignment_id` INT UNSIGNED NOT NULL,

    `respondent_user_id` INT UNSIGNED NULL,   -- ALWAYS null when anonymous
    `resume_key`         CHAR(32) NOT NULL,

    `status`       ENUM('in_progress','submitted') NOT NULL DEFAULT 'in_progress',
    `started_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `submitted_at` DATETIME NULL,

    -- Cached at submission so a results page is one query, and so a later
    -- edit to the weights does not silently rewrite history.
    `score_percent` DECIMAL(5,2) NULL,
    `band_label`    VARCHAR(120) NULL,

    UNIQUE KEY `uq_resume` (`resume_key`),
    KEY `idx_assignment` (`assignment_id`, `status`),
    KEY `idx_respondent` (`tenant_id`, `respondent_user_id`),

    CONSTRAINT `pl_fk_wresp_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wresp_assignment`
        FOREIGN KEY (`assignment_id`) REFERENCES `pl_worksheet_assignments`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wresp_user`
        FOREIGN KEY (`respondent_user_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_worksheet_answers` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `response_id` INT UNSIGNED NOT NULL,
    `field_id`    INT UNSIGNED NOT NULL,

    `value_text`   MEDIUMTEXT NULL,      -- text, dates, and joined multi-select
    `value_number` DECIMAL(18,4) NULL,   -- number, currency, scale
    `document_id`  INT UNSIGNED NULL,    -- file uploads reuse M7

    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_response_field` (`response_id`, `field_id`),
    KEY `idx_field` (`field_id`),

    CONSTRAINT `pl_fk_wans_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wans_response`
        FOREIGN KEY (`response_id`) REFERENCES `pl_worksheet_responses`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wans_field`
        FOREIGN KEY (`field_id`) REFERENCES `pl_worksheet_fields`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wans_document`
        FOREIGN KEY (`document_id`) REFERENCES `pl_documents`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Category subscores, cached at submission alongside the overall score.
CREATE TABLE IF NOT EXISTS `pl_worksheet_subscores` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `response_id` INT UNSIGNED NOT NULL,
    `category`    VARCHAR(60) NOT NULL,
    `score_percent` DECIMAL(5,2) NOT NULL,
    `band_label`  VARCHAR(120) NULL,

    UNIQUE KEY `uq_response_category` (`response_id`, `category`),

    CONSTRAINT `pl_fk_wsub_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_wsub_response`
        FOREIGN KEY (`response_id`) REFERENCES `pl_worksheet_responses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
