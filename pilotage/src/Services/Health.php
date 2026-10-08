<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Engagement health (FR-12.2).
 *
 * A composite of five signals: whether commitments get kept, whether sessions
 * actually happen, whether the numbers get entered, whether messages get
 * answered, and whether the client has logged in lately.
 *
 * ---------------------------------------------------------------------------
 * THE RULE THIS CLASS EXISTS TO ENFORCE: never a bare number.
 *
 * A health score is a management tool pointed at a relationship with a real
 * person in it. "Alpha Manufacturing: 61" tells a coach nothing they can act
 * on, and worse, it invites them to act on it anyway. Every score returned here
 * carries its factors — each with its own value, its own weight, and a sentence
 * saying what actually happened. The band is a reading aid, not a verdict.
 *
 * Two consequences fall out of that and are not negotiable:
 *
 *   1. A factor with no evidence is EXCLUDED from the score, not counted as
 *      zero. An engagement with no metrics defined is not failing at metric
 *      entry; there is simply nothing to enter. Scoring absence as failure is
 *      how a health score becomes a number people learn to ignore, because the
 *      first thing it does with a brand-new engagement is call it sick.
 *
 *   2. The score is computed on demand, never stored. A cached number goes
 *      stale silently and starts disagreeing with the factors printed beside
 *      it, and then nobody trusts either. These are five indexed queries.
 * ---------------------------------------------------------------------------
 */
final class Health
{
    /** Days of history each signal looks back over. */
    public const WINDOW_DAYS = 90;

    /**
     * Minimum weight that must actually be measurable before a band is
     * published.
     *
     * Set at 3.0 so that presence alone (weight 1.0) can never produce one. An
     * engagement where the only thing we know is that someone signed in this
     * morning would otherwise render as "Healthy, 100" — technically the
     * weighted mean of everything measurable, and completely misleading. The
     * factors are still shown; what is withheld is the confident summary.
     *
     * This is the same principle as excluding unmeasured factors, applied one
     * level up: a score is only worth stating when there is enough behind it.
     */
    public const MIN_WEIGHT_FOR_BAND = 3.0;

    /**
     * Weights. Deliberately unequal, and the ordering is the argument:
     *
     * Kept commitments matter most, because that is the product. Sessions held
     * come next — an engagement where the meetings stop is over whether or not
     * anyone has said so. Login recency is weighted lowest because it is the
     * weakest evidence of anything: plenty of people do the work and never sign
     * in, and inferring disengagement from it alone is how you insult a client.
     */
    public const WEIGHTS = [
        'commitments' => 3.0,
        'sessions'    => 2.5,
        'metrics'     => 1.5,
        'responses'   => 1.5,
        'presence'    => 1.0,
    ];

    /**
     * Bands. Named rather than colour-coded, because "amber" says nothing about
     * what to do and "drifting" says quite a lot.
     *
     * @var array<int,array{min:int, label:string, note:string}>
     */
    public const BANDS = [
        ['min' => 80, 'label' => 'Healthy',   'note' => 'The rhythm is holding.'],
        ['min' => 60, 'label' => 'Steady',    'note' => 'Working, with something slipping. Worth a look.'],
        ['min' => 40, 'label' => 'Drifting',  'note' => 'More than one signal is weak. Raise it in the next session.'],
        ['min' => 0,  'label' => 'At risk',   'note' => 'This engagement is not working as it stands. Have the conversation.'],
    ];

