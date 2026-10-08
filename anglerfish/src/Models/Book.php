<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

final class Book
{
    /**
     * Register PDFs the worker found in the watched folder (SPEC §7.1).
     * Dedup is on source_sha256, which catches the byte-identical
     * "Wheel of Time Decay" and "Wheel of Time Decay (1)" case.
     *
     * @param array{books?:array<int,array<string,mixed>>} $data
     */
    public static function applyIntake(array $data): array
    {
        $registered = $skipped = 0;
        $queued = [];

        foreach ($data['books'] ?? [] as $b) {
            $sha = (string) ($b['source_sha256'] ?? '');
            if ($sha === '') {
                continue;
            }

            $existingId = Database::value(
                'SELECT id FROM af_books WHERE source_sha256 = ?', [$sha]);

            if ($existingId) {
                $skipped++;
                // Already registered but never given text, and an OCR'd .txt is
                // sitting right there — queue the cheap import rather than
                // leaving it for a multi-hour re-OCR.
                $hasPages = (int) Database::value(
                    'SELECT COUNT(*) FROM af_pages WHERE book_id = ?', [(int) $existingId]);
                if ($hasPages === 0 && !empty($b['txt_path'])) {
                    $jobId = Job::enqueueUnique('import_existing', 'book', (int) $existingId, [
                        'book_id'     => (int) $existingId,
                        'txt_path'    => $b['txt_path'],
                        'source_path' => (string) ($b['source_path'] ?? ''),
                    ], 2);
                    if ($jobId) {
                        $queued[] = $jobId;
                    }
                }
                continue;
            }

            $slug = self::uniqueSlug((string) ($b['slug'] ?? 'book'));

            $scope = (string) ($b['scope'] ?? 'reference');
            if (!in_array($scope, ['press', 'reference'], true)) {
                $scope = 'reference';
            }

            $id = Database::insert(
                'INSERT INTO af_books (slug, title, source_path, source_sha256, scope,
                                    scope_confirmed, pdf_page_count, ingest_status)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?)',
                [
                    $slug,
                    (string) ($b['title'] ?? $slug),
                    (string) ($b['source_path'] ?? ''),
                    $sha,
                    // Default to reference. Nothing enters the content engine
                    // without being waved through (SPEC §7.2).
                    // Coalesce first, then validate: checking the coalesced
                    // value while assigning the raw one yields null when the
                    // key is absent. Same shape as the /library 500.
                    $scope,
                    isset($b['pdf_page_count']) ? (int) $b['pdf_page_count'] : null,
                    'pending',
                ]
            );

