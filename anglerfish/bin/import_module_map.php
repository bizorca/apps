<?php
/**
 * Import the library-to-module mapping produced by worker/map_modules.py.
 *
 *   php import_module_map.php ../extractions/module-map.json --dry-run
 *   php import_module_map.php ../extractions/module-map.json
 *
 * The file is keyed by natural keys — (book_slug, title) — rather than by
 * database ids, because the mapping pass runs locally in Claude Code against
 * the extraction files and the Big Ideas syntheses, and those files have no
 * idea what a concept's id is. Resolution happens here, where the database is.
 *
 * Anything that fails to resolve is reported and skipped, never guessed. A
 * silently dropped row would look exactly like a row the mapper chose not to
 * emit, and the two need to be distinguishable.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
define('AF_ROOT', $root);
require $root . '/includes/boot.php';

use Anglerfish\Core\Database;

$file = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);

if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "usage: php import_module_map.php <module-map.json> [--dry-run]\n");
    exit(64);
}

$data = json_decode((string) file_get_contents($file), true);
if (!is_array($data) || !isset($data['rows'])) {
    fwrite(STDERR, "Malformed map file: expected an object with a 'rows' array.\n");
    exit(65);
}

/**
 * Resolve one natural key to a subject id.
 *
 * Titles are matched exactly. They come from the same extraction files the
 * importer read when it created these rows, so an inexact match means the
 * material changed underneath and should be looked at rather than fuzzily
 * matched onto whatever is nearest.
 */
function resolve(array $r): ?int
{
    $slug = (string) ($r['book_slug'] ?? '');
    $title = (string) ($r['title'] ?? '');

    // Database::value() hands back PDO's false on no row, not null, so the
    // result is normalised here rather than at four call sites.
    $v = match ($r['subject_type']) {
        'concept' => Database::value(
            'SELECT c.id FROM af_concepts c JOIN af_books b ON b.id = c.book_id
              WHERE b.slug = ? AND c.title = ? LIMIT 1', [$slug, $title]),
        'chapter' => Database::value(
            'SELECT c.id FROM af_chapters c JOIN af_books b ON b.id = c.book_id
              WHERE b.slug = ? AND c.title = ? LIMIT 1', [$slug, $title]),
        'artifact' => Database::value(
            'SELECT a.id FROM af_artifacts a JOIN af_books b ON b.id = a.book_id
              WHERE b.slug = ? AND a.title = ? LIMIT 1', [$slug, $title]),
        'artifact_item' => Database::value(
            'SELECT i.id FROM af_artifact_items i
               JOIN af_artifacts a ON a.id = i.artifact_id
               JOIN af_books b ON b.id = a.book_id
              WHERE b.slug = ? AND a.title = ? AND i.text = ? LIMIT 1',
            [$slug, (string) ($r['artifact_title'] ?? ''), $title]),
        default => null,
    };

    return $v ? (int) $v : null;
}

$resolved = $missing = 0;
$byType = $misses = [];
$rows = [];

foreach ($data['rows'] as $r) {
    $id = resolve($r);
    if (!$id) {
        $missing++;
        if (count($misses) < 15) {
            $misses[] = "{$r['subject_type']}  {$r['book_slug']}  {$r['title']}";
        }
        continue;
    }
    $resolved++;
    $byType[$r['subject_type']] = ($byType[$r['subject_type']] ?? 0) + 1;
    $rows[] = [$r['subject_type'], $id, (int) $r['module_id'], (int) $r['ord'],
               (float) $r['confidence'], (string) $r['rationale']];
}

printf("map rows in file : %d\n", count($data['rows']));
printf("resolved         : %d\n", $resolved);
foreach ($byType as $t => $n) {
    printf("  %-14s %d\n", $t, $n);
}
printf("unresolved       : %d\n", $missing);
foreach ($misses as $m) {
    echo "  miss  $m\n";
}
if ($missing > 15) {
    printf("  ... and %d more\n", $missing - 15);
}

if ($dry) {
    echo "\nDry run. Nothing written.\n";
    exit(0);
}

// Replace this pass's own output and nothing else. Manual rows are the
// operator's judgement and a re-run must never overwrite them — which is the
// entire reason module_map carries a `source` column.
Database::run("DELETE FROM af_module_map WHERE source = 'auto'");

$sql = 'INSERT INTO af_module_map (subject_type, subject_id, module_id, ord,
                                confidence, rationale, source)
        VALUES (?, ?, ?, ?, ?, ?, "auto")
        ON DUPLICATE KEY UPDATE ord = VALUES(ord), confidence = VALUES(confidence),
                                rationale = VALUES(rationale)';
$written = 0;
foreach ($rows as $row) {
    // A manual row for the same (subject, module) wins: the unique key makes
    // this an update, so skip rather than demote it to auto.
    $manual = Database::value(
        "SELECT id FROM af_module_map
          WHERE subject_type = ? AND subject_id = ? AND module_id = ? AND source = 'manual'",
        [$row[0], $row[1], $row[2]]);
    if ($manual) {
        continue;
    }
    Database::run($sql, $row);
    $written++;
}

printf("\nwrote %d auto rows (manual rows left untouched)\n", $written);
