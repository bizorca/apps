-- Fathom, ported from Laravel 11 + SQLite (database/migrations/*). Every table
-- is fm_-prefixed. Ids stay Laravel's UUID strings and polymorphic *_type
-- columns keep their 'App\Models\Card' values, so imported rows are byte-for-
-- byte the originals. Soft deletes (deleted_at) are kept where Laravel had
-- them; every query in includes/ filters them out the way Eloquent did.
--
-- Identity moved: fm_users no longer holds name, email or password. Each row is
-- one person's membership in one Fathom account (workspace), tied to the shared
-- `users` table by user_id. Name and email come from there.
--
-- Dropped (no feature reads them): sessions, cache, cache_locks, jobs,
-- job_batches, failed_jobs, identities (SSO), webhooks, webhook_deliveries.

CREATE TABLE IF NOT EXISTS fm_accounts (
  id            CHAR(36) NOT NULL PRIMARY KEY,
  name          VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) NOT NULL UNIQUE,
  business_type VARCHAR(50) NULL,
  invite_code   VARCHAR(16) NULL UNIQUE,
  plan          VARCHAR(50) NOT NULL DEFAULT 'free',
  settings      JSON NULL,
  cancelled_at  DATETIME NULL,
  created_at    DATETIME NULL,
  updated_at    DATETIME NULL,
  deleted_at    DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_users (
  id                  CHAR(36) NOT NULL PRIMARY KEY,
  user_id             INT NOT NULL UNIQUE,
  account_id          CHAR(36) NOT NULL,
  role                VARCHAR(20) NOT NULL DEFAULT 'member',
  is_sysop            TINYINT(1) NOT NULL DEFAULT 0,
  avatar_path         VARCHAR(255) NULL,
  notification_email  VARCHAR(255) NULL,
  notification_digest TINYINT(1) NOT NULL DEFAULT 0,
  time_zone           VARCHAR(100) NULL,
  created_at          DATETIME NULL,
  updated_at          DATETIME NULL,
  deleted_at          DATETIME NULL,
  INDEX idx_fm_users_account (account_id),
  INDEX idx_fm_users_account_role (account_id, role),
  CONSTRAINT fk_fm_users_user    FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_users_account FOREIGN KEY (account_id) REFERENCES fm_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_boards (
  id            CHAR(36) NOT NULL PRIMARY KEY,
  account_id    CHAR(36) NOT NULL,
  creator_id    CHAR(36) NULL,
  name          VARCHAR(255) NOT NULL,
  description   TEXT NULL,
  color         VARCHAR(20) NULL,
  is_public     TINYINT(1) NOT NULL DEFAULT 0,
  share_token   VARCHAR(64) NULL UNIQUE,
  auto_postpone TINYINT(1) NOT NULL DEFAULT 0,
  postpone_days INT NULL,
  archived_at   DATETIME NULL,
  created_at    DATETIME NULL,
  updated_at    DATETIME NULL,
  deleted_at    DATETIME NULL,
  INDEX idx_fm_boards_account (account_id),
  INDEX idx_fm_boards_account_archived (account_id, archived_at),
  CONSTRAINT fk_fm_boards_account FOREIGN KEY (account_id) REFERENCES fm_accounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_boards_creator FOREIGN KEY (creator_id) REFERENCES fm_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_columns (
  id          CHAR(36) NOT NULL PRIMARY KEY,
  account_id  CHAR(36) NOT NULL,
  board_id    CHAR(36) NOT NULL,
  name        VARCHAR(255) NOT NULL,
  position    INT NOT NULL DEFAULT 1,
  color       VARCHAR(20) NULL,
  cards_limit INT NULL,
  created_at  DATETIME NULL,
  updated_at  DATETIME NULL,
  INDEX idx_fm_columns_board (board_id),
  INDEX idx_fm_columns_board_position (board_id, position),
  CONSTRAINT fk_fm_columns_account FOREIGN KEY (account_id) REFERENCES fm_accounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_columns_board   FOREIGN KEY (board_id)   REFERENCES fm_boards (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_cards (
  id          CHAR(36) NOT NULL PRIMARY KEY,
  account_id  CHAR(36) NOT NULL,
  board_id    CHAR(36) NOT NULL,
  column_id   CHAR(36) NULL,
  creator_id  CHAR(36) NULL,
  title       VARCHAR(500) NOT NULL,
  description LONGTEXT NULL,
  position    INT NOT NULL DEFAULT 1,
  color       VARCHAR(20) NULL,
  is_draft    TINYINT(1) NOT NULL DEFAULT 0,
  is_golden   TINYINT(1) NOT NULL DEFAULT 0,
  share_token VARCHAR(64) NULL UNIQUE,
  closed_at   DATETIME NULL,
  stalled_at  DATETIME NULL,
  due_at      DATETIME NULL,
  created_at  DATETIME NULL,
  updated_at  DATETIME NULL,
  deleted_at  DATETIME NULL,
  INDEX idx_fm_cards_account (account_id),
  INDEX idx_fm_cards_board (board_id),
  INDEX idx_fm_cards_column (column_id),
  INDEX idx_fm_cards_board_closed (board_id, closed_at),
  INDEX idx_fm_cards_column_position (column_id, position),
  CONSTRAINT fk_fm_cards_account FOREIGN KEY (account_id) REFERENCES fm_accounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_cards_board   FOREIGN KEY (board_id)   REFERENCES fm_boards (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_cards_column  FOREIGN KEY (column_id)  REFERENCES fm_columns (id) ON DELETE SET NULL,
  CONSTRAINT fk_fm_cards_creator FOREIGN KEY (creator_id) REFERENCES fm_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_tags (
  id         CHAR(36) NOT NULL PRIMARY KEY,
  account_id CHAR(36) NOT NULL,
  name       VARCHAR(100) NOT NULL,
  color      VARCHAR(20) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_fm_tags_account_name (account_id, name),
  CONSTRAINT fk_fm_tags_account FOREIGN KEY (account_id) REFERENCES fm_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_taggings (
  id            CHAR(36) NOT NULL PRIMARY KEY,
  tag_id        CHAR(36) NOT NULL,
  taggable_type VARCHAR(255) NOT NULL,
  taggable_id   CHAR(36) NOT NULL,
  created_at    DATETIME NULL,
  updated_at    DATETIME NULL,
  UNIQUE KEY uq_fm_taggings (tag_id, taggable_id, taggable_type),
  INDEX idx_fm_taggings_taggable (taggable_type, taggable_id),
  CONSTRAINT fk_fm_taggings_tag FOREIGN KEY (tag_id) REFERENCES fm_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_clients (
  id            CHAR(36) NOT NULL PRIMARY KEY,
  account_id    CHAR(36) NOT NULL,
  name          VARCHAR(255) NOT NULL,
  email_address VARCHAR(255) NOT NULL,
  created_at    DATETIME NULL,
  updated_at    DATETIME NULL,
  deleted_at    DATETIME NULL,
  UNIQUE KEY uq_fm_clients_account_email (account_id, email_address),
  CONSTRAINT fk_fm_clients_account FOREIGN KEY (account_id) REFERENCES fm_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_comments (
  id         CHAR(36) NOT NULL PRIMARY KEY,
  card_id    CHAR(36) NOT NULL,
  creator_id CHAR(36) NULL,
  client_id  CHAR(36) NULL,
  body       LONGTEXT NOT NULL,
  body_html  LONGTEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  INDEX idx_fm_comments_card (card_id),
  CONSTRAINT fk_fm_comments_card    FOREIGN KEY (card_id)    REFERENCES fm_cards (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_comments_creator FOREIGN KEY (creator_id) REFERENCES fm_users (id) ON DELETE SET NULL,
  CONSTRAINT fk_fm_comments_client  FOREIGN KEY (client_id)  REFERENCES fm_clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_reactions (
  id             CHAR(36) NOT NULL PRIMARY KEY,
  user_id        CHAR(36) NOT NULL,
  reactable_type VARCHAR(255) NOT NULL,
  reactable_id   CHAR(36) NOT NULL,
  emoji          VARCHAR(10) NOT NULL,
  created_at     DATETIME NULL,
  updated_at     DATETIME NULL,
  UNIQUE KEY uq_fm_reactions (user_id, reactable_id, reactable_type, emoji),
  INDEX idx_fm_reactions_reactable (reactable_type, reactable_id),
  CONSTRAINT fk_fm_reactions_user FOREIGN KEY (user_id) REFERENCES fm_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_assignments (
  id          CHAR(36) NOT NULL PRIMARY KEY,
  card_id     CHAR(36) NOT NULL,
  user_id     CHAR(36) NOT NULL,
  assigner_id CHAR(36) NULL,
  created_at  DATETIME NULL,
  updated_at  DATETIME NULL,
  UNIQUE KEY uq_fm_assignments (card_id, user_id),
  INDEX idx_fm_assignments_user (user_id),
  CONSTRAINT fk_fm_assignments_card     FOREIGN KEY (card_id)     REFERENCES fm_cards (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_assignments_user     FOREIGN KEY (user_id)     REFERENCES fm_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_assignments_assigner FOREIGN KEY (assigner_id) REFERENCES fm_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_steps (
  id           CHAR(36) NOT NULL PRIMARY KEY,
  card_id      CHAR(36) NOT NULL,
  title        VARCHAR(500) NOT NULL,
  position     INT NOT NULL DEFAULT 1,
  completed    TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  completed_by CHAR(36) NULL,
  created_at   DATETIME NULL,
  updated_at   DATETIME NULL,
  INDEX idx_fm_steps_card (card_id),
  CONSTRAINT fk_fm_steps_card FOREIGN KEY (card_id)      REFERENCES fm_cards (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_steps_by   FOREIGN KEY (completed_by) REFERENCES fm_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_closures (
  id         CHAR(36) NOT NULL PRIMARY KEY,
  card_id    CHAR(36) NOT NULL UNIQUE,
  user_id    CHAR(36) NULL,
  reason     TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  CONSTRAINT fk_fm_closures_card FOREIGN KEY (card_id) REFERENCES fm_cards (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_closures_user FOREIGN KEY (user_id) REFERENCES fm_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_watches (
  id         CHAR(36) NOT NULL PRIMARY KEY,
  user_id    CHAR(36) NOT NULL,
  card_id    CHAR(36) NOT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_fm_watches (user_id, card_id),
  CONSTRAINT fk_fm_watches_user FOREIGN KEY (user_id) REFERENCES fm_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_watches_card FOREIGN KEY (card_id) REFERENCES fm_cards (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_pins (
  id         CHAR(36) NOT NULL PRIMARY KEY,
  user_id    CHAR(36) NOT NULL,
  card_id    CHAR(36) NOT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_fm_pins (user_id, card_id),
  CONSTRAINT fk_fm_pins_user FOREIGN KEY (user_id) REFERENCES fm_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_pins_card FOREIGN KEY (card_id) REFERENCES fm_cards (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_mentions (
  id         CHAR(36) NOT NULL PRIMARY KEY,
  comment_id CHAR(36) NOT NULL,
  user_id    CHAR(36) NOT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_fm_mentions (comment_id, user_id),
  CONSTRAINT fk_fm_mentions_comment FOREIGN KEY (comment_id) REFERENCES fm_comments (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_mentions_user    FOREIGN KEY (user_id)    REFERENCES fm_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_notifications (
  id          CHAR(36) NOT NULL PRIMARY KEY,
  user_id     CHAR(36) NOT NULL,
  card_id     CHAR(36) NULL,
  source_type VARCHAR(255) NOT NULL,
  source_id   CHAR(36) NOT NULL,
  message     TEXT NOT NULL,
  read_at     DATETIME NULL,
  emailed_at  DATETIME NULL,
  created_at  DATETIME NULL,
  updated_at  DATETIME NULL,
  INDEX idx_fm_notifications_user (user_id),
  INDEX idx_fm_notifications_user_read (user_id, read_at),
  INDEX idx_fm_notifications_source (source_type, source_id),
  CONSTRAINT fk_fm_notifications_user FOREIGN KEY (user_id) REFERENCES fm_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_notifications_card FOREIGN KEY (card_id) REFERENCES fm_cards (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_events (
  id             CHAR(36) NOT NULL PRIMARY KEY,
  account_id     CHAR(36) NOT NULL,
  board_id       CHAR(36) NULL,
  card_id        CHAR(36) NULL,
  user_id        CHAR(36) NULL,
  eventable_type VARCHAR(255) NOT NULL,
  eventable_id   CHAR(36) NOT NULL,
  action         VARCHAR(50) NOT NULL,
  metadata       JSON NULL,
  created_at     DATETIME NOT NULL,
  INDEX idx_fm_events_account (account_id),
  INDEX idx_fm_events_account_created (account_id, created_at),
  INDEX idx_fm_events_board (board_id),
  INDEX idx_fm_events_card (card_id),
  INDEX idx_fm_events_eventable (eventable_type, eventable_id),
  CONSTRAINT fk_fm_events_account FOREIGN KEY (account_id) REFERENCES fm_accounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_events_board   FOREIGN KEY (board_id)   REFERENCES fm_boards (id) ON DELETE SET NULL,
  CONSTRAINT fk_fm_events_card    FOREIGN KEY (card_id)    REFERENCES fm_cards (id) ON DELETE SET NULL,
  CONSTRAINT fk_fm_events_user    FOREIGN KEY (user_id)    REFERENCES fm_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_filters (
  id         CHAR(36) NOT NULL PRIMARY KEY,
  user_id    CHAR(36) NOT NULL,
  board_id   CHAR(36) NOT NULL,
  name       VARCHAR(255) NOT NULL,
  params     JSON NOT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  INDEX idx_fm_filters_user_board (user_id, board_id),
  CONSTRAINT fk_fm_filters_user  FOREIGN KEY (user_id)  REFERENCES fm_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_filters_board FOREIGN KEY (board_id) REFERENCES fm_boards (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_accesses (
  id               CHAR(36) NOT NULL PRIMARY KEY,
  user_id          CHAR(36) NOT NULL,
  board_id         CHAR(36) NOT NULL,
  involvement      VARCHAR(30) NULL,
  last_accessed_at DATETIME NULL,
  created_at       DATETIME NULL,
  updated_at       DATETIME NULL,
  UNIQUE KEY uq_fm_accesses (user_id, board_id),
  CONSTRAINT fk_fm_accesses_user  FOREIGN KEY (user_id)  REFERENCES fm_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_accesses_board FOREIGN KEY (board_id) REFERENCES fm_boards (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only clients sign in with magic links now; users use the shared account.
-- authenticatable_type is kept so the column means what it did.
CREATE TABLE IF NOT EXISTS fm_magic_links (
  id                   CHAR(36) NOT NULL PRIMARY KEY,
  authenticatable_type VARCHAR(255) NOT NULL DEFAULT 'App\\Models\\Client',
  authenticatable_id   CHAR(36) NULL,
  token                VARCHAR(128) NOT NULL UNIQUE,
  expires_at           DATETIME NOT NULL,
  used_at              DATETIME NULL,
  created_at           DATETIME NULL,
  updated_at           DATETIME NULL,
  INDEX idx_fm_magic_links_auth (authenticatable_type, authenticatable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_exports (
  id            CHAR(36) NOT NULL PRIMARY KEY,
  account_id    CHAR(36) NOT NULL,
  user_id       CHAR(36) NOT NULL,
  status        VARCHAR(20) NOT NULL DEFAULT 'pending',
  file_path     VARCHAR(255) NULL,
  completed_at  DATETIME NULL,
  failed_at     DATETIME NULL,
  error_message TEXT NULL,
  created_at    DATETIME NULL,
  updated_at    DATETIME NULL,
  INDEX idx_fm_exports_account_status (account_id, status),
  CONSTRAINT fk_fm_exports_account FOREIGN KEY (account_id) REFERENCES fm_accounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_exports_user    FOREIGN KEY (user_id)    REFERENCES fm_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_card_templates (
  id                CHAR(36) NOT NULL PRIMARY KEY,
  account_id        CHAR(36) NOT NULL,
  creator_id        CHAR(36) NOT NULL,
  default_column_id CHAR(36) NULL,
  name              VARCHAR(255) NOT NULL,
  title_pattern     VARCHAR(500) NULL,
  description       TEXT NULL,
  created_at        DATETIME NULL,
  updated_at        DATETIME NULL,
  CONSTRAINT fk_fm_card_templates_account FOREIGN KEY (account_id)        REFERENCES fm_accounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_card_templates_creator FOREIGN KEY (creator_id)        REFERENCES fm_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_card_templates_column  FOREIGN KEY (default_column_id) REFERENCES fm_columns (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_card_template_tags (
  card_template_id CHAR(36) NOT NULL,
  tag_id           CHAR(36) NOT NULL,
  PRIMARY KEY (card_template_id, tag_id),
  CONSTRAINT fk_fm_ctt_template FOREIGN KEY (card_template_id) REFERENCES fm_card_templates (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_ctt_tag      FOREIGN KEY (tag_id)           REFERENCES fm_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_card_template_steps (
  id               CHAR(36) NOT NULL PRIMARY KEY,
  card_template_id CHAR(36) NOT NULL,
  title            VARCHAR(500) NOT NULL,
  position         INT NOT NULL DEFAULT 0,
  created_at       DATETIME NULL,
  updated_at       DATETIME NULL,
  CONSTRAINT fk_fm_cts_template FOREIGN KEY (card_template_id) REFERENCES fm_card_templates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fm_client_cards (
  id         CHAR(36) NOT NULL PRIMARY KEY,
  client_id  CHAR(36) NOT NULL,
  card_id    CHAR(36) NOT NULL,
  granted_by CHAR(36) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_fm_client_cards (client_id, card_id),
  CONSTRAINT fk_fm_client_cards_client  FOREIGN KEY (client_id)  REFERENCES fm_clients (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_client_cards_card    FOREIGN KEY (card_id)    REFERENCES fm_cards (id) ON DELETE CASCADE,
  CONSTRAINT fk_fm_client_cards_granter FOREIGN KEY (granted_by) REFERENCES fm_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Replaces Laravel's cache-backed RateLimiter (portal sign-in, workspace signup).
CREATE TABLE IF NOT EXISTS fm_rate_limits (
  rate_key  VARCHAR(191) NOT NULL PRIMARY KEY,
  attempts  INT NOT NULL DEFAULT 0,
  reset_at  DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
