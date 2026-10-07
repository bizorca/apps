<?php
declare(strict_types=1);

require_once __DIR__ . '/calc.php';

/**
 * The instrument registry.
 *
 * One entry per assessment tool. Order here is the order the client page
 * shows them, and it is the four-layer diagnostic order: Regulatory,
 * Financial, Offer, Operator, then the close. That order is itself the
 * prioritization rule, so it is data, not a hardcoded sequence of links.
 *
 *   key      slug used in the DB and the URL
 *   page     file under public/a/
 *   layer    R | F | O | Op | '' (the close spans all four)
 *   unit     curriculum reference, shown to the advisor
 */
function instruments(): array
{
    return [
        'regulatory' => [
            'name'     => 'Regulatory Intake Screen',
            'page'     => 'regulatory.php',
            'layer'    => 'R',
            'unit'     => 'Unit 1.1',
            'summary'  => 'Entity, licensing, tax registration, worker classification, insurance.',
            'why'      => 'Regulatory comes first because it is the only layer where one problem can end the business overnight.',
            'duration' => 'First meeting',
        ],
        'tax_flags' => [
            'name'     => 'Tax Exposure Flag List',
            'page'     => 'tax-flags.php',
            'layer'    => 'R',
            'unit'     => 'Unit 1.2',
            'summary'  => 'Name the tax exposure, frame the question, refer it out.',
            'why'      => 'The advisor names the issue and refers. Nothing on this screen is advice.',
            'duration' => '15 minutes',
        ],
        'health' => [
            'name'     => 'Three-Metric Health Check',
            'page'     => 'health.php',
            'layer'    => 'F',
            'unit'     => 'Unit 2.4',
            'summary'  => '90-day cash flow, fixed-cost burn and runway, quick liquidity.',
            'why'      => 'A 20-minute read of whether the books are telling the truth. Works from bank statements, not the P&L.',
            'duration' => '20 minutes',
        ],
        'ehr' => [
            'name'     => 'EHR Worksheet and Calculator',
            'page'     => 'ehr.php',
            'layer'    => 'F',
            'unit'     => 'Unit 2.2',
            'summary'  => 'What an hour of the owner\'s life actually earns, and the pricing floor.',
            'why'      => 'The single most clarifying number for most owners.',
            'duration' => '30 minutes',
        ],
        'swot' => [
            'name'     => 'SWOT with the Evidence Rule, and TOWS',
            'page'     => 'swot.php',
            'layer'    => 'O',
            'unit'     => 'Unit 7.1',
            'summary'  => 'A familiar opener that aims the sharper tools. Every entry needs evidence.',
            'why'      => 'A conversation tool, not a deliverable.',
            'duration' => 'First meeting',
        ],
        'capacity' => [
            'name'     => 'Capacity Audit and Handoff Test',
            'page'     => 'capacity.php',
            'layer'    => 'Op',
            'unit'     => 'Units 3.1 and 3.2',
            'summary'  => 'Where the owner\'s week goes, and what a successor would need.',
            'why'      => 'If you had to hand this business to someone next month, what would they need?',
            'duration' => '30 minutes',
        ],
        'priority' => [
            'name'     => 'Priority and Next Action',
            'page'     => 'priority.php',
            'layer'    => '',
            'unit'     => 'Unit 7.5',
            'summary'  => 'Immediate exposure, candidate issues, one priority, one committed action.',
            'why'      => 'Closes every assessment meeting. One priority beats a list of eleven.',
            'duration' => 'End of meeting',
        ],
    ];
}

function instrument(string $key): ?array
{
    $all = instruments();
    if (!isset($all[$key])) return null;
    return $all[$key] + ['key' => $key];
}

function instrumentName(string $key): string
{
    return instruments()[$key]['name'] ?? $key;
}

/** URL of an instrument page for one client. */
function instrumentUrl(string $key, int $clientId, array $params = []): string
{
    $page = instruments()[$key]['page'] ?? '';
    if ($page === '') return url('/client.php?id=' . $clientId);
    $q = http_build_query(['client' => $clientId] + $params);
    return url('/a/' . $page . '?' . $q);
}

/**
 * Per-instrument headline for the client page card.
 *
 * Returns [reading, headline]. Reading drives the badge colour; headline is
 * the one figure worth seeing without opening the worksheet.
 */
