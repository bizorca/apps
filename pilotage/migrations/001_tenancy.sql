-- Pilotage — 001 tenancy
--
-- Phase 0: the tenancy spine only. Enough schema to resolve a tenant, hold
-- users on both sides of the wall, and give the isolation suite a real
-- tenant-owned table to attack.
--
-- Every tenant-owned table carries tenant_id, and tenant_id leads every
-- lookup index — the scope is the first thing the optimiser filters on.

CREATE TABLE IF NOT EXISTS `pl_migrations` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `filename`   VARCHAR(255) NOT NULL,
    `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- A coaching firm or solo practice. The root of every scope.
CREATE TABLE IF NOT EXISTS `pl_tenants` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug`       VARCHAR(63)  NOT NULL,          -- the subdomain label
    `name`       VARCHAR(255) NOT NULL,
    `status`     ENUM('trial','active','suspended','closed') NOT NULL DEFAULT 'trial',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_slug`    (`slug`),
    KEY        `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- The business being advised. First real tenant-owned table.
CREATE TABLE IF NOT EXISTS `pl_client_orgs` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`      INT UNSIGNED NOT NULL,
    `name`           VARCHAR(255) NOT NULL,
    `industry_naics` VARCHAR(10)  NULL,
    `employee_count` INT UNSIGNED NULL,
    `status`         ENUM('prospect','active','archived') NOT NULL DEFAULT 'prospect',
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_tenant_status` (`tenant_id`, `status`),
    KEY `idx_tenant_name`   (`tenant_id`, `name`),
    CONSTRAINT `pl_fk_client_orgs_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Users.
--
-- Spec §4 said "belongs to exactly one tenant OR one client organization,
-- never both." Building it revealed that to be wrong: a client organization
-- already belongs to a tenant, so a client-side user is inside that tenant's
-- world too. Leaving tenant_id NULL for them would put every client-side row
-- outside the scoping mechanism that protects everything else — the one place
-- you least want an exception.
--
-- Corrected model: tenant_id is ALWAYS set (whose world you are in), and
-- client_org_id decides which side of the wall you stand on.
--     client_org_id IS NULL     -> firm-side staff
--     client_org_id IS NOT NULL -> client-side contact
--
-- The CHECK ties side-of-the-wall to role, so a client contact can never be
-- assigned a firm-side role by a future insert path that forgets.
CREATE TABLE IF NOT EXISTS `pl_users` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `client_org_id` INT UNSIGNED NULL,
    `email`         VARCHAR(255) NOT NULL,
    `name`          VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NULL,   -- null until set; magic-link users may never have one
    `role`          ENUM('firm_owner','coach','associate','client_owner','client_member','sponsor') NOT NULL,
    `status`        ENUM('invited','active','disabled') NOT NULL DEFAULT 'invited',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- One identity per email per firm. The same person may be a coach at one
    -- firm and a client contact at another; that is two rows, two tenants.
    UNIQUE KEY `uq_tenant_email`  (`tenant_id`, `email`),
    KEY `idx_tenant_org`  (`tenant_id`, `client_org_id`),
    KEY `idx_tenant_role` (`tenant_id`, `role`),
    KEY `idx_email`       (`email`),

    CONSTRAINT `pl_fk_users_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_users_client_org`
        FOREIGN KEY (`client_org_id`) REFERENCES `pl_client_orgs`(`id`) ON DELETE CASCADE,

    CONSTRAINT `pl_chk_users_side_matches_role`
        CHECK (
            (`client_org_id` IS NULL     AND `role` IN ('firm_owner','coach','associate'))
            OR
            (`client_org_id` IS NOT NULL AND `role` IN ('client_owner','client_member','sponsor'))
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
