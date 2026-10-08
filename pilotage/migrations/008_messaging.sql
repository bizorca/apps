-- Pilotage — 008 messaging
--
-- M8. Asynchronous, email-notified, engagement-scoped. Deliberately NOT
-- real-time chat (SPEC.md §11 out-of-scope): a coaching relationship runs on
-- considered replies, and a chat box creates an expectation of immediacy that
-- an advisor with forty clients cannot meet.
--
-- Comments on artifacts already exist (pl_comments, M6). This adds standalone
-- threads, @-mentions across both, and the reply-by-email path.

CREATE TABLE IF NOT EXISTS `pl_threads` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `engagement_id` INT UNSIGNED NOT NULL,
    `subject`       VARCHAR(255) NOT NULL,
    `created_by`    INT UNSIGNED NULL,
    `status`        ENUM('open','archived') NOT NULL DEFAULT 'open',

    -- Denormalized so a thread list does not need a subquery per row.
    `last_message_at` DATETIME NULL,
    `message_count`   INT UNSIGNED NOT NULL DEFAULT 0,

    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant_engagement` (`tenant_id`, `engagement_id`, `last_message_at`),

    CONSTRAINT `pl_fk_threads_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_threads_engagement`
        FOREIGN KEY (`engagement_id`) REFERENCES `pl_engagements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `pl_messages` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `thread_id`  INT UNSIGNED NOT NULL,
    `author_id`  INT UNSIGNED NULL,
    `author_label` VARCHAR(255) NULL,   -- survives the author being deleted
    `body`       MEDIUMTEXT NOT NULL,

    -- An internal note on a client-facing thread. The coach can think out loud
    -- in the same place the conversation lives without the client seeing it.
    `client_visible` TINYINT(1) NOT NULL DEFAULT 1,

    `via`        ENUM('web','email') NOT NULL DEFAULT 'web',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,   -- soft delete: nothing vanishes while an engagement is live (FR-8.6)

    KEY `idx_thread` (`thread_id`, `created_at`),
    KEY `idx_tenant` (`tenant_id`),

    CONSTRAINT `pl_fk_messages_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_messages_thread`
        FOREIGN KEY (`thread_id`) REFERENCES `pl_threads`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_messages_author`
        FOREIGN KEY (`author_id`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Who is on a thread, and how much of it they have read.
CREATE TABLE IF NOT EXISTS `pl_thread_participants` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `thread_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,

    -- Read position is a MESSAGE ID, not a timestamp. DATETIME has one-second
    -- granularity, so a message posted in the same second as a read compares
    -- as "not newer" and is silently counted as already read. Ids are
    -- monotonic and have no such edge.
    `last_read_message_id` INT UNSIGNED NULL,
    `last_read_at` DATETIME NULL,   -- display only; never used for comparison
    `muted`      TINYINT(1) NOT NULL DEFAULT 0,

    UNIQUE KEY `uq_thread_user` (`thread_id`, `user_id`),
    KEY `idx_tenant_user` (`tenant_id`, `user_id`),

    CONSTRAINT `pl_fk_participants_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_participants_thread`
        FOREIGN KEY (`thread_id`) REFERENCES `pl_threads`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_participants_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- @-mentions (FR-8.4). Recorded rather than parsed at read time so a mention
-- survives the message being edited, and so "what was I pulled into" is one
-- indexed query.
CREATE TABLE IF NOT EXISTS `pl_mentions` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `object_type` VARCHAR(64) NOT NULL,   -- message | comment
    `object_id`   INT UNSIGNED NOT NULL,
    `mentioned_user_id` INT UNSIGNED NOT NULL,
    `by_user_id`  INT UNSIGNED NULL,
    `context_label` VARCHAR(255) NULL,    -- "Task: send the P&L", for the notification
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notified_at` DATETIME NULL,
    `read_at`     DATETIME NULL,

    KEY `idx_user_unread` (`tenant_id`, `mentioned_user_id`, `read_at`),
    KEY `idx_object`      (`object_type`, `object_id`),

    CONSTRAINT `pl_fk_mentions_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_mentions_user`
        FOREIGN KEY (`mentioned_user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Reply-by-email (FR-8.3). The reply-to address carries a selector/verifier
-- token identifying the thread AND the user, so an inbound message can be
-- attributed without trusting the From header — which is trivially forged.
CREATE TABLE IF NOT EXISTS `pl_reply_tokens` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `selector`      CHAR(32) NOT NULL,
    `verifier_hash` CHAR(64) NOT NULL,
    `object_type`   VARCHAR(64) NOT NULL,   -- thread | task | document
    `object_id`     INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`    DATETIME NOT NULL,
    `revoked_at`    DATETIME NULL,

    UNIQUE KEY `uq_selector` (`selector`),
    KEY `idx_object` (`object_type`, `object_id`),

    CONSTRAINT `pl_fk_reply_tokens_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_reply_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Announcements (FR-8.5): one message to every client organization, or to a
-- segment of them.
CREATE TABLE IF NOT EXISTS `pl_announcements` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `subject`    VARCHAR(255) NOT NULL,
    `body`       MEDIUMTEXT NOT NULL,
    `segment`    ENUM('all','active','prospects') NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at`    DATETIME NULL,
    `recipient_count` INT UNSIGNED NOT NULL DEFAULT 0,

    KEY `idx_tenant` (`tenant_id`, `created_at`),

    CONSTRAINT `pl_fk_announcements_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
