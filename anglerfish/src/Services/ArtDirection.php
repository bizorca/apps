<?php

namespace Anglerfish\Services;

/**
 * Claude art-directs the infographic; Gemini draws it (SPEC §9.5).
 *
 * The decisions that should vary per idea — the layout, the sections, the
 * exact words, the figures, the spot illustrations — are made by a model that
 * has read the material. Everything that must NOT vary stays hardcoded in
 * ImagePrompt: palette, type hierarchy, the takeaway box, the footer, the text
 * discipline. Claude cannot drift the brand because it is never asked to
 * decide it.
 *
 * Rewritten 2026-09-17 from a metaphor-and-seven-labels build sheet to an
 * information-design one. The old sheet asked for one drawable metaphor and
 * capped words hard; the pictures came back handsome and mute, with the
 * argument living in the labels. This one asks for a layout that carries the
 * argument, sections with headers, labels with detail lines, real figures
 * from the source, and a boxed takeaway — see ImagePrompt for the reasoning.
 *
 * One call at roughly a cent, against $0.134 for the image it directs.
 */
final class ArtDirection
{
    /**
     * Opus 5 — the app-wide composer model, pinned here too so the direction
     * and the post it accompanies are written by the same reader. Override per
     * call if that changes.
     */
    public const MODEL = 'claude-opus-5';

