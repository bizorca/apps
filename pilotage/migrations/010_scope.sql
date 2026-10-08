-- Pilotage — 010 scope and change control
--
-- M4B, the last Phase 1 module. Ported from Foundry, which got this right
-- (SPEC.md §1.2). Optional per engagement: consultants and management
-- advisors need it, pure coaches can leave it off.
--
-- The mechanism is the paper trail, not the gatekeeping. Scope creep does not
-- usually arrive as a demand; it arrives as a series of small reasonable
-- requests nobody wrote down. A declined change request that stays visible is
-- worth more than one that was never recorded.

CREATE TABLE IF NOT EXISTS `pl_scope_items` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,
    `position`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT NULL,

    -- 0 = added after the lock, via an approved change request.
    `is_original`       TINYINT(1) NOT NULL DEFAULT 1,
    `change_request_id` INT UNSIGNED NULL,

    -- FR-4.15: reconciles "what we agreed to" against "what we handed over".
    `document_id`   INT UNSIGNED NULL,
    `delivered_at`  DATETIME NULL,

    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`, `position`),

    CONSTRAINT `pl_fk_scope_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_scope_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_scope_document`
        FOREIGN KEY (`document_id`) REFERENCES `pl_documents`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_change_requests` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,

    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT NOT NULL,
    `justification` TEXT NULL,

    `status`        ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
    `submitted_by`  INT UNSIGNED NULL,
    `reviewed_by`   INT UNSIGNED NULL,
    `reviewed_at`   DATETIME NULL,
    `review_note`   TEXT NULL,

    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`, `status`),

    CONSTRAINT `pl_fk_changereq_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_changereq_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_changereq_submitter`
        FOREIGN KEY (`submitted_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_changereq_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `pl_scope_items`
    ADD CONSTRAINT `pl_fk_scope_changereq`
        FOREIGN KEY (`change_request_id`) REFERENCES `pl_change_requests`(`id`) ON DELETE SET NULL;
