-- Pilotage — 007 documents
--
-- M7. This is the module that holds clients' tax returns, P&Ls, and bank
-- statements, so the schema is arranged around three separations:
--
-- 1. THREE CONTEXTS (FR-7.1). A template in the firm's library, a deliverable
--    the coach produced, and a file the client uploaded are different things
--    with different permissions. One `context` column rather than three tables,
--    because they share versioning, comments, and storage — but every query
--    filters on it.
--
-- 2. VERSIONS ARE ROWS (FR-7.3). A document is a stable identity; each upload
--    is a version. Prior versions stay visible to the coach and invisible to
--    the client, which is what lets a coach iterate on a deliverable without
--    the client watching the sausage being made.
--
-- 3. DELIVERY IS AN EVENT, NOT A FLAG (FR-7.2). Advisors get paid on delivery
--    and need to prove it happened. Acknowledgment records who, when, and from
--    what address.
--
-- Storage note: bytes live OUTSIDE the web root under storage/files/, named by
-- random token, never by the user's filename. The original name exists only in
-- this schema. See FR-7.5.

CREATE TABLE IF NOT EXISTS `pl_documents` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,

    -- library: reusable firm template, no engagement
    -- deliverable: produced by the firm FOR a client
    -- client_file: uploaded BY the client
    `context`       ENUM('library','deliverable','client_file') NOT NULL,

    `engagement_id` INT UNSIGNED NULL,   -- null only for library
    `client_org_id` INT UNSIGNED NULL,

    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT NULL,
    `folder`        VARCHAR(255) NULL,
    `tags`          VARCHAR(500) NULL,   -- comma separated; search is LIKE at this scale

    -- FR-7.2 lifecycle. 'superseded' is set when a newer document replaces this
    -- one outright, as opposed to a new version of the same document.
    `status`        ENUM('draft','in_review','delivered','acknowledged','superseded') NOT NULL DEFAULT 'draft',

    `current_version_id` INT UNSIGNED NULL,
    `created_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `idx_tenant_context`    (`tenant_id`, `context`, `status`),
    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`),
    KEY `idx_tenant_org`        (`tenant_id`, `client_org_id`),

    CONSTRAINT `pl_fk_documents_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_documents_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_documents_org`
        FOREIGN KEY (`client_org_id`) REFERENCES `pl_client_orgs`(`id`) ON DELETE CASCADE,

    -- A library template belongs to no engagement; anything else must.
    CONSTRAINT `pl_chk_documents_context`
        CHECK (
            (`context` = 'library' AND `engagement_id` IS NULL)
            OR
            (`context` <> 'library' AND `engagement_id` IS NOT NULL)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_document_versions` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`      INT UNSIGNED NOT NULL,
    `document_id`    INT UNSIGNED NOT NULL,
    `version_number` INT UNSIGNED NOT NULL,

    `original_name`  VARCHAR(255) NOT NULL,   -- what the user called it
    `storage_key`    CHAR(64) NOT NULL,       -- what it is called on disk; random
    `mime_type`      VARCHAR(127) NOT NULL,
    `byte_size`      BIGINT UNSIGNED NOT NULL,
    `sha256`         CHAR(64) NOT NULL,       -- integrity, and duplicate detection

    `uploaded_by`    INT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_document_version` (`document_id`, `version_number`),
    UNIQUE KEY `uq_storage_key`      (`storage_key`),
    KEY `idx_tenant` (`tenant_id`),

    CONSTRAINT `pl_fk_docversions_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_docversions_document`
        FOREIGN KEY (`document_id`) REFERENCES `pl_documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Delivery and acknowledgment (FR-7.2). Separate rows rather than columns on
-- the document, because a document can be delivered more than once — a revised
-- deliverable is delivered again, and the earlier acknowledgment must survive.
CREATE TABLE IF NOT EXISTS `pl_document_deliveries` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `document_id` INT UNSIGNED NOT NULL,
    `version_id`  INT UNSIGNED NOT NULL,
    `delivered_by` INT UNSIGNED NULL,
    `delivered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `note`         VARCHAR(500) NULL,

    `acknowledged_at`    DATETIME NULL,
    `acknowledged_by`    INT UNSIGNED NULL,
    `acknowledged_ip`    VARBINARY(16) NULL,   -- proof of receipt

    KEY `idx_tenant_document` (`tenant_id`, `document_id`),

    CONSTRAINT `pl_fk_deliveries_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_deliveries_document`
        FOREIGN KEY (`document_id`) REFERENCES `pl_documents`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_deliveries_version`
        FOREIGN KEY (`version_id`) REFERENCES `pl_document_versions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Document requests (FR-7.4): "last three years of returns, trailing-12 P&L,
-- current cap table". Every advisor doing financial work needs this and almost
-- no coaching tool has it.
CREATE TABLE IF NOT EXISTS `pl_document_requests` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `note`          TEXT NULL,
    `due_on`        DATE NULL,
    `status`        ENUM('open','complete','cancelled') NOT NULL DEFAULT 'open',
    `created_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at`  DATETIME NULL,

    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`, `status`),

    CONSTRAINT `pl_fk_docrequests_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_docrequests_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_document_request_items` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `request_id`  INT UNSIGNED NOT NULL,
    `position`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `label`       VARCHAR(255) NOT NULL,
    `hint`        VARCHAR(500) NULL,
    `required`    TINYINT(1) NOT NULL DEFAULT 1,
    `document_id` INT UNSIGNED NULL,   -- set when fulfilled
    `fulfilled_at` DATETIME NULL,
    `fulfilled_by` INT UNSIGNED NULL,

    KEY `idx_request` (`request_id`, `position`),

    CONSTRAINT `pl_fk_request_items_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_request_items_request`
        FOREIGN KEY (`request_id`) REFERENCES `pl_document_requests`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_request_items_document`
        FOREIGN KEY (`document_id`) REFERENCES `pl_documents`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Expiring share links for third parties (FR-7.6) — a banker, an attorney.
-- Selector/verifier like every other token here, hard expiry, and every view
-- logged: handing a client's P&L to an outside party is exactly the event you
-- want a record of.
CREATE TABLE IF NOT EXISTS `pl_share_links` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `document_id`   INT UNSIGNED NOT NULL,
    `version_id`    INT UNSIGNED NULL,     -- null = always the current version
    `selector`      CHAR(32) NOT NULL,
    `verifier_hash` CHAR(64) NOT NULL,
    `label`         VARCHAR(255) NULL,     -- "Marie at the bank"
    `created_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`    DATETIME NOT NULL,
    `revoked_at`    DATETIME NULL,
    `max_views`     SMALLINT UNSIGNED NULL,
    `view_count`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    UNIQUE KEY `uq_selector` (`selector`),
    KEY `idx_tenant_document` (`tenant_id`, `document_id`),

    CONSTRAINT `pl_fk_share_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_share_document`
        FOREIGN KEY (`document_id`) REFERENCES `pl_documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_share_link_views` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `link_id`    INT UNSIGNED NOT NULL,
    `ip`         VARBINARY(16) NULL,
    `user_agent` VARCHAR(255) NULL,
    `viewed_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_link` (`link_id`, `viewed_at`),

    CONSTRAINT `pl_fk_share_views_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_share_views_link`
        FOREIGN KEY (`link_id`) REFERENCES `pl_share_links`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Download audit. Who opened which client's financial records, and when.
CREATE TABLE IF NOT EXISTS `pl_document_access_log` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `document_id` INT UNSIGNED NOT NULL,
    `version_id`  INT UNSIGNED NULL,
    `user_id`     INT UNSIGNED NULL,
    `via`         ENUM('portal','share_link') NOT NULL DEFAULT 'portal',
    `ip`          VARBINARY(16) NULL,
    `accessed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant_document` (`tenant_id`, `document_id`, `accessed_at`),

    CONSTRAINT `pl_fk_access_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
