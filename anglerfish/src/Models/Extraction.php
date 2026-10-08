<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

/**
 * Parse and import the extraction files Claude Code writes (SPEC §8.2).
 *
 * Format: YAML frontmatter + "## TYPE: Title" blocks, each with `key: value`
 * fields, optional "### Group" headings, and "- " list items. Human-readable
 * so it can be eyeballed and hand-corrected; strict enough to parse without
 * heuristics.
 */
final class Extraction
{
    private const ARTIFACT_TYPES = [
        'CHECKLIST' => 'checklist', 'QUESTION_SET' => 'question_set',
        'SCRIPT' => 'script', 'FRAMEWORK' => 'framework', 'WORKSHEET' => 'worksheet',
        'COMPARISON' => 'comparison', 'STAT' => 'stat', 'PROCEDURE' => 'procedure',
        'WORKED_EXAMPLE' => 'worked_example', 'TABLE' => 'table', 'QUOTE' => 'quote',
    ];

    /** Import one file. Idempotent on (book_slug, pass). */
    public static function import(string $bookSlug, string $pass, string $body): array
    {
        $book = Database::one('SELECT * FROM af_books WHERE slug = ?', [$bookSlug]);
        if (!$book) {
            throw new \RuntimeException("No book with slug '$bookSlug'. Ingest it first.");
        }
        $bookId = (int) $book['id'];

        $blocks = self::parse($body);
        $replaced = self::clearPass($bookId, $pass);

        $imported = match ($pass) {
            'profile'   => self::importProfile($bookId, $blocks),
            'chapters'  => self::importChapters($bookId, $blocks),
            'ideas'     => self::importIdeas($bookId, $blocks),
            'artifacts' => self::importArtifacts($bookId, $blocks),
            'links'     => self::importLinks($bookId, $blocks),
            default     => throw new \RuntimeException("Unknown pass '$pass'."),
        };

        Database::run(
            'INSERT INTO af_extraction_imports (book_id, book_slug, `pass`, filename, body_sha256,
                                             rows_imported, rows_replaced, imported_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE filename=VALUES(filename), body_sha256=VALUES(body_sha256),
                 rows_imported=VALUES(rows_imported), rows_replaced=VALUES(rows_replaced),
                 imported_at=NOW(), book_id=VALUES(book_id)',
            [$bookId, $bookSlug, $pass, "$bookSlug.$pass.md", hash('sha256', $body),
             // note_* keys report what was deliberately not written; counting
             // them as rows imported would overstate the audit record.
             array_sum(array_filter($imported,
                 static fn(string $k): bool => !str_starts_with($k, 'note_'),
                 ARRAY_FILTER_USE_KEY)),
             $replaced]
        );

        if ($pass === 'artifacts' || $pass === 'ideas') {
            Database::run("UPDATE af_books SET extract_status='partial', extracted_at=NOW() WHERE id=?", [$bookId]);
        }

        return $imported + ['replaced' => $replaced];
    }

    /**
     * Split into blocks.
     * Returns [['type','label','title','fields','groups'=>[['label','items','text']],'text'], ...]
     */
    public static function parse(string $body): array
    {
        $body = preg_replace('/\A---\R.*?\R---\R/s', '', $body) ?? $body;

        $blocks = [];
        $current = null;
        $group = null;

        $flushGroup = static function (?array &$block, ?array $g): void {
            if ($block !== null && $g !== null
                && ($g['items'] !== [] || $g['text'] !== [] || $g['label'] !== null)) {
                $g['text'] = implode("\n", $g['text']);
                $block['groups'][] = $g;
            }
        };

        foreach (preg_split('/\R/', $body) as $line) {
            $trim = trim($line);

            // "## TYPE: Title", "## BIG IDEA: Title", "## CHAPTER 4: Title", "## META"
            if (preg_match('/^##\s+(.+)$/', $trim, $m)) {
                $head = trim($m[1]);
                [$left, $title] = str_contains($head, ':')
                    ? array_map('trim', explode(':', $head, 2))
                    : [$head, ''];

                if (!preg_match('/^[A-Z][A-Z0-9_ ]*$/', $left)) {
                    continue;                       // not a block header, ignore
                }

                if ($current) {
                    $flushGroup($current, $group);
                    $blocks[] = self::closeBlock($current);
                }

                // "CHAPTER 4" -> type CHAPTER, label 4
                $label = null;
                if (preg_match('/^([A-Z][A-Z_ ]*?)\s+(\d+)$/', $left, $lm)) {
                    $left = $lm[1];
                    $label = $lm[2];
                }

                $current = [
                    'type'   => strtoupper(str_replace(' ', '_', trim($left))),
                    'label'  => $label,
                    'title'  => $title,
                    'fields' => [], 'groups' => [], 'text' => [],
                ];
                $group = null;
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^###\s+(?:Group:\s*)?(.+)$/i', $trim, $m)) {
                $flushGroup($current, $group);
                $group = ['label' => trim($m[1]), 'items' => [], 'text' => []];
                continue;
            }

            if (preg_match('/^[-*]\s+(?:\[[ xX]?\]\s*)?(.+)$/', $trim, $m)) {
                if ($group === null) {
                    $group = ['label' => null, 'items' => [], 'text' => []];
                }
                $group['items'][] = trim($m[1]);
                continue;
            }

            if ($group === null && preg_match('/^([a-z_]+):\s*(.*)$/', $trim, $m)) {
                $current['fields'][$m[1]] = trim($m[2]);
                continue;
            }

            if ($trim !== '') {
                if ($group !== null) {
                    $group['text'][] = $trim;
                } else {
                    $current['text'][] = $trim;
                }
            }
        }

        if ($current) {
            $flushGroup($current, $group);
            $blocks[] = self::closeBlock($current);
        }
        return $blocks;
    }

