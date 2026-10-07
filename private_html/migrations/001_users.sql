-- The shared account. One row per person, for every tool on tools.bizorca.com.
--
-- Deliberately flat, the same shape as financialhypnosis.com's users table: no
-- teams, no per-tool entitlements. A tool keeps its own data in its own
-- prefixed tables with a user_id foreign key here.

CREATE TABLE IF NOT EXISTS users (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  email             VARCHAR(255) NOT NULL UNIQUE,
  password_hash     VARCHAR(255) NOT NULL,
  name              VARCHAR(100) NOT NULL,
  email_verified_at TIMESTAMP NULL DEFAULT NULL,
  is_admin          TINYINT(1) NOT NULL DEFAULT 0,
  -- Unused today. If tools are ever charged for, this is the one boolean.
  is_paid           TINYINT(1) NOT NULL DEFAULT 0,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reset tokens, stored hashed: a leaked database should not hand out working
-- reset links.
CREATE TABLE IF NOT EXISTS password_resets (
  token_hash  CHAR(64) NOT NULL PRIMARY KEY,
  user_id     INT NOT NULL,
  expires_at  TIMESTAMP NOT NULL,
  used_at     TIMESTAMP NULL DEFAULT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  INDEX idx_password_resets_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
