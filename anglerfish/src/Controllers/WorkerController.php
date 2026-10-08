<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Api;
use Anglerfish\Core\Database;
use Anglerfish\Models\Book;
use Anglerfish\Models\Content;
use Anglerfish\Models\Corpus;
use Anglerfish\Models\Extraction;
use Anglerfish\Models\Job;

/**
 * Worker API (SPEC §13). The local worker polls these endpoints outward over
 * HTTPS — no inbound access to the Mac is required.
 */
final class WorkerController
{
    private array $cfg;

    public function __construct()
    {
        Api::authorize();
        $this->cfg = (require dirname(__DIR__, 2) . '/config/app.php')['worker'];
    }

    /** POST /api/worker/lease  { types: [...], capacity: N } */
    public function lease(): void
    {
        $body = Api::body();
        $types = array_values(array_filter(array_map('strval', $body['types'] ?? [])));
        $capacity = max(1, min(20, (int) ($body['capacity'] ?? 1)));

        $jobs = Job::lease($types, $capacity, (int) $this->cfg['lease_seconds']);

        Api::json([
            'ok'   => true,
            'jobs' => array_map(static fn(array $j) => [
                'job_id'       => (int) $j['id'],
                'type'         => $j['type'],
                'subject_type' => $j['subject_type'],
                'subject_id'   => $j['subject_id'] !== null ? (int) $j['subject_id'] : null,
                'payload'      => $j['payload'],
                'attempt'      => (int) $j['attempts'],
                'max_attempts' => (int) $j['max_attempts'],
                'lease_token'  => $j['lease_token'],
            ], $jobs),
        ]);
    }

    /**
     * POST /api/worker/enqueue  { type, subject_type?, subject_id?, payload?, priority? }
     * Lets the worker (or a cron) kick off work without a browser session.
     * Unique on (type, subject) so repeated calls do not pile up duplicates.
     */
    public function enqueue(): void
    {
        $body = Api::body();
        $type = (string) ($body['type'] ?? '');
        if ($type === '') {
            Api::fail(400, 'type is required.');
        }

        $id = Job::enqueueUnique(
            $type,
            $body['subject_type'] ?? null,
            isset($body['subject_id']) ? (int) $body['subject_id'] : null,
            $body['payload'] ?? [],
            (int) ($body['priority'] ?? 5)
        );

        Api::json(['ok' => true, 'job_id' => $id, 'duplicate' => $id === null]);
    }

    /**
     * POST /api/worker/extractions
     * { book_slug, pass, body }  — the file Claude Code wrote (SPEC §8.5).
     * Idempotent on (book_slug, pass): a corrected re-import replaces.
     */
    public function extractions(): void
    {
        $body = Api::body();
        $slug = (string) ($body['book_slug'] ?? '');
        $pass = (string) ($body['pass'] ?? '');
        $text = (string) ($body['body'] ?? '');

        if ($slug === '' || $pass === '' || $text === '') {
            Api::fail(400, 'book_slug, pass and body are all required.');
        }

        try {
            $result = Extraction::import($slug, $pass, $text);
        } catch (\Throwable $e) {
            Api::fail(422, $e->getMessage());
        }

        Api::json([
            'ok'       => true,
            'imported' => $result,
            'replaced' => $result['replaced'] ?? 0,
        ]);
    }

    /** POST /api/worker/cover  { book_id, image_b64 } — JPEG only, never WebP. */
    public function cover(): void
    {
        $body = Api::body();
        $bookId = (int) ($body['book_id'] ?? 0);
        $blob = base64_decode((string) ($body['image_b64'] ?? ''), true);

        if (!$bookId || $blob === false || $blob === '') {
            Api::fail(400, 'book_id and image_b64 are required.');
        }
        if (strlen($blob) > 2_000_000) {
            Api::fail(413, 'Cover too large.');
        }
        // Verify it really is an image before writing it anywhere.
        $info = @getimagesizefromstring($blob);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            Api::fail(422, 'Cover must be JPEG or PNG.');
        }

        $dir = AF_STORAGE . '/covers';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ext = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        file_put_contents("$dir/book-$bookId.$ext", $blob);