    private static function closeBlock(array $block): array
    {
        $block['text'] = implode("\n", $block['text']);
        return $block;
    }

    /** Remove what this pass wrote last time, so a re-import replaces. */
    private static function clearPass(int $bookId, string $pass): int
    {
        return match ($pass) {
            'profile' => Database::run(
                "DELETE FROM af_concepts WHERE book_id=? AND kind='thesis'", [$bookId]),
            'chapters' => Database::run(
                "DELETE FROM af_concepts WHERE book_id=? AND kind='key_concept'", [$bookId])
                + Database::run('DELETE FROM af_chapters WHERE book_id=?', [$bookId]),
            'ideas' => Database::run(
                "DELETE FROM af_concepts WHERE book_id=? AND kind IN ('big_idea','caveat')", [$bookId]),
            'artifacts' => Database::run('DELETE FROM af_artifacts WHERE book_id=?', [$bookId]),
            // Only this book's extracted edges. Hand-made links and other
            // books' claims about this one both survive a re-run.
            'links' => Database::run(
                "DELETE l FROM af_concept_links l JOIN af_concepts c ON c.id = l.from_concept
                  WHERE c.book_id = ? AND l.origin = 'extraction'", [$bookId]),
            default => 0,
        };
    }

    private static function importProfile(int $bookId, array $blocks): array
    {
        $n = ['concepts' => 0, 'chapters' => 0, 'artifacts' => 0];

        foreach ($blocks as $b) {
            if ($b['type'] === 'META') {
                $f = $b['fields'];
                // printed_page_offset and pagination_note are derived by ingest
                // too, but a person reading folios beats the heuristic and is
                // the only source that can say "the offset drifts, pages are
                // missing". Written only when the profile supplies them.
                // Caveat: applyIngest recomputes both, so re-ingesting a book
                // after this import discards the hand-derived values.
                Database::run(
                    'UPDATE af_books SET title=COALESCE(NULLIF(?,""),title),
                                      subtitle=NULLIF(?,""), year=NULLIF(?,0),
                                      publisher=NULLIF(?,""), isbn=NULLIF(?,""),
                                      printed_page_offset=COALESCE(?,printed_page_offset),
                                      pagination_note=COALESCE(NULLIF(?,""),pagination_note)
                      WHERE id=?',
                    [$f['title'] ?? '', $f['subtitle'] ?? '', (int) ($f['year'] ?? 0),
                     $f['publisher'] ?? '', $f['isbn'] ?? '',
                     isset($f['printed_page_offset']) && $f['printed_page_offset'] !== ''
                         ? (int) $f['printed_page_offset'] : null,
                     $f['pagination_note'] ?? '', $bookId]
                );
                self::linkAuthors($bookId, $f['authors'] ?? '');
                self::linkTerms($bookId, $f['terms'] ?? '');
                self::linkCategory($bookId, $f['category'] ?? '');
            }

            if ($b['type'] === 'COVERS') {
                $n = self::importCovers($bookId, $b, $n);
            }

            // Framing, not source. Stored on the book rather than as a concept
            // so the composer can be told how to treat the material without
            // being handed it as something to write from.
            if ($b['type'] === 'CONTEXT' && $b['text'] !== '') {
                Database::run('UPDATE af_books SET context = ? WHERE id = ?',
                              [$b['text'], $bookId]);
            }

            if ($b['type'] === 'THESIS' && $b['text'] !== '') {
                Database::run(
                    "INSERT INTO af_concepts (book_id, ord, kind, title, body)
                     VALUES (?, 0, 'thesis', 'Central thesis', ?)",
                    [$bookId, $b['text']]
                );
                $n['concepts']++;
            }
        }
        return $n;
    }

    /**
     * Pass 1 COVERS -> proposed chapter boundaries.
     *
     * These ranges are the expensive half of pass 1 — they come from reading
     * observed chapter openers in the body, which is the only method that
     * survives a stale contents page or an offset that drifts. They used to be
     * parsed and dropped, so the ranges existed only in the outbox file and
     * the app went on showing whatever detect_chapters had guessed.
     *
     * Written as `proposed`, never `confirmed`: pass 1 is a reading, and the
     * house rule is that nothing is authoritative until someone confirms it in
     * the UI, because every later pass inherits these boundaries.
     */
    private static function importCovers(int $bookId, array $b, array $n): array
    {
        $rows = [];
        foreach ($b['groups'] as $g) {
            foreach ($g['items'] as $item) {
                // "1. Setting the Stage — pages 13-24". Anchored on the
                // trailing "pages N-M" rather than the first dash it finds,
                // because chapter titles contain dashes of their own.
                if (!preg_match('/^\s*(.+?)\s*[—–-]\s*pages?\s+(\d+)'
                              . '(?:\s*[-–—]\s*(\d+))?\s*$/i', $item, $m)) {
                    continue;
                }
                $label = null;
                $title = trim($m[1]);
                if (preg_match('/^(\d{1,3})[.)]\s*(.+)$/', $title, $lm)) {
                    [$label, $title] = [$lm[1], trim($lm[2])];
                }
                $rows[] = [$label, $title, (int) $m[2],
                           ($m[3] ?? '') !== '' ? (int) $m[3] : null];
            }
        }
        if ($rows === []) {
            return $n;
        }

        // Only machine-proposed boundaries are replaceable. A chapter someone
        // confirmed, or one pass 2 has already written a summary into, is worth
        // more than this list — so in that case nothing is touched and the
        // count says so rather than reporting a silent zero.
        $held = (int) Database::value(
            "SELECT COUNT(*) FROM af_chapters WHERE book_id=? AND status<>'proposed'",
            [$bookId]);
        if ($held > 0) {
            $n['note_covers_skipped'] = count($rows);
            return $n;
        }

        Database::run("DELETE FROM af_chapters WHERE book_id=? AND status='proposed'",
                      [$bookId]);
        $ord = 0;
        foreach ($rows as [$label, $title, $start, $end]) {
            Database::run(
                'INSERT INTO af_chapters (book_id, ord, number_label, title,
                                       pdf_page_start, pdf_page_end, status)
                 VALUES (?, ?, ?, ?, ?, ?, "proposed")',
                [$bookId, ++$ord, $label, mb_substr($title, 0, 500), $start, $end]
            );
            $n['chapters']++;
        }
        return $n;
    }

