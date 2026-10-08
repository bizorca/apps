-- Pilotage — 019 cohorts (Phase 3)
--
-- Group coaching: several client organizations moving through one playbook
-- together, on a shared timeline, meeting together.
--
-- ===========================================================================
-- THE ONE THING THIS MUST NOT BREAK
--
-- A cohort creates a SHARED SURFACE between client organizations that have no
-- other relationship. Shared surfaces are exactly where confidentiality walls
-- break, so the design starts from what stays private and works outwards.
--
-- A cohort is a COORDINATING LAYER ABOVE ENGAGEMENTS. It is not a container
-- for records. Every member organization keeps its own engagement, and every
-- commitment, document, metric, goal, issue and message stays scoped to that
-- engagement exactly as before. Cohort membership grants access to precisely
-- three things and nothing else:
--
--   1. the shared sessions — they were all in the room
--   2. material published to the cohort — deliberately published, by the coach
--   3. the roster, and only if the coach turns it on
--
-- The consequence, and the reason it is built this way: all existing scoping
-- holds unchanged. There is no new path by which Alpha can read Beta's
-- commitments, because nothing about cohort membership touches the queries
-- that return commitments. tests/CohortTest.php attacks this directly rather
-- than assuming it.
-- ===========================================================================


-- ---------------------------------------------------------------------------
-- The cohort.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_cohorts` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,

    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT NULL,

    -- The playbook everyone runs. A version, not a playbook: a cohort that
    -- started in March should not silently change shape because someone edited
    -- the template in June. Same reasoning as engagement instantiation.
    `playbook_version_id` INT UNSIGNED NULL,

    `starts_on` DATE NULL,
    `ends_on`   DATE NULL,
    `cadence`   ENUM('weekly','biweekly','monthly','quarterly','adhoc') NOT NULL DEFAULT 'monthly',

    -- Whether members can see who else is in the cohort.
    --
    -- Off by default, and that default is the careful one. In a peer mastermind
    -- the roster IS the value; in a cohort a coach assembled from clients who
    -- do not know they are in a group together, publishing it is a
    -- confidentiality incident. Defaulting to visible would make that incident
    -- the outcome of doing nothing.
    `roster_visible` TINYINT(1) NOT NULL DEFAULT 0,

    `status` ENUM('draft','active','complete','archived') NOT NULL DEFAULT 'draft',

    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `idx_tenant_status` (`tenant_id`, `status`),

    CONSTRAINT `pl_fk_cohorts_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_cohorts_version`
        FOREIGN KEY (`playbook_version_id`) REFERENCES `pl_playbook_versions`(`id`) ON DELETE SET NULL,
    CONSTRAINT `pl_fk_cohorts_creator`
        FOREIGN KEY (`created_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Membership, by ENGAGEMENT rather than by organization.
--
-- This is the load-bearing choice in the whole module. Joining an engagement
-- rather than an org means:
--
--   - a client can be in a cohort for one piece of work and not another, which
--     is how consulting actually goes.
--   - the existing scoping applies unchanged, because an engagement is already
--     the unit everything else is scoped to.
--   - leaving a cohort cannot orphan anything, because nothing was ever owned
--     by the cohort in the first place.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_cohort_members` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,

    `cohort_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,

    `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `left_at`   DATETIME NULL,

    -- Left in place rather than deleted, so a cohort's history stays honest:
    -- "who was in the room in March" is answerable after someone leaves.
    UNIQUE KEY `uq_member` (`cohort_id`, `engagement_id`),
    KEY `idx_engagement` (`tenant_id`, `engagement_id`),

    CONSTRAINT `pl_fk_cohortmem_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_cohortmem_cohort`
        FOREIGN KEY (`cohort_id`) REFERENCES `pl_cohorts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_cohortmem_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Cohort sessions, on the existing sessions table.
--
-- `engagement_id` becomes nullable and `cohort_id` is added; a session belongs
-- to exactly one of the two.
--
-- Reusing pl_sessions rather than building a parallel table is worth stating
-- plainly, because the alternative looks tidier and is not: a separate table
-- would need its own agenda items, its own attendees, its own shared notes, its
-- own recap, and its own calendar-sync integration. Four duplicated tables and
-- a second copy of the session runner, so that one column could stay NOT NULL.
--
-- The safety property that makes this comfortable: every existing query joins
-- `pl_engagements` on `s.engagement_id` with an INNER JOIN. A cohort session
-- has no engagement, so it drops out of all of them automatically. Existing
-- engagement views cannot accidentally show a cohort session, and they did not
-- have to be changed to achieve that.
-- ---------------------------------------------------------------------------
ALTER TABLE `pl_sessions`
    MODIFY COLUMN `engagement_id` INT UNSIGNED NULL,
    ADD COLUMN `cohort_id` INT UNSIGNED NULL AFTER `engagement_id`;

ALTER TABLE `pl_sessions`
    ADD CONSTRAINT `pl_fk_sessions_cohort`
        FOREIGN KEY (`cohort_id`) REFERENCES `pl_cohorts`(`id`) ON DELETE CASCADE;

-- Exactly one owner. Enforced in the database rather than only in code, because
-- a session belonging to both — or to neither — has no defined audience, and
-- "no defined audience" on a table that carries meeting notes is how a leak
-- starts.
ALTER TABLE `pl_sessions`
    ADD CONSTRAINT `pl_chk_session_owner`
        CHECK ((`engagement_id` IS NULL) <> (`cohort_id` IS NULL));

ALTER TABLE `pl_sessions`
    ADD KEY `idx_tenant_cohort` (`tenant_id`, `cohort_id`, `scheduled_at`);


-- ---------------------------------------------------------------------------
-- Material published to a whole cohort.
--
-- A pointer to a document that already exists in the firm's library, not a
-- copy. Publishing is deliberate and one-directional: the coach shares
-- something with everyone. There is no path by which a member's own document
-- becomes cohort material by accident, which is the failure worth designing
-- against.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_cohort_materials` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,

    `cohort_id`   INT UNSIGNED NOT NULL,
    `document_id` INT UNSIGNED NOT NULL,

    `note`         VARCHAR(500) NULL,
    `published_by` INT UNSIGNED NULL,
    `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_material` (`cohort_id`, `document_id`),
    KEY `idx_cohort` (`tenant_id`, `cohort_id`, `published_at`),

    CONSTRAINT `pl_fk_cohortmat_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_cohortmat_cohort`
        FOREIGN KEY (`cohort_id`) REFERENCES `pl_cohorts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_cohortmat_document`
        FOREIGN KEY (`document_id`) REFERENCES `pl_documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Announcements gain a cohort target.
--
-- The table has existed since 008 with nothing using it. Cohorts are the first
-- thing that genuinely needs "tell this specific set of people something", so
-- it gets wired up here rather than remaining a schema fossil.
-- ---------------------------------------------------------------------------
ALTER TABLE `pl_announcements`
    MODIFY COLUMN `segment` ENUM('all','active','prospects','cohort') NOT NULL DEFAULT 'all',
    ADD COLUMN `cohort_id` INT UNSIGNED NULL AFTER `segment`;

ALTER TABLE `pl_announcements`
    ADD CONSTRAINT `pl_fk_announcements_cohort`
        FOREIGN KEY (`cohort_id`) REFERENCES `pl_cohorts`(`id`) ON DELETE CASCADE;