    /**
     * Score one engagement.
     *
     * @return array{score:?float, band:?string, note:string, factors:array<int,array<string,mixed>>, measured:int}
     */
    public static function forEngagement(int $tenantId, int $engagementId): array
    {
        $factors = [
            self::commitments($tenantId, $engagementId),
            self::sessions($tenantId, $engagementId),
            self::metrics($tenantId, $engagementId),
            self::responses($tenantId, $engagementId),
            self::presence($tenantId, $engagementId),
        ];

        $earned = 0.0;
        $possible = 0.0;
        $measured = 0;

        foreach ($factors as $factor) {
            if ($factor['value'] === null) {
                continue;   // no evidence is not bad evidence
            }

            $weight = self::WEIGHTS[$factor['key']];
            $earned += $factor['value'] * $weight;
            $possible += $weight;
            $measured++;
        }

        // Nothing measurable at all — a brand-new engagement. Say so, rather
        // than inventing a number for it.
        if ($possible <= 0.0) {
            return [
                'score' => null, 'band' => null, 'measured' => 0, 'possible' => 0.0,
                'note' => 'Too early to say. Nothing has happened yet to measure.',
                'factors' => $factors,
            ];
        }

        $score = round($earned / $possible * 100, 1);

        // Enough to compute, not enough to pronounce on.
        if ($possible < self::MIN_WEIGHT_FOR_BAND) {
            $names = [];

            foreach ($factors as $factor) {
                if ($factor['counted']) {
                    $names[] = strtolower($factor['label']);
                }
            }

            return [
                'score' => null, 'band' => null, 'measured' => $measured, 'possible' => $possible,
                'note' => 'Not enough to go on yet — only ' . implode(' and ', $names)
                        . ' can be measured, which is too thin to call this engagement anything.',
                'factors' => $factors,
            ];
        }

        $band = self::bandFor($score);

        return [
            'score'    => $score,
            'band'     => $band['label'],
            'note'     => $band['note'],
            'measured' => $measured,
            'possible' => $possible,
            'factors'  => $factors,
        ];
    }

    /**
     * Every active engagement in the firm, scored. The firm dashboard's spine.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forTenant(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT e.id, e.title, e.client_org_id, e.coach_user_id, e.cadence, e.starts_on,
                    o.name AS org_name, u.name AS coach_name
             FROM pl_engagements e
             JOIN pl_client_orgs o ON o.id = e.client_org_id AND o.tenant_id = e.tenant_id
             LEFT JOIN pl_users u ON u.id = e.coach_user_id AND u.tenant_id = e.tenant_id
             WHERE e.tenant_id = :tid AND e.status = 'active'
             ORDER BY o.name ASC"
        );
        $stmt->execute(['tid' => $tenantId]);

        $out = [];

        foreach ($stmt->fetchAll() as $row) {
            $out[] = $row + ['health' => self::forEngagement($tenantId, (int) $row['id'])];
        }

        return $out;
    }

    /** @return array{min:int, label:string, note:string} */
    public static function bandFor(float $score): array
    {
        foreach (self::BANDS as $band) {
            if ($score >= $band['min']) {
                return $band;
            }
        }

        return self::BANDS[count(self::BANDS) - 1];
    }

    // ------------------------------------------------------------- factors

    /**
     * Commitments kept, over the window.
     *
     * Counts what was DUE in the window, not what was created in it — a task
     * created last year and due last week belongs to last week. Tasks with no
     * due date are excluded, because nothing can be late that was never due.
     *
     * @return array<string,mixed>
     */
    private static function commitments(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT
                COUNT(*) AS due,
                SUM(status = 'done') AS done,
                SUM(status <> 'done' AND due_on < CURDATE()) AS missed
             FROM pl_tasks
             WHERE tenant_id = :tid AND engagement_id = :eid
               AND due_on IS NOT NULL
               AND due_on >= CURDATE() - INTERVAL :days DAY
               AND due_on <= CURDATE()"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId, 'days' => self::WINDOW_DAYS]);
        $row = $stmt->fetch();

        $due = (int) $row['due'];

        if ($due === 0) {
            return self::factor('commitments', 'Commitments kept', null,
                'Nothing has come due in the last ' . self::WINDOW_DAYS . ' days.');
        }

        $done = (int) $row['done'];
        $missed = (int) $row['missed'];

        return self::factor(
            'commitments', 'Commitments kept', $done / $due,
            $done . ' of ' . $due . ' kept'
            . ($missed > 0
                ? ', ' . $missed . ' still open past ' . ($missed === 1 ? 'its date' : 'their dates')
                : '')
            . '.'
        );
    }

