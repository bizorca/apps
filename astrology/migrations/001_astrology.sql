-- Ba Zi Astrology ("Meridian"), ported 2026-10-08 from the original at
-- deprecated/astrology (its migrations 001–021, flattened to their end state).
--
-- Every table is as_-prefixed and people are the shared `users` table. Dropped
-- with the port: the original's users, password_resets and subscriptions
-- tables (the shared account and "everything is free" replace them; there were
-- no subscriptions in the live data). What lived on the original users row and
-- is Astrology's own (primary profile, timezone) is now as_members.

-- One row per person who has used Astrology. Created on first visit or API login.
CREATE TABLE IF NOT EXISTS as_members (
  user_id            INT NOT NULL PRIMARY KEY,
  primary_profile_id INT NULL DEFAULT NULL,
  timezone           VARCHAR(50) NOT NULL DEFAULT 'America/New_York',
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_as_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Astrology admins who are not site owners. Site owners (users.is_admin) are
-- admins here automatically; see isAdmin().
CREATE TABLE IF NOT EXISTS as_admins (
  user_id    INT NOT NULL PRIMARY KEY,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_as_admins_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A birth chart. user_id NULL = made anonymously, owned by session_id until
-- claimed at sign-in (claimPendingProfile()).
CREATE TABLE IF NOT EXISTS as_profiles (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NULL DEFAULT NULL,
  session_id        VARCHAR(128) NULL DEFAULT NULL,
  birth_year        INT NOT NULL,
  birth_month       INT NULL DEFAULT NULL,
  birth_day         INT NULL DEFAULT NULL,
  birth_hour        INT NULL DEFAULT NULL,
  birth_city        VARCHAR(100) NULL DEFAULT NULL,
  zodiac_animal     VARCHAR(20) NOT NULL,
  zodiac_element    VARCHAR(20) NOT NULL,
  yin_yang          VARCHAR(10) NOT NULL,
  heavenly_stem     VARCHAR(40) NOT NULL,
  earthly_branch    VARCHAR(40) NOT NULL,
  four_pillars_json TEXT NULL,
  western_sign      VARCHAR(20) NULL DEFAULT NULL,
  western_element   VARCHAR(10) NULL DEFAULT NULL,
  created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_as_profiles_user (user_id),
  INDEX idx_as_profiles_session (session_id),
  CONSTRAINT fk_as_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- reading_type / created_at, NOT type / generated_at (the API maps them).
CREATE TABLE IF NOT EXISTS as_readings (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  profile_id   INT NOT NULL,
  user_id      INT NULL DEFAULT NULL,
  reading_type ENUM('teaser','full','western_teaser','western_full') NOT NULL,
  content_json TEXT NOT NULL,
  created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_as_readings_profile (profile_id),
  INDEX idx_as_readings_user (user_id),
  CONSTRAINT fk_as_readings_profile FOREIGN KEY (profile_id) REFERENCES as_profiles (id) ON DELETE CASCADE,
  CONSTRAINT fk_as_readings_user    FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monthly forecasts (legacy, unlinked from the nav but still reachable).
CREATE TABLE IF NOT EXISTS as_forecasts (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  forecast_month DATE NOT NULL,
  zodiac_animal  VARCHAR(20) NOT NULL,
  content_json   TEXT NOT NULL,
  generated_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_as_forecasts_month_animal (forecast_month, zodiac_animal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS as_user_forecasts (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  forecast_id INT NOT NULL,
  viewed_at   TIMESTAMP NULL DEFAULT NULL,
  created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_as_user_forecasts (user_id, forecast_id),
  INDEX idx_as_user_forecasts_user (user_id),
  CONSTRAINT fk_as_user_forecasts_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_as_user_forecasts_forecast FOREIGN KEY (forecast_id) REFERENCES as_forecasts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS as_meditations (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  element          VARCHAR(20) NOT NULL,
  title            VARCHAR(100) NOT NULL,
  slug             VARCHAR(100) NOT NULL UNIQUE,
  description      TEXT NOT NULL,
  content_json     TEXT NOT NULL,
  duration_minutes INT NOT NULL DEFAULT 10,
  sort_order       INT NOT NULL DEFAULT 0,
  created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_as_meditations_element (element)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS as_user_meditations (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  meditation_id INT NOT NULL,
  started_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at  TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_as_user_meditations_user (user_id),
  CONSTRAINT fk_as_user_meditations_user       FOREIGN KEY (user_id)       REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_as_user_meditations_meditation FOREIGN KEY (meditation_id) REFERENCES as_meditations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shared per sign and week, not per person.
CREATE TABLE IF NOT EXISTS as_weekly_forecasts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  week_start    DATE NOT NULL,
  forecast_type ENUM('chinese','western') NOT NULL,
  zodiac_key    VARCHAR(50) NOT NULL,
  content_json  LONGTEXT NOT NULL,
  generated_at  DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_as_weekly_forecasts (week_start, forecast_type, zodiac_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per person, replaced on retake.
CREATE TABLE IF NOT EXISTS as_starseed_results (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  primary_lineage   VARCHAR(30) NOT NULL,
  secondary_lineage VARCHAR(30) NOT NULL,
  scores_json       TEXT NOT NULL,
  answers_json      TEXT NOT NULL,
  reading_json      TEXT NULL,
  created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_as_starseed_results_user (user_id),
  CONSTRAINT fk_as_starseed_results_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bearer tokens for the Meridian iOS app. entitlement is kept (always NULL now)
-- so the column the app's responses were built from still exists.
CREATE TABLE IF NOT EXISTS as_api_tokens (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  token        VARCHAR(64) NOT NULL,
  entitlement  VARCHAR(20) NULL DEFAULT NULL,
  created_at   DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NOT NULL,
  last_used_at DATETIME NULL DEFAULT NULL,
  UNIQUE KEY uq_as_api_tokens_token (token),
  INDEX idx_as_api_tokens_user (user_id),
  CONSTRAINT fk_as_api_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Request a spot" leads from work-with-jillian.php.
CREATE TABLE IF NOT EXISTS as_offer_interest (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  offer_key  VARCHAR(40) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_as_offer_interest (user_id, offer_key),
  CONSTRAINT fk_as_offer_interest_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS as_app_settings (
  setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
