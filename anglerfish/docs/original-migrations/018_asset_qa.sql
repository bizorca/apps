-- 018 — A machine check before a person looks.
--
-- Gemini's misses are the cheap-to-see kind: a misspelled label, a panel drawn
-- twice, a figure that is not in the direction, a footer reading "bizorce.com".
-- `worker/image_qa.py` pulls unreviewed infographics down, a Claude Code session
-- reads each one against its own art direction (on the subscription, like
-- extraction), and `cli.php qa-apply` writes the verdict here.
--
-- Separate columns rather than reusing `reviewed_at`, because the verdict and the
-- keep are different facts: a pass is auto-kept (reviewed_at set), a first fail
-- is rejected and redrawn, and a second fail on the same subject is left ready
-- and unreviewed with its notes, so the /review queue's "flagged" tab holds only
-- what the check could not resolve. `qa_verdict` on a kept row is also how a
-- machine keep is told apart from a human one later.

ALTER TABLE assets
  ADD COLUMN qa_verdict VARCHAR(8) NULL AFTER reviewed_at,
  ADD COLUMN qa_notes TEXT NULL AFTER qa_verdict,
  ADD COLUMN qa_at DATETIME NULL AFTER qa_notes;
