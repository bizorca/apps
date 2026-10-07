<?php
declare(strict_types=1);

/**
 * Shared render helpers for the worksheet pages.
 *
 * These exist so the reading badge, the math block, and the save bar are
 * defined once. Three metrics that each drew their own Healthy/Watch/Act
 * badge would drift apart within a month.
 */

/** Healthy / Watch / Act badge. Never colour alone — the word is always there. */
function renderReading(string $reading, string $extra = ''): string
{
    $label = readingLabel($reading);
    $cls   = readingClasses($reading);
    $dot   = match ($reading) {
        READ_HEALTHY => '●', READ_WATCH => '◆', READ_ACT => '▲', default => '○',
    };
    return '<span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold ' . $cls . '">'
         . '<span aria-hidden="true">' . $dot . '</span>' . h($label)
         . ($extra !== '' ? '<span class="font-normal opacity-80">· ' . h($extra) . '</span>' : '')
         . '</span>';
}

/**
 * The math block.
 *
 * "Show your math for every calculation, so the advisor can check it" is a
 * stated rule of the framework, not a nicety. Every computed figure in the app
 * comes with the expression that produced it.
 */
function renderSteps(array $steps, string $heading = 'The math'): string
{
    if (!$steps) return '';
    $out = '<div class="mt-4 rounded-md border border-hairline bg-sunk/60 p-4">'
         . '<h4 class="text-xs font-semibold uppercase tracking-wide text-muted">' . h($heading) . '</h4>'
         . '<dl class="mt-2 divide-y divide-hairline text-sm">';
    foreach ($steps as $s) {
        $out .= '<div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 py-1.5">'
              . '<dt class="text-ink">' . h($s['label'])
              . ($s['expression'] !== '' ? '<span class="ml-2 font-mono text-xs text-muted">' . h($s['expression']) . '</span>' : '')
              . '</dt>'
              . '<dd class="tnum font-semibold text-ink">' . h($s['value']) . '</dd>'
              . '</div>';
    }
    return $out . '</dl></div>';
}

/** A metric panel: title, reading badge, note, math. */
function renderMetric(string $title, string $subtitle, array $calc): string
{
    return '<div class="card p-5">'
         . '<div class="flex flex-wrap items-start justify-between gap-3">'
         . '<div><h3 class="font-semibold text-ink">' . h($title) . '</h3>'
         . '<p class="hint">' . h($subtitle) . '</p></div>'
         . renderReading($calc['reading'])
         . '</div>'
         . '<p class="mt-3 text-sm text-ink">' . h($calc['note']) . '</p>'
         . renderSteps($calc['steps'])
         . '</div>';
}

/** Four-layer tag pill. */
function renderLayerTag(string $layer): string
{
    if ($layer === '' || !isset(LAYERS[$layer])) return '';
    return '<span class="tag ' . layerClasses($layer) . '">' . h($layer) . ' · ' . h(LAYERS[$layer]['name']) . '</span>';
}

/** A <select> of the four layers. */
function layerSelect(string $name, string $value, string $class = 'field'): string
{
    $out = '<select name="' . h($name) . '" class="' . h($class) . '">'
         . '<option value="">Layer…</option>';
    foreach (LAYERS as $k => $meta) {
        $sel = $value === $k ? ' selected' : '';
        $out .= '<option value="' . h($k) . '"' . $sel . '>' . h($k . ' — ' . $meta['name']) . '</option>';
    }
    return $out . '</select>';
}

/**
 * Sticky save bar.
 *
 * Worksheets are long and get filled in live, across a table from the owner.
 * Losing an hour of entry because the save button scrolled off the bottom of
 * the screen is not an acceptable failure mode.
 */
function renderSaveBar(string $status = 'draft', string $note = ''): string
{
    $done = $status === 'complete';
    return '<div class="no-print sticky bottom-0 z-20 -mx-4 mt-8 border-t border-hairline bg-surface/95 px-4 py-3 backdrop-blur">'
         . '<div class="mx-auto flex max-w-6xl flex-wrap items-center gap-3">'
         . '<button type="submit" name="save" value="draft" class="btn-primary">Save</button>'
         . '<button type="submit" name="save" value="complete" class="btn-secondary">'
         . ($done ? 'Saved as complete' : 'Save and mark complete') . '</button>'
         . ($note !== '' ? '<span class="text-sm text-muted">' . h($note) . '</span>' : '')
         . '<span class="ml-auto text-xs text-muted">Nothing is saved until you press Save.</span>'
         . '</div></div>';
}

/** Section heading inside a worksheet. */
function sectionHead(string $label, string $title, string $hint = ''): string
{
    return '<div class="mb-4">'
         . ($label !== '' ? '<span class="text-xs font-semibold uppercase tracking-wide text-primary">' . h($label) . '</span>' : '')
         . '<h2 class="text-lg font-semibold text-ink">' . h($title) . '</h2>'
         . ($hint !== '' ? '<p class="hint max-w-readable">' . h($hint) . '</p>' : '')
         . '</div>';
}

/**
 * A standing reminder the framework insists on.
 *
 * Washington rules change and AI tools state invented details with complete
 * confidence. Anywhere the app touches a regulatory or tax question, it points
 * at the source rather than answering.
 */
function verifyNotice(string $what = 'Rates, thresholds, and requirements change.'): string
{
    return '<p class="mt-2 rounded border border-warn/30 bg-warn-soft px-3 py-2 text-sm text-warn">'
         . '<strong>Verify at the source.</strong> ' . h($what)
         . ' Check the agency directly — nothing in this app is a current-fact citation.</p>';
}
