-- Pilotage — 013 tags
--
-- A real tag model, replacing the comma-separated `tags` column on documents.
--
-- Why not keep the string. Three things it cannot do, all of which the actual
-- use case needs — "show me everything tagged bank financing across all my
-- clients":
--
--   1. Enumerate. You cannot list the tags in use, so there is no picker, no
--      autocomplete, and no way to see that someone typed "cashflow" and
--      someone else typed "cash flow".
--   2. Match exactly. LIKE '%bank%' also matches "bankruptcy" and "embankment".
--   3. Rename or merge. Fixing a typo means rewriting every row that contains
--      it, with no way to find them reliably (see 2).
--
-- Polymorphic on purpose, like pl_comments. The value of a tag is that it
-- CROSSES object types: "bank financing" is a task, three documents and an
-- issue, and seeing them together is the whole point. A tag table per type
-- would defeat that before it started.

CREATE TABLE IF NOT EXISTS `pl_tags` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,

    `name`  VARCHAR(60) NOT NULL,   -- as typed: "Bank financing"
    `slug`  VARCHAR(60) NOT NULL,   -- normalised for matching: "bank-financing"
    `color` CHAR(7) NULL,           -- #rrggbb, optional

    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- One tag per slug per firm. This is what stops "cashflow" and "Cash Flow"
    -- becoming two tags nobody can reconcile later.
    UNIQUE KEY `uq_tenant_slug` (`tenant_id`, `slug`),

    CONSTRAINT `pl_fk_tags_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_tags_creator`
        FOREIGN KEY (`created_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_taggings` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `tag_id`      INT UNSIGNED NOT NULL,
    `object_type` VARCHAR(64)  NOT NULL,   -- task | document | issue | client_org | engagement
    `object_id`   INT UNSIGNED NOT NULL,
    `tagged_by`   INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_tagging` (`tag_id`, `object_type`, `object_id`),
    KEY `idx_object` (`tenant_id`, `object_type`, `object_id`),
    KEY `idx_tag`    (`tenant_id`, `tag_id`),

    CONSTRAINT `pl_fk_taggings_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_taggings_tag`
        FOREIGN KEY (`tag_id`) REFERENCES `pl_tags`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_taggings_tagger`
        FOREIGN KEY (`tagged_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- The old comma-separated column on pl_documents stays for now. Migrating its
-- contents is done in PHP (Tags::migrateDocumentStrings) rather than SQL,
-- because splitting a string and slugging each part is not something MySQL
-- should be asked to do, and the column is dropped only once that has run
-- cleanly against real data.