            $registered++;
            $jobId = empty($b['txt_path'])
                ? Job::enqueueUnique('ingest', 'book', $id, [
                    'book_id'     => $id,
                    'slug'        => $slug,
                    'source_path' => (string) ($b['source_path'] ?? ''),
                ])
                : Job::enqueueUnique('import_existing', 'book', $id, [
                    'book_id'     => $id,
                    'txt_path'    => $b['txt_path'],
                    'source_path' => (string) ($b['source_path'] ?? ''),
                ], 2);
            if ($jobId) {
                $queued[] = $jobId;
            }
        }

        return ['registered' => $registered, 'skipped' => $skipped, 'ingest_queued' => count($queued)];
    }

    /**
     * Store the text of an ingested book: page rows, counts, cover, status.
     *
     * @param array{pages?:array<int,array<string,mixed>>} $data
     */
    public static function applyIngest(int $bookId, array $data): array
    {
        $pdo = Database::pdo();

        // Idempotent: a re-run replaces this book's pages rather than duplicating.
        Database::run('DELETE FROM af_pages WHERE book_id = ?', [$bookId]);

        $st = $pdo->prepare(
            'INSERT INTO af_pages (book_id, pdf_page, printed_page, text, char_count, ocr, ocr_confidence)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $pdo->beginTransaction();
        $n = $ocrPages = 0;
        try {
            foreach ($data['pages'] ?? [] as $p) {
                $text = (string) ($p['text'] ?? '');
                $st->execute([
                    $bookId,
                    (int) ($p['pdf_page'] ?? 0),
                    isset($p['printed_page']) ? (int) $p['printed_page'] : null,
                    $text,
                    mb_strlen($text),
                    (int) (bool) ($p['ocr'] ?? false),
                    isset($p['ocr_confidence']) ? (float) $p['ocr_confidence'] : null,
                ]);
                $n++;
                $ocrPages += (int) (bool) ($p['ocr'] ?? false);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Derived fields normally overwrite rather than COALESCE, or a
        // re-ingest can never clear a stale note and you end up with an offset
        // sitting beside a note claiming no page numbers were found.
        //
        // The exception is a book whose pass 1 profile has been imported. That
        // offset was read off folios by a person, it records drift the
        // heuristic cannot express ("-15 at the front, -11 at the back, four
        // pages missing"), and the heuristic collapses it to a single number
        // or gives up entirely. Re-OCR'ing a book to improve its text must not
        // silently destroy the better answer.
        $held = (int) Database::value(
            "SELECT COUNT(*) FROM af_extraction_imports WHERE book_id=? AND `pass`='profile'",
            [$bookId]);

        Database::run(
            "UPDATE af_books
                SET pdf_page_count      = COALESCE(?, pdf_page_count),
                    has_text_layer      = ?,
                    printed_page_offset = IF(?, printed_page_offset, ?),
                    pagination_note     = IF(?, pagination_note, ?),
                    cover_path          = COALESCE(?, cover_path),
                    ingest_status       = 'clean',
                    ingested_at         = NOW()
              WHERE id = ?",
            [
                isset($data['pdf_page_count']) ? (int) $data['pdf_page_count'] : null,
                isset($data['has_text_layer']) ? (int) (bool) $data['has_text_layer'] : null,
                $held,
                isset($data['printed_page_offset']) ? (int) $data['printed_page_offset'] : null,
                $held,
                $data['pagination_note'] ?? null,
                $data['cover_path'] ?? null,
                $bookId,
            ]
        );

        return ['pages' => $n, 'ocr_pages' => $ocrPages];
    }

    /**
     * Store proposed chapter boundaries (SPEC §7.8). Nothing is authoritative
     * until the operator confirms: every later extraction pass inherits these
     * ranges, so a bad map would poison the whole book.
     */
    public static function applyChapters(int $bookId, array $data): array
    {
        $confirmed = (int) Database::value(
            "SELECT COUNT(*) FROM af_chapters WHERE book_id=? AND status<>'proposed'", [$bookId]);
        if ($confirmed > 0) {
            return ['skipped' => true, 'reason' => "$confirmed confirmed chapters already exist"];
        }

        Database::run("DELETE FROM af_chapters WHERE book_id=? AND status='proposed'", [$bookId]);

        $n = 0;
        foreach ($data['chapters'] ?? [] as $c) {
            Database::run(
                "INSERT INTO af_chapters (book_id, ord, number_label, title,
                                       pdf_page_start, pdf_page_end, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'proposed')",
                [$bookId, (int) ($c['ord'] ?? ++$n), $c['number_label'] ?? null,
                 mb_substr((string) ($c['title'] ?? 'Untitled'), 0, 500),
                 $c['pdf_page_start'] ?? null, $c['pdf_page_end'] ?? null]
            );
            $n++;
        }
        return ['proposed' => $n, 'method' => $data['method'] ?? 'unknown'];
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $base), '-')) ?: 'book';
        $slug = mb_substr($slug, 0, 150);
        $try = $slug;
        $i = 2;
        while (Database::value('SELECT id FROM af_books WHERE slug = ?', [$try])) {
            $try = $slug . '-' . $i++;
        }
        return $try;
    }
}
