-- Placecard, ported from placecard.bizorca.com (api/migrations/schema.sql).
--
-- pc_-prefixed. People are the shared `users` table; what the original kept
-- on its own users table lives in pc_profiles. Event and RSVP ids stay UUID
-- strings, and pc_profiles.public_id carries each person's original UUID, so
-- every id the API returns is the same kind of string as before.
--
-- Hosts and diners point at users(id) with ON DELETE CASCADE: deleting a
-- shared account takes that person's dinners and RSVPs with it. The original
-- had no cascade on events.host_user_id.

CREATE TABLE IF NOT EXISTS pc_cities (
    id      VARCHAR(50)  PRIMARY KEY,
    name    VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    emoji   VARCHAR(10)  NOT NULL,
    tagline VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pc_restaurants (
    id           CHAR(36)     PRIMARY KEY,
    name         VARCHAR(150) NOT NULL,
    city_id      VARCHAR(50)  NOT NULL,
    cuisine      VARCHAR(100),
    neighborhood VARCHAR(100),
    description  TEXT,
    price_range  ENUM('$', '$$', '$$$') DEFAULT '$$',
    is_active    TINYINT(1) DEFAULT 1,
    CONSTRAINT fk_pc_restaurants_city FOREIGN KEY (city_id) REFERENCES pc_cities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pc_profiles (
    user_id             INT          NOT NULL PRIMARY KEY,
    public_id           CHAR(36)     NOT NULL,
    first_name          VARCHAR(100) NOT NULL,
    last_name           VARCHAR(100),
    age                 INT,
    bio                 TEXT,
    interests           JSON,
    dining_preferences  JSON,
    show_exact_age      TINYINT(1) DEFAULT 0,
    show_last_name      TINYINT(1) DEFAULT 0,
    is_profile_complete TINYINT(1) DEFAULT 0,
    member_since        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pc_profiles_public (public_id),
    CONSTRAINT fk_pc_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pc_events (
    id            CHAR(36)     PRIMARY KEY,
    title         VARCHAR(255),
    restaurant_id CHAR(36)    NOT NULL,
    city_id       VARCHAR(50) NOT NULL,
    host_user_id  INT         NOT NULL,
    event_date    DATETIME    NOT NULL,
    max_attendees INT         DEFAULT 6,
    notes         TEXT,
    is_active     TINYINT(1)  DEFAULT 1,
    created_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pc_events_restaurant FOREIGN KEY (restaurant_id) REFERENCES pc_restaurants (id),
    CONSTRAINT fk_pc_events_city       FOREIGN KEY (city_id)       REFERENCES pc_cities (id),
    CONSTRAINT fk_pc_events_host       FOREIGN KEY (host_user_id)  REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_pc_events_city_date (city_id, event_date),
    INDEX idx_pc_events_host (host_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pc_rsvps (
    id         CHAR(36)  PRIMARY KEY,
    event_id   CHAR(36)  NOT NULL,
    user_id    INT       NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pc_rsvps (event_id, user_id),
    CONSTRAINT fk_pc_rsvps_event FOREIGN KEY (event_id) REFERENCES pc_events (id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_rsvps_user  FOREIGN KEY (user_id)  REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- iOS bearer tokens. Stored hashed: a leaked database hands out no sessions.
CREATE TABLE IF NOT EXISTS pc_api_tokens (
    token_hash CHAR(64)  NOT NULL PRIMARY KEY,
    user_id    INT       NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_pc_api_tokens_user (user_id),
    CONSTRAINT fk_pc_api_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
