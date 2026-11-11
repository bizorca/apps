<?php

declare(strict_types=1);

namespace Anglerfish\Services;

/**
 * The weekly post's download content: the JSON that render.py's
 * weekly_deliverable target turns into a PDF.
 *
 * Until 2026-11-11 only weekly_push.py (a post written in Claude Code) could
 * supply it, so a post the server composed promoted into an empty container.
 * Now the composer writes it after the body, and this checks it with the same
 * rules weekly_push.py applies before anything is stored. A spec that fails is
 * kept off the composition (the reason goes in `error`) rather than rendered
 * broken.
 */
final class Deliverable
{
    public const DELIM = '---DELIVERABLE---';

    /** Keys the renderer reads. The post's own columns supply the rest. */
    private const KEYS = ['container', 'title', 'intro', 'sections', 'scoring'];

    /**
     * @return array{spec:?array<string,mixed>,errors:list<string>,warnings:list<string>}
     */
    public static function check(?array $spec, string $container): array
    {
        if ($spec === null) {
            return ['spec' => null, 'errors' => ['no download content after the body'], 'warnings' => []];
        }

        $errors = [];
        $spec = array_intersect_key($spec, array_flip(self::KEYS));
        $spec['container'] = $container;

        $sections = $spec['sections'] ?? null;
        if (!is_array($sections) || $sections === []) {
            return ['spec' => null, 'errors' => ['the download needs a non-empty "sections" list'], 'warnings' => []];
        }

        $items = $fields = 0;
        foreach ($sections as $s) {
            if (!is_array($s) || trim((string) ($s['title'] ?? '')) === '') {
                $errors[] = 'every section needs a title';
                continue;
            }
            $items  += is_array($s['items'] ?? null) ? count($s['items']) : 0;
            $fields += is_array($s['fields'] ?? null) ? count($s['fields']) : 0;
        }

        if ($container === 'test') {
            $scoring = $spec['scoring'] ?? null;
            $scored = 0;
            foreach ($sections as $s) {
                // Items in a section with fields belong to a fill-in sheet, not
                // the score; render.py scores every item in an item section.
                if (is_array($s) && empty($s['fields'])) {
                    $scored += is_array($s['items'] ?? null) ? count($s['items']) : 0;
                }
            }
            if (!is_array($scoring) || empty($scoring['bands'])) {
                $errors[] = 'a test needs a "scoring" block with "max" and "bands"';
            } else {
                if ((int) ($scoring['max'] ?? 0) !== $scored * 5) {
                    $errors[] = "scoring max is " . (int) ($scoring['max'] ?? 0)
                        . " but $scored items at 1-5 each make " . ($scored * 5);
                }
                foreach ($scoring['bands'] as $b) {
                    foreach (['range', 'meaning', 'action'] as $k) {
                        if (trim((string) ($b[$k] ?? '')) === '') {
                            $errors[] = "a scoring band is missing \"$k\"";
                        }
                    }
                }
            }
        } else {
            unset($spec['scoring']);
        }
        if ($container === 'role_document' && $fields === 0) {
            $errors[] = 'a role document needs "fields" (label, hint, lines)';
        }
        if (in_array($container, ['checklist', 'worksheet'], true) && $items === 0 && $fields === 0
            && !self::hasWriteLines($sections)) {
            $errors[] = "a $container needs items, fields or write_lines";
        }

        $warnings = [];
        if (!$errors && ($size = self::size($spec)) > self::TWO_PAGES) {
            $warnings[] = "likely runs past two pages (size $size, aim under " . self::TWO_PAGES
                . '); trim fields or sections before promoting';
        }

        return ['spec' => $errors ? null : $spec, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Rough printed size, in body-line units. Calibrated 2026-11-11 against two
     * real renders: a twelve-item test with a nine-field sheet (two pages)
     * scored 76; a seven-section worksheet (four pages) scored 141. Only the
     * Mac renderer knows the true page count, so this is a warning, not a gate.
     */
    private const TWO_PAGES = 95;

    /** @param array<string,mixed> $spec */
    private static function size(array $spec): int
    {
        $len = static fn($v) => mb_strlen((string) $v);
        $u = $len($spec['intro'] ?? '') / 95 + 2;
        foreach ($spec['sections'] as $s) {
            $u += 2.5 + $len($s['instruction'] ?? '') / 95;
            foreach ((array) ($s['items'] ?? []) as $it) {
                $u += max(1, ceil($len($it['text'] ?? '') / 80)) + $len($it['note'] ?? '') / 95;
            }
            foreach ((array) ($s['fields'] ?? []) as $f) {
                $u += 1.2 + (int) ($f['lines'] ?? 1) * 1.6 + $len($f['hint'] ?? '') / 95;
            }
            $u += (int) ($s['write_lines'] ?? 0) * 1.6;
        }
        if (!empty($spec['scoring']['bands'])) {
            $u += 4 + count($spec['scoring']['bands']) * 2.5;
        }
        return (int) round($u);
    }

    /** @param list<mixed> $sections */
    private static function hasWriteLines(array $sections): bool
    {
        foreach ($sections as $s) {
            if (is_array($s) && (int) ($s['write_lines'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }
}
