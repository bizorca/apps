<?php

namespace Anglerfish\Services;

/**
 * The Gemini prompt for a piece of source material (SPEC §9.5).
 *
 * Deliberately a draft, not a fired shot. An image costs $0.134 and the prompt
 * is snapshotted onto the asset, so this pre-fills a textarea the operator
 * edits rather than generating blind from a title.
 *
 * Rewritten 2026-09-17, the third position on what an infographic is:
 *
 * - v1 (Aug 2026) asked for a headline, bullets and a palette, and got three
 *   columns of text with clip-art icons.
 * - v2 (2026-08-09, loosened 2026-09-04) swung to illustration: one metaphor
 *   scene, at most seven six-word labels, no paragraphs. Six weeks and 400
 *   images later the pictures were handsome and mute — cover the labels on
 *   most of them and you cannot say what they argue. The labels carried the
 *   idea; the drawing decorated it.
 * - v3, this one, is information design. The layout carries the argument
 *   (columns for a contrast, stacked levels for a sequence, a hub for a
 *   mechanism), the text does real work in a clear hierarchy — headline,
 *   sub-headline, section headers, labels with a detail line, real figures
 *   from the source, a boxed takeaway — and illustration is spot art inside
 *   the panels rather than the panel. The models of it are the explainers at
 *   RealEstateFinancialPlanner.com: fifteen to thirty text elements, white
 *   canvas, tinted panels, and you know the shape of the idea before you
 *   read a word.
 *
 * What v1 got wrong was not the amount of text. It was the absence of
 * structure, hierarchy and real objects. This keeps the text and adds those.
 */
final class ImagePrompt
{
    /** Brand anchors. The canvas stays light so colour can carry weight. */
    private const NAVY = '#1B3A5C';
    private const GOLD = '#D4A017';
    private const CREAM = '#F7F3E7';

    /**
     * Who the footer credits. Added once this class served more than one
     * publication: a graphic carrying the wrong masthead's copyright is the
     * kind of error nobody catches until it is already published.
     *
     * The key is what gets stored on a post and passed around in a query
     * string; the line is what gets printed, with {year} filled at render time
     * so an image drawn in January is not stamped with December's.
     */
    public const DEFAULT_ATTRIBUTION = 'bizorca';

    public const ATTRIBUTIONS = [
        'bizorca' => [
            'label' => 'Bizorca Press',
            'line'  => '© {year} bizorca.com. All rights reserved.',
        ],
        'hypnologue' => [
            'label' => 'Hypnologue',
            'line'  => '© {year} Jassen Bowman. Hypnologue.net',
        ],
    ];

    /**
     * The layout grammar. Naming the shape is what makes the picture carry
     * the argument: a reader sees two columns and knows it is a comparison
     * before reading a word. The art director picks one per idea.
     */
    public const LAYOUTS = [
        'levels' => 'Stacked horizontal bands, top to bottom, one per section. Each '
            . 'band is a titled panel with its items laid out in a row, small '
            . 'arrows between them; a downward arrow joins each band to the next. '
            . 'For a sequence whose steps have sub-steps.',
        'columns' => 'Two or three side-by-side columns divided by a clear vertical '
            . 'seam, one section per column, items aligned row by row so the '
            . 'comparison reads straight across. Give the columns contrasting '
            . 'tints. For a contrast or a comparison.',
        'stages' => 'Three to five upright panels left to right, one per section, '
            . 'each with its own tint, a spot illustration at the top and its items '
            . 'listed beneath; a thin arrow or gradient runs along the bottom to '
            . 'show progression. For progressions and transformations.',
        'journey' => 'A winding path or timeline crossing the frame with numbered '
            . 'stations along it, one per section; each station is a small '
            . 'illustrated scene with its items as captions beside it. For a '
            . 'history or a process with distinct stops.',
        'hub' => 'One large central object or panel with the remaining sections '
            . 'arranged around it, each joined by an arrow or line to the part of '
            . 'the centre it explains. For one mechanism and its parts.',
        'before_after' => 'Two large cards, the weaker case on the left and the '
            . 'stronger on the right, a bold unlabelled arrow between them '
            . 'pointing right; each card is an illustrated scene above its items. '
            . 'For one decisive comparison.',
        'annotated_scene' => 'One illustrated scene spanning the frame, annotated '
            . 'with callout labels on leader lines the way a diagram is, and a thin '
            . 'axis or timeline beneath it; the section headers become the phases '
            . 'of the scene. Only for an idea that genuinely is one picture.',
    ];

