-- 014 — Reviewed or not, for every generated image.
--
-- Batch generation made the review the bottleneck rather than the generation.
-- Thirteen images arrive at once today and several hundred will later, and
-- `status='ready'` cannot tell "Gemini answered" apart from "a human looked at
-- it and kept it" — so a queue built on status alone re-presents every image
-- that was already approved, forever.
--
-- A timestamp rather than a new `approved` value in the status enum, for one
-- specific reason: two places supersede an older picture with
-- `WHERE status = 'ready'` (ServerJobs::applyImage and applyPoll). An approved
-- image that no longer said 'ready' would quietly stop being superseded, and
-- the book page would end up showing two current infographics for one chapter.
-- Additive column, nothing existing changes meaning.
--
-- Rejection already has a home in `status`, so this column is only ever set,
-- never cleared: the queue is "ready and not yet looked at".

ALTER TABLE assets
  ADD COLUMN reviewed_at DATETIME NULL AFTER status,
  ADD KEY idx_asset_review (kind, status, reviewed_at);

-- Backfill: everything generated before there was a queue has been seen in the
-- course of ordering it. Starting the queue with months of old assets in it
-- would mean the first act of using the screen is clearing it out.
UPDATE assets
   SET reviewed_at = created_at
 WHERE status = 'ready'
   AND created_at < '2026-09-04'
   AND reviewed_at IS NULL;
