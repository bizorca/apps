-- A weekly draft's download PDF can be rendered before the draft is promoted
-- (2026-11-11), so a composition can own an asset. Promotion still renders the
-- numbered PDF on the post.
ALTER TABLE af_assets MODIFY `subject_type` enum('book','chapter','concept','artifact','post','kit','composition') COLLATE utf8mb4_unicode_ci NOT NULL;
