<?php
/**
 * Import the Big Ideas syntheses as books.
 *
 * A Big Ideas file is a book in every way that matters here: it has a title, an
 * ordered set of themed sections, and named sources. Mapping it onto the
 * existing tables rather than inventing parallel ones means the library view,
 * the chapter list, the reader, extraction, and "Compose from this" all work on
 * day one with no new code.
 *
 *   section -> page      (the text, and its FULLTEXT index)
 *           -> chapter   (navigation; confirmed, since these are the author's
 *                         own headings rather than anything we detected)
 *           -> concept   (kind=big_idea — the composable unit)
 *
 * Concepts are what make this reach the composer: Composition::subject()
 * already accepts a concept and already joins books for book_title, so
 * requiresAngle() fires automatically. That is the correct behaviour — this is
 * other people's material and it needs Jassen's own angle on top.
 *
 * Input is the JSON from worker/parse_big_ideas.py. Re-running is safe: a book
 * is keyed on the file's sha256, and its children are replaced wholesale.
 *
 *   php import_big_ideas.php bigideas.json [--dry-run]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
define('AF_ROOT', $root);
require $root . '/includes/boot.php';

$jsonPath = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if (!is_file($jsonPath)) {
    fwrite(STDERR, "usage: php import_big_ideas.php <parsed.json> [--dry-run]\n");
    exit(64);
}

$data = json_decode((string) file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($data) || !$data) {
    fwrite(STDERR, "No records in $jsonPath\n");
    exit(1);
}

$pdo = \Anglerfish\Core\Database::pdo();

// One corpus. The Dan Kennedy Brain was folded into the Marketing Brain (text
// 2026-08-09, folder removed 2026-10-03) and parse_big_ideas.py only emits
// 'mb'. A 'dk' record is still accepted, as it is everywhere else in the app,
// and resolves to 'mb' — so it can neither label a book with a brain that no
// longer exists nor recreate the deleted big-ideas-dk-* books.
$BRAIN = ['mb' => 'Marketing Brain'];
$books = $sections = $skipped = 0;

foreach ($data as $rec) {
    $brain = $rec['brain'] === 'dk' ? 'mb' : $rec['brain'];
    if (!isset($BRAIN[$brain])) {
        fwrite(STDERR, "  skip (unknown brain '$brain'): {$rec['topic']}\n");
        $skipped++;
        continue;
    }
    $topic = $rec['topic'];
    if (!$rec['sections']) {
        fwrite(STDERR, "  skip (no sections): $brain/$topic\n");
        $skipped++;
        continue;
    }

    $slug = 'big-ideas-' . $brain . '-' . strtolower(trim(
        preg_replace('/[^a-z0-9]+/i', '-', $topic), '-'));
    $title = 'Big Ideas — ' . $topic;
    $people = array_slice($rec['attribution'], 0, 8);
    $subtitle = $BRAIN[$brain] . ' · ' . count($rec['sections']) . ' sections'
        . ($people ? ' · ' . implode(', ', $people) : '');
    // Identity is the content, not the path, so re-synthesising a file lands as
    // an update rather than a duplicate book.
    $sha = hash('sha256', $brain . "\0" . $topic . "\0" . json_encode($rec['sections']));

    if ($dry) {
        printf("  %-42s %2d sections  %s\n", $slug, count($rec['sections']),
            mb_substr($subtitle, 0, 60));
        $books++;
        $sections += count($rec['sections']);
        continue;
    }

    $pdo->beginTransaction();
    try {
        $id = $pdo->prepare('SELECT id FROM af_books WHERE slug = ?');
        $id->execute([$slug]);
        $bookId = (int) $id->fetchColumn();

        if ($bookId) {
            $pdo->prepare(
                'UPDATE af_books SET title=?, subtitle=?, source_sha256=?, pdf_page_count=?,
                                  scope="press", scope_confirmed=1, kind="big_ideas",
                                  brain=?, has_text_layer=1, ingest_status="mapped",
                                  extract_status="complete", ingested_at=NOW()
                  WHERE id=?'
            )->execute([$title, $subtitle, $sha, count($rec['sections']), $brain, $bookId]);
            // Children are derived, so replace them rather than reconcile.
            foreach (['af_concepts', 'af_chapters', 'af_pages'] as $t) {
                $pdo->prepare("DELETE FROM $t WHERE book_id = ?")->execute([$bookId]);
            }
        } else {
            $pdo->prepare(
                'INSERT INTO af_books (slug, title, subtitle, source_path, source_sha256,
                                    scope, scope_confirmed, kind, brain, pdf_page_count,
                                    has_text_layer, ingest_status, extract_status, ingested_at)
                 VALUES (?, ?, ?, ?, ?, "press", 1, "big_ideas", ?, ?, 1, "mapped",
                         "complete", NOW())'
            )->execute([$slug, $title, $subtitle, $rec['file'], $sha, $brain,
                        count($rec['sections'])]);
            $bookId = (int) $pdo->lastInsertId();
        }

        $insPage = $pdo->prepare(
            'INSERT INTO af_pages (book_id, pdf_page, printed_page, text, char_count)
             VALUES (?, ?, ?, ?, ?)');
        $insChap = $pdo->prepare(
            'INSERT INTO af_chapters (book_id, ord, number_label, title, pdf_page_start,
                                   pdf_page_end, printed_page_start, printed_page_end,
                                   word_count, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "confirmed")');
        $insCon = $pdo->prepare(
            'INSERT INTO af_concepts (book_id, chapter_id, ord, kind, title, body, page_ref)
             VALUES (?, ?, ?, "big_idea", ?, ?, ?)');

        foreach ($rec['sections'] as $i => $s) {
            $n = $i + 1;                       // position, not the file's own numbering
            $words = str_word_count(strip_tags($s['body']));
            $insPage->execute([$bookId, $n, $n, $s['body'], mb_strlen($s['body'])]);
            $insChap->execute([$bookId, $n, (string) $n, $s['title'], $n, $n, $n, $n, $words]);
            $chapId = (int) $pdo->lastInsertId();
            $insCon->execute([$bookId, $chapId, $n, $s['title'], $s['body'], "§$n"]);
            $sections++;
        }

        $pdo->commit();
        $books++;
        printf("  ok  %-42s %2d sections\n", $slug, count($rec['sections']));
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "  FAIL $slug: {$e->getMessage()}\n");
        exit(1);
    }
}

printf("\n%s%d books, %d sections%s\n", $dry ? 'DRY RUN — would import ' : '',
    $books, $sections, $skipped ? ", $skipped skipped" : '');
