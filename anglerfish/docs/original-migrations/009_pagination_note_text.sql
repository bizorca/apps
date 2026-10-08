-- pagination_note was VARCHAR(500) and every pass 1 note written so far hit the
-- ceiling and was silently truncated to exactly 500 characters.
--
-- These notes are not decoration. They are the record of how a book's page
-- references were derived, and on a scanned book that reasoning is the only
-- thing standing between a page anchor and a wrong one: which folio shapes
-- survived OCR, where the offset drifts and by how much, how many printed pages
-- are missing from the scan, and which chapter starts were observed versus
-- interpolated. Kadushin's runs to ten offset regions; book 13's records three
-- independent methods agreeing. Cutting that at 500 characters keeps the first
-- sentence and throws away the caveats, which is the worst half to lose.
--
-- TEXT rather than a larger VARCHAR: there is no natural length here, the
-- column is never indexed, and it is read one row at a time on a library page.

ALTER TABLE books MODIFY pagination_note TEXT NULL;
