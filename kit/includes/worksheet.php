<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/templates/partials.php';

/**
 * Boot a worksheet page.
 *
 * Every instrument page starts the same way: authenticate, load the client,
 * open (or resume, or start a new run of) this instrument's record. Returns
 * [$user, $client, $assessment, $data].
 *
 *   ?client=12            open the latest run, or start the first
 *   ?client=12&new=1      start a fresh run, keeping the old one intact
 *   ?client=12&a=34       open a specific earlier run
 */
function worksheetBoot(string $instrumentKey): array
{
    $user   = requireAuth();
    $userId = (int) $user['id'];
    $client = requireClient((int) ($_GET['client'] ?? 0), $userId);
    $cid    = (int) $client['id'];

    $specific = (int) ($_GET['a'] ?? 0);
    if ($specific > 0) {
        $assessment = getAssessment($specific, $userId);
        if (!$assessment || (int) $assessment['client_id'] !== $cid
            || $assessment['instrument'] !== $instrumentKey) {
            http_response_code(404);
            exit('Assessment not found.');
        }
    } else {
        $assessment = openAssessment($cid, $instrumentKey, ($_GET['new'] ?? '') === '1');
        // Starting a new run changes the URL, so the reload after save lands on
        // the new record rather than silently creating another one.
        if (($_GET['new'] ?? '') === '1') {
            redirect(url('/a/' . instruments()[$instrumentKey]['page']
                       . '?client=' . $cid . '&a=' . (int) $assessment['id']));
        }
    }

    return [$user, $client, $assessment, $assessment['data'] ?? []];
}

/**
 * Persist a worksheet and bounce back to it (post/redirect/get).
 *
 * $data is whatever the page assembled from $_POST. The status comes from
 * which save button was pressed.
 */
function worksheetSave(array $client, array $assessment, string $instrumentKey, array $data): never
{
    $status = post('save') === 'complete' ? 'complete' : 'draft';
    saveAssessment((int) $assessment['id'], $data, $status, post('period_label'));

    flashSuccess($status === 'complete'
        ? instrumentName($instrumentKey) . ' saved and marked complete.'
        : 'Saved.');

    redirect(url('/a/' . instruments()[$instrumentKey]['page']
               . '?client=' . (int) $client['id'] . '&a=' . (int) $assessment['id']));
}

/**
 * The bar above every worksheet: which run this is, when it was last touched,
 * how to start a fresh one, and the way back to the client.
 */
function renderRunBar(array $client, array $assessment, string $instrumentKey): string
{
    $cid     = (int) $client['id'];
    $meta    = instruments()[$instrumentKey];
    $history = assessmentHistory($cid, $instrumentKey);
    $isLatest = $history && (int) $history[0]['id'] === (int) $assessment['id'];

    $out = '<div class="no-print mb-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">'
         . '<a href="' . h(url('/client.php?id=' . $cid)) . '" class="text-muted hover:text-ink">← '
         . h($client['business_name']) . '</a>'
         . '<span class="text-muted">' . h($meta['unit']) . '</span>';

    if ((string) $assessment['updated_at'] !== '' && $assessment['status'] !== 'draft') {
        $out .= '<span class="tag bg-good-soft text-good">Complete</span>';
    }
    if (!$isLatest) {
        $out .= '<span class="tag bg-warn-soft text-warn">Earlier run · ' . h(niceDate($assessment['updated_at'])) . '</span>';
    }

    $out .= '<span class="ml-auto flex items-center gap-3">';
    if (count($history) > 1) {
        $out .= '<span class="text-muted">' . count($history) . ' runs</span>';
        foreach (array_slice($history, 0, 6) as $hrun) {
            $cur = (int) $hrun['id'] === (int) $assessment['id'];
            $out .= '<a class="' . ($cur ? 'font-semibold text-ink' : 'text-muted hover:text-ink')
                  . ' underline decoration-hairline underline-offset-4" href="'
                  . h(url('/a/' . $meta['page'] . '?client=' . $cid . '&a=' . (int) $hrun['id'])) . '">'
                  . h($hrun['period_label'] !== '' ? $hrun['period_label'] : niceDate($hrun['updated_at']))
                  . '</a>';
        }
    }
    $out .= '<a class="text-muted underline decoration-hairline underline-offset-4 hover:text-ink" href="'
          . h(url('/a/' . $meta['page'] . '?client=' . $cid . '&new=1')) . '">Start a new run</a>';
    $out .= '</span></div>';

    return $out;
}

/** Period label field — which 90 days, which year, which week this run covers. */
function renderPeriodField(array $assessment, string $placeholder): string
{
    return '<div class="no-print mb-6 max-w-sm">'
         . '<label class="label" for="period_label">Period this run covers</label>'
         . '<input class="field mt-1" id="period_label" name="period_label" placeholder="' . h($placeholder) . '"'
         . ' value="' . h($assessment['period_label'] ?? '') . '">'
         . '</div>';
}

// ---------------------------------------------------------------------------
// Phase 1 helpers (2026-11-11)
// ---------------------------------------------------------------------------

/** The data of the latest run of another instrument for this client, or []. */
function latestData(int $clientId, string $instrumentKey): array
{
    $a = latestAssessment($clientId, $instrumentKey);
    return $a['data'] ?? [];
}

