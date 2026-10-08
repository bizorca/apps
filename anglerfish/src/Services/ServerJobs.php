<?php

namespace Anglerfish\Services;

use Anglerfish\Core\Database;
use Anglerfish\Models\Corpus;
use Anglerfish\Models\ImageBatch;
use Anglerfish\Models\Job;

/**
 * Job types the server can run on its own.
 *
 * The split is capability, not preference. SiteGround has no `tesseract` and no
 * headless Chromium, so OCR and HTML rendering stay on the Mac. Everything that
 * is an HTTPS call, a grep, or pypdfium2 work runs here — no worker needed, no
 * waiting for a laptop to be awake.
 */
final class ServerJobs
{
    /** @return string[] */
    public static function types(): array
    {
        return ['compose', 'generate_image', 'search_corpus_server', 'audiobook_script',
                'art_direct', 'image_batch_submit', 'image_batch_poll'];
    }

    public static function run(string $type, array $payload): array
    {
        return match ($type) {
            'compose'              => self::compose($payload),
            'generate_image'       => self::generateImage($payload),
            'search_corpus_server' => self::searchCorpus($payload),
            'audiobook_script'     => self::audiobookScript($payload),
            'art_direct'           => self::artDirect($payload),
            'image_batch_submit'   => self::imageBatchSubmit($payload),
            'image_batch_poll'     => self::imageBatchPoll($payload),
            default => throw new \RuntimeException("Server cannot run '$type'."),
        };
    }

    public static function apply(string $type, array $data): void
    {
        match ($type) {
            'compose'              => self::applyComposition($data),
            'generate_image'       => self::applyImage($data),
            'search_corpus_server' => Corpus::applyDeep((int) $data['search_id'], $data),
            'audiobook_script'     => self::applyScript($data),
            'art_direct'           => self::applyDirection($data),
            'image_batch_submit'   => self::applySubmit($data),
            'image_batch_poll'     => self::applyPoll($data),
            default => null,
        };
    }

    // ── Gemini ───────────────────────────────────────────────────────────────

