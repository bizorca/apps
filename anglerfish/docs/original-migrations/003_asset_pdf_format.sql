-- 003 — tool sheets are PDFs. Recording them as "png" to satisfy the enum made
-- the column lie about what is on disk.
ALTER TABLE assets MODIFY format ENUM('png','jpg','pdf') NOT NULL DEFAULT 'png';
UPDATE assets SET format='pdf' WHERE web_path LIKE '%.pdf';
