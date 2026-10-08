<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Database;
use Anglerfish\Core\View;
use Anglerfish\Models\Job;

/**
 * Library — the builder's navigation (SPEC §9.1). Cover grid first, then a
 * book detail page that lists everything extraction produced.
 */
final class LibraryController
{
    public function index(): void
    {
        // Coalesce first, then validate. Testing the coalesced value but
        // assigning the raw one leaves $scope null when ?scope= is absent,
        // which the match() below cannot handle.
        $scope = (string) ($_GET['scope'] ?? 'press');
        if (!in_array($scope, ['press', 'reference', 'intake', 'all'], true)) {
            $scope = 'press';
        }
        $q = trim((string) ($_GET['q'] ?? ''));

        $where = match ($scope) {
            'press'     => "b.scope='press' AND b.scope_confirmed=1",
            'reference' => "b.scope='reference' AND b.scope_confirmed=1",
            'intake'    => 'b.scope_confirmed=0',
            'all'       => '1',
        };
        $params = [];
        if ($q !== '') {
            $where .= ' AND (b.title LIKE ? OR b.slug LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        $books = Database::all("
            SELECT b.*,
                   (SELECT COUNT(*) FROM af_pages p     WHERE p.book_id=b.id) AS page_rows,
                   (SELECT COUNT(*) FROM af_artifacts a WHERE a.book_id=b.id) AS artifacts,
                   (SELECT COUNT(*) FROM af_concepts c  WHERE c.book_id=b.id) AS concepts,
                   (SELECT COUNT(*) FROM af_assets s    WHERE s.subject_type='book'
                        AND s.subject_id=b.id AND s.status='ready')        AS assets,
                   (SELECT GROUP_CONCAT(a2.name SEPARATOR ', ')
                      FROM af_authors a2 JOIN af_book_authors ba ON ba.author_id=a2.id
                     WHERE ba.book_id=b.id)                                AS authors
              FROM af_books b
             WHERE $where
             ORDER BY b.scope_confirmed ASC, b.title ASC", $params);

        $tabs = Database::one("
            SELECT SUM(scope='press' AND scope_confirmed=1)     AS press,
                   SUM(scope_confirmed=0)                       AS intake,
                   SUM(scope='reference' AND scope_confirmed=1) AS reference,
                   COUNT(*)                                     AS all_books
              FROM af_books") ?: [];

        View::render('library', compact('books', 'scope', 'tabs', 'q'), 'Library');
    }

    public function show(string $slug): void
    {
        $book = Database::one('SELECT * FROM af_books WHERE slug = ?', [$slug]);
        if (!$book) {
            http_response_code(404);
            View::render('error', ['code' => 404, 'message' => 'No such book.'], 'Not found');
            return;
        }
        $id = (int) $book['id'];

        $book['authors'] = Database::value(
            'SELECT GROUP_CONCAT(a.name SEPARATOR ", ") FROM af_authors a
              JOIN af_book_authors ba ON ba.author_id=a.id WHERE ba.book_id=?', [$id]);
        $book['terms'] = Database::all(
            'SELECT t.name FROM af_terms t JOIN af_book_terms bt ON bt.term_id=t.id
              WHERE bt.book_id=?', [$id]);

        $chapters = Database::all(
            'SELECT * FROM af_chapters WHERE book_id=? ORDER BY ord', [$id]);

        $concepts = Database::all(
            "SELECT * FROM af_concepts WHERE book_id=?
              ORDER BY FIELD(kind,'thesis','big_idea','key_concept','caveat'), ord", [$id]);

        $artifacts = Database::all(
            'SELECT a.*, (SELECT COUNT(*) FROM af_artifact_items i WHERE i.artifact_id=a.id) AS items
               FROM af_artifacts a WHERE a.book_id=? ORDER BY a.ord', [$id]);

        $stats = Database::one(
            'SELECT COUNT(*) AS pages, SUM(ocr) AS ocr_pages, ROUND(AVG(char_count)) AS avg_chars
               FROM af_pages WHERE book_id=?', [$id]) ?: [];

        $passes = Database::all(
            'SELECT `pass`, rows_imported, imported_at FROM af_extraction_imports
              WHERE book_id=? ORDER BY `pass`', [$id]);

        // Existing marks, so the buttons render already lit rather than blank.
        $book['marks'] = self::marksFor('book', [$id])[$id] ?? [];
        $cMarks = self::marksFor('concept', array_column($concepts, 'id'));
        $aMarks = self::marksFor('artifact', array_column($artifacts, 'id'));
        foreach ($concepts as &$c) {
            $c['marks'] = $cMarks[(int) $c['id']] ?? [];
        }
        unset($c);
        foreach ($artifacts as &$a) {
            $a['marks'] = $aMarks[(int) $a['id']] ?? [];
        }
        unset($a);

        // Typed links, both directions, resolved to the far concept and its
        // book. A contrast is worth as much read backwards as forwards, so an
        // inbound edge is shown the same as an outbound one.
        $links = [];
        if ($ids = array_column($concepts, 'id')) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach (Database::all(
                "SELECT l.relation, l.note,
                        IF(l.from_concept IN ($in), l.from_concept, l.to_concept) AS near,
                        c.id, c.title, b.title AS book_title, b.slug AS book_slug
                   FROM af_concept_links l
                   JOIN af_concepts c ON c.id = IF(l.from_concept IN ($in), l.to_concept, l.from_concept)
                   JOIN af_books b ON b.id = c.book_id
                  WHERE l.from_concept IN ($in) OR l.to_concept IN ($in)
                  ORDER BY FIELD(l.relation,'contrasts','expands','supports','related'), c.title
                  LIMIT 400",
                array_merge($ids, $ids, $ids, $ids)
            ) as $l) {
                $links[(int) $l['near']][] = $l;
            }
        }

        View::render('book', compact('book', 'chapters', 'concepts', 'artifacts',
                                     'stats', 'passes', 'links'),
            $book['title']);
    }

    /** Rendered assets, served from outside the web root like covers. */
    public function asset(string $id): void
    {
        $a = Database::one('SELECT web_path, format FROM af_assets WHERE id=?', [(int) $id]);
        $file = $a ? af_file((string) $a['web_path']) : null;

        if (!$file || !is_file($file)) {
            http_response_code(404);
            exit('No such asset.');
        }
        // ?w=substack serves the 1456px derivative, and ?download=1 sends it as
        // an attachment — Substack needs a file on disk, not a browser tab.
        if (isset($_GET['w']) && $a['format'] !== 'pdf') {
            try {
                $file = \Anglerfish\Services\Resize::forWeb($file);
            } catch (\Throwable $e) {
                error_log('asset resize failed: ' . $e->getMessage());   // serve the original
            }
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        });
        if (!empty($_GET['download'])) {
            header('Content-Disposition: attachment; filename="'
                . basename($file) . '"');
        }
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, max-age=86400');
        readfile($file);
    }

    /** POST /library/{slug}/chapters — confirm or edit the proposed map. */
    public function saveChapters(string $slug): void
    {
        $book = Database::one('SELECT id FROM af_books WHERE slug=?', [$slug]);
        if (!$book) {
            redirect('/library');
        }
        $id = (int) $book['id'];

        if (($_POST['action'] ?? '') === 'redetect') {
            Job::enqueueUnique('detect_chapters', 'book', $id, [
                'book_id' => $id,
                'printed_page_offset' => Database::value(
                    'SELECT printed_page_offset FROM af_books WHERE id=?', [$id]),
            ], 2);
            Database::run("DELETE FROM af_chapters WHERE book_id=?", [$id]);
            flash('ok', 'Re-detection queued. Run the worker.');
            redirect('/library/' . rawurlencode($slug));
        }

        $rows = $_POST['ch'] ?? [];
        $kept = 0;
        foreach ($rows as $chapterId => $f) {
            $chapterId = (int) $chapterId;
            if (!empty($f['delete'])) {
                Database::run('DELETE FROM af_chapters WHERE id=? AND book_id=?', [$chapterId, $id]);
                continue;
            }
            Database::run(
                "UPDATE af_chapters SET number_label=NULLIF(?,''), title=?,
                                     pdf_page_start=NULLIF(?,0), pdf_page_end=NULLIF(?,0),
                                     status='confirmed'
                  WHERE id=? AND book_id=?",
                [trim((string) ($f['number_label'] ?? '')),
                 mb_substr(trim((string) ($f['title'] ?? 'Untitled')), 0, 500),
                 (int) ($f['pdf_page_start'] ?? 0), (int) ($f['pdf_page_end'] ?? 0),
                 $chapterId, $id]
            );
            $kept++;
        }

        // Deliberately does not promise that anything will happen. Confirming
        // chapters enqueues nothing, and it never gated extraction either —
        // pass 2 takes its boundaries from the Pass 1 COVERS list, not this
        // table. The old wording implied both.
        flash('ok', "$kept chapter(s) confirmed. Extraction is a separate, manual "
            . "step in Claude Code — see extractions/README.md.");
        redirect('/library/' . rawurlencode($slug));
    }

    /** @return array<int,array<string,bool>> subject_id => [mark => true] */
    private static function marksFor(string $type, array $ids): array
    {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) {
            return [];
        }
        $in = implode(',', $ids);
        $out = [];
        foreach (Database::all(
            "SELECT subject_id, mark FROM af_triage
              WHERE subject_type=? AND subject_id IN ($in)", [$type]) as $r) {
            $out[(int) $r['subject_id']][$r['mark']] = true;
        }
        return $out;
    }

    /** Covers live outside the web root, so they are served rather than linked. */
    public function cover(string $id): void
    {
        $path = Database::value('SELECT cover_path FROM af_books WHERE id=?', [(int) $id]);
        $file = $path ? af_file((string) $path) : null;

        if (!$file || !is_file($file)) {
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=3600');
            // Placeholder in the brand palette rather than a broken image.
            echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300">'
               . '<rect width="200" height="300" fill="#1B3A5C"/>'
               . '<circle cx="100" cy="150" r="26" fill="#D4A017" opacity="0.35"/></svg>';
            return;
        }

        $type = str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg';
        header('Content-Type: ' . $type);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, max-age=86400');
        readfile($file);
    }

    /** POST /library/scope — one-key confirm or flip from the intake queue. */
    public function setScope(): void
    {
        $id    = (int) ($_POST['id'] ?? 0);
        $scope = $_POST['scope'] ?? '';

        if ($id && in_array($scope, ['press', 'reference'], true)) {
            Database::run('UPDATE af_books SET scope=?, scope_confirmed=1 WHERE id=?', [$scope, $id]);
            flash('ok', "Scope set to $scope.");
        }
        redirect('/library?scope=' . urlencode((string) ($_POST['back'] ?? 'intake')));
    }
}