    /**
     * The generate page's picker, kept for the template path and for the
     * `posts.gemini_structure` enum. Each maps onto a layout above.
     */
    public const STRUCTURES = [
        'flow'     => ['label' => 'Flow — how the parts cause each other',   'layout' => 'hub'],
        'metaphor' => ['label' => 'Scene — one annotated picture',            'layout' => 'annotated_scene'],
        'steps'    => ['label' => 'Steps — stages in order',                  'layout' => 'levels'],
        'contrast' => ['label' => 'Contrast — side by side',                  'layout' => 'columns'],
        'anatomy'  => ['label' => 'Anatomy — one thing, its parts',           'layout' => 'hub'],
    ];

    /**
     * The footer's source line, from stored fields only. Never from the
     * model — an author name is exactly the thing it once mangled into
     * "(SENOFF/BODRI)". Null for a synthesis (no author) or a post.
     *
     * @param array<string,mixed> $subject  needs book_title; uses authors,
     *                                      number_label, kind when present
     */
    public static function sourceLine(array $subject): ?string
    {
        $book = trim((string) ($subject['book_title'] ?? ''));
        $authors = trim((string) ($subject['authors'] ?? ''));
        // A concept row's `kind` is the concept's; the book's rides as book_kind.
        $kind = (string) ($subject['book_kind'] ?? $subject['kind'] ?? 'scan');
        if ($book === '' || $authors === '' || $kind !== 'scan') {
            return null;
        }
        // "A; B; C" or "A, B, C" — two names read fine, more become "et al."
        $names = array_values(array_filter(array_map('trim',
            preg_split('/\s*[;,]\s*|\s+and\s+/', $authors) ?: [])));
        $names = array_map(static fn($n) => preg_replace('/\s*\(editors?\)$/i', '', $n), $names);
        $by = count($names) > 2 ? $names[0] . ' et al.' : implode(' & ', $names);

        $chapter = trim((string) ($subject['number_label'] ?? ''));
        if ($chapter !== '' && !preg_match('/^(chapter|part|section|question)/i', $chapter)) {
            $chapter = 'Chapter ' . $chapter;
        }
        return 'Source: ' . $book . ($chapter !== '' ? ', ' . $chapter : '') . ' — ' . $by;
    }

    /**
     * The footer's rights line. The year is the render year, so an image drawn
     * in January is not stamped with December's.
     */
    public static function rightsLine(?string $publication = null): string
    {
        $year = date('Y');
        $key = trim((string) $publication);

        if (isset(self::ATTRIBUTIONS[$key])) {
            return str_replace('{year}', $year, self::ATTRIBUTIONS[$key]['line']);
        }
        // A non-empty value that is not a known key is used verbatim. That is
        // how a one-off render sets its own footer without earning a place in
        // the list — see the RIGHTS: header in press/worker/render_direction.py.
        if ($key !== '') {
            return $key;
        }
        return str_replace('{year}', $year, self::ATTRIBUTIONS[self::DEFAULT_ATTRIBUTION]['line']);
    }

