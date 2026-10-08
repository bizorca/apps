-- Lattice LMS, ported from lattice.bizorca.com's own MySQL database (schema.sql
-- plus its 001_answers_soft_delete migration). Every table is lt_-prefixed and
-- user ids point at the shared users table; Lattice's own users table is gone.
--
-- Ids are signed INT (were INT UNSIGNED) because users.id is signed and MySQL
-- refuses a foreign key across that mismatch.
--
-- Course admin is a Lattice role, not a site role: lt_admins. A site admin
-- (users.is_admin) is always a Lattice admin too; see includes/auth.php.
--
-- One deliberate change: question_responses' references to slots and variants
-- now CASCADE and the selected answer is SET NULL on delete. In the original
-- they RESTRICTed, so deleting a lesson, challenge, unit or course that any
-- student had answered threw SQLSTATE 23000. Retiring an answer
-- (retire_answer) still keeps in-use options rather than deleting them.

CREATE TABLE IF NOT EXISTS lt_courses (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(255) NOT NULL,
  slug           VARCHAR(255) NOT NULL UNIQUE,
  description    TEXT,
  thumbnail_path VARCHAR(500),
  is_published   TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lt_units (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  course_id      INT NOT NULL,
  sort_order     INT NOT NULL DEFAULT 0,
  title          VARCHAR(255) NOT NULL,
  about_html     TEXT,
  tutorials_url  VARCHAR(500),
  is_final       TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_lt_units_course FOREIGN KEY (course_id) REFERENCES lt_courses (id) ON DELETE CASCADE,
  INDEX idx_lt_units_course_sort (course_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- type: challenge | practice_milestone | milestone | final_milestone
CREATE TABLE IF NOT EXISTS lt_challenges (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  unit_id            INT NOT NULL,
  sort_order         INT NOT NULL DEFAULT 0,
  title              VARCHAR(255) NOT NULL,
  type               ENUM('challenge','practice_milestone','milestone','final_milestone') NOT NULL DEFAULT 'challenge',
  time_limit_minutes INT UNSIGNED,
  max_attempts       TINYINT UNSIGNED NOT NULL DEFAULT 2,
  CONSTRAINT fk_lt_challenges_unit FOREIGN KEY (unit_id) REFERENCES lt_units (id) ON DELETE CASCADE,
  INDEX idx_lt_challenges_unit_sort (unit_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each lesson = one numbered nav circle in the challenge split-pane view
CREATE TABLE IF NOT EXISTS lt_lessons (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  challenge_id       INT NOT NULL,
  sort_order         INT NOT NULL DEFAULT 0,
  title              VARCHAR(255) NOT NULL,
  learning_objective TEXT,
  what_covered_text  TEXT,        -- newline-delimited list of sub-topics
  content_html       LONGTEXT,
  CONSTRAINT fk_lt_lessons_challenge FOREIGN KEY (challenge_id) REFERENCES lt_challenges (id) ON DELETE CASCADE,
  INDEX idx_lt_lessons_challenge_sort (challenge_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One slot per lesson (the question "position", holding up to 3 variants)
CREATE TABLE IF NOT EXISTS lt_question_slots (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT NOT NULL UNIQUE,
  CONSTRAINT fk_lt_slots_lesson FOREIGN KEY (lesson_id) REFERENCES lt_lessons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Up to 3 variants per slot, served sequentially on wrong answers
CREATE TABLE IF NOT EXISTS lt_question_variants (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  slot_id        INT NOT NULL,
  variant_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
  question_text  TEXT NOT NULL,
  UNIQUE KEY uq_lt_slot_variant (slot_id, variant_number),
  CONSTRAINT fk_lt_variants_slot FOREIGN KEY (slot_id) REFERENCES lt_question_slots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lt_answers (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  variant_id  INT NOT NULL,
  answer_text TEXT NOT NULL,
  is_correct  TINYINT(1) NOT NULL DEFAULT 0,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  -- Retired instead of deleted once a student has picked it.
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_lt_variant_sort (variant_id, sort_order),
  CONSTRAINT fk_lt_answers_variant FOREIGN KEY (variant_id) REFERENCES lt_question_variants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lt_enrollments (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  course_id   INT NOT NULL,
  enrolled_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lt_user_course (user_id, course_id),
  CONSTRAINT fk_lt_enrollments_user   FOREIGN KEY (user_id)   REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_lt_enrollments_course FOREIGN KEY (course_id) REFERENCES lt_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per attempt at an entire challenge
CREATE TABLE IF NOT EXISTS lt_challenge_attempts (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  challenge_id   INT NOT NULL,
  attempt_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
  started_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at   TIMESTAMP NULL,
  score_pct      DECIMAL(5,2) NULL,
  CONSTRAINT fk_lt_attempts_user      FOREIGN KEY (user_id)      REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_lt_attempts_challenge FOREIGN KEY (challenge_id) REFERENCES lt_challenges (id) ON DELETE CASCADE,
  INDEX idx_lt_attempts_user_challenge (user_id, challenge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per variant answered within an attempt
CREATE TABLE IF NOT EXISTS lt_question_responses (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id         INT NOT NULL,
  slot_id            INT NOT NULL,
  variant_id         INT NOT NULL,
  selected_answer_id INT NULL,
  is_correct         TINYINT(1) NOT NULL DEFAULT 0,
  responded_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lt_responses_attempt FOREIGN KEY (attempt_id)         REFERENCES lt_challenge_attempts (id) ON DELETE CASCADE,
  CONSTRAINT fk_lt_responses_slot    FOREIGN KEY (slot_id)            REFERENCES lt_question_slots (id) ON DELETE CASCADE,
  CONSTRAINT fk_lt_responses_variant FOREIGN KEY (variant_id)         REFERENCES lt_question_variants (id) ON DELETE CASCADE,
  CONSTRAINT fk_lt_responses_answer  FOREIGN KEY (selected_answer_id) REFERENCES lt_answers (id) ON DELETE SET NULL,
  INDEX idx_lt_responses_attempt_slot (attempt_id, slot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lattice course admins. Site admins (users.is_admin) need no row here.
CREATE TABLE IF NOT EXISTS lt_admins (
  user_id    INT NOT NULL PRIMARY KEY,
  granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lt_admins_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
