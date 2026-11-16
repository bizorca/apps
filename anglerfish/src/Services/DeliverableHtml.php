<?php

declare(strict_types=1);

namespace Anglerfish\Services;

/**
 * The weekly download as a print-ready web page, for previewing a draft before
 * it is promoted.
 *
 * A port of render.py's deliverable_html() and deliverable_footer(), same CSS
 * and markup, so what this shows is what the Mac renders into the PDF. The PDF
 * itself is drawn by the Mac worker, which may not poll for hours when idle;
 * this page is instant, and the browser's Print > Save as PDF produces the file.
 * If render.py's template changes, change this too.
 */
final class DeliverableHtml
{
    private const PERMISSION = "Print it, use it, hand it to a client. Don't sell it.";
    private const COHORT_LINES = [
        'foundations' => 'From the Foundations cohort — twelve systems, twelve weeks, one installed at a time.',
        'growth'      => 'From the Growth cohort — for owners installing what they already know they should have.',
    ];
    private const LABELS = ['test' => 'Assessment', 'checklist' => 'Checklist',
                            'worksheet' => 'Worksheet', 'role_document' => 'Role Document'];

    /** @param array<string,mixed> $spec the stored spec plus module_number, module_title, cohort, sequence, post_url, date */
    public static function page(array $spec): string
    {
        $e = static fn($v) => htmlspecialchars(trim((string) $v), ENT_NOQUOTES);
        $container = isset(self::LABELS[$spec['container'] ?? '']) ? $spec['container'] : 'worksheet';
        $cohort = (string) ($spec['cohort'] ?? 'foundations');

        $first = ucfirst($cohort) . ' ' . self::LABELS[$container] . (!empty($spec['sequence']) ? ' ' . (int) $spec['sequence'] : '');
        $bits = [$first];
        if (!empty($spec['module_number'])) {
            $bits[] = rtrim('Module ' . (int) $spec['module_number'] . ': ' . ($spec['module_title'] ?? ''), ': ');
        }
        $eyebrow = implode(' &nbsp;—&nbsp; ', array_map($e, $bits));

        $body = [];
        if (!empty($spec['intro'])) {
            $body[] = "<p class='lede'>" . $e($spec['intro']) . '</p>';
        }
        $n = 1;
        foreach ((array) ($spec['sections'] ?? []) as $sec) {
            $head = '';
            if (!empty($sec['title'])) {
                $head .= '<h2>' . $e($sec['title']) . '</h2>';
            }
            if (!empty($sec['instruction'])) {
                $head .= "<p class='instruct'>" . $e($sec['instruction']) . '</p>';
            }
            $blocks = [];
            foreach ((array) ($sec['items'] ?? []) as $it) {
                $text = is_array($it) ? ($it['text'] ?? '') : $it;
                $note = is_array($it) ? ($it['note'] ?? '') : '';
                $q = str_replace('____', "<span class='blank'></span>", $e($text))
                    . ($note !== '' ? "<span class='note'>" . $e($note) . '</span>' : '');
                if ($container === 'test') {
                    $scale = implode('', array_map(static fn($k) => "<span>$k</span>", range(1, 5)));
                    $blocks[] = "<div class='row'><div class='n'>$n</div><div class='q'>$q</div><div class='scale'>$scale</div></div>";
                } elseif ($container === 'checklist') {
                    $blocks[] = "<div class='row'><div class='box'></div><div class='q'>$q</div></div>";
                } else {
                    $blocks[] = "<div class='row'><div class='n'>$n</div><div class='q'>$q</div></div>";
                }
                $n++;
            }
            if (!empty($sec['write_lines'])) {
                $blocks[] = self::write((int) $sec['write_lines']);
            }
            foreach ((array) ($sec['fields'] ?? []) as $f) {
                $blocks[] = "<div class='field'><div class='lab'>" . $e($f['label'] ?? '') . '</div>'
                    . (!empty($f['hint']) ? "<div class='hint'>" . $e($f['hint']) . '</div>' : '')
                    . self::write((int) ($f['lines'] ?? 2)) . '</div>';
            }
            if ($head !== '') {
                $firstBlock = $blocks ? array_shift($blocks) : '';
                $body[] = "<div class='keep'>$head$firstBlock</div>";
            }
            array_push($body, ...$blocks);
            if (!empty($sec['note'])) {
                $body[] = "<p class='after'>" . $e($sec['note']) . '</p>';
            }
        }
        if ($container === 'test' && !empty($spec['scoring'])) {
            $sc = $spec['scoring'];
            $body[] = "<div class='scoring'><h2>Your score</h2><div class='score'><div class='tot'></div>"
                . "<div class='lab'>Total &nbsp;/&nbsp; " . $e($sc['max'] ?? '') . '</div></div>';
            foreach ((array) ($sc['bands'] ?? []) as $b) {
                $body[] = "<div class='band'><b>" . $e($b['range'] ?? '') . '</b> — ' . $e($b['meaning'] ?? '')
                    . '<br>' . $e($b['action'] ?? '') . '</div>';
            }
            $body[] = '</div>';
        }

        $tag = 'Bizorca Press' . (!empty($spec['sequence']) ? ' &nbsp;·&nbsp; No. ' . (int) $spec['sequence'] : '')
            . (!empty($spec['module_number']) ? ' &nbsp;·&nbsp; Module ' . (int) $spec['module_number'] : '');
        // financialhypnosis.com on every page since 2026-11-16, same as render.py's SITE_URL:
        // the PDFs live there now (/d/<name>), and a visit there is the point of the post.
        $url = 'financialhypnosis.com';
        $date = $e($spec['date'] ?? '');
        $footer = "<footer><div class='ft'><b>$tag</b>&nbsp;·&nbsp;$url" . ($date !== '' ? "&nbsp;·&nbsp;$date" : '') . '</div>'
            . '<div>' . $e(self::COHORT_LINES[$cohort] ?? '') . '</div>'
            . '<div><i>' . $e(self::PERMISSION) . '</i></div></footer>';

        $brand = (string) @file_get_contents(dirname(__DIR__) . '/View/brand.css');
        return "<!doctype html><html><head><meta charset='utf-8'><title>" . $e($spec['title'] ?? 'Download')
            . " (preview)</title><meta name='robots' content='noindex, nofollow'><style>$brand" . self::css() . '</style></head><body>'
            . "<div class='bar'>Preview of the download. Print it, or Save as PDF, to check the page count "
            . "(US Letter). The final PDF is rendered from the same template when the post is promoted.</div>"
            // The table's tfoot repeats on every printed page and reserves the
            // footer's room, so the fixed footer never sits on top of content.
            . "<main><table class='pw'><tbody><tr><td><div class='eyebrow'>Bizorca Press &nbsp;·&nbsp; $eyebrow</div>"
            . '<h1>' . $e($spec['title'] ?? '') . "</h1><div class='rule'></div>" . implode('', $body)
            . "</td></tr></tbody><tfoot><tr><td><div class='fspace'></div></td></tr></tfoot></table>"
            . "</main>$footer</body></html>";
    }