/**
 * Fill empty fields for display from another instrument's latest run.
 *
 * Only on a run that has nothing saved yet, and only into empty keys: a value
 * the advisor typed is never overwritten. Nothing is written until Save, and
 * every prefilled field is named in $notes so the advisor can see where it
 * came from.
 */
function prefill(array $data, array $values, string $source, array &$notes): array
{
    $filled = [];
    foreach ($values as $key => [$label, $value]) {
        $empty = !isset($data[$key]) || $data[$key] === '' || $data[$key] === [] || $data[$key] === 0.0 || $data[$key] === 0;
        $has   = is_array($value) ? $value !== []
               : (is_numeric($value) ? (float) $value !== 0.0 : trim((string) $value) !== '');
        if ($empty && $has) {
            $data[$key] = $value;
            $filled[] = $label;
        }
    }
    if ($filled) $notes[] = implode(', ', $filled) . ' from the latest ' . $source . '.';
    return $data;
}

function renderPrefillNotes(array $notes): string
{
    if (!$notes) return '';
    $out = '<div class="no-print mb-6 rounded border border-primary/30 bg-primary-soft px-3 py-2 text-sm">'
         . '<strong>Prefilled, not yet saved.</strong> ';
    foreach ($notes as $n) $out .= h($n) . ' ';
    return $out . 'Check each figure, then press Save.</div>';
}

/** Is this a fresh run with nothing saved? Prefill only happens then. */
function isFreshRun(array $data): bool
{
    return $data === [];
}

/**
 * The close every Phase 1 instrument shares: the behavioral line, then a
 * pointer to the next instrument in layer order.
 */
function renderBehavioralSection(array $data, string $example, string $next): string
{
    return '<section class="card mt-6 p-5">'
         . sectionHead('Close', 'The sentence in my head when I think about doing this',
             'In the owner\'s words. Stall points are behavioral far more often than informational; '
             . 'name the avoidance so the plan can assume it.')
         . '<textarea class="field" name="behavior_line" rows="2" placeholder="' . h($example) . '">'
         . h((string) ($data['behavior_line'] ?? '')) . '</textarea>'
         . '<p class="mt-4 text-sm text-muted">Next: ' . h($next) . '</p>'
         . '</section>';
}

/** A labeled single-line text input bound to a data key. */
function textField(array $data, string $key, string $label, string $placeholder = '', string $hint = '', string $type = 'text'): string
{
    return '<div><label class="label" for="' . h($key) . '">' . h($label) . '</label>'
         . '<input class="field mt-1" type="' . h($type) . '" id="' . h($key) . '" name="' . h($key) . '"'
         . ' placeholder="' . h($placeholder) . '" value="' . h((string) ($data[$key] ?? '')) . '">'
         . ($hint !== '' ? '<p class="hint">' . h($hint) . '</p>' : '')
         . '</div>';
}

/** A labeled number/money input bound to a data key. */
function numField(array $data, string $key, string $label, string $hint = '', string $placeholder = ''): string
{
    $v = $data[$key] ?? '';
    return '<div><label class="label" for="' . h($key) . '">' . h($label) . '</label>'
         . '<input class="field amount mt-1" id="' . h($key) . '" name="' . h($key) . '" inputmode="decimal"'
         . ' placeholder="' . h($placeholder) . '" value="' . h(is_float($v) || is_int($v) ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') : (string) $v) . '">'
         . ($hint !== '' ? '<p class="hint">' . h($hint) . '</p>' : '')
         . '</div>';
}

/** A labeled textarea bound to a data key. */
function areaField(array $data, string $key, string $label, string $placeholder = '', int $rows = 3, string $hint = ''): string
{
    return '<div><label class="label" for="' . h($key) . '">' . h($label) . '</label>'
         . '<textarea class="field mt-1" id="' . h($key) . '" name="' . h($key) . '" rows="' . $rows . '"'
         . ' placeholder="' . h($placeholder) . '">' . h((string) ($data[$key] ?? '')) . '</textarea>'
         . ($hint !== '' ? '<p class="hint">' . h($hint) . '</p>' : '')
         . '</div>';
}

/** Collect a list of text keys from POST. */
function postTexts(array $keys): array
{
    $out = [];
    foreach ($keys as $k) $out[$k] = post($k);
    return $out;
}

/** Collect a list of numeric keys from POST. */
function postNums(array $keys): array
{
    $out = [];
    foreach ($keys as $k) $out[$k] = postNum($k);
    return $out;
}

/** Reading panel: badge, note, math, and the threshold legend. */
function renderReadingPanel(array $calc, string $legend, string $heading = 'Reading'): string
{
    return '<div class="mt-5 rounded-md border border-hairline p-4">'
         . '<div class="flex items-center justify-between gap-3"><span class="text-sm font-semibold">' . h($heading) . '</span>'
         . renderReading($calc['reading']) . '</div>'
         . (isset($calc['note']) ? '<p class="mt-2 text-sm">' . h($calc['note']) . '</p>' : '')
         . renderSteps($calc['steps'])
         . ($legend !== '' ? '<p class="mt-3 text-xs text-muted">' . h($legend) . '</p>' : '')
         . '</div>';
}
