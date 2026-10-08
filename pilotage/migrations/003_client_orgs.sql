-- Pilotage — 003 client organizations
--
-- M3: the full client organization record (FR-3.1), contacts distinct from
-- users (FR-3.2), and the organization timeline (FR-3.4).
--
-- The important modelling call here: a CONTACT is not a USER. A client
-- organization has people at it — a controller, an ops manager, a silent
-- co-owner — who the coach needs on file but who may never log in. FR-3.2
-- requires an access level of "no portal access", which a users table cannot
-- express. So contacts are the roster, and a contact optionally points at a
-- user once portal access is actually granted and accepted.

ALTER TABLE `pl_client_orgs`
    ADD COLUMN `legal_name`     VARCHAR(255) NULL AFTER `name`,
    ADD COLUMN `entity_type`    ENUM('sole_prop','partnership','llc','s_corp','c_corp','nonprofit','other') NULL AFTER `legal_name`,
    ADD COLUMN `revenue_band`   ENUM('under_250k','250k_1m','1m_5m','5m_25m','25m_plus','undisclosed') NULL AFTER `employee_count`,
    ADD COLUMN `fiscal_year_end` CHAR(5) NULL AFTER `revenue_band`,   -- MM-DD; year is irrelevant
    ADD COLUMN `website`        VARCHAR(255) NULL AFTER `fiscal_year_end`,
    ADD COLUMN `phone`          VARCHAR(50)  NULL AFTER `website`,
    ADD COLUMN `address_line1`  VARCHAR(255) NULL AFTER `phone`,
    ADD COLUMN `address_line2`  VARCHAR(255) NULL AFTER `address_line1`,
    ADD COLUMN `city`           VARCHAR(120) NULL AFTER `address_line2`,
    ADD COLUMN `region`         VARCHAR(120) NULL AFTER `city`,
    ADD COLUMN `postal_code`    VARCHAR(20)  NULL AFTER `region`,
    ADD COLUMN `country`        CHAR(2)      NULL AFTER `postal_code`,
    ADD COLUMN `situation`      TEXT         NULL AFTER `country`,
    ADD COLUMN `owner_user_id`  INT UNSIGNED NULL AFTER `situation`,  -- the coach who owns the relationship
    ADD COLUMN `team_invite_cap` TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER `owner_user_id`,
    ADD COLUMN `converted_at`   DATETIME     NULL AFTER `team_invite_cap`,
    ADD COLUMN `archived_at`    DATETIME     NULL AFTER `converted_at`,
    ADD KEY `idx_tenant_owner` (`tenant_id`, `owner_user_id`),
    ADD CONSTRAINT `pl_fk_client_orgs_owner`
        FOREIGN KEY (`owner_user_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL;


-- The roster of people at a client organization.
--
-- portal_access is the authority on whether this person may log in:
--   none   -> on file only; no user row, no invitation
--   member -> client_member when they accept
--   owner  -> client_owner when they accept
--
-- user_id is populated once an invitation is accepted, linking the roster
-- entry to the login. Contacts survive the user being disabled.
CREATE TABLE IF NOT EXISTS `pl_client_contacts` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `client_org_id` INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NULL,
    `name`          VARCHAR(255) NOT NULL,
    `email`         VARCHAR(255) NULL,          -- null is allowed: not everyone on file has one
    `title`         VARCHAR(120) NULL,
    `phone`         VARCHAR(50)  NULL,
    `portal_access` ENUM('none','member','owner') NOT NULL DEFAULT 'none',
    `is_primary`    TINYINT(1)   NOT NULL DEFAULT 0,
    `notes`         TEXT         NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `idx_tenant_org`  (`tenant_id`, `client_org_id`),
    KEY `idx_tenant_user` (`tenant_id`, `user_id`),
    UNIQUE KEY `uq_org_email` (`client_org_id`, `email`),
    UNIQUE KEY `uq_user`      (`user_id`),      -- one roster entry per login

    CONSTRAINT `pl_fk_contacts_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_contacts_org`
        FOREIGN KEY (`client_org_id`) REFERENCES `pl_client_orgs`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_contacts_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL,

    -- Portal access requires an address to send the invitation to.
    CONSTRAINT `pl_chk_contacts_access_needs_email`
        CHECK (`portal_access` = 'none' OR `email` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Organization timeline (FR-3.4): the "what has happened with this client"
-- feed a coach reads in the ninety seconds before a call.
--
-- Denormalized on purpose. Assembling this by union-ing a dozen tables at
-- read time gets slower with every module; appending one row at write time
-- does not. actor_label is preserved so the feed survives a user deletion.
CREATE TABLE IF NOT EXISTS `pl_org_events` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `client_org_id` INT UNSIGNED NOT NULL,
    `actor_user_id` INT UNSIGNED NULL,
    `actor_label`   VARCHAR(255) NULL,
    `event_type`    VARCHAR(64)  NOT NULL,
    `summary`       VARCHAR(500) NOT NULL,
    `object_type`   VARCHAR(64)  NULL,
    `object_id`     INT UNSIGNED NULL,
    `client_visible` TINYINT(1)  NOT NULL DEFAULT 1,  -- 0 = coach-side only
    `meta`          JSON         NULL,
    `occurred_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant_org_time` (`tenant_id`, `client_org_id`, `occurred_at`),
    KEY `idx_type`            (`event_type`),

    CONSTRAINT `pl_fk_events_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_events_org`
        FOREIGN KEY (`client_org_id`) REFERENCES `pl_client_orgs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
