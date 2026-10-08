<?php
/**
 * Import the library-to-publication routing produced by worker/map_publications.py.
 *
 *   php import_publication_scope.php ../extractions/publication-map.json --dry-run
 *   php import_publication_scope.php ../extractions/publication-map.json
 *
 * Keyed by natural keys — book slug, and (book_slug, title) for concepts and
 * artifacts — because the classifier runs locally against the extraction files
 * and has no idea what a concept's id is. Resolution happens here, where the
 * database is.
 *
 * Anything that fails to resolve is reported and skipped, never guessed. A
 * silently dropped row would look exactly like a row the classifier chose not
 * to emit, and those two need to stay distinguishable.
 *
 * `raw_book` rows are reported and NOT imported. They are captures sitting in
 * book-scans/ that have never been through the five extraction passes, so they
 * have no `books` row to point at. They are in the file because the shortlist
 * is the useful output — it says what putting a book through extraction would
 * buy — not because anything here can store them.
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
use Anglerfish\Models\Publication;

$file = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);

if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "usage: php import_publication_scope.php <publication-map.json> [--dry-run]\n");
    exit(64);
}

$data = json_decode((string) file_get_contents($file), true);
if (!is_array($data) || !isset($data['scope'])) {
    fwrite(STDERR, "Not a publication map: $file\n");
    exit(65);
}

$pubs = Publication::all();
if (!$pubs) {
    fwrite(STDERR, "No publications — run migration 017 first.\n");
    exit(66);
}

/** Resolve a book slug to an id, once per slug. */
$bookCache = [];
$bookId = static function (string $slug) use (&$bookCache): ?int {
    if (!array_key_exists($slug, $bookCache)) {
        $id = Database::value('SELECT id FROM af_books WHERE slug = ?', [$slug]);
        $bookCache[$slug] = $id === null ? null : (int) $id;
    }
    return $bookCache[$slug];
};

$totals = ['resolved' => 0, 'unresolved' => 0, 'raw' => 0, 'skipped_pub' => 0];
$unresolved = [];
$shortlist = [];

foreach ($data['scope'] as $pubkey => $rows) {
    $pub = $pubs[$pubkey] ?? null;
    if (!$pub) {
        fwrite(STDERR, "! no publication '$pubkey' — skipping " . count($rows) . " row(s)\n");
        $totals['skipped_pub'] += count($rows);
        continue;
    }
    $pubId = (int) $pub['id'];

    // Clear this classifier's own rows before writing the new ones. Manual rows
    // are untouched by design — an operator decision has to survive every
    // re-run, which is the same contract module_map uses and the reason the
    // `source` column exists at all.
    if (!$dry) {
        $cleared = Publication::clearAuto($pubId);
        echo "{$pub['name']}: cleared $cleared auto row(s)\n";
    }

    $n = 0;
    foreach ($rows as $r) {
        $kind = (string) ($r['kind'] ?? '');

        if ($kind === 'raw_book') {
            $totals['raw']++;
            $shortlist[$pubkey][] = $r;
            continue;
        }

        $bid = $bookId((string) ($r['book'] ?? ''));
        if ($bid === null) {
            $totals['unresolved']++;
            $unresolved[] = "$pubkey / {$r['book']} / {$r['title']}";
            continue;
        }

        $subjectId = null;
        if ($kind === 'book') {
            $subjectId = $bid;
        } elseif ($kind === 'concept') {
            $subjectId = Database::value(
                'SELECT id FROM af_concepts WHERE book_id = ? AND title = ? LIMIT 1',
                [$bid, (string) $r['title']]
            );
        } elseif ($kind === 'artifact') {
            $subjectId = Database::value(
                'SELECT id FROM af_artifacts WHERE book_id = ? AND title = ? LIMIT 1',
                [$bid, (string) $r['title']]
            );
        }

        if (!$subjectId) {
            $totals['unresolved']++;
            $unresolved[] = "$pubkey / $kind / {$r['book']} / {$r['title']}";
            continue;
        }

        if (!$dry) {
            Publication::scope(
                $pubId,
                $kind,
                (int) $subjectId,
                (float) ($r['relevance'] ?? 0.5),
                'auto',
                ($r['via'] ?? 'score') === 'book' ? 'inherited from book scope' : null
            );
        }
        $totals['resolved']++;
        $n++;
    }
    echo "{$pub['name']}: $n row(s)" . ($dry ? ' (dry run)' : ' written') . "\n";
}

echo "\nresolved {$totals['resolved']}, unresolved {$totals['unresolved']}, "
   . "raw captures {$totals['raw']} (not importable)\n";

if ($unresolved) {
    echo "\nUnresolved — reported, never guessed:\n";
    foreach (array_slice($unresolved, 0, 20) as $u) {
        echo "  - $u\n";
    }
    if (count($unresolved) > 20) {
        echo '  ... and ' . (count($unresolved) - 20) . " more\n";
    }
}

foreach ($shortlist as $pubkey => $rows) {
    echo "\n$pubkey — captures in book-scans/ worth putting through extraction:\n";
    usort($rows, static fn($a, $b) => $b['relevance'] <=> $a['relevance']);
    foreach (array_slice($rows, 0, 15) as $r) {
        printf("  %.2f  %s\n", $r['relevance'], mb_substr((string) $r['title'], 0, 70));
    }
}

if ($dry) {
    echo "\n--dry-run: nothing written.\n";
}
