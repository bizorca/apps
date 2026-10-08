-- 015 — The deliverable's authored content travels with the composition.
--
-- render.py's weekly_deliverable target reads posts.deliverable_spec, and
-- until now nothing anywhere wrote that column: the composer produced a post
-- body and stopped, so every weekly post promoted so far would have printed
-- an empty container. The spec — the test's items and scoring bands, the
-- checklist's rows, the worksheet's prompts, the role document's fields — is
-- content, and content is authored where the post is authored.
--
-- On compositions rather than only on posts because a composition is the
-- reviewable unit: /compose/<id> is where the operator reads the draft before
-- promotion, and the spec should be sitting beside the body there rather than
-- appearing for the first time on a post. promote() copies it across.
--
-- This lands alongside the local composing path (cli.php weekly-import): posts
-- written in Claude Code on the subscription carry their spec in the same
-- file, so the PDF's content costs nothing metered either.

ALTER TABLE compositions
  ADD COLUMN deliverable_spec JSON NULL AFTER deliverable;
