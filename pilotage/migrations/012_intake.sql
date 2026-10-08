-- Pilotage — 012 public intake
--
-- A visitor with no advisor fills in a form and becomes a prospect of a firm.
-- Ported in spirit from Foundry's consulting_applications, generalised so
-- every tenant gets one rather than only the house firm.
--
-- Two routes reach it:
--   firmslug.pilotagehq.com/apply  -> that firm
--   pilotagehq.com/apply           -> the house firm (config: intake.house_tenant)
--
-- Spam is the design constraint, not an afterthought. The single row that ever
-- existed in Foundry's applications table was spam: randomised field values, a
-- throwaway sender domain, a placeholder URL. An unauthenticated POST endpoint
-- on a public domain gets found. Hence the honeypot column, the render
-- timestamp, and IP capture — all of which exist to be checked, not admired.

CREATE TABLE IF NOT EXISTS `pl_intake_submissions` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,

    `name`         VARCHAR(255) NOT NULL,
    `email`        VARCHAR(255) NOT NULL,
    `phone`        VARCHAR(50)  NULL,
    `company_name` VARCHAR(255) NOT NULL,
    `website`      VARCHAR(255) NULL,

    `revenue_band`   ENUM('under_250k','250k_1m','1m_5m','5m_25m','25m_plus','undisclosed') NULL,
    `employee_count` INT UNSIGNED NULL,

    -- The three questions worth asking someone who has never worked with an
    -- advisor. Deliberately open text: a dropdown here collects nothing.
    `situation`       TEXT NULL,   -- what is going on
    `desired_outcome` TEXT NULL,   -- what good looks like
    `timeline`        VARCHAR(120) NULL,

    `status`       ENUM('new','reviewing','accepted','declined','spam') NOT NULL DEFAULT 'new',
    `reviewed_by`  INT UNSIGNED NULL,
    `reviewed_at`  DATETIME NULL,
    `review_note`  VARCHAR(500) NULL,

    -- Set when accepted: the prospect record this became.
    `client_org_id` INT UNSIGNED NULL,

    -- Spam signals, kept so a pattern is visible later rather than guessed at.
    `ip`               VARBINARY(16) NULL,
    `user_agent`       VARCHAR(255) NULL,
    `seconds_to_fill`  SMALLINT UNSIGNED NULL,
    `referrer`         VARCHAR(255) NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant_status` (`tenant_id`, `status`, `created_at`),
    KEY `idx_email`         (`email`),

    CONSTRAINT `pl_fk_intake_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_intake_org`
        FOREIGN KEY (`client_org_id`) REFERENCES `pl_client_orgs`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_intake_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Per-tenant intake settings. Off by default: a firm that has not written its
-- own copy should not have a public form quietly accepting strangers.
ALTER TABLE `pl_tenants`
    ADD COLUMN `intake_enabled`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `seat_limit`,
    ADD COLUMN `intake_headline` VARCHAR(255) NULL AFTER `intake_enabled`,
    ADD COLUMN `intake_blurb`    TEXT NULL AFTER `intake_headline`,
    ADD COLUMN `intake_assign_to` INT UNSIGNED NULL AFTER `intake_blurb`;

ALTER TABLE `pl_tenants`
    ADD CONSTRAINT `pl_fk_tenants_intake_assignee`
        FOREIGN KEY (`intake_assign_to`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL;
