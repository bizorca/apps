-- Pilotage — 002 auth
--
-- M2: identity, sessions, magic links, invitations, TOTP, rate limiting,
-- audit, and impersonation.
--
-- Token tables all use the selector/verifier split rather than storing a
-- token hash alone: the selector is indexed and looked up directly, the
-- verifier is compared with hash_equals against a stored hash. That gives an
-- indexed lookup without a timing oracle, and a database leak yields nothing
-- usable.

-- Server-side session records (FR-2.4). PHP's session cookie carries only the
-- id; everything authoritative lives here, so a session can be revoked
-- server-side and listed back to the user.
CREATE TABLE IF NOT EXISTS `pl_user_sessions` (
    `id`             CHAR(64)     NOT NULL PRIMARY KEY,  -- random, not the PHP session id
    `tenant_id`      INT UNSIGNED NOT NULL,
    `user_id`        INT UNSIGNED NOT NULL,
    `ip`             VARBINARY(16) NULL,                 -- inet6_aton form
    `user_agent`     VARCHAR(255) NULL,
    `is_firm_side`   TINYINT(1)   NOT NULL,              -- drives the idle timeout
    `impersonator_id` INT UNSIGNED NULL,                 -- set when a platform admin is driving
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`     DATETIME     NOT NULL,              -- absolute cap
    `revoked_at`     DATETIME     NULL,
    KEY `idx_tenant_user` (`tenant_id`, `user_id`),
    KEY `idx_expires`     (`expires_at`),
    CONSTRAINT `pl_fk_user_sessions_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_user_sessions_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Magic links (FR-2.1). Business owners will not remember a password for a
-- portal they visit weekly, so this is the primary client-side path.
CREATE TABLE IF NOT EXISTS `pl_magic_links` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,
    `selector`      CHAR(32)     NOT NULL,
    `verifier_hash` CHAR(64)     NOT NULL,   -- sha256 of the verifier half
    `redirect_to`   VARCHAR(512) NULL,       -- deep link the user was reaching for
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`    DATETIME     NOT NULL,
    `consumed_at`   DATETIME     NULL,       -- single use
    `requested_ip`  VARBINARY(16) NULL,
    UNIQUE KEY `uq_selector` (`selector`),
    KEY `idx_tenant_user` (`tenant_id`, `user_id`),
    KEY `idx_expires`     (`expires_at`),
    CONSTRAINT `pl_fk_magic_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_magic_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Invitations (FR-2.3). Single use, 7-day expiry, role baked in at issue time
-- so it cannot be escalated by the invitee.
CREATE TABLE IF NOT EXISTS `pl_invitations` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `client_org_id` INT UNSIGNED NULL,       -- set => inviting a client-side contact
    `email`         VARCHAR(255) NOT NULL,
    `name`          VARCHAR(255) NULL,
    `role`          ENUM('firm_owner','coach','associate','client_owner','client_member','sponsor') NOT NULL,
    `selector`      CHAR(32)     NOT NULL,
    `verifier_hash` CHAR(64)     NOT NULL,
    `invited_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`    DATETIME     NOT NULL,
    `accepted_at`   DATETIME     NULL,
    `accepted_user_id` INT UNSIGNED NULL,
    `revoked_at`    DATETIME     NULL,
    UNIQUE KEY `uq_selector` (`selector`),
    KEY `idx_tenant_email` (`tenant_id`, `email`),
    KEY `idx_expires`      (`expires_at`),
    CONSTRAINT `pl_fk_invitations_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_invitations_org`
        FOREIGN KEY (`client_org_id`) REFERENCES `pl_client_orgs`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_invitations_inviter`
        FOREIGN KEY (`invited_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL,

    -- Same side-of-the-wall rule as pl_users. An invitation that would create
    -- an impossible user must be impossible to issue.
    CONSTRAINT `pl_chk_invitations_side_matches_role`
        CHECK (
            (`client_org_id` IS NULL     AND `role` IN ('firm_owner','coach','associate'))
            OR
            (`client_org_id` IS NOT NULL AND `role` IN ('client_owner','client_member','sponsor'))
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TOTP (FR-2.2). Mandatory for firm owners, optional for everyone else.
CREATE TABLE IF NOT EXISTS `pl_user_2fa` (
    `user_id`      INT UNSIGNED NOT NULL PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `secret`       VARCHAR(128) NOT NULL,   -- base32
    `confirmed_at` DATETIME     NULL,       -- null until a first valid code is entered
    `last_used_at` DATETIME     NULL,
    `last_used_counter` BIGINT UNSIGNED NULL, -- replay guard: a code is good once
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tenant` (`tenant_id`),
    CONSTRAINT `pl_fk_2fa_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_2fa_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_2fa_recovery_codes` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`   INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `code_hash` CHAR(64)     NOT NULL,
    `used_at`   DATETIME     NULL,
    `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_user` (`user_id`),
    CONSTRAINT `pl_fk_recovery_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_recovery_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Rate limiting on login, magic-link issuance, and invitation acceptance (§6).
-- Not tenant-scoped: an attacker enumerating subdomains must not get a fresh
-- budget per tenant.
CREATE TABLE IF NOT EXISTS `pl_auth_attempts` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action`     VARCHAR(32)   NOT NULL,   -- login, magic_link, invite_accept, totp
    `identifier` VARCHAR(255)  NOT NULL,   -- email or token selector, lowercased
    `ip`         VARBINARY(16) NULL,
    `successful` TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_action_identifier` (`action`, `identifier`, `created_at`),
    KEY `idx_action_ip`         (`action`, `ip`, `created_at`),
    KEY `idx_created`           (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Append-only audit log (FR-14.1). No UPDATE or DELETE path exists in code.
CREATE TABLE IF NOT EXISTS `pl_audit_log` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED  NULL,      -- null for platform-level events
    `actor_user_id` INT UNSIGNED NULL,     -- null for anonymous or system events
    `actor_label` VARCHAR(255)  NULL,      -- preserved even if the user is later deleted
    `impersonator_id` INT UNSIGNED NULL,
    `action`      VARCHAR(64)   NOT NULL,
    `object_type` VARCHAR(64)   NULL,
    `object_id`   INT UNSIGNED  NULL,
    `ip`          VARBINARY(16) NULL,
    `meta`        JSON          NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tenant_created` (`tenant_id`, `created_at`),
    KEY `idx_action`         (`action`, `created_at`),
    KEY `idx_object`         (`object_type`, `object_id`)
    -- Deliberately no FK on actor_user_id: the log must outlive the user.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Platform administrators are Bizorca-side and belong to no tenant, so they
-- are a separate table rather than a flag on pl_users.
CREATE TABLE IF NOT EXISTS `pl_platform_admins` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email`         VARCHAR(255) NOT NULL,
    `name`          VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `totp_secret`   VARCHAR(128) NULL,
    `status`        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Support impersonation (FR-2.6): explicit reason, 60-minute cap, audited.
CREATE TABLE IF NOT EXISTS `pl_impersonations` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id`    INT UNSIGNED NOT NULL,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `reason`      VARCHAR(500) NOT NULL,   -- required, never blank
    `started_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`  DATETIME     NOT NULL,   -- started_at + 60 minutes, hard cap
    `ended_at`    DATETIME     NULL,
    KEY `idx_admin`  (`admin_id`, `started_at`),
    KEY `idx_tenant` (`tenant_id`, `started_at`),
    CONSTRAINT `pl_fk_imp_admin`
        FOREIGN KEY (`admin_id`) REFERENCES `pl_platform_admins`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_imp_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_imp_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
