-- Advisor Field Kit, ported from SQLite (the original's sql/schema.sql). Every
-- table is kit_-prefixed and user ids point at the shared users table. Kit's
-- own users table is gone; its sso_sub, first/last name, organization and
-- last_login_at columns did not come across (organization was never shown or
-- used anywhere in the app).
--
-- A client is private to the advisor who created it. Every query that touches
-- a client joins on user_id; there is no shared-pool path in the app.

CREATE TABLE IF NOT EXISTS kit_clients (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  business_name     VARCHAR(255) NOT NULL,
  owner_name        VARCHAR(255) NOT NULL DEFAULT '',
  business_type     VARCHAR(255) NOT NULL DEFAULT '',
  county            VARCHAR(100) NOT NULL DEFAULT '',
  state_code        VARCHAR(2)   NOT NULL DEFAULT 'WA',
  entity_type       VARCHAR(100) NOT NULL DEFAULT '',
  year_started      VARCHAR(20)  NOT NULL DEFAULT '',
  employee_count    VARCHAR(50)  NOT NULL DEFAULT '',
  seasonality       VARCHAR(255) NOT NULL DEFAULT '',
  engagement_status VARCHAR(20)  NOT NULL DEFAULT 'active',
  notes             TEXT NOT NULL DEFAULT (''),
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_kit_clients_user (user_id, engagement_status),
  CONSTRAINT fk_kit_clients_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per run of one instrument for one client. Instruments are re-run
-- over time, so several rows may share (client, instrument); the client page
-- shows the latest and links the rest. Answers are one JSON document per run.
CREATE TABLE IF NOT EXISTS kit_assessments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  client_id    INT NOT NULL,
  instrument   VARCHAR(50)  NOT NULL,
  period_label VARCHAR(100) NOT NULL DEFAULT '',
  data_json    MEDIUMTEXT NOT NULL,
  status       VARCHAR(20)  NOT NULL DEFAULT 'draft',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_kit_assess_client (client_id, instrument, updated_at),
  CONSTRAINT fk_kit_assessments_client FOREIGN KEY (client_id) REFERENCES kit_clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The Client Progress Record (framework 7.5 step 6): advisor activity as
-- outcomes an EDC can report to a funder.
CREATE TABLE IF NOT EXISTS kit_progress_entries (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  client_id       INT NOT NULL,
  assessment_id   INT NULL,
  entry_date      DATE NOT NULL,
  instrument      VARCHAR(50)  NOT NULL DEFAULT '',
  what_changed    TEXT NOT NULL DEFAULT (''),
  next_instrument VARCHAR(50)  NOT NULL DEFAULT '',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_kit_progress_client (client_id, entry_date),
  CONSTRAINT fk_kit_progress_client     FOREIGN KEY (client_id)     REFERENCES kit_clients (id) ON DELETE CASCADE,
  CONSTRAINT fk_kit_progress_assessment FOREIGN KEY (assessment_id) REFERENCES kit_assessments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
