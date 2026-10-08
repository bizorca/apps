-- 008 — Book summaries and audiobook scripts.
--
-- The outline itself is not stored. It is assembled deterministically from
-- concepts, chapters and artifacts that are already extracted, so it costs
-- nothing to rebuild and storing it would only let it drift from the material
-- it summarises.
--
-- The audiobook script IS stored: it costs a model call and a minute of wall
-- clock, and turning a written outline into spoken-word register is a real
-- transformation rather than a reformat.

CREATE TABLE IF NOT EXISTS book_summaries (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id      INT UNSIGNED NOT NULL,
  kind         ENUM('audiobook_script','narrative') NOT NULL DEFAULT 'audiobook_script',
  status       ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued',
  title        VARCHAR(500) NULL,
  body         MEDIUMTEXT NULL,
  word_count   MEDIUMINT UNSIGNED NULL,
  -- Narration runs about 150 wpm, so runtime is the number a listener cares
  -- about and word count is not.
  est_seconds  MEDIUMINT UNSIGNED NULL,
  model        VARCHAR(80) NULL,
  -- Snapshot of what the outline looked like when the script was generated.
  -- Without it there is no way to tell a stale script from a current one after
  -- a re-extraction changes the concepts underneath.
  source_sha   CHAR(64) NULL,
  error        TEXT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at  DATETIME NULL,

  UNIQUE KEY uq_summary (book_id, kind),
  KEY idx_summary_status (status),
  CONSTRAINT fk_summary_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