    /**
     * Build the Gemini prompt from the art direction.
     *
     * The split is deliberate: everything here that could drift the brand is
     * hardcoded — palette, type hierarchy, the takeaway box, the footer, the
     * text discipline, the avoid list. The director supplies only what should
     * vary per idea. That way a bad direction produces a dull picture, never
     * an off-brand one.
     *
     * Accepts the v2 shape too (`elements` with no `sections`), since a
     * direction is snapshotted onto assets and stored on posts, and those
     * must still render.
     *
     * @param array<string,mixed> $d
     */
    public static function fromDirection(array $d, string $target = 'infographic',
        ?string $sourceLine = null, ?string $rightsLine = null): string
    {
        $navy = self::NAVY;
        $gold = self::GOLD;
        $cream = self::CREAM;

        $layoutKey = (string) ($d['layout'] ?? '');
        if (!isset(self::LAYOUTS[$layoutKey])) {
            // A v2 direction named a structure, or nothing. Map, then default.
            $layoutKey = self::STRUCTURES[(string) ($d['structure'] ?? '')]['layout'] ?? 'levels';
        }
        $layout = self::LAYOUTS[$layoutKey];

        $headline = trim((string) ($d['headline'] ?? 'Untitled'));
        $subheadline = trim((string) ($d['subheadline'] ?? ''));
        $composition = trim((string) ($d['composition'] ?? ''));
        $scene = trim((string) ($d['scene'] ?? $d['metaphor'] ?? ''));
        $takeaway = trim((string) ($d['takeaway'] ?? ''));
        $avoid = trim((string) ($d['avoid'] ?? ''));

        $sections = self::sections($d);
        $sectionText = '';
        $n = 0;
        foreach ($sections as $s) {
            $n++;
            $sectionText .= sprintf("\nSECTION %d — header, exactly: \"%s\"\n", $n,
                mb_strtoupper(trim((string) ($s['header'] ?? "Section $n"))));
            foreach ((array) ($s['items'] ?? []) as $it) {
                if (!is_array($it) || empty($it['label'])) {
                    continue;
                }
                $line = sprintf("  - Label, exactly: \"%s\"", trim((string) $it['label']));
                $detail = trim((string) ($it['detail'] ?? ''));
                if ($detail !== '') {
                    $line .= sprintf(" — detail line beneath it, exactly: \"%s\"", $detail);
                }
                $figure = trim((string) ($it['figure'] ?? ''));
                if ($figure !== '') {
                    $line .= sprintf(" — show the figure \"%s\" large beside it", $figure);
                }
                $draw = trim((string) ($it['draw'] ?? ''));
                if ($draw !== '') {
                    $line .= " — spot illustration: " . $draw;
                }
                $sectionText .= $line . "\n";
            }
        }

        $accents = implode(', ', array_filter(array_map(
            static fn($a) => trim((string) $a), (array) ($d['accents'] ?? []))));
        $accentLine = $accents !== ''
            ? "with {$accents} as accents"
            : 'with muted teal, clay red and warm grey as accents';
        $avoidLine = $avoid !== '' ? "\nFor this subject in particular, avoid: {$avoid}" : '';
        $subLine = $subheadline !== ''
            ? "Beneath it, smaller, the sub-headline, exactly: \"{$subheadline}\""
            : 'No sub-headline.';
        $sceneLine = $scene !== ''
            ? "The illustration the layout is built around: {$scene}"
            : 'No single hero scene — the panels and their spot illustrations are the picture.';
        $rights = self::rightsLine($rightsLine);
        $footer = $sourceLine !== null
            ? "Footer: a thin strip along the very bottom edge. On the left, small, exactly: "
              . "\"{$sourceLine}\". On the right, small, exactly: \"{$rights}\"."
            : "Footer: a thin strip along the very bottom edge with, centred and small, "
              . "exactly: \"{$rights}\".";

        return <<<TXT
        A clean editorial explainer {$target}, 16:9 — information design, not an
        illustration. The layout carries the argument, the text does real work in a
        clear hierarchy, and drawings are small spot illustrations inside the panels.
        A reader should know the shape of the idea before reading a word.

        Layout: {$layout}
        Arrangement: {$composition}
        {$sceneLine}

        Headline, top of the frame, the largest type, exactly:
        "{$headline}"
        {$subLine}

        Panels and their contents — render every word exactly as written, once:
        {$sectionText}
        Takeaway: a clearly bordered box, placed where the eye ends, with a small bold
        label "THE TAKEAWAY:" followed by, exactly:
        "{$takeaway}"

        Type: one clean sans-serif family throughout. Headline largest; section headers
        in capitals; item labels bold; detail lines regular and smaller; figures large
        and bold in the accent colour. Nothing smaller than about 2.5% of the image
        height, so it survives a phone screen. Left-align text inside panels.

        Illustration: small drawn objects and figures — a stack of coins, a house, a
        person at a desk, a document — flat editorial vector style with a light outline
        and simple shading, one style held across the whole image. Real objects, not
        generic icons, and not photography. The drawings serve the panels; they never
        replace them.

        Colour: white or a pale warm ground such as {$cream}. Panels tinted in pale
        washes of navy {$navy} and gold {$gold} with thin navy rules; headline and
        section headers in navy; the takeaway box outlined in gold; {$accentLine}.
        Daylight, flat, print-like. No dark full-bleed backgrounds, no dusk lighting,
        no gradients across the canvas.

        Text discipline: render only the headline, sub-headline, section headers, item
        labels, detail lines, figures, the takeaway and the footer. Spell every word
        exactly as written. Nothing appears twice. Arrows carry no words. A drawn
        object may carry a one- or two-word label saying what it is (SALE, MENU,
        PAID), never a sentence. Never invent a statistic, a name, a citation, a
        line of text on a document or screen, or text in parentheses.

        {$footer}

        Avoid: a single painterly scene, slide-deck styling, stock-photo realism, lorem
        ipsum, logos or watermarks beyond the footer, and any text other than what is
        specified here.{$avoidLine}
        TXT;
    }

