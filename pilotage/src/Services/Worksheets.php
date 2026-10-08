<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Worksheets, forms, and scored assessments (M10).
 *
 * One machine, three settings: an intake questionnaire, a reflection
 * worksheet, and a scored business-health assessment differ in whether the
 * answers are read, scored, or aggregated — not in how they are collected.
 *
 * The rule that shapes this class: **anonymity is structural, not a flag.**
 * FR-10.6 hides individual responses from the coach, and a visibility column
 * would not deliver that — anyone with database access could still read who
 * said what. So an anonymous response stores no respondent id at all. The link
 * is destroyed at submission rather than concealed, which is the only version
 * of the promise that survives a coach asking their developer nicely.
 */
final class Worksheets
{
    public const KINDS = ['form' => 'Form', 'assessment' => 'Assessment', 'survey' => 'Team survey'];

    public const FIELD_TYPES = [
        'section'     => 'Section heading',
        'note'        => 'Instruction',
        'short_text'  => 'Short text',
        'long_text'   => 'Long text',
        'number'      => 'Number',
        'currency'    => 'Currency',
        'date'        => 'Date',
        'select_one'  => 'Choose one',
        'select_many' => 'Choose several',
        'scale'       => 'Scale',
        'file'        => 'File upload',
    ];

    /** Field types that carry no answer — structure and instruction only. */
    public const STRUCTURAL = ['section', 'note'];

    /** Field types that can contribute to a score. */
    public const SCORABLE = ['scale', 'number', 'select_one'];

    public const DEFAULT_MIN_RESPONSES = 5;

    // ---------------------------------------------------------------- authoring