    private static function importChapters(int $bookId, array $blocks): array
    {
        $n = ['chapters' => 0, 'concepts' => 0, 'artifacts' => 0];
        $ord = 0;

        foreach ($blocks as $b) {
            if ($b['type'] !== 'CHAPTER') {
                continue;
            }
            [$start, $end] = self::pageRange($b['fields']['pages'] ?? '');
            $label = $b['label'];
            $title = $b['title'];

            $sections = self::sections($b);

            $chapterId = Database::insert(
                'INSERT INTO af_chapters (book_id, ord, number_label, title,
                                       pdf_page_start, pdf_page_end, core_idea, summary, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "extracted")',
                [$bookId, ++$ord, $label, mb_substr($title, 0, 500), $start, $end,
                 $sections['CORE IDEA'] ?? null, $sections['SUMMARY'] ?? null]
            );
            $n['chapters']++;

            foreach ($b['groups'] as $g) {
                // CORE IDEA / SUMMARY are chapter fields, not concepts.
                if ($g['label'] && in_array(strtoupper(trim($g['label'])),
                                            ['CORE IDEA', 'SUMMARY'], true)) {
                    continue;
                }
                foreach ($g['items'] as $i => $item) {
                    [$cTitle, $cBody] = self::splitConcept($item);
                    // The concept carries its own page reference. It lives
                    // inside the chapter's range, and the book page lists
                    // concepts rather than chapters, so it must not depend on
                    // a join to say where in the PDF it came from.
                    $ref = $start ? ($end && $end !== $start ? "$start-$end" : (string) $start) : null;
                    Database::run(
                        "INSERT INTO af_concepts (book_id, chapter_id, ord, kind, title, body, page_ref)
                         VALUES (?, ?, ?, 'key_concept', ?, ?, ?)",
                        [$bookId, $chapterId, $i, mb_substr($cTitle, 0, 500), $cBody, $ref]
                    );
                    $n['concepts']++;
                }
            }
        }
        return $n;
    }

    private static function importIdeas(int $bookId, array $blocks): array
    {
        $n = ['concepts' => 0, 'chapters' => 0, 'artifacts' => 0];
        $ord = 0;

        foreach ($blocks as $b) {
            $kind = match ($b['type']) {
                'BIG_IDEA', 'IDEA' => 'big_idea',
                'CAVEAT' => 'caveat',
                default  => null,
            };
            if ($kind === null) {
                continue;
            }
            Database::run(
                'INSERT INTO af_concepts (book_id, ord, kind, title, body, chapters_ref)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$bookId, ++$ord, $kind, mb_substr($b['title'], 0, 500),
                 $b['text'], $b['fields']['chapters'] ?? null]
            );
            $n['concepts']++;
        }
        return $n;
    }

    private static function importArtifacts(int $bookId, array $blocks): array
    {
        $n = ['artifacts' => 0, 'items' => 0, 'concepts' => 0, 'chapters' => 0];
        $ord = 0;

        foreach ($blocks as $b) {
            $type = self::ARTIFACT_TYPES[$b['type']] ?? null;
            if ($type === null) {
                continue;
            }

            $f = $b['fields'];
            [$start, $end] = self::pageRange($f['pages'] ?? '');
            $verbatim = in_array(strtolower($f['verbatim'] ?? ''), ['yes', 'true', '1'], true);
            $conf = isset($f['confidence']) ? (float) $f['confidence'] : null;

            // The worker validates verbatim text against the source before it
            // ever posts. Anything low-confidence still lands in review.
            $review = ($conf !== null && $conf < 0.80) ? 'flagged' : 'unreviewed';

            $artifactId = Database::insert(
                'INSERT INTO af_artifacts (book_id, chapter_id, ord, type, title, intro,
                                        verbatim, page_start, page_end, confidence, review_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$bookId, self::chapterIdFor($bookId, $f['chapter'] ?? null), ++$ord, $type,
                 mb_substr($b['title'], 0, 500), $f['intro'] ?? ($b['text'] ?: null),
                 (int) $verbatim, $start, $end, $conf, $review]
            );
            $n['artifacts']++;

            $i = 0;
            foreach ($b['groups'] as $g) {
                foreach ($g['items'] as $item) {
                    Database::run(
                        'INSERT INTO af_artifact_items (artifact_id, ord, group_label, text)
                         VALUES (?, ?, ?, ?)',
                        [$artifactId, ++$i, $g['label'], $item]
                    );
                    $n['items']++;
                }
            }
        }
        return $n;
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Pass 5 — typed edges between this book's concepts and the rest of the
     * library.
     *
     * Both endpoints must already exist. A link naming a concept that was never
     * extracted is skipped and counted rather than created, because a silently
     * invented edge is worse than a missing one: it would show up on the book
     * page as a claim nobody made.
     */
    private static function importLinks(int $bookId, array $blocks): array
    {
        $n = ['links' => 0, 'skipped' => 0, 'concepts' => 0, 'chapters' => 0, 'artifacts' => 0];

        foreach ($blocks as $b) {
            if ($b['type'] !== 'LINK') {
                continue;
            }
            $f = $b['fields'];
            $relation = strtolower(trim((string) ($f['relation'] ?? 'related')));
            if (!in_array($relation, ['supports', 'contrasts', 'expands', 'related'], true)) {
                $relation = 'related';
            }

            $from = Database::value(
                'SELECT id FROM af_concepts WHERE book_id = ? AND title = ? LIMIT 1',
                [$bookId, $b['title']]
            );
            $to = Database::value(
                'SELECT c.id FROM af_concepts c JOIN af_books b ON b.id = c.book_id
                  WHERE c.title = ? AND b.slug = ? LIMIT 1',
                [trim((string) ($f['to'] ?? '')), trim((string) ($f['to_book'] ?? ''))]
            );

            // A self-link would render as a concept relating to itself.
            if (!$from || !$to || (int) $from === (int) $to) {
                $n['skipped']++;
                continue;
            }

            Database::run(
                "INSERT INTO af_concept_links (from_concept, to_concept, relation, note, origin)
                 VALUES (?, ?, ?, ?, 'extraction')
                 ON DUPLICATE KEY UPDATE note = VALUES(note)",
                [(int) $from, (int) $to, $relation,
                 mb_substr(trim((string) ($b['text'] ?? '')), 0, 500) ?: null]
            );
            $n['links']++;
        }
        return $n;
    }

    /** "### CORE IDEA" / "### SUMMARY" sections inside a CHAPTER block. */
    private static function sections(array $block): array
    {
        $out = [];
        foreach ($block['groups'] as $g) {
            if (!$g['label']) {
                continue;
            }
            $key = strtoupper(trim($g['label']));
            if (in_array($key, ['CORE IDEA', 'SUMMARY'], true)) {
                $out[$key] = trim($g['text'] !== '' ? $g['text'] : implode(' ', $g['items']));
            }
        }
        return $out;
    }

    private static function splitConcept(string $item): array
    {
        if (preg_match('/^\*\*(.+?)\*\*\s*[—–-]\s*(.+)$/u', $item, $m)) {
            return [trim($m[1]), trim($m[2])];
        }
        if (preg_match('/^(.+?)\s+[—–]\s+(.+)$/u', $item, $m)) {
            return [trim($m[1]), trim($m[2])];
        }
        return [mb_substr($item, 0, 200), $item];
    }

    private static function pageRange(string $s): array
    {
        if (preg_match('/(\d+)\s*[-–—]\s*(\d+)/', $s, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/(\d+)/', $s, $m)) {
            return [(int) $m[1], null];
        }
        return [null, null];
    }

    private static function chapterIdFor(int $bookId, ?string $ref): ?int
    {
        if ($ref === null || !preg_match('/(\d+)/', $ref, $m)) {
            return null;
        }
        $id = Database::value(
            'SELECT id FROM af_chapters WHERE book_id=? AND (number_label=? OR ord=?) LIMIT 1',
            [$bookId, $m[1], (int) $m[1]]
        );
        return $id ? (int) $id : null;
    }

    private static function linkAuthors(int $bookId, string $csv): void
    {
        Database::run('DELETE FROM af_book_authors WHERE book_id=?', [$bookId]);
        foreach (self::csv($csv) as $i => $name) {
            $slug = self::slug($name);
            $id = Database::value('SELECT id FROM af_authors WHERE slug=?', [$slug])
                ?: Database::insert('INSERT INTO af_authors (slug, name) VALUES (?, ?)', [$slug, $name]);
            Database::run('INSERT IGNORE INTO af_book_authors (book_id, author_id, ord) VALUES (?, ?, ?)',
                [$bookId, (int) $id, $i]);
        }
    }

    private static function linkTerms(int $bookId, string $csv): void
    {
        Database::run('DELETE FROM af_book_terms WHERE book_id=?', [$bookId]);
        foreach (self::csv($csv) as $name) {
            $slug = self::slug($name);
            $id = Database::value('SELECT id FROM af_terms WHERE slug=?', [$slug])
                ?: Database::insert('INSERT INTO af_terms (slug, name, kind) VALUES (?, ?, "topic")',
                    [$slug, $name]);
            Database::run('INSERT IGNORE INTO af_book_terms (book_id, term_id) VALUES (?, ?)',
                [$bookId, (int) $id]);
        }
    }

    private static function linkCategory(int $bookId, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }
        $slug = self::slug($name);
        $id = Database::value('SELECT id FROM af_categories WHERE slug=?', [$slug])
            ?: Database::insert('INSERT INTO af_categories (slug, name) VALUES (?, ?)', [$slug, $name]);
        Database::run('DELETE FROM af_book_categories WHERE book_id=?', [$bookId]);
        Database::run('INSERT IGNORE INTO af_book_categories (book_id, category_id) VALUES (?, ?)',
            [$bookId, (int) $id]);
    }

    /** @return string[] */
    private static function csv(string $s): array
    {
        $s = trim($s, " \t[]");
        return array_values(array_filter(array_map('trim', explode(',', $s)), 'strlen'));
    }

    private static function slug(string $s): string
    {
        return mb_substr(trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower($s)), '-'), 0, 150);
    }
}
