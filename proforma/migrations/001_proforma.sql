-- Proforma, ported from SQLite (the original's sql/schema.sql plus its runtime
-- column migrations: worker_type, share_token). Every table is pf_-prefixed,
-- and user ids point at the shared users table. Proforma's own users table is
-- gone; its first_name/last_name/sso_sub columns did not come across.
--
-- Types: SQLite REAL -> DOUBLE (the calculators do float math, not decimal),
-- TEXT dates -> DATETIME in UTC (tl_db() sets the session zone to +00:00).

CREATE TABLE IF NOT EXISTS pf_businesses (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  user_id             INT NOT NULL,
  business_type       VARCHAR(20)  NOT NULL DEFAULT 'yoga',
  business_name       VARCHAR(255) NOT NULL DEFAULT '',
  owner_salary_annual DOUBLE NOT NULL DEFAULT 0,
  weeks_per_year      INT NOT NULL DEFAULT 50,
  share_token         CHAR(48) NULL DEFAULT NULL UNIQUE,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pf_businesses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_expenses (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  business_id    INT NOT NULL,
  category       VARCHAR(50)  NOT NULL,
  label          VARCHAR(255) NOT NULL,
  amount_monthly DOUBLE NOT NULL DEFAULT 0,
  is_variable    TINYINT(1) NOT NULL DEFAULT 0,
  sort_order     INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pf_expenses_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_class_schedules (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  business_id      INT NOT NULL,
  class_name       VARCHAR(255) NOT NULL DEFAULT 'General Class',
  class_type       VARCHAR(20)  NOT NULL DEFAULT 'in_person',
  classes_per_week INT NOT NULL DEFAULT 10,
  room_capacity    INT NULL,
  avg_fill_rate    DOUBLE NOT NULL DEFAULT 0.60,
  CONSTRAINT fk_pf_class_schedules_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_revenue_streams (
  id                      INT AUTO_INCREMENT PRIMARY KEY,
  business_id             INT NOT NULL,
  stream_type             VARCHAR(30)  NOT NULL,
  label                   VARCHAR(255) NOT NULL,
  price                   DOUBLE NOT NULL DEFAULT 0,
  units_included          INT NULL,
  estimated_monthly_units DOUBLE NOT NULL DEFAULT 0,
  conversion_source       VARCHAR(50) NULL,
  conversion_rate         DOUBLE NULL,
  is_enabled              TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_pf_revenue_streams_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_instructors (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  business_id       INT NOT NULL,
  name              VARCHAR(255) NOT NULL DEFAULT 'Instructor',
  worker_type       VARCHAR(20)  NOT NULL DEFAULT 'contractor',
  pay_type          VARCHAR(20)  NOT NULL DEFAULT 'flat',
  pay_per_class     DOUBLE NOT NULL DEFAULT 0,
  revenue_share_pct DOUBLE NULL,
  classes_per_week  DOUBLE NOT NULL DEFAULT 0,
  CONSTRAINT fk_pf_instructors_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_insurance_payers (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  payer_name  VARCHAR(255) NOT NULL,
  client_pct  DOUBLE NOT NULL DEFAULT 0,
  is_cash_pay TINYINT(1) NOT NULL DEFAULT 0,
  sort_order  INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pf_insurance_payers_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_cpt_codes (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  business_id        INT NOT NULL,
  code               VARCHAR(20)  NOT NULL,
  description        VARCHAR(255) NOT NULL,
  sessions_per_month DOUBLE NOT NULL DEFAULT 0,
  sort_order         INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pf_cpt_codes_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_payer_rates (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  payer_id INT NOT NULL,
  cpt_id   INT NOT NULL,
  rate     DOUBLE NOT NULL DEFAULT 0,
  UNIQUE KEY uq_pf_payer_rates (payer_id, cpt_id),
  CONSTRAINT fk_pf_payer_rates_payer FOREIGN KEY (payer_id) REFERENCES pf_insurance_payers (id) ON DELETE CASCADE,
  CONSTRAINT fk_pf_payer_rates_cpt   FOREIGN KEY (cpt_id)   REFERENCES pf_cpt_codes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_staff_providers (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  business_id       INT NOT NULL,
  name              VARCHAR(255) NOT NULL,
  credential        VARCHAR(50)  NOT NULL DEFAULT '',
  worker_type       VARCHAR(20)  NOT NULL DEFAULT 'contractor',
  sessions_per_week DOUBLE NOT NULL DEFAULT 20,
  hours_per_week    DOUBLE NOT NULL DEFAULT 25,
  pay_type          VARCHAR(20)  NOT NULL DEFAULT 'hourly',
  pay_rate          DOUBLE NOT NULL DEFAULT 0,
  is_owner          TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_pf_staff_providers_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_monthly_actuals (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  business_id    INT NOT NULL,
  year           INT NOT NULL,
  month          TINYINT NOT NULL,
  gross_revenue  DOUBLE NOT NULL DEFAULT 0,
  student_visits INT NOT NULL DEFAULT 0,
  total_expenses DOUBLE NOT NULL DEFAULT 0,
  notes          TEXT NOT NULL DEFAULT (''),
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pf_monthly_actuals (business_id, year, month),
  CONSTRAINT fk_pf_monthly_actuals_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_market_profiles (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  business_id       INT NOT NULL UNIQUE,
  town_name         VARCHAR(255) NOT NULL DEFAULT '',
  state_code        VARCHAR(2)   NOT NULL DEFAULT '',
  zip_code          VARCHAR(10)  NOT NULL DEFAULT '',
  population        INT NOT NULL DEFAULT 0,
  median_income     DOUBLE NULL,
  lat               DOUBLE NULL,
  lng               DOUBLE NULL,
  census_fetched_at DATETIME NULL,
  places_fetched_at DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pf_market_profiles_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_local_organizations (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  business_id    INT NOT NULL,
  org_name       VARCHAR(255) NOT NULL,
  org_type       VARCHAR(30)  NOT NULL DEFAULT 'other',
  place_id       VARCHAR(255) NULL,
  source         VARCHAR(20)  NOT NULL DEFAULT 'manual',
  employee_count INT NULL,
  notes          TEXT NOT NULL DEFAULT (''),
  sort_order     INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pf_local_organizations_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_recommendations (
  id                        INT AUTO_INCREMENT PRIMARY KEY,
  business_id               INT NOT NULL,
  rule_key                  VARCHAR(100) NOT NULL,
  title                     VARCHAR(255) NOT NULL,
  description               TEXT NOT NULL,
  category                  VARCHAR(30)  NOT NULL DEFAULT 'b2b',
  estimated_monthly_revenue VARCHAR(255) NULL,
  priority                  INT NOT NULL DEFAULT 50,
  is_dismissed              TINYINT(1) NOT NULL DEFAULT 0,
  playbook_json             MEDIUMTEXT NULL,
  created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pf_recommendations (business_id, rule_key),
  CONSTRAINT fk_pf_recommendations_business FOREIGN KEY (business_id) REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pf_recommendation_feedback (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  recommendation_id INT NOT NULL,
  user_id           INT NOT NULL,
  business_id       INT NOT NULL,
  status            VARCHAR(20) NOT NULL DEFAULT 'considering',
  monthly_revenue   DOUBLE NULL,
  notes             TEXT NOT NULL DEFAULT (''),
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pf_recommendation_feedback (recommendation_id, business_id),
  CONSTRAINT fk_pf_rec_feedback_rec      FOREIGN KEY (recommendation_id) REFERENCES pf_recommendations (id) ON DELETE CASCADE,
  CONSTRAINT fk_pf_rec_feedback_user     FOREIGN KEY (user_id)           REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pf_rec_feedback_business FOREIGN KEY (business_id)       REFERENCES pf_businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Census / geocoding response cache. Not per-user.
CREATE TABLE IF NOT EXISTS pf_api_cache (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  cache_key  VARCHAR(255) NOT NULL UNIQUE,
  response   MEDIUMTEXT NOT NULL,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user payroll tax rates. Proforma-specific, so it stays here rather than
-- on the shared users table.
CREATE TABLE IF NOT EXISTS pf_user_settings (
  user_id                 INT NOT NULL PRIMARY KEY,
  federal_payroll_tax_pct DOUBLE NOT NULL DEFAULT 0.0765,
  state_payroll_tax_pct   DOUBLE NOT NULL DEFAULT 0.03,
  workers_comp_pct        DOUBLE NOT NULL DEFAULT 0.02,
  CONSTRAINT fk_pf_user_settings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
