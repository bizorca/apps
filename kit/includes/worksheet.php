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
