<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Reporting (M12).
 *
 * Two things live here: the firm-wide roll-up behind FR-12.3, and the
 * period engagement report behind FR-12.5 — the renewal conversation in a
 * document. What was done, what moved, what was delivered.
 *
 * ---------------------------------------------------------------------------
 * ON PDF, AND A DELIBERATE DEPARTURE FROM HOUSE CONVENTION
 *
 * The Archipelago convention is DOMPDF, and FR-12.6 names it. This module does
 * not use it, because DOMPDF arrives through Composer and this project has zero
 * Composer dependencies on purpose: the autoloader is hand-rolled so that
 * deployment stays "rsync the files" with no `composer install` on a SiteGround
 * box. Pulling in DOMPDF to render a report would trade that whole property for
 * one screen.
 *
 * So a report renders as an HTML page with a print stylesheet, and the coach
 * uses their browser's Print → Save as PDF. This is not a workaround dressed up
 * as a decision. Browser print output is better than DOMPDF's — real font
 * rendering, proper page breaks, working links, correct Unicode — and it costs
 * nothing to maintain. What it does not do is generate a PDF unattended, which
 * matters exactly once: if a scheduled "email the client their quarterly report"
 * feature is ever built. At that point revisit, and the answer will probably be
 * headless Chrome on a machine that is not SiteGround, not DOMPDF.
 *
 * CSV is native and unqualified. That is the half of FR-12.6 people actually
 * use, because the next thing anyone does with a report is put it in a
 * spreadsheet.
 * ---------------------------------------------------------------------------
 */
final class Reports
{
    /**
     * Everything the period report needs, in one call.
     *
     * @return array<string,mixed>
     */
    public static function engagementReport(
        int $tenantId,
        int $engagementId,
        string $from,
        string $to
    ): array {
        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT e.*, o.name AS org_name, u.name AS coach_name
             FROM pl_engagements e
             JOIN pl_client_orgs o ON o.id = e.client_org_id AND o.tenant_id = e.tenant_id
             LEFT JOIN pl_users u ON u.id = e.coach_user_id AND u.tenant_id = e.tenant_id
             WHERE e.tenant_id = :tid AND e.id = :eid'
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);
        $engagement = $stmt->fetch();

        if ($engagement === false) {
            throw new \RuntimeException('No such engagement.');
        }

        return [
            'engagement'  => $engagement,
            'from'        => $from,
            'to'          => $to,
            'sessions'    => self::sessionsIn($tenantId, $engagementId, $from, $to),
            'commitments' => self::commitmentsIn($tenantId, $engagementId, $from, $to),
            'deliverables' => self::deliverablesIn($tenantId, $engagementId, $from, $to),
            'goals'       => self::goalsIn($tenantId, $engagementId, $from, $to),
            'metrics'     => self::metricMovement($tenantId, $engagementId, $from, $to),
            'assessments' => self::assessmentDeltas($tenantId, $engagementId),
            'issues'      => self::issuesIn($tenantId, $engagementId, $from, $to),
            'health'      => Health::forEngagement($tenantId, $engagementId),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function sessionsIn(int $tenantId, int $engagementId, string $from, string $to): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT s.id, s.title, s.session_type, s.status,
                    COALESCE(s.ended_at, s.scheduled_at) AS held_at,
                    (SELECT COUNT(*) FROM pl_session_attendees a
                      WHERE a.tenant_id = s.tenant_id AND a.session_id = s.id AND a.attended = 1) AS attendees
             FROM pl_sessions s
             WHERE s.tenant_id = :tid AND s.engagement_id = :eid
               AND COALESCE(s.ended_at, s.scheduled_at) BETWEEN :from AND :to
             ORDER BY held_at ASC"
        );
        $stmt->execute(self::bind($tenantId, $engagementId, $from, $to));

