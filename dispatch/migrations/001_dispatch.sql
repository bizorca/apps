-- Dispatch, ported from dispatch.bizorca.com (MySQL schema as live on
-- 2026-10-07, i.e. schema.sql plus migrations 002-008). Tables are dp_-prefixed
-- and user ids point at the shared users table.
--
-- Dropped: Dispatch's own users table (accounts are the shared tools account;
-- sso_id, password, verification and reset columns went with it) and
-- rate_limits (only the old login/registration used it).
--
-- dp_profiles carries what the old users table held that is Dispatch's own:
-- the role and the digest settings.
--
-- Foreign keys to users: the original never cascaded, which would block
-- deleting a shared account that ever used Dispatch. What a person owns
-- (campaigns, their action items, flyers, documents, notifications,
-- submissions) now goes with them; references to someone else (assignee,
-- reviewer, venue creator/suggester) are set NULL.

CREATE TABLE IF NOT EXISTS dp_profiles (
  user_id                 INT NOT NULL PRIMARY KEY,
  role                    VARCHAR(20) NOT NULL DEFAULT 'user',   -- user | admin | sysop
  notification_preference VARCHAR(20) NOT NULL DEFAULT 'daily',  -- daily | weekly | none
  remind_days_before      TINYINT NOT NULL DEFAULT 3,
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dp_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dp_venues (
  id                   INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name                 VARCHAR(200) NOT NULL,
  type                 VARCHAR(50)  NOT NULL DEFAULT 'other',
  submission_url       VARCHAR(500) DEFAULT NULL,
  submission_email     VARCHAR(255) DEFAULT NULL,
  lead_time_days       INT NOT NULL DEFAULT 7,
  buffer_days          INT NOT NULL DEFAULT 1,
  submission_method    VARCHAR(50)  NOT NULL DEFAULT 'web',
  asset_requirements   TEXT,
  notes                TEXT,
  contact_name         VARCHAR(200) DEFAULT NULL,
  contact_email        VARCHAR(255) DEFAULT NULL,
  contact_notes        TEXT,
  is_active            TINYINT(1) DEFAULT 1,
  suggested_by_user_id INT DEFAULT NULL,
  created_by           INT DEFAULT NULL,
  created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY fk_dp_venues_suggested_by (suggested_by_user_id),
  KEY fk_dp_venues_created_by (created_by),
  CONSTRAINT fk_dp_venues_created_by   FOREIGN KEY (created_by)           REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_dp_venues_suggested_by FOREIGN KEY (suggested_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dp_venue_submissions (
  id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id             INT NOT NULL,
  venue_name          VARCHAR(200) NOT NULL,
  submission_url      VARCHAR(500) DEFAULT NULL,
  submission_email    VARCHAR(255) DEFAULT NULL,
  venue_type          VARCHAR(50)  DEFAULT NULL,
  perceived_lead_time TEXT,
  asset_requirements  TEXT,
  justification       TEXT,
  status              VARCHAR(20) DEFAULT 'pending',
  admin_notes         TEXT,
  reviewed_by         INT DEFAULT NULL,
  reviewed_at         DATETIME DEFAULT NULL,
  created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY fk_dp_vs_reviewed_by (reviewed_by),
  KEY idx_dp_venue_submissions_status (status),
  KEY idx_dp_venue_submissions_user (user_id),
  KEY idx_dp_venue_submissions_created (created_at),
  CONSTRAINT fk_dp_vs_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_dp_vs_user        FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dp_campaigns (
  id                     INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id                INT NOT NULL,
  name                   VARCHAR(200) NOT NULL,
  description            TEXT,
  event_date             DATE NOT NULL,
  event_time             TIME DEFAULT NULL,
  location               VARCHAR(300) DEFAULT NULL,
  recurrence_type        VARCHAR(20)  DEFAULT NULL,
  recurrence_day_pattern VARCHAR(255) DEFAULT NULL,
  recurrence_interval    INT DEFAULT NULL,
  recurrence_end_date    DATE DEFAULT NULL,
  status                 VARCHAR(20) DEFAULT 'active',
  base_assets            TEXT,
  asset_link             VARCHAR(500) DEFAULT NULL,
  post_event_notes       TEXT,
  post_event_rating      TINYINT DEFAULT NULL,
  created_at             DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_dp_campaigns_user (user_id),
  KEY idx_dp_campaigns_event_date (event_date),
  CONSTRAINT fk_dp_campaigns_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dp_campaign_venues (
  id          INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT NOT NULL,
  venue_id    INT NOT NULL,
  is_active   TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_dp_campaign_venue (campaign_id, venue_id),
  KEY idx_dp_campaign_venues_venue (venue_id),
  CONSTRAINT fk_dp_cv_campaign FOREIGN KEY (campaign_id) REFERENCES dp_campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_dp_cv_venue    FOREIGN KEY (venue_id)    REFERENCES dp_venues (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dp_action_items (
  id                      INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id             INT NOT NULL,
  venue_id                INT NOT NULL,
  user_id                 INT NOT NULL,
  assigned_to_user_id     INT DEFAULT NULL,
  due_date                DATE NOT NULL,
  event_date              DATE NOT NULL,
  target_publication_date DATE DEFAULT NULL,
  status                  VARCHAR(20) DEFAULT 'pending',
  notes                   TEXT,
  estimated_cost          DECIMAL(10,2) DEFAULT NULL,
  actual_cost             DECIMAL(10,2) DEFAULT NULL,
  completed_at            DATETIME DEFAULT NULL,
  submitted_at            DATETIME DEFAULT NULL,
  confirmed_at            DATETIME DEFAULT NULL,
  follow_up_date          DATE DEFAULT NULL,
  created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY fk_dp_ai_campaign (campaign_id),
  KEY fk_dp_ai_venue (venue_id),
  KEY idx_dp_action_items_user (user_id),
  KEY idx_dp_action_items_due_date (due_date),
  KEY idx_dp_action_items_status (status),
  KEY fk_dp_ai_assigned (assigned_to_user_id),
  CONSTRAINT fk_dp_ai_assigned FOREIGN KEY (assigned_to_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_dp_ai_campaign FOREIGN KEY (campaign_id) REFERENCES dp_campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_dp_ai_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_dp_ai_venue    FOREIGN KEY (venue_id)    REFERENCES dp_venues (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dp_flyer_locations (
  id             INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  campaign_id    INT DEFAULT NULL,
  location_name  VARCHAR(200) NOT NULL,
  address        TEXT,
  posted_at      DATE DEFAULT NULL,
  removed_at     DATE DEFAULT NULL,
  quantity       INT DEFAULT 1,
  notes          TEXT,
  photo_filename VARCHAR(255) DEFAULT NULL,
  status         VARCHAR(20) DEFAULT 'active',
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY fk_dp_fl_campaign (campaign_id),
  KEY idx_dp_flyer_locations_user (user_id),
  CONSTRAINT fk_dp_fl_campaign FOREIGN KEY (campaign_id) REFERENCES dp_campaigns (id) ON DELETE SET NULL,
  CONSTRAINT fk_dp_fl_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dp_notifications (
  id         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  type       VARCHAR(50)  NOT NULL,
  title      VARCHAR(200) NOT NULL,
  message    TEXT NOT NULL,
  data       TEXT,
  link       VARCHAR(500) DEFAULT NULL,
  read_at    DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dp_notifications_user (user_id),
  CONSTRAINT fk_dp_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dp_documents (
  id          INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  campaign_id INT DEFAULT NULL,
  name        VARCHAR(255) NOT NULL,
  description TEXT,
  content     MEDIUMTEXT,
  file_path   VARCHAR(500) DEFAULT NULL,
  file_name   VARCHAR(255) DEFAULT NULL,
  file_size   INT DEFAULT NULL,
  mime_type   VARCHAR(100) DEFAULT NULL,
  is_template TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_dp_doc_user (user_id),
  KEY idx_dp_doc_campaign (campaign_id),
  KEY idx_dp_doc_template (is_template),
  CONSTRAINT fk_dp_doc_campaign FOREIGN KEY (campaign_id) REFERENCES dp_campaigns (id) ON DELETE SET NULL,
  CONSTRAINT fk_dp_doc_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
