-- Pod: the Bizorca community (courses, forum, support tickets, events).
-- Ported 2026-10-08 from pod.bizorca.com, whose tables sat unprefixed in the
-- shared login.bizorca.com database. Every table is pd_-prefixed and every
-- person is a row in the shared `users` table.
--
-- Foreign keys: what a person owns cascades with their account; a reference to
-- someone else (ticket assignee, note author) is set NULL. The original had no
-- foreign keys on reactions, reads, notifications or notes, so deleting a post
-- left orphaned reactions behind.

-- Pod's view of an account: one row per person who has used Pod, created on
-- their first visit. Replaces the columns Pod had bolted onto the login
-- server's users table (is_staff, is_active, bio, avatar_url). is_admin is a
-- Pod-only admin flag; a site admin (users.is_admin) is always a Pod admin.
CREATE TABLE IF NOT EXISTS pd_profiles (
  user_id    INT NOT NULL PRIMARY KEY,
  is_admin   TINYINT(1) NOT NULL DEFAULT 0,
  is_staff   TINYINT(1) NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  bio        TEXT NULL,
  avatar_url VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pd_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pod members with the shape the original's queries read from `users`
-- (first_name, last_name, is_staff, is_admin, is_active, bio, avatar_url).
-- The shared account has a single `name`; first word = first name.
CREATE OR REPLACE VIEW pd_users AS
    SELECT u.id, u.email, u.name,
           SUBSTRING_INDEX(TRIM(u.name), ' ', 1) AS first_name,
           TRIM(SUBSTRING(TRIM(u.name), CHAR_LENGTH(SUBSTRING_INDEX(TRIM(u.name), ' ', 1)) + 1)) AS last_name,
           (u.is_admin = 1 OR p.is_admin = 1) AS is_admin,
           p.is_staff, p.is_active, p.bio, p.avatar_url, p.created_at
    FROM users u
    JOIN pd_profiles p ON p.user_id = u.id;

CREATE TABLE IF NOT EXISTS pd_courses (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) NOT NULL UNIQUE,
  description   TEXT NULL,
  thumbnail_url VARCHAR(500) NULL,
  sort_order    SMALLINT NOT NULL DEFAULT 0,
  is_published  TINYINT(1) NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pd_lessons (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  course_id    INT NOT NULL,
  title        VARCHAR(255) NOT NULL,
  slug         VARCHAR(255) NOT NULL,
  video_embed  TEXT NULL COMMENT 'iframe HTML built by parseVideoEmbed() from a YouTube/Vimeo URL',
  content      LONGTEXT NULL,
  sort_order   SMALLINT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pd_lessons_course_slug (course_id, slug),
  CONSTRAINT fk_pd_lessons_course FOREIGN KEY (course_id) REFERENCES pd_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pd_lesson_progress (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  lesson_id    INT NOT NULL,
  completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pd_lesson_progress (user_id, lesson_id),
  CONSTRAINT fk_pd_lesson_progress_user   FOREIGN KEY (user_id)   REFERENCES users (id)      ON DELETE CASCADE,
  CONSTRAINT fk_pd_lesson_progress_lesson FOREIGN KEY (lesson_id) REFERENCES pd_lessons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pd_forum_categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  sort_order  SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pd_forum_posts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  category_id   INT NOT NULL,
  title         VARCHAR(255) NOT NULL,
  body          LONGTEXT NOT NULL,
  is_pinned     TINYINT(1) NOT NULL DEFAULT 0,
  is_locked     TINYINT(1) NOT NULL DEFAULT 0,
  reply_count   INT NOT NULL DEFAULT 0,
  last_reply_at TIMESTAMP NULL DEFAULT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pd_forum_posts_category (category_id),
  CONSTRAINT fk_pd_forum_posts_user     FOREIGN KEY (user_id)     REFERENCES users (id)               ON DELETE CASCADE,
  CONSTRAINT fk_pd_forum_posts_category FOREIGN KEY (category_id) REFERENCES pd_forum_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pd_forum_replies (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  post_id    INT NOT NULL,
  user_id    INT NOT NULL,
  body       LONGTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pd_forum_replies_post FOREIGN KEY (post_id) REFERENCES pd_forum_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_pd_forum_replies_user FOREIGN KEY (user_id) REFERENCES users (id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pd_forum_reactions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  post_id    INT NOT NULL,
  user_id    INT NOT NULL,
  emoji      VARCHAR(10) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pd_forum_reactions (post_id, user_id, emoji),
  CONSTRAINT fk_pd_forum_reactions_post FOREIGN KEY (post_id) REFERENCES pd_forum_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_pd_forum_reactions_user FOREIGN KEY (user_id) REFERENCES users (id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pd_forum_category_reads (
  user_id      INT NOT NULL,
  category_id  INT NOT NULL,
  last_read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, category_id),
  CONSTRAINT fk_pd_forum_reads_user     FOREIGN KEY (user_id)     REFERENCES users (id)               ON DELETE CASCADE,
  CONSTRAINT fk_pd_forum_reads_category FOREIGN KEY (category_id) REFERENCES pd_forum_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pd_tickets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  status      ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
  assigned_to INT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pd_tickets_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pd_tickets_assignee FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pd_ticket_messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id  INT NOT NULL,
  user_id    INT NOT NULL,
  body       LONGTEXT NOT NULL,
  is_staff   TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pd_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES pd_tickets (id) ON DELETE CASCADE,
  CONSTRAINT fk_pd_ticket_messages_user   FOREIGN KEY (user_id)   REFERENCES users (id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- starts_at/ends_at are wall-clock times in PD_TIMEZONE (Pacific), as typed
-- into the admin form.
CREATE TABLE IF NOT EXISTS pd_events (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(255) NOT NULL,
  description     TEXT NULL,
  starts_at       DATETIME NOT NULL,
  ends_at         DATETIME NOT NULL,
  zoom_meeting_id VARCHAR(50) NULL,
  zoom_join_url   VARCHAR(500) NULL,
  is_published    TINYINT(1) NOT NULL DEFAULT 0,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pd_event_rsvps (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  event_id   INT NOT NULL,
  user_id    INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pd_event_rsvps (event_id, user_id),
  CONSTRAINT fk_pd_event_rsvps_event FOREIGN KEY (event_id) REFERENCES pd_events (id) ON DELETE CASCADE,
  CONSTRAINT fk_pd_event_rsvps_user  FOREIGN KEY (user_id)  REFERENCES users (id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- url is an app path ('/forum/post/5'), turned into a link when shown, so
-- stored notifications survive the switch to clean URLs. The original stored
-- absolute https://pod.bizorca.com/... links.
CREATE TABLE IF NOT EXISTS pd_notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  type       VARCHAR(50) NOT NULL,
  message    VARCHAR(500) NOT NULL,
  url        VARCHAR(500) NOT NULL DEFAULT '',
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pd_notifications_unread (user_id, is_read),
  CONSTRAINT fk_pd_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin notes about a member. admin_id is NULL once the author's account is gone.
CREATE TABLE IF NOT EXISTS pd_user_notes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  admin_id   INT NULL,
  body       TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pd_user_notes_user (user_id),
  CONSTRAINT fk_pd_user_notes_user  FOREIGN KEY (user_id)  REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pd_user_notes_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zoom Server-to-Server OAuth access token (one row, about an hour long).
CREATE TABLE IF NOT EXISTS pd_zoom_token_cache (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  access_token TEXT NOT NULL,
  expires_at   DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
