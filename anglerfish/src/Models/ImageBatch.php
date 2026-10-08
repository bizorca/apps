<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;
use Anglerfish\Services\GeminiBatch;

/**
 * One ordered run of batch images, and the chapter rows waiting on it.
 *
 * A batch is planned, not fired: the chapters are chosen here, an art-direction
 * job is queued for each, and the submit job waits until every direction is in
 * before it builds one request. That ordering is what makes the whole thing
 * survive a host that kills anything slow — no step here runs longer than a
 * single Claude call, and the 24-hour wait belongs to Gemini rather than to a
 * lease.
 *
 * Chapters are the unit because the summaries already exist. Pass 2 wrote a
 * core idea and a 150–300 word summary for 494 chapters, which is exactly the
 * shape `ArtDirection` wants: a title and a compact argument. The full chapter
 * text — what `Composition::subject('chapter', …)` returns — is the wrong input
 * here, being pages of OCR where the summary is the argument already distilled.
 */
final class ImageBatch
{
    /**
     * The floor on how much material an image can be built from.
     *
     * 3,994 of the library's concepts are one-line definitions averaging 104
     * characters — "Velvet Ropes — barriers that make the client audition for
     * you". An art director given one sentence invents the other nine tenths,
     * and what comes back is a generic picture that cost $0.067. The substantial
     * concepts (theses, big ideas, caveats) run 400–1,000 characters and carry
     * an actual argument.
     */
    public const MIN_BODY = 200;

    /** Chapters with something to illustrate, in reading order. */
    public static function chapters(int $bookId, bool $redo = false): array
    {
        $sql = "SELECT c.id, c.ord, c.number_label, c.title, c.core_idea, c.summary
                  FROM af_chapters c
                 WHERE c.book_id = ?
                   AND c.status = 'extracted'
                   AND c.summary IS NOT NULL AND c.summary <> ''";
        if (!$redo) {
            $sql .= ' AND ' . self::unillustrated('chapter', 'c.id');
        }
        return Database::all($sql . ' ORDER BY c.ord, c.id', [$bookId]);
    }

    /**
     * Concepts worth illustrating, for one book or the whole library.
     *
     * `kind` is the filter that matters. A thesis, a big idea and a caveat argue
     * something; a key_concept is a glossary entry, and the library holds 3,994
     * of those against 1,261 of the rest.
     *
     * @param string[] $kinds
     */
    public static function concepts(?int $bookId = null,
                                    array $kinds = ['thesis', 'big_idea', 'caveat'],
                                    bool $redo = false): array
    {
        $args = [];
        $sql = "SELECT c.id, c.ord, c.kind, c.title, c.body, c.book_id,
                       b.title AS book_title, b.slug AS book_slug
                  FROM af_concepts c JOIN af_books b ON b.id = c.book_id
                 WHERE b.scope = 'press'
                   AND c.body IS NOT NULL
                   AND CHAR_LENGTH(c.body) >= " . self::MIN_BODY;
        if ($kinds) {
            $sql .= ' AND c.kind IN (' . implode(',', array_fill(0, count($kinds), '?')) . ')';
            $args = $kinds;
        }
        if ($bookId) {
            $sql .= ' AND c.book_id = ?';
            $args[] = $bookId;
        }
        if (!$redo) {
            $sql .= ' AND ' . self::unillustrated('concept', 'c.id');
        }
        return Database::all($sql . ' ORDER BY c.book_id, c.ord, c.id', $args);
    }

    /**
     * Every chapter in the library still missing an image, across all books.
     *
     * Press scope only. Reference-scope books — the Instant Pot manuals, the
     * Robotech novels, the Quileute primer — are searchable but never reach the
     * builder, and illustrating them would be spending image money on material
     * that has no output to appear in.
     */
    public static function allChapters(bool $redo = false): array
    {
        $sql = "SELECT c.id, c.ord, c.number_label, c.title, c.core_idea, c.summary,
                       c.book_id, b.title AS book_title, b.slug AS book_slug
                  FROM af_chapters c JOIN af_books b ON b.id = c.book_id
                 WHERE b.scope = 'press'
                   AND c.status = 'extracted'
                   AND c.summary IS NOT NULL AND c.summary <> ''";
        if (!$redo) {
            $sql .= ' AND ' . self::unillustrated('chapter', 'c.id');
        }
        return Database::all($sql . ' ORDER BY c.book_id, c.ord, c.id');
    }

