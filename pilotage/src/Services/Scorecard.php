<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Goals, metrics, and the scorecard (M9).
 *
 * The 13-week quarter is the unit the whole product assumes, so periods are
 * computed from a fixed anchor rather than from whenever someone happened to
 * click. Two people opening the scorecard on different days must see the same
 * columns, or the conversation is about the tool instead of the business.
 */
final class Scorecard
{
    /** Above this, a quarter's priority list stops being a priority list. */
    public const GOAL_SOFT_CAP = 7;
    public const GOAL_FLOOR = 3;

    /** Columns in the grid — one quarter of weeks (FR-9.4). */
    public const PERIODS = 13;

    // ------------------------------------------------------------------ goals

    /** @param array<string,mixed> $data */
    public static function createGoal(int $tenantId, int $engagementId, array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw new \InvalidArgumentException('A goal needs a title.');
        }

        $targetDate = $data['target_date'] ?? self::quarterEnd();
        $quarter = self::quarterLabel($targetDate);

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_goals
                (tenant_id, engagement_id, title, success_criteria, owner_user_id, target_date, quarter_label, position)
             VALUES (:tid, :eid, :title, :criteria, :owner, :target, :quarter,
                     (SELECT COALESCE(MAX(g.position), -1) + 1 FROM pl_goals g
                       WHERE g.tenant_id = :tid2 AND g.engagement_id = :eid2 AND g.quarter_label = :quarter2))'
        )->execute([
            'tid' => $tenantId, 'tid2' => $tenantId,
            'eid' => $engagementId, 'eid2' => $engagementId,
            'title' => mb_substr($title, 0, 255),
            'criteria' => $data['success_criteria'] ?? null,
            'owner' => $data['owner_user_id'] ?? null,
            'target' => $targetDate,
            'quarter' => $quarter, 'quarter2' => $quarter,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * How full is this quarter's list? The soft cap is advice, not a block —
     * refusing an eighth goal would just mean the eighth lives in someone's
     * head instead, which is worse. But a coach should see the warning.
     *
     * @return array{count:int, over_cap:bool, under_floor:bool, advice:?string}
     */
    public static function goalLoad(int $tenantId, int $engagementId, ?string $quarter = null): array
    {
        $quarter = $quarter ?? self::quarterLabel();

        $stmt = Database::conn()->prepare(
            "SELECT COUNT(*) AS c FROM pl_goals
             WHERE tenant_id = :tid AND engagement_id = :eid AND quarter_label = :q
               AND status NOT IN ('dropped')"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId, 'q' => $quarter]);

        $count = (int) $stmt->fetch()['c'];

        return [
            'count'       => $count,
            'over_cap'    => $count > self::GOAL_SOFT_CAP,
            'under_floor' => $count > 0 && $count < self::GOAL_FLOOR,
            'advice'      => match (true) {
                $count > self::GOAL_SOFT_CAP => 'That is more than ' . self::GOAL_SOFT_CAP
                    . ' priorities for one quarter. A list of twelve priorities is a list of none —'
                    . ' worth asking which of these actually has to happen by the target date.',
                $count === 0 => 'No priorities set for this quarter yet.',
                default => null,
            },
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function goals(int $tenantId, int $engagementId, ?string $quarter = null): array
    {
        $quarter = $quarter ?? self::quarterLabel();

        $stmt = Database::conn()->prepare(
            'SELECT g.*, u.name AS owner_name FROM pl_goals g
             LEFT JOIN pl_users u ON u.id = g.owner_user_id
             WHERE g.tenant_id = :tid AND g.engagement_id = :eid AND g.quarter_label = :q
             ORDER BY g.position ASC, g.id ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId, 'q' => $quarter]);

        $goals = $stmt->fetchAll();

        $milestones = Database::conn()->prepare(
            'SELECT * FROM pl_goal_milestones WHERE tenant_id = :tid AND goal_id = :gid ORDER BY position ASC'
        );

        foreach ($goals as $i => $goal) {
            $milestones->execute(['tid' => $tenantId, 'gid' => (int) $goal['id']]);
            $goals[$i]['milestones'] = $milestones->fetchAll();
        }

        return $goals;
    }

    public static function setGoalStatus(int $tenantId, int $goalId, string $status): bool
    {
        if (!in_array($status, ['on_track', 'at_risk', 'off_track', 'done', 'dropped'], true)) {
            throw new \InvalidArgumentException('Unknown goal status.');
        }

        $stmt = Database::conn()->prepare(
            'UPDATE pl_goals SET status = :s WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute(['s' => $status, 'tid' => $tenantId, 'id' => $goalId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Quarterly rollover (FR-9.2): score what happened, then carry forward or
     * close.
     *
     * Carrying forward creates a NEW goal linked to the old one rather than
     * moving the old one's date. A priority that has slipped two quarters
     * should be visibly a priority that has slipped two quarters.
     *
     * @param array<int,string> $decisions goalId => 'done'|'carry'|'drop'
     * @return array{scored:int, carried:int}
     */
    public static function rollQuarter(int $tenantId, int $engagementId, array $decisions, ?string $intoQuarter = null): array
    {
        $db = Database::conn();
        $intoQuarter = $intoQuarter ?? self::quarterLabel(self::nextQuarterEnd());
        $intoDate = self::quarterEndFromLabel($intoQuarter);

        $scored = 0;
        $carried = 0;

        foreach ($decisions as $goalId => $decision) {
            $goalId = (int) $goalId;

            $stmt = $db->prepare('SELECT * FROM pl_goals WHERE tenant_id = :tid AND id = :id LIMIT 1');
            $stmt->execute(['tid' => $tenantId, 'id' => $goalId]);
            $goal = $stmt->fetch();

            if ($goal === false || (int) $goal['engagement_id'] !== $engagementId) {
                continue;
            }

            $status = match ($decision) {
                'done'  => 'done',
                'drop'  => 'dropped',
                default => 'off_track',   // carried forward means it did not land
            };

            $db->prepare('UPDATE pl_goals SET status = :s, reviewed_at = NOW() WHERE tenant_id = :tid AND id = :id')
               ->execute(['s' => $status, 'tid' => $tenantId, 'id' => $goalId]);

            $scored++;

            if ($decision === 'carry') {
                $db->prepare(
                    'INSERT INTO pl_goals
                        (tenant_id, engagement_id, title, success_criteria, owner_user_id,
                         target_date, quarter_label, carried_from_id)
                     VALUES (:tid, :eid, :title, :criteria, :owner, :target, :quarter, :from)'
                )->execute([
                    'tid' => $tenantId, 'eid' => $engagementId,
                    'title' => (string) $goal['title'],
                    'criteria' => $goal['success_criteria'],
                    'owner' => $goal['owner_user_id'],
                    'target' => $intoDate,
                    'quarter' => $intoQuarter,
                    'from' => $goalId,
                ]);
                $carried++;
            }
        }

        return ['scored' => $scored, 'carried' => $carried];
    }

    // ---------------------------------------------------------------- metrics

    /** @param array<string,mixed> $data */
    public static function createMetric(int $tenantId, int $engagementId, array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('A metric needs a name.');
        }

        $direction = (string) ($data['direction'] ?? 'higher');

        if (!in_array($direction, ['higher', 'lower'], true)) {
            throw new \InvalidArgumentException('Direction must be higher or lower.');
        }

        $frequency = (string) ($data['frequency'] ?? 'weekly');

        if (!in_array($frequency, ['weekly', 'monthly', 'quarterly'], true)) {
            throw new \InvalidArgumentException('Unknown frequency.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_metrics
                (tenant_id, engagement_id, name, unit, direction, target_value, frequency, owner_user_id, position)
             VALUES (:tid, :eid, :name, :unit, :dir, :target, :freq, :owner,
                     (SELECT COALESCE(MAX(m.position), -1) + 1 FROM pl_metrics m
                       WHERE m.tenant_id = :tid2 AND m.engagement_id = :eid2))'
        )->execute([
            'tid' => $tenantId, 'tid2' => $tenantId,
            'eid' => $engagementId, 'eid2' => $engagementId,
            'name' => mb_substr($name, 0, 255),
            'unit' => $data['unit'] ?? null,
            'dir' => $direction,
            'target' => $data['target_value'] ?? null,
            'freq' => $frequency,
            'owner' => $data['owner_user_id'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Record a number. Upserts on (metric, period) so a correction replaces
     * rather than duplicates.
     */
    public static function record(
        int $tenantId,
        int $metricId,
        string $periodStart,
        float $value,
        int $enteredBy,
        bool $onBehalf = false,
        ?string $note = null
    ): bool {
        $metric = self::metric($tenantId, $metricId);

        if ($metric === null) {
            return false;
        }

        $periodStart = self::normalizePeriod($periodStart, (string) $metric['frequency']);

        Database::conn()->prepare(
            'INSERT INTO pl_metric_values (tenant_id, metric_id, period_start, value, entered_by, on_behalf, note)
             VALUES (:tid, :mid, :period, :val, :by, :behalf, :note)
             ON DUPLICATE KEY UPDATE value = VALUES(value), entered_by = VALUES(entered_by),
                                     on_behalf = VALUES(on_behalf), note = VALUES(note)'
        )->execute([
            'tid' => $tenantId, 'mid' => $metricId, 'period' => $periodStart,
            'val' => $value, 'by' => $enteredBy, 'behalf' => $onBehalf ? 1 : 0, 'note' => $note,
        ]);

        return true;
    }

    /** @return array<string,mixed>|null */
    public static function metric(int $tenantId, int $metricId): ?array
    {
        $stmt = Database::conn()->prepare('SELECT * FROM pl_metrics WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $metricId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * The grid: metrics down the side, periods across (FR-9.4).
     *
     * @return array{periods:array<int,string>, rows:array<int,array<string,mixed>>}
     */
    public static function grid(int $tenantId, int $engagementId, ?string $endingOn = null): array
    {
        $periods = self::periodStarts($endingOn);

        $stmt = Database::conn()->prepare(
            'SELECT m.*, u.name AS owner_name FROM pl_metrics m
             LEFT JOIN pl_users u ON u.id = m.owner_user_id
             WHERE m.tenant_id = :tid AND m.engagement_id = :eid AND m.active = 1
             ORDER BY m.position ASC, m.id ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);
        $metrics = $stmt->fetchAll();

        $values = Database::conn()->prepare(
            'SELECT * FROM pl_metric_values
             WHERE tenant_id = :tid AND metric_id = :mid AND period_start >= :from'
        );

        $rows = [];

        foreach ($metrics as $metric) {
            $values->execute([
                'tid' => $tenantId,
                'mid' => (int) $metric['id'],
                'from' => $periods[0],
            ]);

            $byPeriod = [];
            foreach ($values->fetchAll() as $v) {
                $byPeriod[(string) $v['period_start']] = $v;
            }

            $cells = [];
            $onTarget = 0;
            $recorded = 0;

            foreach ($periods as $period) {
                $v = $byPeriod[$period] ?? null;

                if ($v === null) {
                    $cells[] = ['period' => $period, 'value' => null, 'hit' => null, 'on_behalf' => false];
                    continue;
                }

                $recorded++;
                $hit = self::hitsTarget($metric, (float) $v['value']);

                if ($hit === true) {
                    $onTarget++;
                }

                $cells[] = [
                    'period'    => $period,
                    'value'     => (float) $v['value'],
                    'hit'       => $hit,
                    'on_behalf' => (int) $v['on_behalf'] === 1,
                    'note'      => $v['note'],
                ];
            }

            $rows[] = $metric + [
                'cells'       => $cells,
                'recorded'    => $recorded,
                'on_target'   => $onTarget,
                'entry_rate'  => count($periods) === 0 ? 0 : (int) round($recorded / count($periods) * 100),
            ];
        }

        return ['periods' => $periods, 'rows' => $rows];
    }

    /** Null when there is no target to compare against. */
    public static function hitsTarget(array $metric, float $value): ?bool
    {
        if ($metric['target_value'] === null) {
            return null;
        }

        $target = (float) $metric['target_value'];

        return (string) $metric['direction'] === 'lower' ? $value <= $target : $value >= $target;
    }

    /**
     * Metrics with no number for the current period — the nag list, and the
     * "the numbers" agenda block.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function missingThisPeriod(int $tenantId, int $engagementId, ?string $on = null): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT m.*, u.name AS owner_name FROM pl_metrics m
             LEFT JOIN pl_users u ON u.id = m.owner_user_id
             WHERE m.tenant_id = :tid AND m.engagement_id = :eid AND m.active = 1
             ORDER BY m.position ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        $check = Database::conn()->prepare(
            'SELECT 1 FROM pl_metric_values
             WHERE tenant_id = :tid AND metric_id = :mid AND period_start = :period LIMIT 1'
        );

        $missing = [];

        foreach ($stmt->fetchAll() as $metric) {
            $period = self::normalizePeriod($on ?? date('Y-m-d'), (string) $metric['frequency']);
            $check->execute(['tid' => $tenantId, 'mid' => (int) $metric['id'], 'period' => $period]);

            if ($check->fetch() === false) {
                $metric['period_start'] = $period;
                $missing[] = $metric;
            }
        }

        return $missing;
    }

    // ----------------------------------------------------------------- issues

    /** @return array<int,array<string,mixed>> */
    public static function issues(int $tenantId, int $engagementId, bool $openOnly = true): array
    {
        $sql = 'SELECT i.*, u.name AS owner_name FROM pl_issues i
                LEFT JOIN pl_users u ON u.id = i.owner_user_id
                WHERE i.tenant_id = :tid AND i.engagement_id = :eid';

        if ($openOnly) {
            $sql .= " AND i.status IN ('open','discussing')";
        }

        $sql .= " ORDER BY FIELD(i.priority,'high','normal','low'), i.created_at ASC";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        return $stmt->fetchAll();
    }

    public static function raiseIssue(
        int $tenantId,
        int $engagementId,
        string $title,
        ?string $detail = null,
        string $origin = 'ad_hoc',
        ?int $raisedBy = null,
        string $priority = 'normal'
    ): int {
        $title = trim($title);

        if ($title === '') {
            throw new \InvalidArgumentException('An issue needs a title.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_issues (tenant_id, engagement_id, title, detail, origin, priority, raised_by)
             VALUES (:tid, :eid, :title, :detail, :origin, :priority, :by)'
        )->execute([
            'tid' => $tenantId, 'eid' => $engagementId,
            'title' => mb_substr($title, 0, 255), 'detail' => $detail,
            'origin' => $origin, 'priority' => $priority, 'by' => $raisedBy,
        ]);

        $issueId = (int) $db->lastInsertId();

        // Both sides hear about it. An issue list only works if it is a shared
        // list — one where the coach quietly logs concerns the client never
        // sees is a private grievance file, not an accountability tool.
        Notifications::queueMany(
            $tenantId,
            array_merge(
                Notifications::firmRecipients($tenantId, $engagementId),
                Notifications::clientRecipients($tenantId, $engagementId)
            ),
            'issue.raised',
            'New issue: ' . $title,
            $detail === null ? null : mb_substr($detail, 0, 200),
            '/engagements/' . $engagementId . '/scoreboard',
            ['object_type' => 'issue', 'object_id' => $issueId]
                + Notifications::orgContext($tenantId, $engagementId),
            $raisedBy
        );

        return $issueId;
    }


    /**
     * Resolve an issue with a record of what was identified, discussed, and
     * decided (FR-9.7).
     *
     * A decision is required. An issue closed with nothing decided is an issue
     * that will be raised again next month, and the point of the record is to
     * stop that.
     */
    public static function resolveIssue(
        int $tenantId,
        int $issueId,
        string $identified,
        string $discussed,
        string $decided,
        int $userId
    ): bool {
        if (trim($decided) === '') {
            throw new \InvalidArgumentException('An issue is not resolved until something is decided.');
        }

        $db = Database::conn();

        $stmt = $db->prepare(
            "UPDATE pl_issues SET status = 'resolved', resolved_at = NOW()
             WHERE tenant_id = :tid AND id = :id AND status IN ('open','discussing')"
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $issueId]);

        if ($stmt->rowCount() !== 1) {
            return false;
        }

        $db->prepare(
            'INSERT INTO pl_issue_resolutions (tenant_id, issue_id, identified, discussed, decided, resolved_by)
             VALUES (:tid, :iid, :ident, :disc, :dec, :by)
             ON DUPLICATE KEY UPDATE identified = VALUES(identified), discussed = VALUES(discussed),
                                     decided = VALUES(decided), resolved_by = VALUES(resolved_by)'
        )->execute([
            'tid' => $tenantId, 'iid' => $issueId,
            'ident' => trim($identified) ?: null, 'disc' => trim($discussed) ?: null,
            'dec' => trim($decided), 'by' => $userId,
        ]);

        return true;
    }

    // ------------------------------------------------------- agenda providers

    /** M9 pays off the last three agenda blocks M5 was waiting on. */
    public static function registerAgendaProviders(): void
    {
        AgendaBuilder::registerProvider('metrics', static function (int $tenantId, array $session): array {
            $grid = self::grid($tenantId, (int) $session['engagement_id']);
            $items = [];

            foreach ($grid['rows'] as $row) {
                $latest = null;
                foreach (array_reverse($row['cells']) as $cell) {
                    if ($cell['value'] !== null) { $latest = $cell; break; }
                }

                if ($latest === null) {
                    $items[] = $row['name'] . ' — no number yet';
                    continue;
                }

                $mark = $latest['hit'] === null ? '' : ($latest['hit'] ? ' — on target' : ' — OFF TARGET');
                $items[] = $row['name'] . ': ' . rtrim(rtrim(number_format($latest['value'], 2), '0'), '.')
                    . ($row['unit'] ? ' ' . $row['unit'] : '') . $mark;
            }

            return ['items' => $items, 'note' => $items === [] ? 'No numbers on the scorecard yet.' : null];
        });

        AgendaBuilder::registerProvider('goals', static function (int $tenantId, array $session): array {
            $items = [];

            foreach (self::goals($tenantId, (int) $session['engagement_id']) as $goal) {
                $items[] = $goal['title'] . ' — ' . str_replace('_', ' ', (string) $goal['status'])
                    . ($goal['owner_name'] ? ' (' . $goal['owner_name'] . ')' : '');
            }

            return ['items' => $items, 'note' => $items === [] ? 'No priorities set for this quarter.' : null];
        });

        AgendaBuilder::registerProvider('issues', static function (int $tenantId, array $session): array {
            $items = [];

            foreach (self::issues($tenantId, (int) $session['engagement_id']) as $issue) {
                $items[] = ((string) $issue['priority'] === 'high' ? '! ' : '') . $issue['title'];
            }

            return ['items' => $items, 'note' => $items === [] ? 'Nothing on the list.' : null];
        });
    }

    // ----------------------------------------------------------- period maths

    /** @return array<int,string> The PERIODS period-starts ending with the current one. */
    public static function periodStarts(?string $endingOn = null, string $frequency = 'weekly'): array
    {
        $end = self::normalizePeriod($endingOn ?? date('Y-m-d'), $frequency);
        $step = match ($frequency) {
            'monthly'   => '1 month',
            'quarterly' => '3 months',
            default     => '1 week',
        };

        $periods = [];

        for ($i = self::PERIODS - 1; $i >= 0; $i--) {
            $periods[] = date('Y-m-d', strtotime($end . ' -' . ($i * (int) explode(' ', $step)[0]) . ' ' . explode(' ', $step)[1]));
        }

        return $periods;
    }

    /** The first day of the period containing a date. Monday-anchored weeks. */
    public static function normalizePeriod(string $date, string $frequency = 'weekly'): string
    {
        $ts = strtotime($date);

        if ($ts === false) {
            throw new \InvalidArgumentException('Unparseable date.');
        }

        return match ($frequency) {
            'monthly'   => date('Y-m-01', $ts),
            'quarterly' => date('Y-m-01', mktime(0, 0, 0, (int) (floor((((int) date('n', $ts)) - 1) / 3) * 3) + 1, 1, (int) date('Y', $ts))),
            default     => date('Y-m-d', strtotime('monday this week', $ts)),
        };
    }

    public static function quarterLabel(?string $date = null): string
    {
        $ts = strtotime($date ?? 'now');
        $ts = $ts === false ? time() : $ts;

        return date('Y', $ts) . '-Q' . (int) ceil(((int) date('n', $ts)) / 3);
    }

    public static function quarterEnd(?string $date = null): string
    {
        $ts = strtotime($date ?? 'now');
        $ts = $ts === false ? time() : $ts;
        $q = (int) ceil(((int) date('n', $ts)) / 3);

        return date('Y-m-t', mktime(0, 0, 0, $q * 3, 1, (int) date('Y', $ts)));
    }

    public static function nextQuarterEnd(?string $date = null): string
    {
        return self::quarterEnd(date('Y-m-d', strtotime(self::quarterEnd($date) . ' +1 day')));
    }

    public static function quarterEndFromLabel(string $label): string
    {
        if (!preg_match('/^(\d{4})-Q([1-4])$/', $label, $m)) {
            throw new \InvalidArgumentException('Bad quarter label.');
        }

        return date('Y-m-t', mktime(0, 0, 0, ((int) $m[2]) * 3, 1, (int) $m[1]));
    }
}
