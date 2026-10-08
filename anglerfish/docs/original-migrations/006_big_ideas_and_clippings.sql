-- 006 — Big Ideas as books, and corpus clippings.
--
-- Two ways into the composer that did not exist before.
--
-- Big Ideas needs almost no schema: a Big Ideas file IS a book (sections are
-- chapters, section text is a page), so it reuses books/pages/chapters/concepts
-- wholesale. Only a marker column is new, so the library can tell a synthesised
-- topic file apart from a scanned PDF.
--
-- Clippings are new. A corpus hit is not a row — it is a snippet inside one of
-- 38,086 .txt files — so there was nothing to compose from. A clipping promotes
-- a hit into a real record carrying its own provenance, which is also where the
-- verbatim flag lives: this is other people's copyrighted writing and the
-- composer must transform it rather than reproduce it.

ALTER TABLE books
  ADD COLUMN kind ENUM('scan','big_ideas') NOT NULL DEFAULT 'scan' AFTER scope_confirmed;

ALTER TABLE books
  ADD COLUMN brain ENUM('mb','dk') NULL AFTER kind;

CREATE INDEX idx_books_kind ON books (kind);

CREATE TABLE IF NOT EXISTS clippings (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brain       ENUM('mb','dk') NOT NULL,
  path        VARCHAR(1000) NOT NULL,
  path_hash   CHAR(40)     NOT NULL,        -- sha1(path); MySQL can't index 1000
  title       VARCHAR(500) NOT NULL,
  body        TEXT         NOT NULL,        -- the excerpt, as found
  note        VARCHAR(1000) NULL,           -- why it was worth keeping
  author      VARCHAR(255) NULL,
  collection  VARCHAR(255) NULL,
  line_ref    VARCHAR(60)  NULL,
  -- Corpus excerpts are transcribed verbatim by definition. The flag exists so
  -- prompt assembly can say so; it is not a per-clipping judgement call.
  verbatim    TINYINT(1)   NOT NULL DEFAULT 1,
  query       VARCHAR(255) NULL,            -- the search that surfaced it
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_clip_brain (brain),
  KEY idx_clip_path  (path_hash),
  FULLTEXT KEY ft_clippings (title, body)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
