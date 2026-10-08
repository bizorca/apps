-- 005 — Composer output. Generation runs on the worker (the API key never
-- touches the server), so a composition is a job whose result lands here.
CREATE TABLE IF NOT EXISTS compositions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_type  ENUM('concept','artifact','post','source','freeform') NOT NULL,
  subject_id    INT UNSIGNED NULL,
  format        ENUM('before_noon','gut_check','steal_this','the_upgrade',
                     'the_protocol','one_number') NOT NULL,
  angle         TEXT NULL,              -- mandatory when book-sourced (§11.2)
  audience      VARCHAR(60)  NOT NULL DEFAULT 'practitioners',
  length        ENUM('short','medium','long') NOT NULL DEFAULT 'medium',
  voice_preset  VARCHAR(60)  NOT NULL DEFAULT 'bizorca_press',
  extra         TEXT NULL,
  model         VARCHAR(80)  NULL,
  status        ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued',
  title         VARCHAR(500) NULL,
  subtitle      VARCHAR(500) NULL,
  body          MEDIUMTEXT NULL,
  post_id       INT UNSIGNED NULL,      -- set when promoted to a draft
  cost_usd      DECIMAL(10,4) NOT NULL DEFAULT 0,
  error         TEXT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at   DATETIME NULL,
  KEY idx_comp_status (status),
  KEY idx_comp_subject (subject_type, subject_id),
  CONSTRAINT fk_comp_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