        Database::run('UPDATE af_books SET cover_path=?, cover_source="auto" WHERE id=?',
            ["storage/covers/book-$bookId.$ext", $bookId]);

        Api::json(['ok' => true, 'bytes' => strlen($blob),
                   'dimensions' => "{$info[0]}x{$info[1]}"]);
    }

    /** POST /api/worker/fetch  { subject_type, subject_id } — data for a render. */
    public function fetch(): void
    {
        $body = Api::body();
        $type = (string) ($body['subject_type'] ?? '');
        $id   = (int) ($body['subject_id'] ?? 0);
        if (!$id) {
            Api::fail(400, 'subject_id is required.');
        }

        if ($type === 'artifact') {
            $a = Database::one(
                'SELECT a.*, k.number AS kit_number, k.title AS kit_title, b.title AS book_title
                   FROM af_artifacts a
                   LEFT JOIN af_kits  k ON k.id = a.kit_id
                   LEFT JOIN af_books b ON b.id = a.book_id
                  WHERE a.id = ?', [$id]);
            if (!$a) {
                Api::fail(404, 'No such artifact.');
            }
            $a['kit_label'] = $a['kit_number']
                ? sprintf('Kit %02d', (int) $a['kit_number'])
                : ($a['book_title'] ?: null);
            $a['items'] = Database::all(
                'SELECT ord, group_label, text, note FROM af_artifact_items
                  WHERE artifact_id = ? ORDER BY ord', [$id]);
            Api::json(['ok' => true, 'subject' => $a]);
        }

        if ($type === 'post') {
            // The module joins in because the weekly deliverable prints its
            // title and cohort on every page, and the renderer has no database.
            $post = Database::one(
                'SELECT p.*, m.title AS module_title, m.cohort
                   FROM af_posts p LEFT JOIN af_modules m ON m.id = p.module_id
                  WHERE p.id = ?', [$id]);
            if (!$post) {
                Api::fail(404, 'No such post.');
            }
            if (!empty($post['deliverable_spec'])) {
                $post['deliverable_spec'] = json_decode((string) $post['deliverable_spec'], true);
            }
            Api::json(['ok' => true, 'subject' => $post]);
        }

        Api::fail(400, "Unsupported subject_type '$type'.");
    }

    /**
     * POST /api/worker/asset
     * { subject_type, subject_id, target, kind, format, width, height, file_b64 }
     * Idempotent on (subject, target): a re-render supersedes rather than piles up.
     */
    public function asset(): void
    {
        $body = Api::body();
        $subjectType = (string) ($body['subject_type'] ?? '');
        $subjectId   = (int) ($body['subject_id'] ?? 0);
        $target      = (string) ($body['target'] ?? '');
        $format      = strtolower((string) ($body['format'] ?? 'png'));
        $blob        = base64_decode((string) ($body['file_b64'] ?? ''), true);

        if (!$subjectId || $subjectType === '' || $target === '' || !$blob) {
            Api::fail(400, 'subject_type, subject_id, target and file_b64 are required.');
        }
        // PNG and JPEG only. WebP is never used anywhere in this system.
        if (!in_array($format, ['png', 'jpg', 'pdf'], true)) {
            Api::fail(422, "Unsupported format '$format'. PNG, JPEG or PDF only.");
        }
        if (strlen($blob) > 12_000_000) {
            Api::fail(413, 'Asset too large.');
        }

        $dir = AF_STORAGE . '/assets';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        Database::run(
            "UPDATE af_assets SET status='superseded'
              WHERE subject_type=? AND subject_id=? AND target=? AND status='ready'",
            [$subjectType, $subjectId, $target]
        );

        $version = 1 + (int) Database::value(
            'SELECT COALESCE(MAX(version),0) FROM af_assets
              WHERE subject_type=? AND subject_id=? AND target=?',
            [$subjectType, $subjectId, $target]);

        $id = Database::insert(
            'INSERT INTO af_assets (subject_type, subject_id, kind, target, route,
                                 file_path, web_path, width, height, format,
                                 status, version, cost_usd)
             VALUES (?, ?, ?, ?, "template", ?, ?, ?, ?, ?, "ready", ?, 0)',
            [$subjectType, $subjectId, (string) ($body['kind'] ?? 'card'), $target,
             $body['file_path'] ?? null, '', (int) ($body['width'] ?? 0),
             (int) ($body['height'] ?? 0), $format, $version]
        );

        $name = "asset-$id.$format";
        file_put_contents("$dir/$name", $blob);
        Database::run('UPDATE af_assets SET web_path=? WHERE id=?',
            ["storage/assets/$name", $id]);

        Api::json(['ok' => true, 'asset_id' => $id, 'version' => $version,
                   'bytes' => strlen($blob)]);
    }

    /** POST /api/worker/content  { kind, rows[] } — batched bulk import. */
    public function content(): void
    {
        $body = Api::body();
        $kind = (string) ($body['kind'] ?? '');
        $rows = $body['rows'] ?? [];

        if ($kind === '' || !is_array($rows)) {
            Api::fail(400, 'kind and rows are required.');
        }

        try {
            $res = Content::import($kind, $rows);
        } catch (\Throwable $e) {
            Api::fail(422, $e->getMessage());
        }

        Api::json(['ok' => true] + $res);
    }

    /** POST /api/worker/jobs/{id}/heartbeat  { lease_token } */
    public function heartbeat(string $id): void
    {
        $body = Api::body();
        $ok = Job::heartbeat((int) $id, (string) ($body['lease_token'] ?? ''),
                             (int) $this->cfg['lease_seconds']);
        if (!$ok) {
            Api::fail(409, 'Lease not held. It probably expired and was reclaimed.');
        }
        Api::json(['ok' => true, 'extended_seconds' => (int) $this->cfg['lease_seconds']]);
    }

    /**
     * POST /api/worker/jobs/{id}/result
     * { lease_token, status: done|failed, error?, cost_usd?, data?, api_calls?[] }
     */
    public function result(string $id): void
    {
        $body  = Api::body();
        $jobId = (int) $id;
        $token = (string) ($body['lease_token'] ?? '');

        $job = Job::verifyLease($jobId, $token);
        if (!$job) {
            Api::fail(409, 'Lease not held. It probably expired and was reclaimed.');
        }

        foreach (($body['api_calls'] ?? []) as $call) {
            Database::run(
                'INSERT INTO af_api_calls (job_id, vendor, model, input_tokens, output_tokens,
                                        images, cost_usd, latency_ms)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$jobId, $call['vendor'] ?? 'google', $call['model'] ?? '',
                 (int) ($call['input_tokens'] ?? 0), (int) ($call['output_tokens'] ?? 0),
                 (int) ($call['images'] ?? 0), (float) ($call['cost_usd'] ?? 0),
                 isset($call['latency_ms']) ? (int) $call['latency_ms'] : null]
            );
        }

        if (($body['status'] ?? '') === 'failed') {
            Job::fail($jobId, $token, (string) ($body['error'] ?? 'unspecified'));
            Api::json(['ok' => true, 'recorded' => 'failed']);
        }

        // Apply the result before closing the job, so a write failure retries.
        $applied = $this->apply($job, $body['data'] ?? []);

        Job::complete($jobId, $token, (float) ($body['cost_usd'] ?? 0));
        Api::json(['ok' => true, 'recorded' => 'done', 'applied' => $applied]);
    }

    /** Route a finished job's payload into the right tables. */
    private function apply(array $job, array $data): array
    {
        return match ($job['type']) {
            'intake'  => Book::applyIntake($data),
            'ingest', 'import_existing'
                      => Book::applyIngest((int) $job['subject_id'], $data),
            'detect_chapters'
                      => Book::applyChapters((int) $job['subject_id'], $data),
            'search_corpus'
                      => Corpus::applyDeep((int) $data['search_id'], $data),
            // Bulk imports stream rows through /api/worker/content while running,
            // so by the time the result lands there is nothing left to apply.
            'import_kits', 'import_drafts', 'import_sources'
                      => ['note' => 'rows applied via /api/worker/content'],
            default   => ['note' => 'no handler for type ' . $job['type']],
        };
    }
}