    private static function write(int $lines): string
    {
        return "<div class='write'>" . str_repeat("<div class='line'></div>", max(1, $lines)) . '</div>';
    }

    /** render.py's deliverable_css(10.5), plus page and footer rules for printing from a browser. */
    private static function css(): string
    {
        return <<<'CSS'
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--serif); font-size: 10.5pt; line-height: 1.5; color: var(--ink); background: #e9ecf0; }
main { background: #fff; width: 8.5in; margin: 0 auto 40px; padding: 0.7in 0.85in 1.0in 0.95in; }
.bar { font-family: var(--sans); font-size: 9pt; color: #1B3A5C; background: #F7F3E7; padding: 8px 14px; text-align: center; }
footer { width: 8.5in; margin: -40px auto 40px; padding: 0 0.85in 20px 0.95in; background: #fff;
         font-family: Georgia, serif; font-size: 7pt; color: #58616D; line-height: 1.5; }
footer .ft { border-top: 1px solid #D4A017; padding-top: 4px; }
footer b { color: #1B3A5C; }
table.pw { width: 100%; border-collapse: collapse; }
table.pw td { padding: 0; vertical-align: top; }
.fspace { height: 0; }
@page { size: letter; margin: 0.7in 0.85in 0.3in 0.95in; }
@media print {
  body { background: #fff; }
  .bar { display: none; }
  main { width: auto; margin: 0; padding: 0; }
  .fspace { height: 0.7in; }
  footer { position: fixed; bottom: 0; left: 0; right: 0; width: auto; margin: 0; padding: 0; background: #fff; }
}
.eyebrow { font-family: var(--sans); font-size: 7.6pt; font-weight: bold; letter-spacing: 2.4px; text-transform: uppercase; color: var(--gold); }
h1 { font-size: 19pt; line-height: 1.12; color: var(--navy); margin: 5px 0 7px; }
.rule { height: 2.5px; background: var(--rule); margin: 0 0 10px; }
.lede { font-style: italic; color: #4a5462; margin-bottom: 12px; }
h2 { font-family: var(--sans); font-size: 9.4pt; font-weight: bold; letter-spacing: 1.1px; text-transform: uppercase; color: var(--navy);
     border-left: 3px solid var(--gold); padding-left: 8px; margin: 15px 0 7px; break-after: avoid; }
.instruct { font-size: .92em; color: var(--muted); margin: -3px 0 8px; }
.row { display: flex; gap: 10px; align-items: baseline; padding: 5.5px 0; border-bottom: 1px solid #E3E7EC; break-inside: avoid; }
.row .n { font-family: var(--sans); font-weight: bold; color: var(--navy); min-width: 17px; font-size: .92em; }
.row .q { flex: 1; }
.row .note { display: block; color: var(--muted); font-size: .88em; }
.scale { display: flex; gap: 4px; flex: 0 0 auto; padding-right: 3px; }
.scale span { width: 17px; height: 17px; border: 1px solid var(--navy); border-radius: 2px; font-family: var(--sans); font-size: 7.2pt;
              line-height: 16px; text-align: center; color: var(--muted); }
.box { flex: 0 0 auto; width: 12px; height: 12px; border: 1.4px solid var(--navy); border-radius: 2px; margin-top: 2px; }
.write { margin: 3px 0 7px; break-inside: avoid; }
.write .line { border-bottom: 1px solid #98a0aa; height: 20px; }
.blank { display: inline-block; min-width: 58px; border-bottom: 1px solid #98a0aa; }
.score { display: flex; gap: 10px; align-items: flex-end; margin: 12px 0 4px; break-inside: avoid; }
.score .tot { border: 2px solid var(--navy); width: 66px; height: 46px; border-radius: 3px; }
.score .lab { font-family: var(--sans); font-size: 7.6pt; font-weight: bold; letter-spacing: 1.6px; text-transform: uppercase; color: var(--navy); padding-bottom: 3px; }
.band { padding: 4px 0 4px 10px; border-left: 3px solid var(--gold); margin: 5px 0; break-inside: avoid; }
.band b { color: var(--navy); }
.scoring { break-inside: avoid; }
.keep { break-inside: avoid; }
.after { font-size: .92em; color: var(--muted); margin: 7px 0 0; break-before: avoid; }
.field { margin: 6px 0; break-inside: avoid; }
.field .lab { font-family: var(--sans); font-size: 7.8pt; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; color: var(--navy); }
.field .hint { font-size: .88em; color: var(--muted); margin-bottom: 3px; }
CSS;
    }
}
