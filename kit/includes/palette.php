<?php
declare(strict_types=1);

/**
 * Colour tokens — the single source of truth.
 *
 * Tailwind resolves every colour to rgb(var(--token) / <alpha-value>), so
 * changing a value here changes the whole app with no rebuild and no Node.
 * Only adding or removing utility *classes* requires ./build-css.sh.
 *
 * Colours are authored as hex because that is what a person reads, and emitted
 * as space-separated RGB channels because that is the only form Tailwind can
 * slot an alpha channel into. Without the channel form, every opacity modifier
 * in the app (border-bad/30, focus:ring-primary/20, bg-surface/95) silently
 * produces no rule at all — a missing border is far harder to notice than a
 * build error.
 *
 * The three reading colours carry meaning the advisor acts on, so they are
 * chosen for contrast against the page at WCAG AA for body text, and they are
 * never the only signal — every badge also carries the word.
 */
function palette(): array
{
    return [
        // Surfaces
        'paper'        => '#f7f6f3',   // page ground, warm off-white
        'surface'      => '#ffffff',   // cards, inputs
        'sunk'         => '#f0eeea',   // hovers, table stripes, section wells
        'hairline'     => '#e0ddd6',

        // Type
        'ink'          => '#1b2422',
        'muted'        => '#5e6a67',

        // Accent
        'primary'      => '#1d5c56',
        'primary-ink'  => '#ffffff',   // type on a primary ground
        'primary-soft' => '#e6f0ee',

        // Readings: Healthy / Watch / Act
        'good'         => '#2c6b4a',
        'good-soft'    => '#e7f2ea',
        'warn'         => '#8a5410',
        'warn-soft'    => '#fbf0dd',
        'bad'          => '#9a2b24',
        'bad-soft'     => '#fbeae8',
    ];
}

/** "#1d5c56" -> "29 92 86". Tailwind needs the channels, not the hex. */
function hexChannels(string $hex): string
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    return implode(' ', [
        (string) hexdec(substr($hex, 0, 2)),
        (string) hexdec(substr($hex, 2, 2)),
        (string) hexdec(substr($hex, 4, 2)),
    ]);
}

/** The :root block, emitted inline in the head. */
function paletteCss(): string
{
    $out = '';
    foreach (palette() as $token => $value) {
        $out .= '--' . $token . ':' . hexChannels($value) . ';';
    }
    return ':root{' . $out . '}';
}

/** Tailwind classes for a four-layer tag. Used wherever a layer is shown. */
function layerClasses(string $layer): string
{
    return match ($layer) {
        'R'  => 'bg-bad-soft text-bad',
        'F'  => 'bg-warn-soft text-warn',
        'O'  => 'bg-primary-soft text-primary',
        'Op' => 'bg-sunk text-muted',
        default => 'bg-sunk text-muted',
    };
}

function layerName(string $layer): string
{
    return LAYERS[$layer]['name'] ?? '';
}

// ---------------------------------------------------------------------------
// The app mark
//
// A field kit: handle, case, latch. Defined once here because the header badge
// (inline SVG) and the favicon (a data: URI) have to stay identical, and they
// use different encodings — a copy in each would drift.
//
// Deliberately not a letter mark: the initials of "Advisor Field Kit" spell
// AFK, which reads as "away from keyboard" to anyone who has used a computer.
// ---------------------------------------------------------------------------

/** The glyph shapes, on a 100x100 viewBox. $fg is the kit, $bg the latch cut. */
function appMarkShapes(string $fg, string $bg): string
{
    return '<path d="M38 28h24a8 8 0 0 1 8 8v8h-9v-7H39v7h-9v-8a8 8 0 0 1 8-8z" fill="' . $fg . '"/>'
         . '<rect x="20" y="46" width="60" height="30" rx="5" fill="' . $fg . '"/>'
         . '<rect x="45" y="54" width="10" height="14" rx="2" fill="' . $bg . '"/>';
}

/** Inline SVG for the header badge. Sits on a primary-coloured ground. */
function appMark(string $class = 'h-7 w-7'): string
{
    $primary = '#' . ltrim(palette()['primary'], '#');
    return '<svg class="' . h($class) . '" viewBox="0 0 100 100" aria-hidden="true" focusable="false">'
         . '<rect width="100" height="100" rx="18" fill="' . $primary . '"/>'
         . appMarkShapes('#ffffff', $primary)
         . '</svg>';
}

/** The same mark as a data: URI, for <link rel="icon">. */
function appFavicon(): string
{
    $primary = '#' . ltrim(palette()['primary'], '#');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
         . '<rect width="100" height="100" rx="18" fill="' . $primary . '"/>'
         . appMarkShapes('#ffffff', $primary)
         . '</svg>';
    return 'data:image/svg+xml,' . rawurlencode($svg);
}
