-- Every post's infographic prompt was being rebuilt from scratch on each page
-- load and thrown away afterwards.
--
-- Two paths existed and neither persisted anything. ImagePrompt::draft() mines
-- the post body for label-length sentences and drops them into a fixed template,
-- which is instant and free and gives every post the same composition. Or
-- ArtDirection runs Opus at about a cent, returns a real per-post direction, and
-- that direction survives only if the operator gets as far as generating an
-- image — reload the generate page and it is gone. There was nowhere to keep a
-- direction that had been thought about, corrected and approved.
--
-- So an infographic could not be prepared ahead of publishing. The 410 drafts
-- carry a title and a body and nothing about what their picture should be.
--
-- This stores the direction, not the assembled prompt. The assembled prompt is
-- most of a page of brand anchors, attribution, text discipline and the avoid
-- list, and ImagePrompt owns all of it precisely so that Claude is never asked
-- to decide the brand and therefore cannot drift it. Materialising that prose
-- into 415 rows would copy the invariants 415 times and make every one of them
-- stale the day the palette changes. What is genuinely per-post is the
-- direction: the metaphor, the arrangement, the exact labels, the takeaway, the
-- accents and the cliché to avoid. ImagePrompt::fromDirection() already consumes
-- exactly that shape, so the column matches the contract that exists rather than
-- inventing a second one.
--
-- gemini_structure is a separate axis from the direction and belongs in its own
-- column: it is one of five fixed shapes (flow, metaphor, steps, contrast,
-- anatomy), the generate-page picker switches on it, and naming a structure up
-- front is what stops the model defaulting to three columns of text.
--
-- What actually got sent to Gemini is already recorded per render in
-- assets.prompt_snapshot. This is the authored intent; that is the audit trail.
-- Both are wanted and they are not the same thing.

ALTER TABLE posts
  ADD COLUMN gemini_structure ENUM('flow','metaphor','steps','contrast','anatomy')
      NULL AFTER body,
  ADD COLUMN gemini_direction JSON NULL AFTER gemini_structure,
  ADD COLUMN gemini_direction_at DATETIME NULL AFTER gemini_direction;