function instrumentSummary(string $key, ?array $assessment): array
{
    if (!$assessment) return [READ_UNKNOWN, 'Not started'];
    $d = $assessment['data'] ?? [];

    switch ($key) {
        case 'health':
            $hc = calcHealthCheck($d);
            if ($hc['overall'] === READ_UNKNOWN) return [READ_UNKNOWN, 'In progress'];
            return [$hc['overall'], sprintf(
                'Net %s · runway %s · liquidity %s',
                money0($hc['m1']['net']),
                $hc['m2']['burn'] > 0 ? number_format($hc['m2']['runway'], 1) . ' mo' : '—',
                $hc['m3']['bills']  > 0 ? number_format($hc['m3']['ratio'], 2)       : '—'
            )];

        case 'ehr':
            $e = calcEHR($d);
            if ($e['b10'] <= 0) return [READ_UNKNOWN, 'In progress'];
            $reading = ($e['c2'] > 0 && $e['c3'] < 0.35) ? READ_ACT
                     : (($e['gap'] > 0) ? READ_WATCH : READ_HEALTHY);
            $bits = ['EHR ' . money($e['c1'])];
            if ($e['c2'] > 0)  $bits[] = pct($e['c3']) . ' of the quoted rate';
            if ($e['d4'] > 0)  $bits[] = 'floor ' . money($e['d4']);
            return [$reading, implode(' · ', $bits)];

        case 'capacity':
            $c = calcCapacity($d);
            if ($c['total'] <= 0) return [READ_UNKNOWN, 'In progress'];
            $reading = ($c['over_ceiling'] > 0 || $c['only_me_unwritten'] > 0) ? READ_WATCH : READ_HEALTHY;
            if ($c['over_ceiling'] > 0 && $c['only_me_unwritten'] > 0)         $reading = READ_ACT;
            return [$reading, sprintf('%s/week · %s "only me" · %s not written down',
                number_format($c['total'], 0), number_format($c['only_me'], 0),
                number_format($c['only_me_unwritten'], 0))];

        case 'regulatory':
            $open = 0; $answered = 0;
            foreach (jrows($d, 'items') as $row) {
                if (($row['status'] ?? '') !== '') $answered++;
                if (($row['status'] ?? '') === 'exposure') $open++;
            }
            if ($answered === 0) return [READ_UNKNOWN, 'In progress'];
            return [$open > 0 ? READ_ACT : READ_HEALTHY,
                    $open > 0 ? $open . ' exposure item' . ($open === 1 ? '' : 's') . ' flagged'
                              : 'No exposure flagged'];

        case 'tax_flags':
            $flagged = 0;
            foreach (jrows($d, 'flags') as $row) {
                if (!empty($row['flagged'])) $flagged++;
            }
            if (!jrows($d, 'flags')) return [READ_UNKNOWN, 'In progress'];
            return [$flagged > 0 ? READ_WATCH : READ_HEALTHY,
                    $flagged > 0 ? $flagged . ' flagged for referral' : 'Nothing flagged'];

        case 'swot':
            $s = calcSwot($d);
            if ($s['total'] === 0) return [READ_UNKNOWN, 'In progress'];
            $n = count($s['unevidenced']);
            return [$n > 0 ? READ_WATCH : READ_HEALTHY, sprintf(
                '%d entr%s · %s', $s['total'], $s['total'] === 1 ? 'y' : 'ies',
                $n > 0 ? $n . ' without evidence' : 'all evidenced')];

        case 'priority':
            $p = calcPriority($d);
            $one = trim((string) ($d['priority_statement'] ?? ''));
            if ($one === '' && !$p['issues']) return [READ_UNKNOWN, 'In progress'];
            if ($one === '') return [READ_WATCH, count($p['issues']) . ' candidate issues, no priority named'];
            return [$p['open_exposure_count'] > 0 ? READ_WATCH : READ_HEALTHY,
                    mb_strimwidth($one, 0, 70, '…')];
    }
    return [READ_UNKNOWN, 'In progress'];
}

/**
 * The next instrument to reach for.
 *
 * The framework points from one reading to the next rather than leaving the
 * advisor to pick. This drives the "what's next" nudge on the client page.
 */
function suggestedNext(int $clientId): array
{
    $order = ['regulatory', 'health', 'ehr', 'capacity', 'swot', 'priority'];
    foreach ($order as $key) {
        $a = latestAssessment($clientId, $key);
        [$reading, ] = instrumentSummary($key, $a);
        if ($reading === READ_UNKNOWN) {
            return [$key, $a ? 'Pick up where you left off.' : 'Not started yet.'];
        }
    }
    return ['priority', 'Every instrument has a reading. Close with one priority and one committed action.'];
}
