-- Pilotage — 016 billing and subscription (M13)
--
-- Stripe, over its REST API rather than its PHP SDK. Same reasoning as the
-- mailer: this project carries zero Composer dependencies so deployment stays
-- "rsync the files", and the four Stripe endpoints actually needed here are a
-- curl call each.
--
-- The billing relationship is between Bizorca and the FIRM, always. A client
-- organization is never billed by the platform and never sees a platform
-- payment screen (FR-13.4). That is not a UI decision to be revisited: the
-- client belongs to the coach, and putting our payment form in front of the
-- coach's customer would break the white-label promise that the whole subdomain
-- architecture exists to protect.


-- ---------------------------------------------------------------------------
-- Subscription state, on the tenant.
--
-- A separate table was the obvious alternative and is wrong: a tenant has
-- exactly one subscription, forever, and the state is read on nearly every
-- request to decide whether the workspace is writable. A join for a one-to-one
-- relationship that gates every page is a join for nothing.
--
-- Stripe remains the source of truth for MONEY. These columns are a local cache
-- of what Stripe last told us, so that a page load does not become an API call
-- and an outage at Stripe does not lock every firm out of its own data. When
-- the two disagree, Stripe wins and the webhook corrects us.
-- ---------------------------------------------------------------------------
ALTER TABLE `pl_tenants`
    ADD COLUMN `plan` VARCHAR(32) NOT NULL DEFAULT 'trial'
        COMMENT 'key into Billing::PLANS'
        AFTER `status`,
    ADD COLUMN `billing_status` ENUM('trialing','active','past_due','canceled','unpaid') NOT NULL DEFAULT 'trialing'
        AFTER `plan`,
    ADD COLUMN `trial_ends_at` DATETIME NULL
        AFTER `billing_status`,
    ADD COLUMN `stripe_customer_id` VARCHAR(64) NULL AFTER `trial_ends_at`,
    ADD COLUMN `stripe_subscription_id` VARCHAR(64) NULL AFTER `stripe_customer_id`,
    ADD COLUMN `billing_interval` ENUM('month','year') NOT NULL DEFAULT 'month' AFTER `stripe_subscription_id`,
    ADD COLUMN `current_period_end` DATETIME NULL AFTER `billing_interval`,
    -- Set when a subscription is cancelled but paid up to the period end. The
    -- firm keeps working until then; there is no reason to punish someone for
    -- cancelling early other than spite.
    ADD COLUMN `cancel_at_period_end` TINYINT(1) NOT NULL DEFAULT 0 AFTER `current_period_end`;

ALTER TABLE `pl_tenants`
    ADD UNIQUE KEY `uq_stripe_customer` (`stripe_customer_id`),
    ADD KEY `idx_billing_status` (`billing_status`, `trial_ends_at`);


-- ---------------------------------------------------------------------------
-- Every webhook Stripe has sent us.
--
-- This exists for ONE reason: idempotency. Stripe retries a webhook it did not
-- get a 2xx for, up to three days, and it will happily deliver the same event
-- twice when a response is slow rather than absent. A handler that upgrades a
-- plan or extends a period without checking whether it has already seen the
-- event will do it twice.
--
-- The unique index on stripe_event_id is the whole mechanism. Insert first,
-- handle second: if the insert raises a duplicate key, another delivery of the
-- same event already has it.
--
-- The raw payload is kept because billing disputes are answered with evidence,
-- and "what exactly did Stripe tell us, and when" is the only useful evidence.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_billing_events` (
    `id`        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NULL,      -- null until we can map it to a firm

    `stripe_event_id` VARCHAR(64)  NOT NULL,
    `event_type`      VARCHAR(64)  NOT NULL,

    `payload`      JSON     NULL,
    `handled_at`   DATETIME NULL,
    `handler_note` VARCHAR(500) NULL,   -- why it was ignored, when it was

    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_event` (`stripe_event_id`),
    KEY `idx_tenant` (`tenant_id`, `received_at`),
    KEY `idx_type`   (`event_type`, `received_at`),

    -- ON DELETE SET NULL rather than CASCADE. A closed firm's billing history
    -- has to outlive the firm — it is the answer to a chargeback that arrives
    -- two months after they left.
    CONSTRAINT `pl_fk_billing_events_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Seat history.
--
-- The firm is billed per active coach seat, so the seat count is a billable
-- quantity and every change to it is a money event. Recording them locally
-- means a firm asking "why did my bill go up in March" can be answered from
-- our own data rather than by reading Stripe invoices line by line.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_seat_changes` (
    `id`        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,

    `seats_before` SMALLINT UNSIGNED NOT NULL,
    `seats_after`  SMALLINT UNSIGNED NOT NULL,
    `reason`       VARCHAR(120) NOT NULL,
    `changed_by`   INT UNSIGNED NULL,
    `synced_at`    DATETIME NULL,        -- when Stripe was told, if ever

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_tenant` (`tenant_id`, `created_at`),

    CONSTRAINT `pl_fk_seat_changes_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_seat_changes_user`
        FOREIGN KEY (`changed_by`) REFERENCES `pl_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Existing firms are on trial from today rather than from whenever they signed
-- up. Backdating would expire the only tenant in production the moment this
-- migration lands, which is a rude way to introduce billing.
UPDATE `pl_tenants`
   SET `trial_ends_at` = NOW() + INTERVAL 14 DAY
 WHERE `trial_ends_at` IS NULL AND `status` IN ('trial', 'active');
