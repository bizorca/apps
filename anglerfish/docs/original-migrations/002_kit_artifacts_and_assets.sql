-- 002 — Vault tools are artifacts too, but they come from a kit rather than a
-- book. One artifact store, two possible origins (SPEC §6.2).

ALTER TABLE artifacts
  MODIFY book_id INT UNSIGNED NULL,
  ADD COLUMN kit_id INT UNSIGNED NULL AFTER book_id,
  ADD KEY idx_art_kit (kit_id),
  ADD CONSTRAINT fk_art_kit FOREIGN KEY (kit_id) REFERENCES kits(id) ON DELETE CASCADE;

-- Assets, candidates and triage can all hang off a kit now.
ALTER TABLE assets
  MODIFY subject_type ENUM('book','chapter','concept','artifact','post','kit') NOT NULL;
ALTER TABLE candidates
  MODIFY subject_type ENUM('book','chapter','concept','artifact','post','kit') NOT NULL;
ALTER TABLE triage
  MODIFY subject_type ENUM('book','chapter','concept','artifact','candidate','asset','post','kit') NOT NULL;

-- Where a rendered file ended up, so re-renders can replace rather than pile up.
ALTER TABLE assets
  ADD COLUMN target VARCHAR(40) NULL AFTER kind,
  ADD KEY idx_asset_target (subject_type, subject_id, target);
