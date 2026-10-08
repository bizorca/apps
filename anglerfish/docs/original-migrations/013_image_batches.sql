-- 013 — Batch image generation.
--
-- Gemini's Batch API runs the same image model at half price — $0.067 against
-- $0.134 for a 1K-2K Nano Banana Pro image — with a 24-hour turnaround target.
-- The money is not the reason. One infographic per chapter across the library is
-- 494 images, and 494 is not a number of buttons anyone is going to click.
--
-- Three things follow from the 24-hour window, and they are why this needs
-- storage rather than a flag on the existing generate_image job:
--
--   1. A batch outlives every lease. `generate_image` fires, waits ~40s and
--      writes an asset inside one job. A batch is submitted now and answered
--      later, so the remote job name has to live somewhere durable and be
--      polled by a job that did not submit it.
--   2. The asset rows exist before the pixels do. Each request carries a key
--      that comes back with its response, so `batch_key` on the asset is what
--      matches one returned image to the row waiting for it. Rows start
--      `pending` and become `ready` when the bytes land.
--   3. Poll jobs must be able to wait. Without `jobs.not_before` a poller
--      re-queued for a 24-hour batch runs every minute for a day; with it, the
--      queue simply skips it until it is due.
--
-- assets already carries subject_type='chapter' and kind='infographic', so
-- nothing about what a batch produces is new — only how it is ordered and
-- collected.

CREATE TABLE IF NOT EXISTS image_batches (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider      ENUM('gemini') NOT NULL DEFAULT 'gemini',
  model         VARCHAR(80) NOT NULL,
  subject_type  ENUM('book') NOT NULL DEFAULT 'book',
  subject_id    INT UNSIGNED NOT NULL,

  -- 'directing'  art direction jobs are still running
  -- 'submitting' directions are in; the request is being built
  -- 'running'    accepted by Gemini, waiting on the remote job
  -- 'done'       every response applied (or accounted for as failed)
  status        ENUM('directing','submitting','running','done','failed','cancelled')
                  NOT NULL DEFAULT 'directing',

  -- Gemini's own name for the job, e.g. "batches/abc123". The only handle
  -- there is: lose it and the results cannot be collected at all.
  remote_name   VARCHAR(200) NULL,
  remote_state  VARCHAR(60) NULL,

  aspect_ratio  VARCHAR(12) NOT NULL DEFAULT '16:9',
  image_size    VARCHAR(8)  NOT NULL DEFAULT '2K',
  request_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  applied_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  failed_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  polls         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cost_usd      DECIMAL(10,4) NOT NULL DEFAULT 0,
  error         TEXT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at  DATETIME NULL,
  completed_at  DATETIME NULL,
  KEY idx_batch_status (status),
  KEY idx_batch_subject (subject_type, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE assets
  ADD COLUMN batch_id  INT UNSIGNED NULL AFTER candidate_id,
  ADD COLUMN batch_key VARCHAR(64)  NULL AFTER batch_id,
  ADD KEY idx_asset_batch (batch_id, batch_key);

-- Deliberately no foreign key from assets.batch_id: a batch row is a receipt
-- for how an image was ordered, and deleting the receipt must never cascade
-- into deleting the image.

ALTER TABLE jobs
  ADD COLUMN not_before DATETIME NULL AFTER priority,
  ADD KEY idx_jobs_due (status, not_before);