    /**
     * Sections from a direction, tolerating the v2 flat `elements` shape by
     * folding it into one untitled section.
     *
     * @param array<string,mixed> $d
     * @return list<array<string,mixed>>
     */
    private static function sections(array $d): array
    {
        $sections = array_values(array_filter((array) ($d['sections'] ?? []), 'is_array'));
        if ($sections) {
            return $sections;
        }
        $items = array_values(array_filter((array) ($d['elements'] ?? []), 'is_array'));
        return $items ? [['header' => 'The Parts', 'items' => $items]] : [];
    }

    /**
     * The template prompt — no model, no cost — for the generate page's
     * instant path. Same brand layer as fromDirection(); the sections are cut
     * mechanically from the source, which is why the directed path exists.
     */
    public static function draft(
        array $subject,
        string $target = 'infographic',
        string $structure = 'flow',
    ): string {
        $structure = isset(self::STRUCTURES[$structure]) ? $structure : 'flow';
        $title = trim((string) ($subject['title'] ?? 'Untitled'));
        $body = trim((string) ($subject['body'] ?? $subject['intro'] ?? ''));

        // Sentence-length points, up to eight, split across two sections so
        // the layout has something to arrange. Anything longer than a detail
        // line becomes a wall of text in the render.
        $points = [];
        foreach (preg_split('/(?<=[.!?])\s+|\R/', $body) ?: [] as $line) {
            $line = trim($line, " \t-•*\"");
            // Source bullets carry a trailing "(Michael Senoff / Bill Bodri)".
            // That is provenance for the composer, not something to render —
            // the model tried to draw one and produced "(SENOFF/BODRI)".
            $line = trim(preg_replace('/\s*\([^()]{0,60}\)\s*$/u', '', $line));
            if (mb_strlen($line) >= 20 && mb_strlen($line) <= 140) {
                $points[] = rtrim($line, '.');
            }
            if (count($points) >= 8) {
                break;
            }
        }
        $half = (int) ceil(count($points) / 2);
        $mk = static fn(array $ps) => array_map(
            static fn($p) => ['label' => mb_substr($p, 0, 60), 'detail' => '', 'draw' => ''], $ps);
        $direction = [
            'layout'      => self::STRUCTURES[$structure]['layout'],
            'headline'    => $title,
            'subheadline' => '',
            'composition' => 'Arrange the sections as the layout describes; the eye enters at '
                . 'the headline and ends on the takeaway box.',
            'scene'       => '',
            'sections'    => $points ? [
                ['header' => 'The Idea',      'items' => $mk(array_slice($points, 0, $half))],
                ['header' => 'In Practice',   'items' => $mk(array_slice($points, $half))],
            ] : [],
            'takeaway'    => $title,
            'accents'     => [],
            'avoid'       => '',
        ];
        return self::fromDirection($direction, $target, self::sourceLine($subject));
    }
}