        return $stmt->fetchAll();
    }

    /**
     * Commitments that came due in the period, and what became of them.
     *
     * @return array{rows:array<int,array<string,mixed>>, kept:int, total:int}
     */
    public static function commitmentsIn(int $tenantId, int $engagementId, string $from, string $to): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT t.id, t.title, t.status, t.due_on, t.completed_at, t.miss_count,
                    u.name AS owner_name
             FROM pl_tasks t
             LEFT JOIN pl_users u ON u.id = t.owner_user_id AND u.tenant_id = t.tenant_id
             WHERE t.tenant_id = :tid AND t.engagement_id = :eid
               AND t.due_on IS NOT NULL AND t.due_on BETWEEN DATE(:from) AND DATE(:to)
             ORDER BY t.due_on ASC"
        );
        $stmt->execute(self::bind($tenantId, $engagementId, $from, $to));

        $rows = $stmt->fetchAll();
        $kept = 0;

        foreach ($rows as $row) {
            if ((string) $row['status'] === 'done') {
                $kept++;
            }
        }

        return ['rows' => $rows, 'kept' => $kept, 'total' => count($rows)];
    }

    /** @return array<int,array<string,mixed>> */
    public static function deliverablesIn(int $tenantId, int $engagementId, string $from, string $to): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT d.id, d.title, d.status, dd.delivered_at, dd.acknowledged_at
             FROM pl_documents d
             JOIN pl_document_deliveries dd ON dd.document_id = d.id AND dd.tenant_id = d.tenant_id
             WHERE d.tenant_id = :tid AND d.engagement_id = :eid
               AND dd.delivered_at BETWEEN :from AND :to
             ORDER BY dd.delivered_at ASC"
        );
        $stmt->execute(self::bind($tenantId, $engagementId, $from, $to));

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public static function goalsIn(int $tenantId, int $engagementId, string $from, string $to): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT g.id, g.title, g.status, g.quarter_label, g.target_date, g.success_criteria,
                    u.name AS owner_name
             FROM pl_goals g
             LEFT JOIN pl_users u ON u.id = g.owner_user_id AND u.tenant_id = g.tenant_id
             WHERE g.tenant_id = :tid AND g.engagement_id = :eid
               -- Live during the period: it existed by the end of it, and it
               -- had not already run out before the start.
               AND g.created_at <= :to
               AND (g.target_date IS NULL OR g.target_date >= DATE(:from))
             ORDER BY g.quarter_label ASC, g.position ASC, g.id ASC"
        );
        $stmt->execute(self::bind($tenantId, $engagementId, $from, $to));

        return $stmt->fetchAll();
    }

    /**
     * Where each number started the period and where it ended.
     *
     * The single most persuasive thing in a renewal conversation, and the one
     * a coach otherwise assembles by hand out of the scorecard the night
     * before.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function metricMovement(int $tenantId, int $engagementId, string $from, string $to): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT m.id, m.name, m.unit, m.direction, m.target_value,
                    -- Aliased period_first / period_last, NOT first_value /
                    -- last_value: those are reserved in MySQL 8 (the window
                    -- functions) and the parse error they produce points at the
                    -- line after the real one.
                    (SELECT v.value FROM pl_metric_values v
                      WHERE v.tenant_id = m.tenant_id AND v.metric_id = m.id
                        AND v.period_start BETWEEN DATE(:from) AND DATE(:to)
                      ORDER BY v.period_start ASC LIMIT 1) AS period_first,
                    (SELECT v.value FROM pl_metric_values v
                      WHERE v.tenant_id = m.tenant_id AND v.metric_id = m.id
                        AND v.period_start BETWEEN DATE(:from2) AND DATE(:to2)
                      ORDER BY v.period_start DESC LIMIT 1) AS period_last,
                    (SELECT COUNT(*) FROM pl_metric_values v
                      WHERE v.tenant_id = m.tenant_id AND v.metric_id = m.id
                        AND v.period_start BETWEEN DATE(:from3) AND DATE(:to3)) AS entries
             FROM pl_metrics m
             WHERE m.tenant_id = :tid AND m.engagement_id = :eid AND m.active = 1
             ORDER BY m.position ASC, m.id ASC"
        );
        $stmt->execute([
            'tid' => $tenantId, 'eid' => $engagementId,
            'from' => $from, 'to' => $to, 'from2' => $from, 'to2' => $to,
            'from3' => $from, 'to3' => $to,
        ]);

        $rows = $stmt->fetchAll();

        foreach ($rows as $i => $row) {
            $first = $row['period_first'];
            $last = $row['period_last'];

            $rows[$i]['delta'] = ($first === null || $last === null)
                ? null
                : round((float) $last - (float) $first, 2);

            // "Improved" depends on which way the metric is supposed to go.
            // Days-to-collect falling is good; revenue falling is not. The enum
            // is higher/lower, not up/down.
            $rows[$i]['improved'] = $rows[$i]['delta'] === null
                ? null
                : ((string) $row['direction'] === 'lower'
                    ? $rows[$i]['delta'] < 0
                    : $rows[$i]['delta'] > 0);
        }

        return $rows;
    }

    /**
     * Assessment deltas — the same worksheet run more than once (FR-10.5).
     *
     * Not period-limited on purpose. The point of an assessment delta is the
     * whole arc, and clipping it to a quarter throws away the baseline that
     * makes the number mean anything.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function assessmentDeltas(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT DISTINCT a.worksheet_id, w.title
             FROM pl_worksheet_assignments a
             JOIN pl_worksheets w ON w.id = a.worksheet_id AND w.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tid AND a.engagement_id = :eid"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        $out = [];

        foreach ($stmt->fetchAll() as $row) {
            $trend = Worksheets::overTime($tenantId, (int) $row['worksheet_id'], $engagementId);

            // One run is a measurement, not a trend. Nothing to say yet.
            if (count($trend) < 2) {
                continue;
            }

            $first = $trend[0]['score'];
            $last = $trend[count($trend) - 1]['score'];

            $out[] = [
                'title' => (string) $row['title'],
                'runs'  => count($trend),
                'first' => $first,
                'last'  => $last,
                'delta' => ($first === null || $last === null) ? null : round((float) $last - (float) $first, 1),
                'trend' => $trend,
            ];
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public static function issuesIn(int $tenantId, int $engagementId, string $from, string $to): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT i.id, i.title, i.status, i.priority, i.origin, i.created_at, i.resolved_at
             FROM pl_issues i
             WHERE i.tenant_id = :tid AND i.engagement_id = :eid
               AND i.created_at BETWEEN :from AND :to
             ORDER BY i.created_at ASC"
        );
        $stmt->execute(self::bind($tenantId, $engagementId, $from, $to));

        return $stmt->fetchAll();
    }

    // ------------------------------------------------------- firm roll-up

    /**
     * The firm dashboard (FR-12.3).
     *
     * Utilization is expressed as engagements and open commitments per coach,
     * not hours. Pilotage does not track time, and inventing a utilization
     * figure out of data that cannot support one would be worse than not
     * showing it — a firm would make staffing decisions on a made-up number.
     *
     * Revenue per engagement is absent for the same reason: the platform bills
     * the firm (M13) and never the firm's clients (FR-13.4), so it genuinely
     * does not know what an engagement is worth. That stays out until a coach
     * can enter a contract value.
     *
     * @return array<string,mixed>
     */
    public static function firmDashboard(int $tenantId): array
    {
        $db = Database::conn();

        $coaches = $db->prepare(
            "SELECT u.id, u.name, u.role,
                    (SELECT COUNT(*) FROM pl_engagements e
                      WHERE e.tenant_id = u.tenant_id AND e.coach_user_id = u.id AND e.status = 'active') AS engagements,
                    (SELECT COUNT(*) FROM pl_tasks t
                      JOIN pl_engagements e2 ON e2.id = t.engagement_id AND e2.tenant_id = t.tenant_id
                      WHERE t.tenant_id = u.tenant_id AND e2.coach_user_id = u.id
                        AND t.status IN ('open','in_progress')) AS open_commitments,
                    (SELECT COUNT(*) FROM pl_sessions s
                      JOIN pl_engagements e3 ON e3.id = s.engagement_id AND e3.tenant_id = s.tenant_id
                      WHERE s.tenant_id = u.tenant_id AND e3.coach_user_id = u.id
                        AND s.status = 'complete'
                        AND COALESCE(s.ended_at, s.scheduled_at) >= NOW() - INTERVAL 30 DAY) AS sessions_30d
             FROM pl_users u
             WHERE u.tenant_id = :tid AND u.client_org_id IS NULL AND u.status = 'active'
             ORDER BY u.name ASC"
        );
        $coaches->execute(['tid' => $tenantId]);

        $playbooks = $db->prepare(
            // Completion is derived from steps, not stored on the instance —
            // there is no status column on pl_engagement_playbooks, and a
            // denormalised one would be a second source of truth to keep in
            // step with the runner.
            "SELECT p.id, p.name,
                    COUNT(DISTINCT ep.id) AS applied,
                    COUNT(es.id) AS steps,
                    SUM(es.status IN ('complete','skipped')) AS steps_done
             FROM pl_playbooks p
             JOIN pl_engagement_playbooks ep
                    ON ep.playbook_id = p.id AND ep.tenant_id = p.tenant_id
             LEFT JOIN pl_engagement_steps es
                    ON es.engagement_playbook_id = ep.id AND es.tenant_id = ep.tenant_id
             WHERE p.tenant_id = :tid
             GROUP BY p.id
             ORDER BY applied DESC"
        );
        $playbooks->execute(['tid' => $tenantId]);

        $counts = $db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM pl_engagements WHERE tenant_id = :tid AND status = 'active') AS active,
                (SELECT COUNT(*) FROM pl_engagements WHERE tenant_id = :tid2 AND status = 'complete') AS completed,
                (SELECT COUNT(*) FROM pl_client_orgs WHERE tenant_id = :tid3 AND status = 'active') AS clients,
                (SELECT COUNT(*) FROM pl_users WHERE tenant_id = :tid4 AND client_org_id IS NULL AND status = 'active') AS seats"
        );
        $counts->execute(['tid' => $tenantId, 'tid2' => $tenantId, 'tid3' => $tenantId, 'tid4' => $tenantId]);

        $playbookRows = $playbooks->fetchAll();

        foreach ($playbookRows as $i => $row) {
            $steps = (int) $row['steps'];
            $playbookRows[$i]['completion'] = $steps === 0
                ? null
                : (int) round(((int) $row['steps_done']) / $steps * 100);
        }

        $engagements = Health::forTenant($tenantId);

        $atRisk = array_values(array_filter($engagements, static function (array $e): bool {
            $score = $e['health']['score'];

            return $score !== null && $score < 60;
        }));

        usort($atRisk, static fn (array $a, array $b): int => $a['health']['score'] <=> $b['health']['score']);

        return [
            'counts'      => $counts->fetch(),
            'coaches'     => $coaches->fetchAll(),
            'playbooks'   => $playbookRows,
            'engagements' => $engagements,
            'at_risk'     => $atRisk,
            'slipped'     => SessionService::cadenceSlipped($tenantId),
        ];
    }

    // -------------------------------------------------------------- export

    /**
     * The report as CSV.
     *
     * Sectioned rather than one wide table with mostly-empty columns. A report
     * genuinely is several different shapes of thing, and flattening them into
     * a single grid produces a file nobody can use for either.
     *
     * @param array<string,mixed> $report
     */
    public static function toCsv(array $report): string
    {
        $out = fopen('php://temp', 'r+');

        if ($out === false) {
            throw new \RuntimeException('Cannot open a buffer for the export.');
        }

        // Explicit escape argument: PHP 8.4 deprecates the implicit default,
        // and '' means "no escape character", which is what RFC 4180 says.
        $put = static function (array $row) use ($out): void {
            fputcsv($out, $row, ',', '"', '');
        };

        $engagement = $report['engagement'];

        $put(['Engagement report']);
        $put(['Client', (string) $engagement['org_name']]);
        $put(['Engagement', (string) $engagement['title']]);
        $put(['Period', (string) $report['from'], 'to', (string) $report['to']]);
        $put([]);

        $put(['Sessions']);
        $put(['Date', 'Title', 'Type', 'Status', 'Attended']);

        foreach ($report['sessions'] as $s) {
            $put([
                substr((string) $s['held_at'], 0, 10), $s['title'], $s['session_type'],
                $s['status'], $s['attendees'],
            ]);
        }

        $put([]);
        $put(['Commitments', $report['commitments']['kept'] . ' of ' . $report['commitments']['total'] . ' kept']);
        $put(['Due', 'Title', 'Owner', 'Status', 'Times missed']);

        foreach ($report['commitments']['rows'] as $t) {
            $put([$t['due_on'], $t['title'], $t['owner_name'] ?? '', $t['status'], $t['miss_count']]);
        }

        $put([]);
        $put(['Deliverables']);
        $put(['Delivered', 'Title', 'Acknowledged']);

        foreach ($report['deliverables'] as $d) {
            $put([substr((string) $d['delivered_at'], 0, 10), $d['title'], $d['acknowledged_at'] ?? 'not yet']);
        }

        $put([]);
        $put(['Goals']);
        $put(['Quarter', 'Goal', 'Status', 'Owner', 'Target date']);

        foreach ($report['goals'] as $g) {
            $put([$g['quarter_label'], $g['title'], $g['status'], $g['owner_name'] ?? '', $g['target_date'] ?? '']);
        }

        $put([]);
        $put(['Metric movement']);
        $put(['Metric', 'Unit', 'Start of period', 'End of period', 'Change', 'Entries']);

        foreach ($report['metrics'] as $m) {
            $put([
                $m['name'], $m['unit'], num($m['period_first']), num($m['period_last']),
                num($m['delta']), $m['entries'],
            ]);
        }

        if ($report['assessments'] !== []) {
            $put([]);
            $put(['Assessments']);
            $put(['Assessment', 'Runs', 'First', 'Latest', 'Change']);

            foreach ($report['assessments'] as $a) {
                $put([$a['title'], $a['runs'], $a['first'], $a['last'], $a['delta'] ?? '']);
            }
        }

        $put([]);
        $put(['Health']);
        $put(['Score', $report['health']['score'] ?? 'not enough data', $report['health']['band'] ?? '']);
        $put(['Factor', 'Score', 'Weight', 'Detail']);

        foreach ($report['health']['factors'] as $f) {
            $put([
                $f['label'],
                $f['counted'] ? $f['percent'] . '%' : 'not measured',
                $f['weight'],
                $f['detail'],
            ]);
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * Sane default period: the quarter that just ended, or this one if it is
     * more than half over. A coach opening the report screen in November wants
     * Q4-to-date, not Q3 again.
     *
     * @return array{0:string, 1:string}
     */
    public static function defaultPeriod(?string $today = null): array
    {
        $now = $today === null ? time() : (int) strtotime($today);
        $month = (int) date('n', $now);
        $year = (int) date('Y', $now);

        $quarterStartMonth = ((int) floor(($month - 1) / 3)) * 3 + 1;
        $start = sprintf('%04d-%02d-01', $year, $quarterStartMonth);

        return [$start, date('Y-m-d', $now)];
    }

    /** @return array<string,mixed> */
    private static function bind(int $tenantId, int $engagementId, string $from, string $to): array
    {
        return [
            'tid' => $tenantId, 'eid' => $engagementId,
            'from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59',
        ];
    }
}
