<?php

namespace Anglerfish\Services;

use Anglerfish\Core\Database;

/**
 * The whole book, condensed into one file (SPEC §11.5).
 *
 * Everything here is already extracted — the thesis, the big ideas, the
 * per-chapter core ideas, the artifacts. This assembles them in reading order
 * and emits one document. No model is involved and nothing is stored: the
 * outline is a view over the concepts, so rebuilding it is instant and it can
 * never drift from the material underneath.
 *
 * Verbatim artifacts are the one thing that needs care. They are transcribed
 * word-for-word from copyrighted books and stored intact for internal
 * reference, so the outline marks them plainly and the audiobook script — the
 * output that looks most like a product — is told to transform rather than
 * read them out (SPEC §15.2).
 */
final class BookSummary
{
    /** Narration pace. The runtime, not the word count, is what a listener asks about. */
    public const WORDS_PER_MINUTE = 150;

    private const KIND_HEADING = [
        'thesis'      => 'The central thesis',
        'big_idea'    => 'The big ideas',
        'key_concept' => 'Concepts, chapter by chapter',
        'caveat'      => 'Limits and caveats',
    ];

    /**
     * @return array{book:array,markdown:string,words:int,minutes:int,
     *               counts:array<string,int>,verbatim:int,sha:string}|null
     */
    public static function outline(string $slug, bool $includeArtifacts = true): ?array
    {
        $book = Database::one('SELECT * FROM af_books WHERE slug = ?', [$slug]);
        if (!$book) {
            return null;
        }
        $id = (int) $book['id'];

        $concepts = Database::all(
            "SELECT kind, title, body, page_ref, chapters_ref, chapter_id, ord
               FROM af_concepts WHERE book_id = ?
              ORDER BY FIELD(kind,'thesis','big_idea','key_concept','caveat'), ord",
            [$id]
        );
        $chapters = Database::all(
            "SELECT id, number_label, title, core_idea, summary, pdf_page_start
               FROM af_chapters WHERE book_id = ? AND status <> 'proposed' ORDER BY ord",
            [$id]
        );
        $artifacts = $includeArtifacts ? Database::all(
            'SELECT id, type, title, intro, verbatim FROM af_artifacts
              WHERE book_id = ? ORDER BY ord', [$id]) : [];

        if (!$concepts && !$chapters && !$artifacts) {
            return null;                       // nothing extracted yet
        }

        $authors = Database::value(
            'SELECT GROUP_CONCAT(a.name ORDER BY ba.ord SEPARATOR ", ")
               FROM af_book_authors ba JOIN af_authors a ON a.id = ba.author_id
              WHERE ba.book_id = ?', [$id]);

        $md   = [];
        $md[] = '# ' . $book['title'];
        if ($book['subtitle']) {
            $md[] = '*' . $book['subtitle'] . '*';
        }
        $meta = array_filter([$authors, $book['year'], $book['publisher']]);
        if ($meta) {
            $md[] = implode(' · ', $meta);
        }
        $md[] = '';
        $md[] = '> Summary assembled from extracted concepts. Not a substitute for the '
              . 'book, and not for redistribution — the source is in copyright.';
        $md[] = '';

        $counts = ['thesis' => 0, 'big_idea' => 0, 'key_concept' => 0, 'caveat' => 0];
        $byKind = [];
        foreach ($concepts as $c) {
            $byKind[$c['kind']][] = $c;
            $counts[$c['kind']] = ($counts[$c['kind']] ?? 0) + 1;
        }

        // Chapter titles, so a key concept can say where it came from without
        // the reader holding the book open beside it.
        $chapterTitle = [];
        foreach ($chapters as $ch) {
            $chapterTitle[(int) $ch['id']] = $ch['title'];
        }

        foreach (['thesis', 'big_idea'] as $kind) {
            if (empty($byKind[$kind])) {
                continue;
            }
            $md[] = '## ' . self::KIND_HEADING[$kind];
            $md[] = '';
            foreach ($byKind[$kind] as $c) {
                $md[] = '### ' . $c['title'] . self::ref($c);
                $md[] = '';
                $md[] = trim((string) $c['body']);
                $md[] = '';
            }
        }

        if (!empty($byKind['key_concept'])) {
            $md[] = '## ' . self::KIND_HEADING['key_concept'];
            $md[] = '';
            $current = null;
            foreach ($byKind['key_concept'] as $c) {
                $cid = (int) $c['chapter_id'];
                if ($cid && $cid !== $current) {
                    $current = $cid;
                    $md[] = '### ' . ($chapterTitle[$cid] ?? 'Chapter');
                    $md[] = '';
                }
                $md[] = '**' . $c['title'] . '**' . self::ref($c) . ' — '
                      . trim((string) $c['body']);
                $md[] = '';
            }
        }

        // Chapter core ideas are their own spine, and they read as a summary on
        // their own when a book has few named concepts.
        $withIdeas = array_filter($chapters, fn($c) => trim((string) $c['core_idea']) !== '');
        if ($withIdeas) {
            $md[] = '## Chapter by chapter';
            $md[] = '';
            foreach ($withIdeas as $ch) {
                $label = trim(($ch['number_label'] ? $ch['number_label'] . '. ' : '') . $ch['title']);
                $md[] = '### ' . $label
                      . ($ch['pdf_page_start'] ? '  — p' . (int) $ch['pdf_page_start'] : '');
                $md[] = '';
                $md[] = trim((string) $ch['core_idea']);
                if (trim((string) $ch['summary']) !== '') {
                    $md[] = '';
                    $md[] = trim((string) $ch['summary']);
                }
                $md[] = '';
            }
        }

        $verbatim = 0;
        if ($artifacts) {
            $md[] = '## Tools and frameworks in the book';
            $md[] = '';
            foreach ($artifacts as $a) {
                $items = Database::all(
                    'SELECT text FROM af_artifact_items WHERE artifact_id = ? ORDER BY ord LIMIT 40',
                    [(int) $a['id']]);
                $isVerbatim = (int) $a['verbatim'] === 1;
                $verbatim += $isVerbatim ? 1 : 0;

                $md[] = '### ' . $a['title']
                      . '  `' . strtolower(str_replace('_', ' ', $a['type'])) . '`'
                      . ($isVerbatim ? '  **[verbatim — do not republish]**' : '');
                $md[] = '';
                if (trim((string) $a['intro']) !== '') {
                    $md[] = trim((string) $a['intro']);
                    $md[] = '';
                }
                foreach ($items as $it) {
                    $md[] = '- ' . trim((string) $it['text']);
                }
                $md[] = '';
            }
        }

        if (!empty($byKind['caveat'])) {
            $md[] = '## ' . self::KIND_HEADING['caveat'];
            $md[] = '';
            foreach ($byKind['caveat'] as $c) {
                $md[] = '- **' . $c['title'] . '** — ' . trim((string) $c['body']);
            }
            $md[] = '';
        }

        $markdown = preg_replace("/\n{3,}/", "\n\n", implode("\n", $md)) . "\n";
        $words = str_word_count(strip_tags($markdown));

        return [
            'book'     => $book,
            'markdown' => $markdown,
            'words'    => $words,
            'minutes'  => (int) ceil($words / self::WORDS_PER_MINUTE),
            'counts'   => $counts,
            'chapters' => count($withIdeas),
            'artifacts' => count($artifacts),
            'verbatim' => $verbatim,
            // Identifies the material the script was built from, so a stale
            // script can be spotted after a re-extraction.
            'sha'      => hash('sha256', $markdown),
        ];
    }

    /** "  — pp 12-18" / "  — ch 2, 5" — whichever anchor the concept carries. */
    private static function ref(array $c): string
    {
        if (!empty($c['page_ref'])) {
            $p = (string) $c['page_ref'];
            return '  — ' . (ctype_digit($p[0]) ? 'pp ' : '') . $p;
        }
        return !empty($c['chapters_ref']) ? '  — ch ' . $c['chapters_ref'] : '';
    }

    public static function filename(array $book, string $ext): string
    {
        return preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $book['slug'])) . '.' . $ext;
    }
}
