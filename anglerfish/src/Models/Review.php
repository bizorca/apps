<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

/**
 * Every generated infographic in the system, waiting to be looked at.
 *
 * The contact sheet answers "how did this book come out"; this answers "what
 * have I not decided about yet", across all books at once. Batch generation
 * made that the scarce thing — ordering 494 images takes one command and
 * reviewing them takes an afternoon — so the queue is one image per row, at a
 * size you can actually judge, with the two verbs that resolve a row.
 *
 * A row leaves the queue when `reviewed_at` is set (kept) or the status is no
 * longer `ready` (rejected, superseded by a redraw). Nothing is deleted: a
 * rejected image stays on disk and in the table, because the reason to look at
 * it again is usually to work out why the direction went wrong.
 */
final class Review
{
    /**
     * Subject titles for every type an infographic can hang off.
     *
     * One query per type rather than a five-way LEFT JOIN, because the subject
     * tables share no common shape and `assets` is polymorphic with no foreign
     * key to any of them — the same reason `triage` and `module_map` are.
     */
    private const TITLE_SQL = [
        'chapter'  => 'SELECT c.id, CONCAT(COALESCE(CONCAT(c.number_label, " — "), ""),
                              c.title) AS title, b.title AS context, b.slug AS slug,
                              c.core_idea AS note
                         FROM af_chapters c JOIN af_books b ON b.id = c.book_id
                        WHERE c.id IN (%s)',
        'concept'  => 'SELECT c.id, c.title, b.title AS context, b.slug AS slug,
                              c.body AS note
                         FROM af_concepts c LEFT JOIN af_books b ON b.id = c.book_id
                        WHERE c.id IN (%s)',
        'artifact' => 'SELECT a.id, a.title, b.title AS context, b.slug AS slug,
                              a.intro AS note
                         FROM af_artifacts a LEFT JOIN af_books b ON b.id = a.book_id
                        WHERE a.id IN (%s)',
        'post'     => 'SELECT p.id, p.title, "Post" AS context, p.slug AS slug,
                              p.subtitle AS note
                         FROM af_posts p WHERE p.id IN (%s)',
        'book'     => 'SELECT b.id, b.title, "Book" AS context, b.slug AS slug,
                              b.subtitle AS note
                         FROM af_books b WHERE b.id IN (%s)',
    ];

    /** How many rows a page of the queue holds. */
    public const PER_PAGE = 20;

    /**
     * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,pages:int}
     */
    public static function queue(string $filter = 'unreviewed', int $page = 1): array
    {
        [$where, $args] = self::filter($filter);

        $total = (int) Database::value(
            "SELECT COUNT(*) FROM af_assets WHERE kind = 'infographic' AND $where", $args);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * self::PER_PAGE;

        // Oldest first. A queue worked newest-first never reaches the bottom,
        // and the bottom is where the images nobody has judged have been
        // sitting longest.
        $rows = Database::all(
            "SELECT id, subject_type, subject_id, status, reviewed_at, web_path,
                    format, width, height, version, model, cost_usd, batch_id,
                    prompt_snapshot, spec_json, created_at, qa_verdict, qa_notes
               FROM af_assets
              WHERE kind = 'infographic' AND $where
              ORDER BY id ASC
              LIMIT " . self::PER_PAGE . " OFFSET $offset", $args);

        return ['rows' => self::withSubjects($rows), 'total' => $total,
                'page' => $page, 'pages' => $pages];
    }

    /** Counts for the filter tabs, in one pass rather than four queries. */
    public static function counts(): array
    {
        $r = Database::one(
            "SELECT COUNT(*) AS all_n,
                    SUM(status = 'ready' AND reviewed_at IS NULL) AS unreviewed,
                    SUM(status = 'ready' AND reviewed_at IS NOT NULL) AS kept,
                    SUM(status = 'rejected') AS rejected,
                    SUM(status IN ('pending','rendering')) AS waiting,
                    SUM(status = 'ready' AND reviewed_at IS NULL
                        AND qa_verdict IN ('fail','unsure')) AS flagged
               FROM af_assets WHERE kind = 'infographic'") ?: [];
        return array_map('intval', array_map(static fn($v) => $v ?? 0, $r));
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private static function filter(string $filter): array
    {
        return match ($filter) {
            'kept'     => ["status = 'ready' AND reviewed_at IS NOT NULL", []],
            'flagged'  => ["status = 'ready' AND reviewed_at IS NULL
                            AND qa_verdict IN ('fail','unsure')", []],
            'rejected' => ["status = 'rejected'", []],
            'waiting'  => ["status IN ('pending','rendering')", []],
            'all'      => ['1 = 1', []],
            default    => ["status = 'ready' AND reviewed_at IS NULL", []],
        };
    }

    /**
     * Hang each asset's subject onto it.
     *
     * A row whose subject has since been deleted keeps a placeholder title
     * rather than disappearing — an image with no subject is exactly the thing
     * a review queue should surface, not hide.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function withSubjects(array $rows, int $noteLen = 220): array
    {
        $byType = [];
        foreach ($rows as $r) {
            $byType[$r['subject_type']][] = (int) $r['subject_id'];
        }

        $titles = [];
        foreach ($byType as $type => $ids) {
            if (!isset(self::TITLE_SQL[$type]) || !$ids) {
                continue;
            }
            $ids = array_values(array_unique($ids));
            $sql = sprintf(self::TITLE_SQL[$type],
                implode(',', array_map('intval', $ids)));
            foreach (Database::all($sql) as $row) {
                $titles[$type][(int) $row['id']] = $row;
            }
        }

        foreach ($rows as &$r) {
            $s = $titles[$r['subject_type']][(int) $r['subject_id']] ?? null;
            $r['subject_title'] = $s['title'] ?? ('[deleted ' . $r['subject_type']
                . ' ' . $r['subject_id'] . ']');
            $r['subject_context'] = $s['context'] ?? '';
            $r['subject_slug'] = $s['slug'] ?? '';
            $r['subject_note'] = mb_substr(trim((string) ($s['note'] ?? '')), 0, $noteLen);
            $r['headline'] = self::headline($r['spec_json'] ?? null);
        }
        return $rows;
    }

    /**
     * The headline the art director chose, for the row label.
     *
     * Worth showing beside the picture: when a redraw comes back wrong, the
     * headline usually says why before the image does.
     */
    private static function headline(?string $specJson): string
    {
        if (!$specJson) {
            return '';
        }
        $spec = json_decode($specJson, true);
        return is_array($spec) ? trim((string) ($spec['headline'] ?? '')) : '';
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            "SELECT * FROM af_assets WHERE id = ? AND kind = 'infographic'", [$id]);
    }

    /** Keep it. The only action that costs nothing and ends the row. */
    public static function keep(int $id): void
    {
        Database::run(
            "UPDATE af_assets SET reviewed_at = NOW()
              WHERE id = ? AND kind = 'infographic' AND status = 'ready'
                AND reviewed_at IS NULL", [$id]);
    }

    public static function reject(int $id): void
    {
        Database::run(
            "UPDATE af_assets SET status = 'rejected', reviewed_at = NOW()
              WHERE id = ? AND kind = 'infographic'
                AND status IN ('ready','pending','rendering')", [$id]);
    }

    /**
     * Reject and order a replacement, art-directed afresh.
     *
     * The fresh direction is the whole point. Re-firing the stored prompt
     * returns the same picture with different noise; re-directing gives the
     * model a different metaphor to draw, which is what "this one is wrong"
     * actually asks for.
     */
    public static function redraw(int $id): bool
    {
        $asset = self::find($id);
        if (!$asset) {
            return false;
        }
        self::reject($id);

        Job::enqueue('art_direct', $asset['subject_type'], (int) $asset['subject_id'], [
            'subject_type' => $asset['subject_type'],
            'subject_id'   => (int) $asset['subject_id'],
            'model'        => $asset['model'],
        ], 1, 8);
        return true;
    }

    // ── Machine check (worker/image_qa.py) ──────────────────────────────────

    /** A subject this many failed checks deep goes to a person, not another draw. */
    public const QA_MAX_FAILS = 2;

    public const QA_VERDICTS = ['pass', 'fail', 'unsure'];

    /**
     * Unreviewed, unchecked infographics, with everything a checker needs to
     * judge one without the app: the art direction (the exact words that should
     * be on it), the assembled prompt (the footer it was told to draw), the
     * subject's own text, and how many times this subject has already failed.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function qaExport(?string $slug = null, int $limit = 50): array
    {
        $rows = Database::all(
            "SELECT id, subject_type, subject_id, web_path, format, width, height,
                    spec_json, prompt_snapshot, created_at
               FROM af_assets
              WHERE kind = 'infographic' AND status = 'ready'
                AND reviewed_at IS NULL AND qa_at IS NULL
              ORDER BY id ASC");
        $rows = self::withSubjects($rows, 2000);
        if ($slug !== null && $slug !== '') {
            $rows = array_values(array_filter($rows,
                static fn($r) => $r['subject_slug'] === $slug));
        }
        $rows = array_slice($rows, 0, max(1, $limit));
        foreach ($rows as &$r) {
            $r['prior_fails'] = self::priorFails($r['subject_type'], (int) $r['subject_id']);
        }
        return $rows;
    }

    private static function priorFails(string $type, int $id): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM af_assets
              WHERE kind = 'infographic' AND subject_type = ? AND subject_id = ?
                AND qa_verdict = 'fail'", [$type, $id]);
    }

    /**
     * Record one verdict and act on it.
     *
     * pass   → kept (reviewed_at set), leaves the queue.
     * fail   → rejected and re-art-directed, unless this subject has now failed
     *          QA_MAX_FAILS times, in which case it stays in the queue, flagged.
     * unsure → stays in the queue, flagged, untouched.
     *
     * Only acts on a row still ready and unreviewed, so a verdict that arrives
     * after a person already decided changes nothing.
     */
    public static function qaApply(int $id, string $verdict, string $notes): string
    {
        if (!in_array($verdict, self::QA_VERDICTS, true)) {
            return 'bad-verdict';
        }
        $asset = Database::one(
            "SELECT id, subject_type, subject_id FROM af_assets
              WHERE id = ? AND kind = 'infographic' AND status = 'ready'
                AND reviewed_at IS NULL", [$id]);
        if (!$asset) {
            return 'skipped';
        }
        Database::run(
            "UPDATE af_assets SET qa_verdict = ?, qa_notes = ?, qa_at = NOW() WHERE id = ?",
            [$verdict, mb_substr($notes, 0, 4000), $id]);

        if ($verdict === 'pass') {
            self::keep($id);
            return 'kept';
        }
        if ($verdict === 'fail') {
            $fails = self::priorFails($asset['subject_type'], (int) $asset['subject_id']);
            if ($fails >= self::QA_MAX_FAILS) {
                return 'flagged';
            }
            self::redraw($id);
            return 'redrawn';
        }
        return 'flagged';
    }
}
