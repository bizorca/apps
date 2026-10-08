-- 007 — Typed links between concepts.
--
-- Expand (§11.2a) already finds related material at compose time, but it is
-- untyped and recomputed on every call. This stores the relationships a reader
-- would actually want to browse: what backs this idea up, what argues against
-- it, what takes it further.
--
-- Links are directional and cross-book by design — the point is that Kennedy's
-- take on referrals should visibly contrast with a financial-counselling
-- textbook's, not sit in a separate silo. Display resolves both directions.

CREATE TABLE IF NOT EXISTS concept_links (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_concept  INT UNSIGNED NOT NULL,
  to_concept    INT UNSIGNED NOT NULL,
  relation      ENUM('supports','contrasts','expands','related') NOT NULL DEFAULT 'related',
  note          VARCHAR(500) NULL,      -- one line on why, written at extraction
  -- Where the claim came from, so a bad batch can be re-run or rolled back
  -- without taking hand-made links with it.
  origin        ENUM('extraction','manual') NOT NULL DEFAULT 'extraction',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_link (from_concept, to_concept, relation),
  KEY idx_link_from (from_concept, relation),
  KEY idx_link_to   (to_concept, relation),
  CONSTRAINT fk_link_from FOREIGN KEY (from_concept)
    REFERENCES concepts(id) ON DELETE CASCADE,
  CONSTRAINT fk_link_to FOREIGN KEY (to_concept)
    REFERENCES concepts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