    /** @param array<string,mixed> $data */
    public static function create(int $tenantId, array $data, ?int $userId = null): int
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw new \InvalidArgumentException('A worksheet needs a title.');
        }

        $kind = (string) ($data['kind'] ?? 'form');

        if (!isset(self::KINDS[$kind])) {
            throw new \InvalidArgumentException('Unknown worksheet type.');
        }

        // A survey is anonymous by definition; anything else is only anonymous
        // if asked for. Making this implicit stops a coach shipping a "team
        // survey" that quietly names everyone.
        $anonymous = $kind === 'survey' ? true : !empty($data['is_anonymous']);

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_worksheets (tenant_id, title, description, kind, is_anonymous, min_responses, created_by)
             VALUES (:tid, :title, :desc, :kind, :anon, :min, :by)'
        )->execute([
            'tid'   => $tenantId,
            'title' => mb_substr($title, 0, 255),
            'desc'  => trim((string) ($data['description'] ?? '')) ?: null,
            'kind'  => $kind,
            'anon'  => $anonymous ? 1 : 0,
            'min'   => max(2, (int) ($data['min_responses'] ?? self::DEFAULT_MIN_RESPONSES)),
            'by'    => $userId,
        ]);

        return (int) $db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function addField(int $tenantId, int $worksheetId, array $data): int
    {
        $type = (string) ($data['field_type'] ?? '');

        if (!isset(self::FIELD_TYPES[$type])) {
            throw new \InvalidArgumentException('Unknown field type.');
        }

        $label = trim((string) ($data['label'] ?? ''));

        if ($label === '') {
            throw new \InvalidArgumentException('Every field needs a label.');
        }

        if (in_array($type, ['select_one', 'select_many'], true)
            && trim((string) ($data['options'] ?? '')) === '') {
            throw new \InvalidArgumentException('A choice field needs some choices.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_worksheet_fields
                (tenant_id, worksheet_id, position, field_type, label, help, required, options,
                 scale_min, scale_max, scale_min_label, scale_max_label, category, weight)
             VALUES (:tid, :wid,
                     (SELECT COALESCE(MAX(f.position), -1) + 1 FROM pl_worksheet_fields f
                       WHERE f.tenant_id = :tid2 AND f.worksheet_id = :wid2),
                     :type, :label, :help, :req, :opts, :smin, :smax, :sminl, :smaxl, :cat, :weight)'
        )->execute([
            'tid'   => $tenantId, 'tid2' => $tenantId,
            'wid'   => $worksheetId, 'wid2' => $worksheetId,
            'type'  => $type,
            'label' => mb_substr($label, 0, 500),
            'help'  => trim((string) ($data['help'] ?? '')) ?: null,
            // Structural fields cannot be required — there is nothing to fill in.
            'req'   => (!in_array($type, self::STRUCTURAL, true) && !empty($data['required'])) ? 1 : 0,
            'opts'  => trim((string) ($data['options'] ?? '')) ?: null,
            'smin'  => $type === 'scale' ? (int) ($data['scale_min'] ?? 1) : null,
            'smax'  => $type === 'scale' ? (int) ($data['scale_max'] ?? 10) : null,
            'sminl' => trim((string) ($data['scale_min_label'] ?? '')) ?: null,
            'smaxl' => trim((string) ($data['scale_max_label'] ?? '')) ?: null,
            'cat'   => trim((string) ($data['category'] ?? '')) ?: null,
            'weight' => max(0, (float) ($data['weight'] ?? 1)),
        ]);

        return (int) $db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function addBand(int $tenantId, int $worksheetId, array $data): int
    {
        $min = (int) ($data['min_percent'] ?? 0);
        $max = (int) ($data['max_percent'] ?? 100);

        if ($min > $max) {
            throw new \InvalidArgumentException('A band cannot start above where it ends.');
        }

        $label = trim((string) ($data['label'] ?? ''));

        if ($label === '') {
            throw new \InvalidArgumentException('A band needs a label.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_worksheet_bands (tenant_id, worksheet_id, category, min_percent, max_percent, label, interpretation)
             VALUES (:tid, :wid, :cat, :min, :max, :label, :interp)'
        )->execute([
            'tid' => $tenantId, 'wid' => $worksheetId,
            'cat' => trim((string) ($data['category'] ?? '')) ?: null,
            'min' => max(0, min(100, $min)),
            'max' => max(0, min(100, $max)),
            'label' => mb_substr($label, 0, 120),
            'interp' => trim((string) ($data['interpretation'] ?? '')) ?: null,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function publish(int $tenantId, int $worksheetId): bool
    {
        if (self::answerableFields($tenantId, $worksheetId) === []) {
            throw new \RuntimeException('A worksheet needs at least one question before it can go out.');
        }

        $stmt = Database::conn()->prepare(
            "UPDATE pl_worksheets SET status = 'published' WHERE tenant_id = :tid AND id = :id AND status = 'draft'"
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $worksheetId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $tenantId, int $worksheetId): ?array
    {
        $stmt = Database::conn()->prepare('SELECT * FROM pl_worksheets WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $worksheetId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function fields(int $tenantId, int $worksheetId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_worksheet_fields WHERE tenant_id = :tid AND worksheet_id = :wid
             ORDER BY position ASC, id ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'wid' => $worksheetId]);

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Fields that actually take an answer. */
    public static function answerableFields(int $tenantId, int $worksheetId): array
    {
        return array_values(array_filter(
            self::fields($tenantId, $worksheetId),
            static fn (array $f): bool => !in_array((string) $f['field_type'], self::STRUCTURAL, true)
        ));
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(int $tenantId, ?string $kind = null): array
    {
        $sql = "SELECT w.*, (SELECT COUNT(*) FROM pl_worksheet_fields f WHERE f.worksheet_id = w.id) AS field_count
                FROM pl_worksheets w
                WHERE w.tenant_id = :tid AND w.status <> 'archived'";

        $params = ['tid' => $tenantId];

        if ($kind !== null) {
            $sql .= ' AND w.kind = :kind';
            $params['kind'] = $kind;
        }

        $sql .= ' ORDER BY w.title ASC';

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------- assigning

    public static function assign(
        int $tenantId,
        int $worksheetId,
        int $engagementId,
        ?int $assignedUserId,
        ?string $label = null,
        ?string $dueOn = null,
        ?int $assignedBy = null
    ): int {
        $worksheet = self::find($tenantId, $worksheetId);

        if ($worksheet === null) {
            throw new \RuntimeException('No such worksheet.');
        }

        if ((string) $worksheet['status'] !== 'published') {
            throw new \RuntimeException('Publish it before sending it out.');
        }

        // An anonymous worksheet assigned to ONE person is not anonymous —
        // the coach would know exactly whose answers those are.
        if ((int) $worksheet['is_anonymous'] === 1 && $assignedUserId !== null) {
            throw new \InvalidArgumentException(
                'An anonymous worksheet goes to the whole team, not to one person — otherwise it is not anonymous.'
            );
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_worksheet_assignments
                (tenant_id, worksheet_id, engagement_id, label, due_on, assigned_user_id, assigned_by)
             VALUES (:tid, :wid, :eid, :label, :due, :uid, :by)'
        )->execute([
            'tid' => $tenantId, 'wid' => $worksheetId, 'eid' => $engagementId,
            'label' => mb_substr(trim((string) $label), 0, 120) ?: null,
            'due' => $dueOn ?: null, 'uid' => $assignedUserId, 'by' => $assignedBy,
        ]);

        $assignmentId = (int) $db->lastInsertId();

        // Transactional: being asked to fill something in is the work. An
        // anonymous survey goes to the whole org, a named one to its person.
        $recipients = $assignedUserId !== null
            ? [$assignedUserId]
            : Notifications::clientRecipients($tenantId, $engagementId);

        Notifications::queueMany(
            $tenantId, $recipients, 'worksheet.assigned',
            'Please fill in "' . (string) $worksheet['title'] . '"',
            $dueOn ? 'Due ' . date('j F', strtotime($dueOn)) . '.' : null,
            '/worksheets/fill/' . $assignmentId,
            ['object_type' => 'worksheet_assignment', 'object_id' => $assignmentId]
                + Notifications::orgContext($tenantId, $engagementId),
            $assignedBy
        );

        return $assignmentId;
    }


    /** @return array<string,mixed>|null */
    public static function assignment(int $tenantId, int $assignmentId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT a.*, w.title, w.kind, w.is_anonymous, w.min_responses, w.description
             FROM pl_worksheet_assignments a
             JOIN pl_worksheets w ON w.id = a.worksheet_id
             WHERE a.tenant_id = :tid AND a.id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $assignmentId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function assignmentsFor(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT a.*, w.title, w.kind, w.is_anonymous, w.min_responses,
                    (SELECT COUNT(*) FROM pl_worksheet_responses r
                      WHERE r.assignment_id = a.id AND r.status = "submitted") AS submitted_count
             FROM pl_worksheet_assignments a
             JOIN pl_worksheets w ON w.id = a.worksheet_id
             WHERE a.tenant_id = :tid AND a.engagement_id = :eid
             ORDER BY a.created_at DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------- answering

    /**
     * Start or resume a response (FR-10.3).
     *
     * For a named worksheet the respondent is recorded and their part-finished
     * attempt is found again by user id. For an anonymous one, nothing is
     * recorded and the caller must hold the returned resume key — typically in
     * the session. Losing it means starting again, which is the price of the
     * link genuinely not existing.
     *
     * @return array<string,mixed>
     */
    public static function startOrResume(int $tenantId, int $assignmentId, ?int $userId, ?string $resumeKey = null): array
    {
        $assignment = self::assignment($tenantId, $assignmentId);

        if ($assignment === null) {
            throw new \RuntimeException('No such assignment.');
        }

        $anonymous = (int) $assignment['is_anonymous'] === 1;
        $db = Database::conn();

        // Resume by key first — it works for both kinds.
        if ($resumeKey !== null && preg_match('/^[a-f0-9]{32}$/', $resumeKey)) {
            $stmt = $db->prepare(
                'SELECT * FROM pl_worksheet_responses
                 WHERE tenant_id = :tid AND assignment_id = :aid AND resume_key = :key LIMIT 1'
            );
            $stmt->execute(['tid' => $tenantId, 'aid' => $assignmentId, 'key' => $resumeKey]);
            $existing = $stmt->fetch();

            if ($existing !== false) {
                return $existing;
            }
        }

        if (!$anonymous && $userId !== null) {
            $stmt = $db->prepare(
                'SELECT * FROM pl_worksheet_responses
                 WHERE tenant_id = :tid AND assignment_id = :aid AND respondent_user_id = :uid LIMIT 1'
            );
            $stmt->execute(['tid' => $tenantId, 'aid' => $assignmentId, 'uid' => $userId]);
            $existing = $stmt->fetch();

            if ($existing !== false) {
                return $existing;
            }
        }

        $key = bin2hex(random_bytes(16));

        $db->prepare(
            'INSERT INTO pl_worksheet_responses (tenant_id, assignment_id, respondent_user_id, resume_key)
             VALUES (:tid, :aid, :uid, :key)'
        )->execute([
            'tid' => $tenantId,
            'aid' => $assignmentId,
            // The mechanism, in one line: an anonymous response never learns
            // who filled it in.
            'uid' => $anonymous ? null : $userId,
            'key' => $key,
        ]);

        $id = (int) $db->lastInsertId();

        // Scoped even though the id came straight from lastInsertId. An
        // unscoped read on a tenant table is the shape of the bug, and a
        // grep for one should come back empty.
        $stmt = $db->prepare('SELECT * FROM pl_worksheet_responses WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['tid' => $tenantId, 'id' => $id]);

        return $stmt->fetch();
    }

    /**
     * Save one answer. Called as the respondent goes, so a half-finished
     * worksheet survives a closed laptop.
     */
    public static function saveAnswer(int $tenantId, int $responseId, int $fieldId, mixed $value): void
    {
        $field = self::field($tenantId, $fieldId);

        if ($field === null || in_array((string) $field['field_type'], self::STRUCTURAL, true)) {
            return;
        }

        $text = null;
        $number = null;

        switch ((string) $field['field_type']) {
            case 'number':
            case 'currency':
            case 'scale':
                $raw = is_array($value) ? '' : trim((string) $value);
                $number = $raw === '' || !is_numeric($raw) ? null : (float) $raw;
                break;

            case 'select_many':
                $picked = is_array($value) ? $value : array_filter([$value]);
                $text = $picked === [] ? null : implode("\n", array_map('strval', $picked));
                break;

            default:
                $text = is_array($value) ? implode("\n", array_map('strval', $value)) : trim((string) $value);
                $text = $text === '' ? null : $text;
        }

        Database::conn()->prepare(
            'INSERT INTO pl_worksheet_answers (tenant_id, response_id, field_id, value_text, value_number)
             VALUES (:tid, :rid, :fid, :text, :num)
             ON DUPLICATE KEY UPDATE value_text = VALUES(value_text), value_number = VALUES(value_number)'
        )->execute(['tid' => $tenantId, 'rid' => $responseId, 'fid' => $fieldId, 'text' => $text, 'num' => $number]);
    }

    /**
     * Submit. Scores if the worksheet scores, then freezes the result.
     *
     * @return array{missing:array<int,string>, score:?float, band:?string}
     */
    public static function submit(int $tenantId, int $responseId): array
    {
        $response = self::response($tenantId, $responseId);

        if ($response === null) {
            throw new \RuntimeException('No such response.');
        }

        $assignment = self::assignment($tenantId, (int) $response['assignment_id']);
        $fields = self::answerableFields($tenantId, (int) $assignment['worksheet_id']);
        $answers = self::answersByField($tenantId, $responseId);

        $missing = [];

        foreach ($fields as $f) {
            if ((int) $f['required'] !== 1) {
                continue;
            }

            $a = $answers[(int) $f['id']] ?? null;

            if ($a === null || ($a['value_text'] === null && $a['value_number'] === null && $a['document_id'] === null)) {
                $missing[] = (string) $f['label'];
            }
        }

        if ($missing !== []) {
            return ['missing' => $missing, 'score' => null, 'band' => null];
        }

        $scored = self::score($tenantId, (int) $assignment['worksheet_id'], $fields, $answers);

        $db = Database::conn();

        $db->prepare(
            "UPDATE pl_worksheet_responses
             SET status = 'submitted', submitted_at = NOW(), score_percent = :score, band_label = :band
             WHERE tenant_id = :tid AND id = :id"
        )->execute([
            'score' => $scored['overall'],
            'band'  => $scored['band'],
            'tid'   => $tenantId,
            'id'    => $responseId,
        ]);

        $db->prepare('DELETE FROM pl_worksheet_subscores WHERE tenant_id = :tid AND response_id = :rid')
           ->execute(['tid' => $tenantId, 'rid' => $responseId]);

        $insert = $db->prepare(
            'INSERT INTO pl_worksheet_subscores (tenant_id, response_id, category, score_percent, band_label)
             VALUES (:tid, :rid, :cat, :score, :band)'
        );

        foreach ($scored['categories'] as $category => $detail) {
            $insert->execute([
                'tid' => $tenantId, 'rid' => $responseId, 'cat' => $category,
                'score' => $detail['percent'], 'band' => $detail['band'],
            ]);
        }

        // The coach hears it came back — never who wrote it, and never what
        // they said. On an anonymous worksheet there is nothing to tell them
        // anyway, which is the point of M10.
        Notifications::queueMany(
            $tenantId,
            Notifications::firmRecipients($tenantId, (int) $assignment['engagement_id']),
            'worksheet.submitted',
            'A response came back for "' . (string) $assignment['title'] . '"',
            null,
            '/worksheets/results/' . (int) $assignment['id'],
            ['object_type' => 'worksheet_assignment', 'object_id' => (int) $assignment['id']]
                + Notifications::orgContext($tenantId, (int) $assignment['engagement_id'])
        );

        return ['missing' => [], 'score' => $scored['overall'], 'band' => $scored['band']];
    }

    /**
     * Score a set of answers (FR-10.4).
     *
     * Each scorable field contributes its value as a proportion of its own
     * maximum, weighted. Unanswered optional questions are excluded from the
     * denominator rather than counted as zero — scoring someone down for a
     * question they were not required to answer would be nonsense.
     *
     * @param array<int,array<string,mixed>> $fields
     * @param array<int,array<string,mixed>> $answers
     * @return array{overall:?float, band:?string, categories:array<string,array{percent:float, band:?string}>}
     */
    public static function score(int $tenantId, int $worksheetId, array $fields, array $answers): array
    {
        $totals = ['_overall' => ['earned' => 0.0, 'possible' => 0.0]];

        foreach ($fields as $f) {
            if (!in_array((string) $f['field_type'], self::SCORABLE, true)) {
                continue;
            }

            $a = $answers[(int) $f['id']] ?? null;

            if ($a === null || $a['value_number'] === null) {
                continue;   // not answered: out of the denominator entirely
            }

            $value = (float) $a['value_number'];
            $weight = (float) $f['weight'];

            $max = match ((string) $f['field_type']) {
                'scale' => (float) ($f['scale_max'] ?? 10),
                default => null,
            };

            if ($max === null || $max <= 0) {
                continue;   // nothing to be a proportion OF
            }

            $min = (float) ($f['scale_min'] ?? 0);
            $span = $max - $min;

            if ($span <= 0) {
                continue;
            }

            $normalised = max(0.0, min(1.0, ($value - $min) / $span));

            $buckets = ['_overall'];

            if (!empty($f['category'])) {
                $buckets[] = (string) $f['category'];
            }

            foreach ($buckets as $bucket) {
                $totals[$bucket] ??= ['earned' => 0.0, 'possible' => 0.0];
                $totals[$bucket]['earned'] += $normalised * $weight;
                $totals[$bucket]['possible'] += $weight;
            }
        }

        $bands = self::bands($tenantId, $worksheetId);

        $overall = $totals['_overall']['possible'] > 0
            ? round($totals['_overall']['earned'] / $totals['_overall']['possible'] * 100, 2)
            : null;

        $categories = [];

        foreach ($totals as $key => $t) {
            if ($key === '_overall' || $t['possible'] <= 0) {
                continue;
            }

            $percent = round($t['earned'] / $t['possible'] * 100, 2);
            $categories[$key] = ['percent' => $percent, 'band' => self::bandFor($bands, $key, $percent)];
        }

        return [
            'overall'    => $overall,
            'band'       => $overall === null ? null : self::bandFor($bands, null, $overall),
            'categories' => $categories,
        ];
    }

    // --------------------------------------------------------------- reading

    /**
     * A coach reading responses.
     *
     * For an anonymous worksheet this returns AGGREGATES ONLY, and nothing at
     * all below the response threshold. There is no parameter to override
     * that, deliberately — an override is a thing that gets used.
     *
     * @return array{anonymous:bool, count:int, withheld:bool, responses:array<int,array<string,mixed>>, aggregates:array<int,array<string,mixed>>}
     */
    public static function results(int $tenantId, int $assignmentId): array
    {
        $assignment = self::assignment($tenantId, $assignmentId);

        if ($assignment === null) {
            throw new \RuntimeException('No such assignment.');
        }

        $anonymous = (int) $assignment['is_anonymous'] === 1;
        $db = Database::conn();

        $stmt = $db->prepare(
            "SELECT COUNT(*) AS c FROM pl_worksheet_responses
             WHERE tenant_id = :tid AND assignment_id = :aid AND status = 'submitted'"
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $assignmentId]);
        $count = (int) $stmt->fetch()['c'];

        if (!$anonymous) {
            $stmt = $db->prepare(
                "SELECT r.*, u.name AS respondent_name
                 FROM pl_worksheet_responses r
                 LEFT JOIN pl_users u ON u.id = r.respondent_user_id
                 WHERE r.tenant_id = :tid AND r.assignment_id = :aid AND r.status = 'submitted'
                 ORDER BY r.submitted_at DESC"
            );
            $stmt->execute(['tid' => $tenantId, 'aid' => $assignmentId]);

            return [
                'anonymous' => false, 'count' => $count, 'withheld' => false,
                'responses' => $stmt->fetchAll(), 'aggregates' => [],
            ];
        }

        $threshold = (int) $assignment['min_responses'];

        if ($count < $threshold) {
            // Below the threshold, arithmetic de-anonymises. Say how many are
            // needed, and nothing else.
            return [
                'anonymous' => true, 'count' => $count, 'withheld' => true,
                'responses' => [], 'aggregates' => [],
            ];
        }

        return [
            'anonymous' => true, 'count' => $count, 'withheld' => false,
            'responses' => [], 'aggregates' => self::aggregate($tenantId, $assignmentId),
        ];
    }

    /**
     * Per-question aggregates for an anonymous worksheet.
     *
     * Free-text answers are NOT returned. A sentence someone wrote is often
     * identifiable by voice alone, and a team of eight would recognise each
     * other instantly.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function aggregate(int $tenantId, int $assignmentId): array
    {
        $assignment = self::assignment($tenantId, $assignmentId);
        $fields = self::answerableFields($tenantId, (int) $assignment['worksheet_id']);

        $db = Database::conn();
        $out = [];

        foreach ($fields as $f) {
            $type = (string) $f['field_type'];

            if (in_array($type, ['scale', 'number', 'currency'], true)) {
                $stmt = $db->prepare(
                    "SELECT COUNT(a.id) AS n, AVG(a.value_number) AS mean,
                            MIN(a.value_number) AS lowest, MAX(a.value_number) AS highest
                     FROM pl_worksheet_answers a
                     JOIN pl_worksheet_responses r ON r.id = a.response_id
                     WHERE a.tenant_id = :tid AND a.field_id = :fid
                       AND r.assignment_id = :aid AND r.status = 'submitted'
                       AND a.value_number IS NOT NULL"
                );
                $stmt->execute(['tid' => $tenantId, 'fid' => (int) $f['id'], 'aid' => $assignmentId]);
                $row = $stmt->fetch();

                $out[] = [
                    'label' => $f['label'], 'type' => $type, 'n' => (int) $row['n'],
                    'mean' => $row['mean'] === null ? null : round((float) $row['mean'], 2),
                    'lowest' => $row['lowest'], 'highest' => $row['highest'],
                ];
                continue;
            }

            if (in_array($type, ['select_one', 'select_many'], true)) {
                $stmt = $db->prepare(
                    "SELECT a.value_text FROM pl_worksheet_answers a
                     JOIN pl_worksheet_responses r ON r.id = a.response_id
                     WHERE a.tenant_id = :tid AND a.field_id = :fid
                       AND r.assignment_id = :aid AND r.status = 'submitted'
                       AND a.value_text IS NOT NULL"
                );
                $stmt->execute(['tid' => $tenantId, 'fid' => (int) $f['id'], 'aid' => $assignmentId]);

                $counts = [];

                foreach ($stmt->fetchAll() as $r) {
                    foreach (explode("\n", (string) $r['value_text']) as $choice) {
                        $choice = trim($choice);
                        if ($choice !== '') {
                            $counts[$choice] = ($counts[$choice] ?? 0) + 1;
                        }
                    }
                }

                arsort($counts);
                $out[] = ['label' => $f['label'], 'type' => $type, 'counts' => $counts];
                continue;
            }

            // Free text: report only that answers exist. The words themselves
            // identify people.
            $stmt = $db->prepare(
                "SELECT COUNT(a.id) AS n FROM pl_worksheet_answers a
                 JOIN pl_worksheet_responses r ON r.id = a.response_id
                 WHERE a.tenant_id = :tid AND a.field_id = :fid
                   AND r.assignment_id = :aid AND r.status = 'submitted' AND a.value_text IS NOT NULL"
            );
            $stmt->execute(['tid' => $tenantId, 'fid' => (int) $f['id'], 'aid' => $assignmentId]);

            $out[] = [
                'label' => $f['label'], 'type' => $type,
                'n' => (int) $stmt->fetch()['n'],
                'note' => 'Written answers are not shown — the words identify people.',
            ];
        }

        return $out;
    }

    /**
     * The same assessment over time (FR-10.5). The strongest ROI story a coach
     * can show, and it should be one query.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function overTime(int $tenantId, int $worksheetId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.id AS assignment_id, a.label, a.created_at,
                    AVG(r.score_percent) AS score, COUNT(r.id) AS responses
             FROM pl_worksheet_assignments a
             JOIN pl_worksheet_responses r ON r.assignment_id = a.id AND r.status = 'submitted'
             WHERE a.tenant_id = :tid AND a.worksheet_id = :wid AND a.engagement_id = :eid
             GROUP BY a.id
             ORDER BY a.created_at ASC"
        );
        $stmt->execute(['tid' => $tenantId, 'wid' => $worksheetId, 'eid' => $engagementId]);

        $rows = $stmt->fetchAll();

        foreach ($rows as $i => $row) {
            $rows[$i]['score'] = $row['score'] === null ? null : round((float) $row['score'], 2);
            $rows[$i]['delta'] = $i === 0 || $rows[$i - 1]['score'] === null || $row['score'] === null
                ? null
                : round((float) $row['score'] - (float) $rows[$i - 1]['score'], 2);
        }

        return $rows;
    }

    /**
     * FR-10.7. Anonymous worksheets export aggregates, never rows.
     *
     * The $escape argument is passed explicitly as '' throughout: PHP 8.4
     * deprecates omitting it, and empty is what RFC 4180 actually specifies —
     * a CSV has no backslash escaping, only doubled quotes.
     */
    public static function exportCsv(int $tenantId, int $assignmentId): string
    {
        $assignment = self::assignment($tenantId, $assignmentId);

        if ($assignment === null) {
            throw new \RuntimeException('No such assignment.');
        }

        $fields = self::answerableFields($tenantId, (int) $assignment['worksheet_id']);
        $results = self::results($tenantId, $assignmentId);

        $out = fopen('php://temp', 'r+');

        $put = static function (array $row) use ($out): void {
            fputcsv($out, $row, ',', '"', '');
        };

        if ($results['anonymous']) {
            $put(['Question', 'Responses', 'Mean', 'Lowest', 'Highest']);

            if ($results['withheld']) {
                $put(['Withheld — fewer than ' . (int) $assignment['min_responses'] . ' responses', '', '', '', '']);
            } else {
                foreach ($results['aggregates'] as $agg) {
                    $put([
                        $agg['label'],
                        $agg['n'] ?? '',
                        $agg['mean'] ?? '',
                        $agg['lowest'] ?? '',
                        $agg['highest'] ?? '',
                    ]);
                }
            }
        } else {
            $header = ['Respondent', 'Submitted', 'Score %'];

            foreach ($fields as $f) {
                $header[] = (string) $f['label'];
            }

            $put($header);

            foreach ($results['responses'] as $r) {
                $answers = self::answersByField($tenantId, (int) $r['id']);
                $row = [$r['respondent_name'] ?? '(unknown)', $r['submitted_at'], $r['score_percent']];

                foreach ($fields as $f) {
                    $a = $answers[(int) $f['id']] ?? null;
                    $row[] = $a === null ? '' : ($a['value_text'] ?? $a['value_number'] ?? '');
                }

                $put($row);
            }
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    // ------------------------------------------------------------- internals

    /** @return array<string,mixed>|null */
    public static function response(int $tenantId, int $responseId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_worksheet_responses WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $responseId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> Keyed by field id. */
    public static function answersByField(int $tenantId, int $responseId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_worksheet_answers WHERE tenant_id = :tid AND response_id = :rid'
        );
        $stmt->execute(['tid' => $tenantId, 'rid' => $responseId]);

        $out = [];

        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['field_id']] = $row;
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public static function subscores(int $tenantId, int $responseId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_worksheet_subscores WHERE tenant_id = :tid AND response_id = :rid ORDER BY category ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'rid' => $responseId]);

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public static function bands(int $tenantId, int $worksheetId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_worksheet_bands WHERE tenant_id = :tid AND worksheet_id = :wid
             ORDER BY min_percent ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'wid' => $worksheetId]);

        return $stmt->fetchAll();
    }

    /** @param array<int,array<string,mixed>> $bands */
    private static function bandFor(array $bands, ?string $category, float $percent): ?string
    {
        foreach ($bands as $band) {
            $bandCategory = $band['category'] === null ? null : (string) $band['category'];

            if ($bandCategory !== $category) {
                continue;
            }

            if ($percent >= (float) $band['min_percent'] && $percent <= (float) $band['max_percent']) {
                return (string) $band['label'];
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private static function field(int $tenantId, int $fieldId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_worksheet_fields WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $fieldId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