    /**
     * Sessions held against sessions booked.
     *
     * A cancelled or no-showed session is the loudest signal in this list, and
     * it is the one a coach is most likely to explain away one at a time.
     *
     * @return array<string,mixed>
     */
    private static function sessions(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT
                COUNT(*) AS booked,
                SUM(status = 'complete') AS held,
                SUM(status = 'cancelled') AS cancelled,
                SUM(status = 'no_show') AS no_show
             FROM pl_sessions
             WHERE tenant_id = :tid AND engagement_id = :eid
               AND scheduled_at IS NOT NULL
               AND scheduled_at >= NOW() - INTERVAL :days DAY
               AND scheduled_at <= NOW()"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId, 'days' => self::WINDOW_DAYS]);
        $row = $stmt->fetch();

        $booked = (int) $row['booked'];

        if ($booked === 0) {
            return self::factor('sessions', 'Sessions held', null,
                'No sessions were scheduled in the window.');
        }

        $held = (int) $row['held'];
        $cancelled = (int) $row['cancelled'];
        $noShow = (int) $row['no_show'];

        // A no-show is named separately from a cancellation. Cancelling is a
        // decision someone communicated; not turning up is not, and rolling
        // them together hides the difference a coach most needs to see.
        $notes = [];

        if ($cancelled > 0) {
            $notes[] = $cancelled . ' cancelled';
        }
        if ($noShow > 0) {
            $notes[] = $noShow . ' not attended';
        }

        return self::factor(
            'sessions', 'Sessions held', $held / $booked,
            $held . ' of ' . $booked . ' held'
            . ($notes === [] ? '' : ', ' . implode(', ', $notes))
            . '.'
        );
    }

    /**
     * Metric entry consistency.
     *
     * Measured against periods that have actually elapsed since the metric was
     * defined. A weekly metric created on Monday is not four weeks behind.
     *
     * @return array<string,mixed>
     */
    private static function metrics(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT m.id, m.name, m.frequency, m.created_at,
                    (SELECT COUNT(DISTINCT v.period_start) FROM pl_metric_values v
                      WHERE v.tenant_id = m.tenant_id AND v.metric_id = m.id
                        AND v.period_start >= CURDATE() - INTERVAL :days DAY) AS entries
             FROM pl_metrics m
             WHERE m.tenant_id = :tid AND m.engagement_id = :eid AND m.active = 1"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId, 'days' => self::WINDOW_DAYS]);
        $metrics = $stmt->fetchAll();

        if ($metrics === []) {
            return self::factor('metrics', 'Numbers kept up to date', null,
                'No metrics are being tracked on this engagement.');
        }

        $expected = 0;
        $actual = 0;
        $behind = [];

        foreach ($metrics as $metric) {
            $days = min(self::WINDOW_DAYS, max(0, (int) floor((time() - strtotime((string) $metric['created_at'])) / 86400)));

            $periodDays = match ((string) $metric['frequency']) {
                'daily'     => 1,
                'weekly'    => 7,
                'monthly'   => 30,
                'quarterly' => 91,
                default     => 7,
            };

            $due = (int) floor($days / $periodDays);

            if ($due <= 0) {
                continue;   // too new to be behind on
            }

            $entries = min($due, (int) $metric['entries']);

            $expected += $due;
            $actual += $entries;

            if ($entries < $due) {
                $behind[] = (string) $metric['name'];
            }
        }

        if ($expected === 0) {
            return self::factor('metrics', 'Numbers kept up to date', null,
                'The metrics here are too new to be behind on.');
        }

        return self::factor(
            'metrics', 'Numbers kept up to date', $actual / $expected,
            $actual . ' of ' . $expected . ' expected entries'
            . ($behind === [] ? '.' : '. Behind: ' . implode(', ', array_slice($behind, 0, 3))
                . (count($behind) > 3 ? ' and ' . (count($behind) - 3) . ' more.' : '.'))
        );
    }

