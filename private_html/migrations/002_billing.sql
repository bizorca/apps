-- Billing for the shared account: one paid membership that unlocks every paid
-- tool. Ported from billing.bizorca.com (2026-10-08), minus its plan matrix:
-- the old plans/plan_entitlements per-app grid is replaced by one boolean
-- question, "is this account a member?", answered by an active subscription or
-- a comp. Which tools are paid, and whether that is enforced at all, is config
-- (TL_PAID_TOOLS, TL_BILLING_ENFORCE), not schema.

-- The Stripe customer behind an account. One per account, created lazily at
-- first checkout.
CREATE TABLE IF NOT EXISTS tl_billing_customers (
  user_id            INT NOT NULL PRIMARY KEY,
  stripe_customer_id VARCHAR(100) NOT NULL UNIQUE,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tl_billing_customers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stripe subscriptions, mirrored by the webhook. status is Stripe's own value
-- (active, trialing, past_due, unpaid, canceled, incomplete, incomplete_expired,
-- paused) rather than a lossy local mapping.
CREATE TABLE IF NOT EXISTS tl_subscriptions (
  id                     INT AUTO_INCREMENT PRIMARY KEY,
  user_id                INT NOT NULL,
  stripe_subscription_id VARCHAR(100) NOT NULL UNIQUE,
  stripe_customer_id     VARCHAR(100) NOT NULL,
  billing_interval       VARCHAR(10)  NOT NULL DEFAULT 'month',
  status                 VARCHAR(30)  NOT NULL,
  current_period_end     DATETIME NULL,
  cancel_at_period_end   TINYINT(1) NOT NULL DEFAULT 0,
  created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tl_subscriptions_user (user_id, status),
  CONSTRAINT fk_tl_subscriptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Free memberships granted by an admin (replaces user_entitlement_overrides).
CREATE TABLE IF NOT EXISTS tl_comps (
  user_id    INT NOT NULL PRIMARY KEY,
  note       VARCHAR(255) NOT NULL DEFAULT '',
  expires_at DATETIME NULL DEFAULT NULL,
  granted_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tl_comps_user    FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_tl_comps_granter FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every Stripe event id we have processed. Stripe retries and can deliver the
-- same event twice; the primary key makes a second delivery a no-op.
CREATE TABLE IF NOT EXISTS tl_stripe_events (
  event_id    VARCHAR(100) NOT NULL PRIMARY KEY,
  type        VARCHAR(100) NOT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