    /** Enforced at the API, because a missing field is a broken render. */
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'layout' => [
                'type' => 'string',
                'enum' => ['levels', 'columns', 'stages', 'journey', 'hub',
                           'before_after', 'annotated_scene'],
                'description' => 'The shape the idea actually has. columns for a '
                    . 'contrast, levels for a sequence with sub-steps, stages for a '
                    . 'progression, journey for a history or multi-stop process, hub '
                    . 'for one mechanism and its parts, before_after for one decisive '
                    . 'comparison, annotated_scene only when the idea is genuinely '
                    . 'one picture.',
            ],
            'headline' => [
                'type' => 'string',
                'description' => 'Exact headline. Eight words or fewer, title case, '
                    . 'no trailing punctuation. Names the idea, not the book.',
            ],
            'subheadline' => [
                'type' => 'string',
                'description' => 'One line under the headline, fourteen words or '
                    . 'fewer, that says what the picture compares, walks through, '
                    . 'or takes apart. Empty string if the headline already does.',
            ],
            'glance' => [
                'type' => 'string',
                'description' => 'One sentence: what a viewer concludes from the '
                    . 'layout and drawings alone, with every word covered. If this '
                    . 'cannot be written, the layout is wrong.',
            ],
            'composition' => [
                'type' => 'string',
                'description' => 'How the sections sit in the frame: what is where, '
                    . 'what arrows join what, where the eye starts and ends. Two or '
                    . 'three sentences.',
            ],
            'scene' => [
                'type' => 'string',
                'description' => 'For annotated_scene, hub and before_after: the one '
                    . 'illustrated object or environment the layout is built '
                    . 'around, concrete enough to draw. Empty string for the other '
                    . 'layouts.',
            ],
            'sections' => [
                'type' => 'array',
                'description' => 'Two to six titled panels. Each one is a column, '
                    . 'a band, a stage, a station or a card, depending on layout.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'header' => [
                            'type' => 'string',
                            'description' => 'Panel title, six words or fewer. '
                                . 'Rendered in capitals.',
                        ],
                        'items' => [
                            'type' => 'array',
                            'description' => 'One to six entries in this panel.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'label' => [
                                        'type' => 'string',
                                        'description' => 'The entry, eight words or '
                                            . 'fewer. Names the real-world thing — '
                                            . 'the practice, the step, the option — '
                                            . 'never a piece of the metaphor.',
                                    ],
                                    'detail' => [
                                        'type' => 'string',
                                        'description' => 'One line beneath the label, '
                                            . 'sixteen words or fewer, saying what it '
                                            . 'means or how it works. Empty string if '
                                            . 'the label is self-evident.',
                                    ],
                                    'figure' => [
                                        'type' => 'string',
                                        'description' => 'A number or range that '
                                            . 'appears verbatim in the material, to '
                                            . 'show large beside the label. Empty '
                                            . 'string if the material has none. Never '
                                            . 'invent one.',
                                    ],
                                    'draw' => [
                                        'type' => 'string',
                                        'description' => 'The small spot illustration '
                                            . 'for this entry: a specific object or '
                                            . 'figure an illustrator could draw '
                                            . 'without asking. Empty string if none '
                                            . 'is needed.',
                                    ],
                                ],
                                'required' => ['label', 'detail', 'figure', 'draw'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['header', 'items'],
                    'additionalProperties' => false,
                ],
            ],
            'takeaway' => [
                'type' => 'string',
                'description' => 'The one thing worth remembering, thirty words or '
                    . 'fewer, plain and declarative. Not the headline restated.',
            ],
            'accents' => [
                'type' => 'array',
                'description' => 'Two or three accent colours beyond navy and gold, '
                    . 'named plainly (e.g. "muted teal", "clay red").',
                'items' => ['type' => 'string'],
            ],
            'avoid' => [
                'type' => 'string',
                'description' => 'The obvious cliché for this particular subject, '
                    . 'which the illustration should stay away from.',
            ],
        ],
        'required' => ['layout', 'headline', 'subheadline', 'glance', 'composition',
                       'scene', 'sections', 'takeaway', 'accents', 'avoid'],
        'additionalProperties' => false,
    ];

    private const SYSTEM = <<<'TXT'
    You art-direct editorial explainer infographics. Your output is fed straight to
    an image model, so it is a build sheet, not advice.

    The job is information design, not illustration. The layout carries the
    argument: a reader who sees two columns knows it is a comparison, who sees
    stacked bands knows it is a sequence, before reading a word. Choose the layout
    the idea actually has. Then fill it with text that does real work — section
    headers, labels, a detail line under each, real figures from the material — and
    small spot illustrations of real objects inside the panels. Think of the
    explainer graphics a good financial newsletter runs: fifteen to thirty text
    elements, white canvas, tinted panels, a boxed takeaway.

    The test is the `glance` field: cover every word and say what the picture
    argues. If you cannot, change the layout, not the labels.

    Labels name the real-world thing — the practice, the step, the option, the
    number — never a piece of a metaphor. "Card on file, paid automatically" is a
    label. "Frayed ends, nothing returns" is not. Where the material carries a
    figure, put it in `figure` verbatim and let it be shown large; where it does
    not, leave `figure` empty rather than invent one.

    Constraints you must work within:
    - Two to six sections; one to six items each. Twenty to thirty text elements in
      total is the target. Fewer than ten is a poster, not an explainer.
    - Headline eight words or fewer; section headers six; labels eight; detail
      lines sixteen; takeaway thirty. Every string spelled exactly as it should
      appear, and no string repeated anywhere in the sheet.
    - Never write author names, book titles, citations, parentheses, or statistics
      that are not in the material. The source line is added by the system.
    - `draw` names a specific object or figure — "a stack of three gold coins beside
      a small house", "a person at a desk sliding a card across" — never "an icon".
      Empty is better than generic.
    - The palette is anchored on deep navy and warm gold on a white ground. Your
      accents extend that; they do not replace it. No neon, no rainbow.

    Use `annotated_scene` sparingly. It is right when the idea genuinely is one
    picture — a wave with the phases of a craving marked along it — and wrong for
    almost everything else, where it turns into a handsome scene that says nothing
    until the labels are read.
    TXT;

    /**
     * @return array{data:array<string,mixed>,model:string}
     */
    public static function forSubject(array $subject, ?string $model = null): array
    {
        $title = trim((string) ($subject['title'] ?? ''));
        $body = trim((string) ($subject['body'] ?? $subject['intro'] ?? ''));
        if ($title === '' && $body === '') {
            throw new \RuntimeException('Nothing to art-direct — the source is empty.');
        }

        // Attributions are provenance for the composer, not for the picture, and
        // an image model will happily try to render "(Senoff / Bodri)".
        $body = preg_replace('/\s*\([^()]{0,60}\)(?=\s|$)/u', '', $body) ?? $body;

        $user = "Direct an explainer infographic for this idea.\n\n"
            . "## Title\n{$title}\n\n"
            . "## The material\n" . mb_substr($body, 0, 6000);
        if (!empty($subject['book_title'])) {
            $user .= "\n\n## Source\nFrom \"{$subject['book_title']}\". Do not put "
                . "the book title or any author name in the sheet; the footer "
                . "carries them.";
        }

        return Anthropic::structured(self::SYSTEM, $user, self::SCHEMA,
            $model ?? self::MODEL);
    }
}
