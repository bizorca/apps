-- Numbrella on tools.bizorca.com. Users are the shared `users` table; the
-- standalone app's own users table is gone. MySQL 8.4.

CREATE TABLE IF NOT EXISTS nb_reports (
  id                          INT AUTO_INCREMENT PRIMARY KEY,
  user_id                     INT NOT NULL,

  -- Step 1: the business
  business_name               VARCHAR(255) NOT NULL DEFAULT '',
  industry_key                VARCHAR(50)  NOT NULL DEFAULT 'other',
  years_in_operation          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  num_employees               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  business_type               VARCHAR(20)  NOT NULL DEFAULT 'llc',
  asking_price                DECIMAL(15,2) NULL,

  -- Step 2: financials. Y3 is the most recent full year. A NULL revenue year
  -- means "not provided" (a young business), which the engine leaves out
  -- rather than counting as zero.
  revenue_y1                  DECIMAL(15,2) NULL,
  revenue_y2                  DECIMAL(15,2) NULL,
  revenue_y3                  DECIMAL(15,2) NULL,
  net_profit_y1               DECIMAL(15,2) NULL,
  net_profit_y2               DECIMAL(15,2) NULL,
  net_profit_y3               DECIMAL(15,2) NULL,
  owner_salary                DECIMAL(15,2) NOT NULL DEFAULT 0,
  addbacks_json               TEXT NULL,

  -- Step 3: market position
  customer_concentration_pct  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  has_written_contracts       TINYINT(1) NOT NULL DEFAULT 0,
  owner_works_full_time       TINYINT(1) NOT NULL DEFAULT 1,
  has_key_employees           TINYINT(1) NOT NULL DEFAULT 0,
  lease_years_remaining       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  notes                       TEXT NULL,

  -- Output, computed once when the report is finalized and never recomputed on view
  valuation_json              MEDIUMTEXT NULL,
  risk_flags_json             TEXT NULL,

  wizard_step                 TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status                      ENUM('draft','complete') NOT NULL DEFAULT 'draft',
  -- 'premium' is the full report. While NB_PAYMENTS_ENABLED is off every
  -- report is finalized as premium.
  tier                        ENUM('standard','premium') NOT NULL DEFAULT 'premium',
  share_token                 CHAR(64) NULL,

  created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at                TIMESTAMP NULL,

  UNIQUE KEY uq_nb_reports_share (share_token),
  KEY idx_nb_reports_user (user_id, created_at),
  CONSTRAINT fk_nb_reports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only written when NB_PAYMENTS_ENABLED is on (Stripe Checkout, one-time).
CREATE TABLE IF NOT EXISTS nb_payments (
  id                     INT AUTO_INCREMENT PRIMARY KEY,
  report_id              INT NOT NULL,
  user_id                INT NOT NULL,
  tier                   ENUM('standard','premium') NOT NULL,
  amount_cents           INT UNSIGNED NOT NULL,
  stripe_session_id      VARCHAR(255) NULL,
  stripe_payment_intent  VARCHAR(255) NULL,
  status                 ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at                TIMESTAMP NULL,

  UNIQUE KEY uq_nb_payments_session (stripe_session_id),
  KEY idx_nb_payments_report (report_id),
  CONSTRAINT fk_nb_payments_report FOREIGN KEY (report_id) REFERENCES nb_reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_nb_payments_user   FOREIGN KEY (user_id)   REFERENCES users(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
