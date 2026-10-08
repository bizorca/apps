-- TimeBank, ported from the original database.sql (MySQL already).
--
-- Every table is tm_-prefixed. A member row is a MEMBERSHIP: one person's
-- profile, balance and role in one community, now tied to the shared tools
-- account by user_id (one account may belong to several communities). Login
-- columns are gone (password_hash, reset_token*), as is password_resets: the
-- shared account handles sign-in and resets. The per-community PayPal/Stripe
-- key columns on tenants are gone too: the code never read them, and keys do
-- not belong in a table.
--
-- Hours and balances stay DECIMAL, exactly as in the original, so the ledger
-- carries over to the cent with no conversion.
--
-- No seed rows: communities come from bin/import-mysqldump.php (the live
-- "demo" community) or bin/create-community.php.

CREATE TABLE IF NOT EXISTS `tm_tenants` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subdomain`               VARCHAR(50)  NOT NULL,
  `name`                    VARCHAR(150) NOT NULL,
  `tagline`                 TEXT         NULL,
  `logo_path`               VARCHAR(255) NULL,
  `timezone`                VARCHAR(50)  NOT NULL DEFAULT 'America/Chicago',
  `currency_name`           VARCHAR(50)  NOT NULL DEFAULT 'Hour',
  `currency_name_plural`    VARCHAR(50)  NOT NULL DEFAULT 'Hours',
  `welcome_credits`         DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  `community_fund_balance`  DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `settings`                JSON         NULL     COMMENT 'allow_self_registration, require_approval, etc',
  `is_active`               BOOLEAN      NOT NULL DEFAULT TRUE,
  `created_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tm_tenants_subdomain` (`subdomain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_members` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`           INT UNSIGNED  NOT NULL,
  `user_id`             INT           NOT NULL COMMENT 'shared tools account (users.id)',
  `email`               VARCHAR(255)  NOT NULL COMMENT 'copy of users.email, kept in sync by Auth::membership()',
  `role`                ENUM('member','admin','super_admin') NOT NULL DEFAULT 'member',
  `first_name`          VARCHAR(75)   NOT NULL DEFAULT '',
  `last_name`           VARCHAR(75)   NOT NULL DEFAULT '',
  `display_name`        VARCHAR(100)  NULL,
  `bio`                 TEXT          NULL,
  `avatar_path`         VARCHAR(255)  NULL,
  `phone`               VARCHAR(30)   NULL,
  `address`             VARCHAR(255)  NULL,
  `city`                VARCHAR(75)   NULL,
  `state`               VARCHAR(50)   NULL,
  `zip`                 VARCHAR(20)   NULL,
  `country`             VARCHAR(3)    NOT NULL DEFAULT 'US',
  `balance`             DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
  `is_active`           BOOLEAN       NOT NULL DEFAULT TRUE,
  `is_approved`         BOOLEAN       NOT NULL DEFAULT FALSE,
  `household_head_id`   INT UNSIGNED  NULL     COMMENT 'Points to the head-of-household member',
  `privacy_settings`    JSON          NULL     COMMENT '{show_email, show_phone, show_address}',
  `email_preferences`   JSON          NULL     COMMENT '{weekly_digest, new_message, transaction_recorded}',
  `terms_accepted_at`   TIMESTAMP     NULL,
  `guardian_id`         INT UNSIGNED  NULL     COMMENT 'Guardian for minor accounts',
  `last_login_at`       TIMESTAMP     NULL,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tm_members_tenant_user`  (`tenant_id`, `user_id`),
  UNIQUE KEY `uq_tm_members_tenant_email` (`tenant_id`, `email`),
  KEY `idx_tm_members_user`         (`user_id`),
  KEY `idx_tm_members_tenant_id`         (`tenant_id`),
  KEY `idx_tm_members_household_head`    (`household_head_id`),
  KEY `idx_tm_members_guardian`          (`guardian_id`),
  KEY `idx_tm_members_role`              (`role`),
  KEY `idx_tm_members_is_active`         (`is_active`, `is_approved`),
  CONSTRAINT `fk_tm_members_user`        FOREIGN KEY (`user_id`)           REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_members_tenant`      FOREIGN KEY (`tenant_id`)         REFERENCES `tm_tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_members_household`   FOREIGN KEY (`household_head_id`) REFERENCES `tm_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tm_members_guardian`    FOREIGN KEY (`guardian_id`)       REFERENCES `tm_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_groups` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED  NOT NULL,
  `name`        VARCHAR(100)  NOT NULL,
  `description` TEXT          NULL,
  `created_by`  INT UNSIGNED  NOT NULL,
  `is_active`   BOOLEAN       NOT NULL DEFAULT TRUE,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_groups_tenant_id`  (`tenant_id`),
  KEY `idx_tm_groups_created_by` (`created_by`),
  CONSTRAINT `fk_tm_groups_tenant`      FOREIGN KEY (`tenant_id`) REFERENCES `tm_tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_groups_created_by`  FOREIGN KEY (`created_by`) REFERENCES `tm_members` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_group_members` (
  `group_id`   INT UNSIGNED NOT NULL,
  `member_id`  INT UNSIGNED NOT NULL,
  `role`       ENUM('member','moderator') NOT NULL DEFAULT 'member',
  `joined_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`, `member_id`),
  KEY `idx_tm_group_members_member_id` (`member_id`),
  CONSTRAINT `fk_tm_group_members_group`  FOREIGN KEY (`group_id`)  REFERENCES `tm_groups`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_group_members_member` FOREIGN KEY (`member_id`) REFERENCES `tm_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `icon`       VARCHAR(50)  NOT NULL DEFAULT 'tag',
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_active`  BOOLEAN      NOT NULL DEFAULT TRUE,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_categories_tenant_id`  (`tenant_id`),
  KEY `idx_tm_categories_sort_order` (`sort_order`),
  CONSTRAINT `fk_tm_categories_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tm_tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_offers` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED  NOT NULL,
  `member_id`   INT UNSIGNED  NOT NULL,
  `type`        ENUM('offer','request') NOT NULL,
  `title`       VARCHAR(200)  NOT NULL,
  `description` TEXT          NULL,
  `category_id` INT UNSIGNED  NULL,
  `image_path`  VARCHAR(255)  NULL,
  `is_active`   BOOLEAN       NOT NULL DEFAULT TRUE,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_offers_tenant_id`   (`tenant_id`),
  KEY `idx_tm_offers_member_id`   (`member_id`),
  KEY `idx_tm_offers_category_id` (`category_id`),
  KEY `idx_tm_offers_type`        (`type`, `is_active`),
  FULLTEXT KEY `ft_tm_offers_search` (`title`, `description`),
  CONSTRAINT `fk_tm_offers_tenant`   FOREIGN KEY (`tenant_id`)   REFERENCES `tm_tenants`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_offers_member`   FOREIGN KEY (`member_id`)   REFERENCES `tm_members`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_offers_category` FOREIGN KEY (`category_id`) REFERENCES `tm_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_transactions` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED    NOT NULL,
  `type`        ENUM('one_to_one','one_to_many','many_to_one') NOT NULL DEFAULT 'one_to_one',
  `provider_id` INT UNSIGNED    NOT NULL,
  `receiver_id` INT UNSIGNED    NULL,
  `group_id`    INT UNSIGNED    NULL,
  `hours`       DECIMAL(6,2)    NOT NULL,
  `prep_hours`  DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
  `service_date` DATE           NOT NULL,
  `description` TEXT            NULL,
  `offer_id`    INT UNSIGNED    NULL,
  `status`      ENUM('confirmed','disputed','cancelled') NOT NULL DEFAULT 'confirmed',
  `recorded_by` INT UNSIGNED    NOT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_transactions_tenant_id`   (`tenant_id`),
  KEY `idx_tm_transactions_provider_id` (`provider_id`),
  KEY `idx_tm_transactions_receiver_id` (`receiver_id`),
  KEY `idx_tm_transactions_group_id`    (`group_id`),
  KEY `idx_tm_transactions_offer_id`    (`offer_id`),
  KEY `idx_tm_transactions_status`      (`status`),
  KEY `idx_tm_transactions_service_date` (`service_date`),
  CONSTRAINT `fk_tm_transactions_tenant`      FOREIGN KEY (`tenant_id`)   REFERENCES `tm_tenants`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_transactions_provider`    FOREIGN KEY (`provider_id`) REFERENCES `tm_members`      (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tm_transactions_receiver`    FOREIGN KEY (`receiver_id`) REFERENCES `tm_members`      (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tm_transactions_group`       FOREIGN KEY (`group_id`)    REFERENCES `tm_groups`       (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tm_transactions_offer`       FOREIGN KEY (`offer_id`)    REFERENCES `tm_offers`       (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tm_transactions_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `tm_members`      (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_transaction_participants` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id` INT UNSIGNED NOT NULL,
  `member_id`      INT UNSIGNED NOT NULL,
  `role`           ENUM('provider','receiver') NOT NULL,
  `hours`          DECIMAL(6,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tm_tp_transaction_id` (`transaction_id`),
  KEY `idx_tm_tp_member_id`      (`member_id`),
  CONSTRAINT `fk_tm_tp_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `tm_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_tp_member`      FOREIGN KEY (`member_id`)      REFERENCES `tm_members`      (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_messages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `sender_id`  INT UNSIGNED NOT NULL,
  `subject`    VARCHAR(255) NOT NULL DEFAULT '',
  `body`       TEXT         NOT NULL,
  `group_id`   INT UNSIGNED NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_messages_tenant_id`  (`tenant_id`),
  KEY `idx_tm_messages_sender_id`  (`sender_id`),
  KEY `idx_tm_messages_group_id`   (`group_id`),
  CONSTRAINT `fk_tm_messages_tenant`  FOREIGN KEY (`tenant_id`) REFERENCES `tm_tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_messages_sender`  FOREIGN KEY (`sender_id`) REFERENCES `tm_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_messages_group`   FOREIGN KEY (`group_id`)  REFERENCES `tm_groups`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_message_recipients` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id`   INT UNSIGNED NOT NULL,
  `recipient_id` INT UNSIGNED NOT NULL,
  `is_read`      BOOLEAN      NOT NULL DEFAULT FALSE,
  `read_at`      TIMESTAMP    NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tm_mr_message_id`   (`message_id`),
  KEY `idx_tm_mr_recipient_id` (`recipient_id`),
  KEY `idx_tm_mr_is_read`      (`recipient_id`, `is_read`),
  CONSTRAINT `fk_tm_mr_message`   FOREIGN KEY (`message_id`)   REFERENCES `tm_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_mr_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `tm_members`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_announcements` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `author_id`  INT UNSIGNED NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `body`       TEXT         NOT NULL,
  `group_id`   INT UNSIGNED NULL,
  `is_pinned`  BOOLEAN      NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_announcements_tenant_id` (`tenant_id`),
  KEY `idx_tm_announcements_author_id` (`author_id`),
  KEY `idx_tm_announcements_group_id`  (`group_id`),
  KEY `idx_tm_announcements_pinned`    (`tenant_id`, `is_pinned`),
  CONSTRAINT `fk_tm_announcements_tenant`  FOREIGN KEY (`tenant_id`) REFERENCES `tm_tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_announcements_author`  FOREIGN KEY (`author_id`) REFERENCES `tm_members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tm_announcements_group`   FOREIGN KEY (`group_id`)  REFERENCES `tm_groups`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_endorsements` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `tenant_id`      INT UNSIGNED   NOT NULL,
  `from_member_id` INT UNSIGNED   NOT NULL,
  `to_member_id`   INT UNSIGNED   NOT NULL,
  `transaction_id` INT UNSIGNED   NULL,
  `rating`         TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1-5 stars',
  `comment`        TEXT           NULL,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_endorsements_tenant_id`      (`tenant_id`),
  KEY `idx_tm_endorsements_from_member`    (`from_member_id`),
  KEY `idx_tm_endorsements_to_member`      (`to_member_id`),
  KEY `idx_tm_endorsements_transaction_id` (`transaction_id`),
  CONSTRAINT `fk_tm_endorsements_tenant`      FOREIGN KEY (`tenant_id`)      REFERENCES `tm_tenants`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_endorsements_from_member` FOREIGN KEY (`from_member_id`) REFERENCES `tm_members`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_endorsements_to_member`   FOREIGN KEY (`to_member_id`)   REFERENCES `tm_members`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_endorsements_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `tm_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_donations` (
  `id`                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `tenant_id`         INT UNSIGNED   NOT NULL,
  `member_id`         INT UNSIGNED   NOT NULL,
  `amount_usd`        DECIMAL(8,2)   NULL,
  `hours`             DECIMAL(6,2)   NULL,
  `payment_method`    ENUM('paypal','stripe','forgiven','manual') NOT NULL,
  `status`            ENUM('pending','paid','forgiven') NOT NULL DEFAULT 'pending',
  `payment_reference` VARCHAR(255)   NULL,
  `notes`             TEXT           NULL,
  `requested_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at`           TIMESTAMP      NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tm_donations_tenant_id`  (`tenant_id`),
  KEY `idx_tm_donations_member_id`  (`member_id`),
  KEY `idx_tm_donations_status`     (`status`),
  KEY `idx_tm_donations_requested_at` (`requested_at`),
  CONSTRAINT `fk_tm_donations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tm_tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_donations_member` FOREIGN KEY (`member_id`) REFERENCES `tm_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_email_templates` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `slug`       VARCHAR(100) NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `subject`    VARCHAR(255) NOT NULL,
  `body`       TEXT         NOT NULL,
  `variables`  TEXT         NULL COMMENT 'comma-separated available variable names',
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tm_email_templates_tenant_slug` (`tenant_id`, `slug`),
  KEY `idx_tm_email_templates_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_tm_email_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tm_tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `member_id`  INT UNSIGNED NOT NULL,
  `type`       VARCHAR(50)  NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `body`       TEXT         NULL,
  `url`        VARCHAR(255) NULL,
  `is_read`    BOOLEAN      NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_notifications_tenant_id`  (`tenant_id`),
  KEY `idx_tm_notifications_member_id`  (`member_id`),
  KEY `idx_tm_notifications_is_read`    (`member_id`, `is_read`),
  CONSTRAINT `fk_tm_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tm_tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_notifications_member` FOREIGN KEY (`member_id`) REFERENCES `tm_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
