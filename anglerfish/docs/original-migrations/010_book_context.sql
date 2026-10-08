-- Pass 1 writes a CONTEXT block and the importer dropped it on the floor.
--
-- It is the part of a profile that says how the book should be *used*: who the
-- author is and from what vantage, who it was written for, which chapters
-- transfer beyond the author's own field and which are field-bound, and what a
-- reader has to be careful about. Ede's records that her mental-health claims
-- need attributing to her rather than asserting; Kadushin's records that the
-- 1997 cross-cultural chapter is of its moment; the Freeman options title's
-- records that the book is a lead-generation funnel as well as a text.
--
-- That is exactly the material that keeps a composed post from misattributing
-- or over-claiming, and it existed only in files on one Mac.
--
-- A column on books rather than a concept, deliberately. A concept is something
-- to write *from* and the composer picks it up as material. This is something
-- to write carefully *because of* — framing, not source.

ALTER TABLE books ADD COLUMN context TEXT NULL AFTER pagination_note;
