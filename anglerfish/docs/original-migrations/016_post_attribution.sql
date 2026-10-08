-- The footer credit was hardcoded to bizorca.com inside ImagePrompt, which was
-- correct for as long as this app served one publication.
--
-- It now serves two. A Hypnologue graphic generated through the same pipeline
-- came out stamped "© 2026 bizorca.com", and nothing in the app could have
-- prevented it: rightsLine() took no argument and every caller got the same
-- string. The error is quiet in the worst way — the image looks finished, the
-- footer is the last place anyone reads, and by the time it is noticed it has
-- been published.
--
-- ImagePrompt::ATTRIBUTIONS is now the list, and rightsLine() resolves a key
-- from it. This column is where a post remembers which one it belongs to, so
-- the generate screen defaults correctly instead of relying on the operator to
-- pick it every single time. Defaulting to 'bizorca' keeps every existing post
-- exactly as it was.
--
-- Deliberately a short varchar holding a key, not the printed line: the line
-- carries a year and the wording may change, and neither should be frozen into
-- hundreds of rows.

ALTER TABLE posts
    ADD COLUMN attribution VARCHAR(32) NOT NULL DEFAULT 'bizorca'
    AFTER gemini_structure;