    /**
     * Message responsiveness — does the client side answer?
     *
     * Only counts threads the FIRM started. A coach who never writes is not
     * evidence of an unresponsive client, and measuring it the other way round
     * would let a silent coach score their client down for it.
     *
     * @return array<string,mixed>
     */
    private static function responses(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT
                COUNT(*) AS asked,
                SUM(replied) AS answered
             FROM (
                SELECT t.id,
                       EXISTS (
                         SELECT 1 FROM pl_messages m2
                         JOIN pl_users u2 ON u2.id = m2.author_id AND u2.tenant_id = m2.tenant_id
                         WHERE m2.tenant_id = t.tenant_id AND m2.thread_id = t.id
                           AND u2.client_org_id IS NOT NULL
                       ) AS replied
                FROM pl_threads t
                JOIN pl_messages m ON m.thread_id = t.id AND m.tenant_id = t.tenant_id
                JOIN pl_users u ON u.id = m.author_id AND u.tenant_id = m.tenant_id
                WHERE t.tenant_id = :tid AND t.engagement_id = :eid
                  AND m.client_visible = 1
                  AND u.client_org_id IS NULL
                  AND t.created_at >= NOW() - INTERVAL :days DAY
                GROUP BY t.id
             ) x"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId, 'days' => self::WINDOW_DAYS]);
        $row = $stmt->fetch();

        $asked = (int) $row['asked'];

        if ($asked === 0) {
            return self::factor('responses', 'Messages answered', null,
                'No conversations were started from your side in the window.');
        }

        $answered = (int) $row['answered'];

        return self::factor(
            'responses', 'Messages answered', $answered / $asked,
            $answered . ' of ' . $asked . ' threads got a reply from the client side.'
        );
    }

    /**
     * How recently anyone client-side signed in.
     *
     * Scored on a gentle ramp rather than a cliff, and weighted lowest of the
     * five. Plenty of people do the work and rarely sign in; treating silence
     * here as trouble is how a coach ends up asking an insulting question.
     *
     * @return array<string,mixed>
     */
    private static function presence(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT DATEDIFF(NOW(), MAX(s.created_at)) AS days_since
             FROM pl_user_sessions s
             JOIN pl_users u ON u.id = s.user_id AND u.tenant_id = s.tenant_id
             JOIN pl_engagements e ON e.client_org_id = u.client_org_id AND e.tenant_id = u.tenant_id
             WHERE s.tenant_id = :tid AND e.id = :eid AND u.client_org_id IS NOT NULL"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);
        $row = $stmt->fetch();

        if ($row === false || $row['days_since'] === null) {
            return self::factor('presence', 'Client signing in', null,
                'Nobody client-side has signed in yet.');
        }

        $days = (int) $row['days_since'];

        // Full marks inside a fortnight, nothing after ten weeks, linear between.
        $value = $days <= 14 ? 1.0 : max(0.0, 1.0 - (($days - 14) / 56));

        return self::factor(
            'presence', 'Client signing in', round($value, 3),
            $days === 0 ? 'Someone signed in today.' : 'Last signed in ' . $days . ' day' . ($days === 1 ? '' : 's') . ' ago.'
        );
    }

    /** @return array<string,mixed> */
    private static function factor(string $key, string $label, ?float $value, string $detail): array
    {
        return [
            'key'     => $key,
            'label'   => $label,
            'value'   => $value === null ? null : max(0.0, min(1.0, $value)),
            'percent' => $value === null ? null : (int) round(max(0.0, min(1.0, $value)) * 100),
            'weight'  => self::WEIGHTS[$key],
            'detail'  => $detail,
            'counted' => $value !== null,
        ];
    }
}