    private static function gemini(string $path, array $body, int $timeout = 180): array
    {
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        $key = (string) ($cfg['gemini']['key'] ?? '');
        if ($key === '') {
            throw new \RuntimeException('GEMINI_API_KEY not set on the server.');
        }

        $ch = curl_init('https://generativelanguage.googleapis.com/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $key,
                'User-Agent: AnglerfishServer/1.0',
            ],
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException("Gemini unreachable: $err");
        }
        $out = json_decode((string) $raw, true);
        if ($code >= 400) {
            $msg = $out['error']['message'] ?? substr((string) $raw, 0, 300);
            throw new \RuntimeException("Gemini $code: $msg");
        }
        return is_array($out) ? $out : [];
    }

    /**
     * Write the post.
     *
     * Claude by default: this is voice matching against a long style guide, and
     * that is the job it is best at. Gemini stays reachable by setting
     * provider=gemini on the job, so a bad Anthropic day is a config change
     * rather than a deploy.
     */
    private static function compose(array $p): array
    {
        $provider = $p['provider'] ?? (Anthropic::configured() ? 'anthropic' : 'gemini');

        if ($provider === 'anthropic') {
            // Model and effort come off the job payload, which Composition
            // fills from the publication. A per-publication model is the point:
            // Hypnologue composes on Fable 5.1 because its posts have to carry
            // an argument, a study that may contradict it, and a citation that
            // genuinely supports the sentence it is attached to. Bizorca stays
            // on Opus 5, where 1,400 words of tactical prose has never needed
            // more. Null on either falls back to the app default.
            $res = Anthropic::complete(
                Compose::system($p),
                Compose::user($p),
                $p['model'] ?? null,
                $p['effort'] ?? null,
            );
            $text = $res['text'];
            $model = $res['model'];
        } else {
            $model = $p['model'] ?? 'gemini-3.1-pro-preview';
            $text = self::outputText(self::gemini('v1beta/interactions', [
                'model' => $model,
                'input' => Compose::prompt($p),
            ]));
        }

        $parsed = Compose::parse($text);

        return [
            'composition_id' => (int) $p['composition_id'],
            'model'          => $model,
            'title'          => $parsed['title'],
            'subtitle'       => $parsed['subtitle'],
            'body'           => $parsed['body'],
        ];
    }

    private static function generateImage(array $p): array
    {
        $model = $p['model'] ?? 'gemini-3-pro-image';
        $res = self::gemini('v1beta/interactions', [
            'model' => $model,
            'input' => (string) $p['prompt'],
            'response_format' => [
                'type'         => 'image',
                'aspect_ratio' => $p['aspect_ratio'] ?? '16:9',
                'image_size'   => $p['image_size'] ?? '2K',
            ],
        ], 300);

        $b64 = self::outputImage($res);
        if ($b64 === null) {
            throw new \RuntimeException('Gemini returned no image.');
        }

        // Container sniffing, the WebP refusal and the file naming all live in
        // storeImage(), shared with the batch path so the two cannot drift.
        $stored = self::storeImage($b64, 'gen');

        return [
            'subject_type' => $p['subject_type'] ?? 'concept',
            'subject_id'   => (int) ($p['subject_id'] ?? 0),
            'target'       => $p['target'] ?? 'infographic',
            'model'        => $model,
            'web_path'     => $stored['web_path'],
            'format'       => $stored['format'],
            'bytes'        => $stored['bytes'],
            'prompt'       => (string) $p['prompt'],
        ];
    }

    /** Deep corpus search, now that the bodies can live on the server. */
    private static function searchCorpus(array $p): array
    {
        $query = trim((string) ($p['query'] ?? ''));
        if (mb_strlen($query) < 3) {
            throw new \RuntimeException('Query too short.');
        }

        $base = AF_STORAGE . '/corpus';
        $roots = [];
        // One corpus since the fold; 'dk' and 'both' both resolve to 'mb'.
        foreach (in_array($p['brain'] ?? 'both', ['both', 'dk'], true) ? ['mb'] : [$p['brain']] as $b) {
            if (is_dir("$base/$b")) {
                $roots[] = "$base/$b";
            }
        }
        if (!$roots) {
            throw new \RuntimeException('Corpus not synced to the server yet.');
        }

        // -H forces the filename prefix; without it a single-file batch omits
        // the name and the parse silently breaks.
        $cmd = 'find ' . implode(' ', array_map('escapeshellarg', $roots))
             . ' -name "*.txt" -print0 | xargs -0 -P 4 -n 40 '
             . 'grep -inH -m 3 -e ' . escapeshellarg($query) . ' 2>/dev/null';
        exec($cmd, $lines);

        $byFile = [];
        $hits = 0;
        foreach ($lines as $line) {
            $parts = explode(':', $line, 3);
            if (count($parts) < 3 || !ctype_digit($parts[1])) {
                continue;
            }
            $hits++;
            $rel = str_replace("$base/", '', $parts[0]);
            $brain = 'mb';
            $byFile[$parts[0]] ??= ['path' => $rel, 'brain' => $brain, 'snippets' => []];
            if (count($byFile[$parts[0]]['snippets']) < 3) {
                $byFile[$parts[0]]['snippets'][] = [
                    'line' => (int) $parts[1],
                    'text' => mb_substr(trim($parts[2]), 0, 300),
                ];
            }
        }

        return [
            'search_id' => (int) $p['search_id'],
            'hits'      => $hits,
            'files'     => count($byFile),
            'results'   => array_slice(array_values($byFile), 0, 60),
        ];
    }

    /**
     * Written summary -> spoken script.
     *
     * The outline is rebuilt here rather than carried in the payload: it is
     * deterministic and cheap, and rebuilding means the script is always
     * generated from the concepts as they stand right now.
     */
    private static function audiobookScript(array $p): array
    {
        $slug = (string) ($p['slug'] ?? '');
        $outline = BookSummary::outline($slug);
        if (!$outline) {
            throw new \RuntimeException("Nothing extracted for '$slug' to summarise yet.");
        }

        $minutes = max(3, min(45, (int) ($p['minutes'] ?? 12)));
        $spec = dirname(__DIR__, 2) . '/worker/prompts/audiobook-script.md';
        $system = is_file($spec) ? (string) file_get_contents($spec) : '';

        $user = "Target runtime: about {$minutes} minutes ("
            . ($minutes * BookSummary::WORDS_PER_MINUTE) . " words).\n\n"
            . "# Summary to convert\n\n" . $outline['markdown'];

        $res = Anthropic::complete($system, $user, $p['model'] ?? null);

        $text = $res['text'];
        $title = '';
        if (preg_match('/^\s*TITLE:\s*(.+)$/mi', $text, $m)) {
            $title = trim($m[1]);
            $text = trim((string) preg_replace('/^\s*TITLE:.*$/mi', '', $text, 1));
        }

        $words = str_word_count($text);
        return [
            'book_id'     => (int) $outline['book']['id'],
            'title'       => $title ?: ($outline['book']['title'] . ' — Summary'),
            'body'        => $text,
            'word_count'  => $words,
            'est_seconds' => (int) round($words / BookSummary::WORDS_PER_MINUTE * 60),
            'model'       => $res['model'],
            'source_sha'  => $outline['sha'],
        ];
    }


    // ── batch image generation ───────────────────────────────────────────────

    /**
     * One chapter, art-directed. About 14 seconds and a cent.
     *
     * The input is the chapter's own core idea and summary, not its pages.
     * `Composition::subject('chapter', …)` returns the full page text, which for
     * a scanned book is thousands of words of OCR; pass 2 already distilled the
     * argument into 150–300 words, and that is what an art director can hold in
     * one read.
     */
    private static function artDirect(array $p): array
    {
        $type = (string) ($p['subject_type'] ?? 'chapter');
        $id   = (int) ($p['subject_id'] ?? $p['chapter_id'] ?? 0);
        $batchId = (int) ($p['batch_id'] ?? 0);

        // A batch names the model it will draw with; a lone redraw takes the
        // configured default, because there is no batch row to ask.
        $model = (string) ($p['model'] ?? '');
        if ($batchId) {
            $batch = ImageBatch::find($batchId);
            if (!$batch) {
                throw new \RuntimeException("No such image batch: {$batchId}");
            }
            $model = (string) $batch['model'];
        }
        if ($model === '') {
            $cfg = require dirname(__DIR__, 2) . '/config/app.php';
            $model = (string) ($cfg['gemini']['image_model'] ?? 'gemini-3-pro-image');
        }

        $subject = self::directable($type, $id);

        $res = ArtDirection::forSubject($subject);

        return [
            'subject_type' => $type,
            'subject_id'   => $id,
            'batch_id'     => $batchId,
            'batch_key'    => substr($type, 0, 2) . $id,
            'direction'    => $res['data'],
            'director'     => $res['model'],
            'model'        => $model,
            'prompt'       => ImagePrompt::fromDirection($res['data'], 'infographic',
                                  ImagePrompt::sourceLine($subject)),
        ];
    }

    /**
     * What an art director should read for a subject.
     *
     * A chapter is the special case and the reason this exists: its rows carry
     * a core idea and a 150–300 word summary from pass 2, while
     * `Composition::subject()` returns the chapter's *pages* — thousands of
     * words of OCR. The summary is the argument already distilled, which is
     * what fits in one read. Everything else goes through the normal subject
     * lookup the compose picker uses.
     *
     * @return array<string,mixed>
     */
    private static function directable(string $type, int $id): array
    {
        if ($type === 'chapter') {
            $ch = Database::one(
                'SELECT c.id, c.title, c.number_label, c.core_idea, c.summary,
                        b.title AS book_title, b.kind,
                        (SELECT GROUP_CONCAT(a.name ORDER BY ba.ord SEPARATOR "; ")
                           FROM af_book_authors ba JOIN af_authors a ON a.id = ba.author_id
                          WHERE ba.book_id = b.id) AS authors
                   FROM af_chapters c JOIN af_books b ON b.id = c.book_id
                  WHERE c.id = ?', [$id]);
            if (!$ch) {
                throw new \RuntimeException("No such chapter: {$id}");
            }
            $body = trim((string) ($ch['core_idea'] ?? '') . "\n\n"
                       . (string) ($ch['summary'] ?? ''));
            if ($body === '') {
                throw new \RuntimeException(
                    "Chapter {$id} has no summary to direct from.");
            }
            return [
                'title'        => trim(($ch['number_label'] ? $ch['number_label'] . ' — ' : '')
                                     . (string) $ch['title']),
                'body'         => $body,
                'book_title'   => $ch['book_title'],
                // For the footer's source line, built by ImagePrompt::sourceLine()
                // from these stored fields and never by the model.
                'number_label' => $ch['number_label'],
                'authors'      => $ch['authors'],
                'kind'         => $ch['kind'],
            ];
        }

        $subject = \Anglerfish\Models\Composition::subject($type, $id);
        if (!$subject) {
            throw new \RuntimeException("Nothing to direct for {$type}:{$id}.");
        }
        return $subject;
    }

    /**
     * Build one request out of the directions and hand it to Gemini.
     *
     * It defers rather than fails while directions are still running. A failure
     * spends an attempt and eventually kills the job; a deferral re-queues a
     * fresh one a minute out and costs nothing, which is the right shape for
     * "not my turn yet".
     */
    private static function imageBatchSubmit(array $p): array
    {
        $batchId = (int) ($p['batch_id'] ?? 0);
        $batch = ImageBatch::find($batchId);
        if (!$batch) {
            throw new \RuntimeException("No such image batch: {$batchId}");
        }
        $bookId = (int) $batch['subject_id'];

        // Already sent, or already given up on. Either way, not this job's work.
        if (!in_array($batch['status'], ['directing', 'submitting'], true)) {
            return ['batch_id' => $batchId, 'book_id' => $bookId, 'noop' => true];
        }

        $outstanding = ImageBatch::pendingDirections($batchId);
        if ($outstanding > 0) {
            // Outstanding means direction jobs are still *queued*, so the work
            // is coming and waiting is correct however long it takes. A whole
            // library run is 481 directions at ~30s sharing one cron, which is
            // several hours before the last batch is reached — an age-based
            // stall check sized for one book kills those batches while they are
            // behaving perfectly. Directions that genuinely fail leave the
            // outstanding count at zero and fall through to the check below.
            //
            // The 24-hour valve is only against a batch orphaned by the queue
            // itself being stopped, which no amount of deferring would fix.
            if (strtotime((string) $batch['created_at']) < time() - 86400) {
                return ['batch_id' => $batchId, 'book_id' => $bookId,
                        'fail' => "art direction stalled a day with {$outstanding} outstanding"];
            }
            return ['batch_id' => $batchId, 'book_id' => $bookId,
                    'defer' => 120, 'outstanding' => $outstanding];
        }

        $rows = array_values(array_filter(
            ImageBatch::assets($batchId),
            static fn(array $a) => $a['status'] === 'pending'
                && trim((string) $a['prompt_snapshot']) !== ''
        ));
        if (!$rows) {
            return ['batch_id' => $batchId, 'book_id' => $bookId,
                    'fail' => 'every direction failed; there is nothing to submit'];
        }

        $requests = [];
        foreach ($rows as $a) {
            $requests[] = GeminiBatch::imageRequest(
                (string) $a['prompt_snapshot'], (string) $a['batch_key'],
                (string) $batch['aspect_ratio'], (string) $batch['image_size']);
        }

        $sent = GeminiBatch::submit((string) $batch['model'], $requests,
            'anglerfish-book-' . $bookId . '-batch-' . $batchId);

        return [
            'batch_id'    => $batchId,
            'book_id'     => $bookId,
            'remote_name' => $sent['name'],
            'state'       => $sent['state'],
            'count'       => count($requests),
        ];
    }

    /**
     * Ask Gemini whether the batch is answered, and collect it if so.
     *
     * The raw operation is written to `storage/logs/batch-N.json` with the
     * base64 elided every time it is polled. This is a response shape nothing
     * here has consumed before, and a record of what actually came back is the
     * difference between fixing a parse in one pass and re-running a batch to
     * see it again.
     */
    private static function imageBatchPoll(array $p): array
    {
        $batchId = (int) ($p['batch_id'] ?? 0);
        $batch = ImageBatch::find($batchId);
        if (!$batch) {
            throw new \RuntimeException("No such image batch: {$batchId}");
        }
        $bookId = (int) $batch['subject_id'];
        if ($batch['status'] !== 'running' || !$batch['remote_name']) {
            return ['batch_id' => $batchId, 'book_id' => $bookId, 'noop' => true];
        }

        $op = GeminiBatch::status((string) $batch['remote_name']);
        $state = GeminiBatch::stateOf($op);

        $logs = AF_STORAGE . '/logs';
        if (!is_dir($logs)) {
            mkdir($logs, 0775, true);
        }
        file_put_contents("$logs/batch-{$batchId}.json",
            json_encode(GeminiBatch::sanitise($op),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (!GeminiBatch::isDone($op)) {
            $polls = (int) $batch['polls'];
            if ($polls > 200) {
                return ['batch_id' => $batchId, 'book_id' => $bookId,
                        'fail' => "still {$state} after {$polls} polls; giving up"];
            }
            // Fast at first because most batches finish in minutes, then slow
            // down rather than knocking every ten minutes for a day.
            return ['batch_id' => $batchId, 'book_id' => $bookId, 'state' => $state,
                    'defer' => $polls < 5 ? 120 : 600];
        }

        if (in_array($state, [GeminiBatch::FAILED, GeminiBatch::CANCELLED,
                              GeminiBatch::EXPIRED], true)) {
            return ['batch_id' => $batchId, 'book_id' => $bookId,
                    'fail' => 'batch ended ' . $state . ': '
                        . mb_substr((string) ($op['error']['message'] ?? 'no reason given'), 0, 300)];
        }

        $harvest = GeminiBatch::harvest($op);
        $images = [];
        foreach ($harvest['images'] as $key => $b64) {
            $images[] = ['key' => $key]
                + self::storeImage($b64, "batch-{$batchId}-{$key}");
        }

        return [
            'batch_id' => $batchId,
            'book_id'  => $bookId,
            'state'    => $state,
            'images'   => $images,
            'errors'   => $harvest['errors'],
        ];
    }

    /**
     * Base64 in, a file on disk out.
     *
     * Shared with the single-image path because the container rule is the part
     * that must never diverge: Gemini picks JPEG or PNG on its own, a filename
     * that contradicts its bytes breaks downloads and the Substack paste, and
     * WebP is never stored anywhere in this system.
     *
     * @return array{web_path:string,format:string,bytes:int,width:int,height:int}
     */
    private static function storeImage(string $b64, string $stem): array
    {
        $bytes = base64_decode($b64, true);
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('Gemini returned an image that will not decode.');
        }

        $ext = match (true) {
            str_starts_with($bytes, "\x89PNG")      => 'png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpg',
            // Hard rule: PNG and JPEG only, never WebP, anywhere, any purpose.
            str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 0, 16), 'WEBP')
                => throw new \RuntimeException(
                    'Gemini returned WebP, which this system never stores. '
                    . 'Ask for PNG or JPEG explicitly.'),
            default => throw new \RuntimeException(
                'Unrecognised image container from Gemini: '
                . bin2hex(substr($bytes, 0, 4))),
        };

        $dir = AF_STORAGE . '/assets';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = preg_replace('/[^a-z0-9-]/i', '', $stem) . '-'
              . bin2hex(random_bytes(4)) . '.' . $ext;
        file_put_contents("$dir/$name", $bytes);

        $size = @getimagesize("$dir/$name") ?: [0, 0];
        return [
            'web_path' => "storage/assets/$name",
            'format'   => $ext,
            'bytes'    => strlen($bytes),
            'width'    => (int) $size[0],
            'height'   => (int) $size[1],
        ];
    }

    // ── result appliers ──────────────────────────────────────────────────────

    private static function applyComposition(array $d): void
    {
        Database::run(
            "UPDATE af_compositions
                SET status='done', title=?, subtitle=?, body=?, model=?, finished_at=NOW()
              WHERE id=?",
            [$d['title'], $d['subtitle'], $d['body'], $d['model'], (int) $d['composition_id']]
        );
    }

    private static function applyScript(array $d): void
    {
        Database::run(
            "INSERT INTO af_book_summaries (book_id, kind, status, title, body,
                                         word_count, est_seconds, model, source_sha,
                                         finished_at)
             VALUES (?, 'audiobook_script', 'done', ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE status='done', title=VALUES(title), body=VALUES(body),
                 word_count=VALUES(word_count), est_seconds=VALUES(est_seconds),
                 model=VALUES(model), source_sha=VALUES(source_sha),
                 error=NULL, finished_at=NOW()",
            [(int) $d['book_id'], $d['title'], $d['body'], (int) $d['word_count'],
             (int) $d['est_seconds'], $d['model'], $d['source_sha']]
        );
    }

    private static function applyImage(array $d): void
    {
        Database::run(
            "UPDATE af_assets SET status='superseded'
              WHERE subject_type=? AND subject_id=? AND target=? AND status='ready'",
            [$d['subject_type'], $d['subject_id'], $d['target']]
        );
        Database::insert(
            'INSERT INTO af_assets (subject_type, subject_id, kind, target, route,
                                 web_path, format, status, version, model,
                                 prompt_snapshot, cost_usd)
             VALUES (?, ?, "infographic", ?, "generate", ?, ?, "ready",
                     1 + COALESCE((SELECT v FROM (SELECT MAX(version) v FROM af_assets
                        WHERE subject_type=? AND subject_id=? AND target=?) t), 0),
                     ?, ?, 0.134)',
            [$d['subject_type'], $d['subject_id'], $d['target'], $d['web_path'],
             $d['format'] ?? 'png',
             $d['subject_type'], $d['subject_id'], $d['target'],
             $d['model'], $d['prompt']]
        );
    }


    /**
     * A direction becomes a pending asset row: the picture is ordered but not
     * yet drawn. The row has to exist before submission, because the key it
     * carries is what matches a returned image back to a chapter.
     */
    private static function applyDirection(array $d): void
    {
        $batchId = (int) ($d['batch_id'] ?? 0);
        $type = (string) $d['subject_type'];
        $id   = (int) $d['subject_id'];

        // No batch means a single redraw asked for from the review queue. It
        // goes down the interactive path at full price rather than waiting on a
        // batch of one: somebody is sitting there looking at the picture they
        // want replaced, and 24 hours is not a review loop.
        if (!$batchId) {
            Job::enqueue('generate_image', $type, $id, [
                'subject_type' => $type,
                'subject_id'   => $id,
                'target'       => 'infographic',
                'prompt'       => $d['prompt'],
                'aspect_ratio' => '16:9',
                'image_size'   => '2K',
                'model'        => $d['model'],
            ], 1);
            return;
        }

        // A retried direction replaces its own order rather than placing a
        // second one for the same subject.
        Database::run(
            'DELETE FROM af_assets WHERE batch_id = ? AND batch_key = ? AND status = "pending"',
            [$batchId, $d['batch_key']]);

        Database::insert(
            'INSERT INTO af_assets (subject_type, subject_id, kind, target, route, status,
                                 batch_id, batch_key, prompt_snapshot, spec_json, model,
                                 version)
             VALUES (?, ?, "infographic", "infographic", "generate", "pending",
                     ?, ?, ?, ?, ?,
                     1 + COALESCE((SELECT v FROM (SELECT MAX(version) v FROM af_assets
                        WHERE subject_type = ? AND subject_id = ?
                          AND kind = "infographic") t), 0))',
            [$type, $id, $batchId, $d['batch_key'], $d['prompt'],
             json_encode($d['direction']), $d['model'], $type, $id]);
    }

    private static function applySubmit(array $d): void
    {
        $id = (int) $d['batch_id'];
        if (!empty($d['noop'])) {
            return;
        }
        if (!empty($d['fail'])) {
            self::abandonBatch($id, (string) $d['fail']);
            return;
        }
        if (!empty($d['defer'])) {
            Job::enqueueIn((int) $d['defer'], 'image_batch_submit', 'book',
                (int) ($d['book_id'] ?? 0), ['batch_id' => $id], 4, 60);
            return;
        }

        ImageBatch::setStatus($id, 'running', [
            'remote_name'   => $d['remote_name'],
            'remote_state'  => $d['state'],
            'request_count' => (int) $d['count'],
            'cost_usd'      => round((int) $d['count'] * GeminiBatch::PRICE_PER_IMAGE, 4),
            'submitted_at'  => date('Y-m-d H:i:s'),
        ]);
        Job::enqueueIn(120, 'image_batch_poll', 'book', (int) ($d['book_id'] ?? 0),
            ['batch_id' => $id], 4, 400);
    }

    private static function applyPoll(array $d): void
    {
        $id = (int) $d['batch_id'];
        if (!empty($d['noop'])) {
            return;
        }
        if (!empty($d['fail'])) {
            self::abandonBatch($id, (string) $d['fail']);
            return;
        }
        if (!empty($d['defer'])) {
            Database::run(
                'UPDATE af_image_batches SET polls = polls + 1, remote_state = ? WHERE id = ?',
                [$d['state'] ?? null, $id]);
            Job::enqueueIn((int) $d['defer'], 'image_batch_poll', 'book',
                (int) ($d['book_id'] ?? 0), ['batch_id' => $id], 4, 400);
            return;
        }

        foreach ((array) ($d['images'] ?? []) as $img) {
            $asset = Database::one(
                'SELECT id, subject_id FROM af_assets WHERE batch_id = ? AND batch_key = ?',
                [$id, $img['key']]);
            if (!$asset) {
                continue;                 // a key nothing is waiting for
            }
            Database::run(
                "UPDATE af_assets SET status = 'superseded'
                  WHERE subject_type = 'chapter' AND subject_id = ? AND kind = 'infographic'
                    AND status = 'ready' AND id <> ?",
                [(int) $asset['subject_id'], (int) $asset['id']]);
            Database::run(
                "UPDATE af_assets SET status = 'ready', web_path = ?, format = ?,
                        width = ?, height = ?, cost_usd = ?
                  WHERE id = ?",
                [$img['web_path'], $img['format'], (int) $img['width'],
                 (int) $img['height'], GeminiBatch::PRICE_PER_IMAGE, (int) $asset['id']]);
        }

        foreach ((array) ($d['errors'] ?? []) as $key => $message) {
            Database::run(
                "UPDATE af_assets SET status = 'rejected'
                  WHERE batch_id = ? AND batch_key = ? AND status = 'pending'",
                [$id, $key]);
        }

        // Anything still pending was neither answered nor refused — the batch
        // came back without it. Mark it rather than leaving a row that looks
        // like it is still coming.
        $stranded = Database::run(
            "UPDATE af_assets SET status = 'rejected' WHERE batch_id = ? AND status = 'pending'",
            [$id]);

        ImageBatch::setStatus($id, 'done', [
            'applied_count' => count((array) ($d['images'] ?? [])),
            'failed_count'  => count((array) ($d['errors'] ?? [])) + $stranded,
            'remote_state'  => $d['state'] ?? null,
            'cost_usd'      => round(count((array) ($d['images'] ?? []))
                                     * GeminiBatch::PRICE_PER_IMAGE, 4),
            'completed_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /** A batch that will never produce pictures, and the orders it leaves behind. */
    private static function abandonBatch(int $id, string $why): void
    {
        ImageBatch::setStatus($id, 'failed', [
            'error'        => mb_substr($why, 0, 2000),
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
        Database::run(
            "UPDATE af_assets SET status = 'rejected' WHERE batch_id = ? AND status = 'pending'",
            [$id]);
    }

    // ── response shape helpers ───────────────────────────────────────────────

    private static function outputText(array $res): string
    {
        foreach (['output_text', 'outputText'] as $k) {
            if (!empty($res[$k]) && is_string($res[$k])) {
                return $res[$k];
            }
        }
        // Fall back to walking the output array for any text part.
        $text = '';
        array_walk_recursive($res, static function ($v, $k) use (&$text) {
            if ($k === 'text' && is_string($v)) {
                $text .= $v;
            }
        });
        return $text;
    }

    private static function outputImage(array $res): ?string
    {
        $found = null;
        array_walk_recursive($res, static function ($v, $k) use (&$found) {
            if ($found === null && is_string($v) && strlen($v) > 1000
                && in_array($k, ['data', 'b64_json', 'bytesBase64Encoded'], true)) {
                $found = $v;
            }
        });
        return $found;
    }
}