    /**
     * Already illustrated, or ordered and not yet answered.
     *
     * Re-running a planner must never pay for the same subject twice, and this
     * is the clause that guarantees it. `rejected` is deliberately absent: a
     * rejected image is one somebody threw away, so its subject is eligible
     * again.
     */
    private static function unillustrated(string $type, string $idCol): string
    {
        // The inner alias is `ax`, not `a`, and that is load-bearing. Callers
        // that select FROM af_assets AS a collided with it, so the correlation
        // `a.subject_id = a.subject_id` compared the inner row to itself, was
        // always true, and the NOT EXISTS excluded every row in the table. The
        // query returned nothing and looked like an empty backlog rather than a
        // broken filter.
        return "NOT EXISTS (
                  SELECT 1 FROM af_assets ax
                   WHERE ax.subject_type = '$type' AND ax.subject_id = $idCol
                     AND ax.kind = 'infographic'
                     AND ax.status IN ('pending','rendering','ready'))";
    }

    /**
     * Create the batch rows and queue the work.
     *
     * Chunked at `GeminiBatch::CHUNK` because the response, not the request, is
     * what gets big: 25 images of base64 at 2K is already ~50MB of JSON to
     * decode in one poll.
     *
     * @param array<int,array<string,mixed>> $chapters
     * @return int[] batch ids
     */
    public static function plan(int $bookId, array $chapters, string $model,
                                string $aspect = '16:9', string $size = '2K'): array
    {
        $ids = [];
        foreach (array_chunk($chapters, GeminiBatch::CHUNK) as $chunk) {
            $batchId = Database::insert(
                'INSERT INTO af_image_batches (model, subject_type, subject_id, status,
                                            aspect_ratio, image_size, request_count,
                                            cost_usd)
                 VALUES (?, "book", ?, "directing", ?, ?, ?, ?)',
                [$model, $bookId, $aspect, $size, count($chunk),
                 round(count($chunk) * GeminiBatch::PRICE_PER_IMAGE, 4)]
            );

            foreach ($chunk as $ch) {
                // max_attempts is raised because the cron window defers rather
                // than fails: a 14-second direction that does not fit in the
                // tail of a tick comes back as an attempt, and three of those
                // would kill a chapter that was never actually broken.
                Job::enqueue('art_direct', 'chapter', (int) $ch['id'],
                    ['batch_id'     => $batchId,
                     'subject_type' => 'chapter',
                     'subject_id'   => (int) $ch['id']], 4, 8);
            }

            // Waits for its own directions; see ServerJobs::imageBatchSubmit().
            Job::enqueueIn(45, 'image_batch_submit', 'book', $bookId,
                ['batch_id' => $batchId], 4, 60);

            $ids[] = $batchId;
        }
        return $ids;
    }

    /**
     * Images whose art direction survived but whose draw did not.
     *
     * Worth separating from a fresh order because the expensive, and currently
     * rationed, half of the work is already done: the prompt is snapshotted on
     * the rejected asset. Re-running the planner would spend an Anthropic call
     * to regenerate a direction that is sitting right there.
     *
     * Only rows whose subject has since acquired nothing — a chapter that was
     * redrawn successfully must not be ordered a third time.
     */
    public static function retryable(): array
    {
        return Database::all(
            "SELECT a.id, a.subject_type, a.subject_id, a.batch_key, a.model,
                    a.prompt_snapshot, a.spec_json, c.book_id, b.title AS book_title
               FROM af_assets a
               JOIN af_chapters c ON c.id = a.subject_id AND a.subject_type = 'chapter'
               JOIN af_books b ON b.id = c.book_id
              WHERE a.kind = 'infographic' AND a.status = 'rejected'
                AND a.prompt_snapshot IS NOT NULL AND a.prompt_snapshot <> ''
                AND " . self::unillustrated('chapter', 'a.subject_id') . "
              ORDER BY c.book_id, c.ord, c.id");
    }

    /**
     * Order images from prompts that already exist — no art direction.
     *
     * The submit job fires on its first tick rather than waiting, because
     * `pendingDirections()` is zero from the start: there are no direction jobs
     * to wait for.
     *
     * @param array<int,array<string,mixed>> $assets rows from retryable()
     * @return int[] batch ids
     */
    public static function planFromPrompts(int $bookId, array $assets, string $model,
                                           string $aspect = '16:9',
                                           string $size = '2K'): array
    {
        $ids = [];
        foreach (array_chunk($assets, GeminiBatch::CHUNK) as $chunk) {
            $batchId = Database::insert(
                'INSERT INTO af_image_batches (model, subject_type, subject_id, status,
                                            aspect_ratio, image_size, request_count,
                                            cost_usd)
                 VALUES (?, "book", ?, "submitting", ?, ?, ?, ?)',
                [$model, $bookId, $aspect, $size, count($chunk),
                 round(count($chunk) * GeminiBatch::PRICE_PER_IMAGE, 4)]
            );

            foreach ($chunk as $a) {
                Database::insert(
                    'INSERT INTO af_assets (subject_type, subject_id, kind, target, route,
                                         status, batch_id, batch_key, prompt_snapshot,
                                         spec_json, model, version)
                     VALUES (?, ?, "infographic", "infographic", "generate", "pending",
                             ?, ?, ?, ?, ?,
                             1 + COALESCE((SELECT v FROM (SELECT MAX(version) v
                                FROM af_assets WHERE subject_type = ? AND subject_id = ?
                                  AND kind = "infographic") t), 0))',
                    [$a['subject_type'], (int) $a['subject_id'], $batchId,
                     $a['batch_key'], $a['prompt_snapshot'], $a['spec_json'],
                     $model, $a['subject_type'], (int) $a['subject_id']]);
            }

            Job::enqueue('image_batch_submit', 'book', $bookId,
                ['batch_id' => $batchId], 3, 60);
            $ids[] = $batchId;
        }
        return $ids;
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM af_image_batches WHERE id = ?', [$id]);
    }

    public static function forBook(int $bookId): array
    {
        return Database::all(
            'SELECT * FROM af_image_batches WHERE subject_type = "book" AND subject_id = ?
              ORDER BY id DESC', [$bookId]);
    }

    /** Directions still outstanding for a batch — queued, leased or running. */
    public static function pendingDirections(int $batchId): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM af_jobs
              WHERE type = 'art_direct' AND status IN ('queued','leased','running')
                AND CAST(JSON_EXTRACT(payload, '$.batch_id') AS UNSIGNED) = ?", [$batchId]);
    }

    /** Asset rows this batch is waiting to fill. */
    public static function assets(int $batchId): array
    {
        return Database::all(
            'SELECT id, subject_id, batch_key, prompt_snapshot, status
               FROM af_assets WHERE batch_id = ? ORDER BY id', [$batchId]);
    }

    public static function setStatus(int $id, string $status, array $extra = []): void
    {
        $sets = ['status = ?'];
        $args = [$status];
        foreach ($extra as $col => $val) {
            $sets[] = "$col = ?";
            $args[] = $val;
        }
        $args[] = $id;
        Database::run('UPDATE af_image_batches SET ' . implode(', ', $sets)
            . ' WHERE id = ?', $args);
    }

    /**
     * The contact sheet: every extracted chapter of a book beside its newest
     * infographic, whatever state that is in.
     *
     * A LEFT JOIN on the highest asset id rather than on `status='ready'`, so a
     * chapter whose image is still pending shows as pending instead of
     * vanishing from the sheet and looking like it was never ordered.
     */
    public static function sheet(int $bookId): array
    {
        return Database::all(
            "SELECT c.id AS chapter_id, c.ord, c.number_label, c.title, c.core_idea,
                    a.id AS asset_id, a.status AS asset_status, a.web_path, a.version,
                    a.batch_id, a.cost_usd, a.created_at AS asset_created_at,
                    a.prompt_snapshot
               FROM af_chapters c
               LEFT JOIN af_assets a
                 ON a.id = (SELECT a2.id FROM af_assets a2
                             WHERE a2.subject_type = 'chapter' AND a2.subject_id = c.id
                               AND a2.kind = 'infographic'
                             ORDER BY a2.id DESC LIMIT 1)
              WHERE c.book_id = ? AND c.status = 'extracted'
                AND c.summary IS NOT NULL AND c.summary <> ''
              ORDER BY c.ord, c.id", [$bookId]);
    }

    /** Totals for the sheet header. */
    public static function tally(int $bookId): array
    {
        $rows = self::sheet($bookId);
        $t = ['chapters' => count($rows), 'ready' => 0, 'pending' => 0,
              'rejected' => 0, 'none' => 0, 'spent' => 0.0];
        foreach ($rows as $r) {
            $t['spent'] += (float) ($r['cost_usd'] ?? 0);
            $status = $r['asset_status'] ?? null;
            $t[match ($status) {
                'ready' => 'ready',
                'pending', 'rendering' => 'pending',
                'rejected', 'superseded' => 'rejected',
                default => 'none',
            }]++;
        }
        return $t;
    }
}
