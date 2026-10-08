-- 004 — Deep corpus search. The index lives on the server, the 1.2GB of bodies
-- stays on the Mac, so a body search is a job whose results land here.
CREATE TABLE IF NOT EXISTS corpus_searches (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  query       VARCHAR(255) NOT NULL,
  brain       ENUM('mb','dk','both') NOT NULL DEFAULT 'both',
  status      ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued',
  hits        INT UNSIGNED NOT NULL DEFAULT 0,
  files       INT UNSIGNED NOT NULL DEFAULT 0,
  results     JSON NULL,
  error       TEXT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  KEY idx_cs_query (query, brain),
  KEY idx_cs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
