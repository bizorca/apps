<?php
/**
 * Server-side job runner.
 *
 * There is no daemon here. A Cloudways cron calls this every minute (it was a
 * Site Tools cron on SiteGround); it drains a few jobs and
 * exits well inside the execution window.
 *
 *   php cli.php work                 drain up to 5 jobs, then exit
 *   php cli.php work --max=1
 *   php cli.php work --types=compose,generate_image
 *   php cli.php status
 *
 * The Mac worker still handles what the server cannot: OCR (no tesseract) and
 * HTML rendering (no headless Chromium). Both poll the same queue, filtered by
 * job type, so neither steps on the other.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = __DIR__;
define('AF_ROOT', $root);
require $root . '/includes/boot.php';

use Anglerfish\Core\Database;
use Anglerfish\Models\Job;
use Anglerfish\Services\ServerJobs;

$cmd = $argv[1] ?? 'work';
$opts = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}

// Pushed files arrive in the data folder (private_html/data/anglerfish/incoming)
// and the Mac scripts still name them "storage/incoming/...", as they did when
// storage/ sat inside the app. Resolve that the way stored paths are resolved.
if (is_string($opts['from'] ?? null) && str_starts_with($opts['from'], 'storage/')) {
    $opts['from'] = af_file($opts['from']);
}

/** Jobs the server is actually capable of running. */
$serverTypes = ServerJobs::types();

// ── Publications ─────────────────────────────────────────────────────────────

if ($cmd === 'publications') {
    printf("%-3s %-12s %-16s %-20s %-7s %-6s %-6s %s\n",
        'ID', 'KEY', 'NAME', 'MODEL', 'EFFORT', 'POSTS', 'BOOKS', 'CITES?');
    foreach (\Anglerfish\Models\Publication::summary() as $p) {
        printf("%-3d %-12s %-16s %-20s %-7s %-6d %-6d %s\n",
            $p['id'], $p['pubkey'], mb_substr($p['name'], 0, 16),
            $p['model'] ?: '(app default)',
            \Anglerfish\Models\Publication::effort($p) ?: '-',
            $p['posts'], $p['books'],
            $p['citation_required'] ? 'REQUIRED' : 'no');
    }
    exit;
}

// Non-book corpora and how they must be credited. Printed in full rather than
// truncated, because the caution is the reason the row exists — a Mandel lead
// used as a citation is the specific failure this table is here to prevent.
if ($cmd === 'corpus') {
    $pub = \Anglerfish\Models\Publication::resolve($opts['pub'] ?? 'hypnologue');
    $rows = Database::all(
        'SELECT * FROM af_publication_sources WHERE publication_id = ? ORDER BY slug',
        [(int) $pub['id']]);
    if (!$rows) {
        echo "No non-book corpora registered for {$pub['name']}.\n";
        exit;
    }
    foreach ($rows as $r) {
        echo "\n{$r['slug']}  ({$r['kind']}, {$r['item_count']} items)\n";
        echo "  {$r['name']}\n";
        echo "  path:    {$r['local_path']}\n";
        echo "  credit:  {$r['attribution']}\n";
        echo '  citable: ' . ($r['citable'] ? 'yes' : 'NO — leads only') . "\n";
        if ($r['caution']) {
            echo '  ' . wordwrap("caution: {$r['caution']}", 76, "\n           ") . "\n";
        }
    }
    exit;
}

// ── Citations ────────────────────────────────────────────────────────────────

if ($cmd === 'cite') {
    $svc = \Anglerfish\Services\Citations::class;

    // Sweep a composition's body and verify every DOI in it.
    if (!empty($opts['check'])) {
        $id = (int) $opts['check'];
        $c = Database::one('SELECT * FROM af_compositions WHERE id = ?', [$id]);
        if (!$c) {
            fwrite(STDERR, "No composition #$id\n");
            exit(65);
        }
        $found = $svc::extract((string) $c['body']);
        if (!$found) {
            echo "No DOI in the body of composition #$id.\n";
            exit(1);
        }
        echo count($found) . " DOI(s) found. Checking against CrossRef...\n\n";
        $ok = 0;
        foreach ($svc::sweep((string) $c['body'], null, $id) as $r) {
            printf("  %-8s %s\n", $r['ok'] ? 'OK' : 'FAIL', $r['doi']);
            echo "           {$r['reason']}\n";
            $ok += $r['ok'] ? 1 : 0;
        }
        echo "\n$ok of " . count($found) . " verified.\n";
        $gate = \Anglerfish\Models\Composition::canPromote(
            Database::one('SELECT * FROM af_compositions WHERE id = ?', [$id]));
        echo $gate['ok'] ? "Promotion gate: PASS\n" : "Promotion gate: BLOCKED — {$gate['reason']}\n";
        exit($gate['ok'] ? 0 : 1);
    }

    // Verify one DOI by hand and attach it.
    if (!empty($opts['doi'])) {
        $r = $svc::verify(
            (string) $opts['doi'],
            isset($opts['post']) ? (int) $opts['post'] : null,
            isset($opts['composition']) ? (int) $opts['composition'] : null,
            (string) ($opts['supports'] ?? 'supports'),
            isset($opts['claim']) ? (string) $opts['claim'] : null,
        );
        echo ($r['ok'] ? 'OK   ' : 'FAIL ') . $svc::normalise((string) $opts['doi']) . "\n";
        echo "  {$r['reason']}\n";
        if ($r['row']) {
            echo "  {$r['row']['authors']} ({$r['row']['year']}). {$r['row']['title']}\n";
            echo "  {$r['row']['container']} {$r['row']['volume']}({$r['row']['issue']}), "
               . "{$r['row']['pages']}\n";
        }
        exit($r['ok'] ? 0 : 1);
    }

    fwrite(STDERR, "usage: php cli.php cite --check=<composition_id>\n"
                 . "       php cli.php cite --doi=<doi> [--composition=N|--post=N]\n"
                 . "                        [--supports=supports|contradicts|complicates|background]\n");
    exit(64);
}

// ── Hypnologue ───────────────────────────────────────────────────────────────

/**
 * Land a Hypnologue post drafted in Claude Code as a finished composition.
 *
 * This is the default path, not the fallback. A post written in a Claude Code
 * session runs on the subscription; the same post composed by `cli.php hypno`
 * is metered API spend. The server never makes a model call here — it parses a
 * file, verifies the DOIs against CrossRef, and writes a row.
 *
 * File shape: front matter between --- lines, then the title on its own line,
 * then the body. Citations are verified from whatever DOIs the body contains,
 * so no separate citation block is needed — and cannot be faked, because the
 * verification is a live CrossRef fetch either way.
 *
 *   ---
 *   publication: hypnologue
 *   subtitle: What the research says about training poor hypnotic subjects
 *   composition: 12          # optional — updates an existing row instead
 *   ---
 *   Backwards Into Trance
 *
 *   Clark Hull wrote the line in 1933 ...
 */
if ($cmd === 'hypno-import') {
    $file = (string) ($opts['from'] ?? '');
    if ($file === '' || !is_file($file)) {
        fwrite(STDERR, "--from=<file> is required and must exist\n");
        exit(64);
    }
    $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));

    if (!preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $raw, $m)) {
        fwrite(STDERR, "no front-matter block — the file must start with --- key: value ---\n");
        exit(65);
    }
    $meta = [];
    foreach (preg_split('/\n/', $m[1]) as $line) {
        if (preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $kv)) {
            $meta[$kv[1]] = trim($kv[2]);
        }
    }
    $lines = preg_split('/\n/', trim($m[2]));
    $title = trim((string) array_shift($lines));
    $body = trim(implode("\n", $lines));
    if ($title === '' || $body === '') {
        fwrite(STDERR, "need a title line and a body after the front matter\n");
        exit(65);
    }

    $pub = \Anglerfish\Models\Publication::resolve($meta['publication'] ?? 'hypnologue');

    // Re-import into the same row, so a rewrite lands where the first draft did
    // rather than accumulating near-duplicate compositions.
    $existing = isset($meta['composition']) ? (int) $meta['composition'] : 0;
    if ($existing) {
        $n = Database::run(
            'UPDATE af_compositions SET title=?, subtitle=?, body=?, status="done",
                                     finished_at=NOW()
              WHERE id=? AND publication_id=?',
            [$title, $meta['subtitle'] ?? null, $body, $existing, (int) $pub['id']]);
        if (!$n) {
            fwrite(STDERR, "No composition #$existing for {$pub['name']}\n");
            exit(65);
        }
        $id = $existing;
        // Its old citations described the old body.
        Database::run('DELETE FROM af_citations WHERE composition_id = ?', [$id]);
        echo "composition #$id updated\n";
    } else {
        $id = Database::insert(
            'INSERT INTO af_compositions (publication_id, subject_type, subject_id, format,
                                       angle, audience, length, voice_preset,
                                       status, title, subtitle, body, model, finished_at)
             VALUES (?, "freeform", NULL, "essay", NULLIF(?, ""), ?, "short", ?,
                     "done", ?, ?, ?, ?, NOW())',
            [(int) $pub['id'], $meta['angle'] ?? '',
             $meta['audience'] ?? 'curious adults and practitioners',
             $pub['voice_preset'], $title, $meta['subtitle'] ?? null, $body,
             'claude-code (subscription)']);
        echo "composition #$id created — {$pub['name']}\n";
    }

    // Verify every DOI in the body. This is the same check the API path gets;
    // a post drafted by hand is not exempt, because the failure mode being
    // guarded against — a fluent, plausible, non-existent citation — is not
    // unique to models.
    $svc = \Anglerfish\Services\Citations::class;
    $found = $svc::extract($body);
    echo count($found) . " DOI(s) in the body\n";
    foreach ($svc::sweep($body, null, $id) as $r) {
        printf("  %-5s %s — %s\n", $r['ok'] ? 'OK' : 'FAIL', $r['doi'], $r['reason']);
    }

    $gate = \Anglerfish\Models\Composition::canPromote(
        Database::one('SELECT * FROM af_compositions WHERE id = ?', [$id]));
    echo $gate['ok']
        ? "\nPromotion gate: PASS — /compose/$id\n"
        : "\nPromotion gate: BLOCKED — {$gate['reason']}\n";
    exit($gate['ok'] ? 0 : 1);
}


if ($cmd === 'hypno') {
    $pub = \Anglerfish\Models\Publication::resolve('hypnologue');

    $subjectType = 'freeform';
    $subjectId = null;
    if (!empty($opts['source']) && preg_match('/^(concept|artifact|post|source):(\d+)$/',
            (string) $opts['source'], $m)) {
        $subjectType = $m[1];
        $subjectId = (int) $m[2];
    }

    $angle = (string) ($opts['angle'] ?? '');
    if ($angle === '' && $subjectId === null) {
        fwrite(STDERR,
            "A Hypnologue post needs either --angle or --source.\n\n"
          . "  php cli.php hypno --angle='Hull said anything that assumes trance causes trance'\n"
          . "  php cli.php hypno --source=concept:949 --angle='...'\n\n"
          . "Composes on {$pub['model']} at effort " . ($pub['effort'] ?: 'default')
          . ", and cannot be promoted without a verified peer-reviewed citation.\n");
        exit(64);
    }

    $id = \Anglerfish\Models\Composition::create([
        'publication'  => 'hypnologue',
        'subject_type' => $subjectType,
        'subject_id'   => $subjectId,
        'format'       => 'essay',
        'angle'        => $angle,
        'audience'     => (string) ($opts['audience'] ?? 'curious adults and practitioners'),
        'length'       => (string) ($opts['length'] ?? 'short'),
        'voice_preset' => '',
        'extra'        => (string) ($opts['extra'] ?? ''),
        'expand'       => !isset($opts['no-expand']),
    ], $subjectId ? \Anglerfish\Models\Composition::subject($subjectType, $subjectId) : null);

    echo "composition #$id queued — Hypnologue, {$pub['model']}"
       . ", effort " . ($pub['effort'] ?: 'default') . "\n";
    echo "  php cli.php work --types=compose --max=1\n";
    echo "  php cli.php cite --check=$id\n";
    exit;
}


if ($cmd === 'status') {
    printf("%-18s %-10s %s\n", 'TYPE', 'STATUS', 'N');
    foreach (Database::all(
        'SELECT type, status, COUNT(*) n FROM af_jobs
          WHERE status IN ("queued","leased","running","dead")
          GROUP BY type, status ORDER BY type') as $r) {
        printf("%-18s %-10s %d\n", $r['type'], $r['status'], $r['n']);
    }
    $can = array_intersect($serverTypes, array_column(Database::all(
        'SELECT DISTINCT type FROM af_jobs WHERE status="queued"'), 'type'));
    echo "\nserver can run now: " . ($can ? implode(', ', $can) : 'nothing queued') . "\n";
    exit(0);
}

/**
 * A weekly post written in Claude Code, filed as a finished composition.
 *
 *   php cli.php weekly-import --from=storage/incoming/weekly-m01-money-baseline.txt
 *
 * The default way a weekly post gets written, as of 2026-09-18. The server
 * composer (`weekly` + `work`, below) calls the Anthropic API and is metered
 * against the spend limit; a post written in a Claude Code session runs on the
 * subscription and costs nothing per token. This command takes that post and
 * puts it exactly where the server composer would have — a `compositions` row
 * with status `done` — so /compose/<id>, promotion and the deliverable render
 * are the same screens either way. No job is queued and no model is called.
 *
 * The file is the draft with a front-matter block and, optionally, the
 * deliverable's content after a ---DELIVERABLE--- line:
 *
 *   ---
 *   module: 1
 *   deliverable: test
 *   subtitle: One line under the title
 *   angle: The failure-first opening, if one was set
 *   composition: 9            (optional — update this row instead of inserting)
 *   ---
 *   The Title, on the first line after the front matter
 *
 *   Body, plain text, blank lines between paragraphs, exactly as the server
 *   composer would have produced it. Post::toHtml() handles the HTML later.
 *
 *   ---DELIVERABLE---
 *   { "intro": "...", "sections": [ ... ], "scoring": { ... } }
 *
 * `composition:` is how a rewrite gets back into the row it came from — the
 * operator pulls a draft into bizorca-drafts/, rewrites it in their own voice,
 * and pushes it back before promoting. Inserting a fresh row for a rewrite
 * would leave the machine draft behind as a second composition of the same
 * post.
 */
if ($cmd === 'video-import') {
    // A video script written in Claude Code (see writer/video-scripts/BRIEF.md and
    // worker/video_push.py). Front matter, a title line, the spoken script, then
    // ---ON-SCREEN---, ---SOURCES--- and ---NOTES--- blocks. Overwrites the script
    // fields of the seeded row; production tracking is left alone.
    $file = (string) ($opts['from'] ?? '');
    if ($file === '' || !is_file($file)) {
        fwrite(STDERR, "--from=<file> is required and must exist\n");
        exit(64);
    }
    $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));
    if (!preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $raw, $m)) {
        fwrite(STDERR, "no front-matter block\n");
        exit(65);
    }
    $meta = [];
    foreach (explode("\n", $m[1]) as $line) {
        if (preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $kv)) {
            $meta[$kv[1]] = trim($kv[2]);
        }
    }
    $blocks = preg_split('/\n---(ON-SCREEN|SOURCES|NOTES)---\n/', "\n" . $m[2], -1, PREG_SPLIT_DELIM_CAPTURE);
    $main = trim(array_shift($blocks));
    $parts = [];
    for ($i = 0; $i + 1 < count($blocks); $i += 2) {
        $parts[$blocks[$i]] = trim($blocks[$i + 1]);
    }
    $lines = explode("\n", $main);
    $title = trim((string) array_shift($lines));
    $script = trim(implode("\n", $lines));
    $number = (int) ($meta['number'] ?? 0);
    if ($number < 1 || $title === '' || $script === '') {
        fwrite(STDERR, "need number: in front matter, a title line and a script\n");
        exit(65);
    }
    if (empty($parts['SOURCES'])) {
        fwrite(STDERR, "#{$number}: a script with no ---SOURCES--- block is not filed\n");
        exit(65);
    }
    $spoken = preg_replace('/\[[^\]]*\]/', '', $script);
    $words = str_word_count((string) $spoken);
    try {
        \Anglerfish\Models\Video::import($number, [
            'title'     => mb_substr($title, 0, 255),
            'script'    => $script,
            'on_screen' => $parts['ON-SCREEN'] ?? '',
            'sources'   => $parts['SOURCES'],
            'notes'     => $parts['NOTES'] ?? '',
            'words'     => $words,
            'runtime'   => (int) ($meta['runtime'] ?? max(1, round($words / 140))),
            'model'     => (string) ($meta['model'] ?? 'claude-code'),
        ]);
    } catch (\InvalidArgumentException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(65);
    }
    printf("video #%d filed: %s (%d words)\n", $number, $title, $words);
    exit(0);
}

if ($cmd === 'weekly-import') {
    $file = (string) ($opts['from'] ?? '');
    if ($file === '' || !is_file($file)) {
        fwrite(STDERR, "--from=<file> is required and must exist\n");
        exit(64);
    }
    $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));

    // Front matter: key: value lines between the first two `---` lines.
    if (!preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $raw, $m)) {
        fwrite(STDERR, "no front-matter block — the file must start with --- key: value --- \n");
        exit(65);
    }
    $meta = [];
    foreach (preg_split('/\n/', $m[1]) as $line) {
        if (preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $kv)) {
            $meta[$kv[1]] = trim($kv[2]);
        }
    }
    $rest = $m[2];

    // The deliverable block, if present, is JSON after its own delimiter.
    $spec = null;
    if (($at = strpos($rest, "\n---DELIVERABLE---\n")) !== false) {
        $json = trim(substr($rest, $at + strlen("\n---DELIVERABLE---\n")));
        $rest = substr($rest, 0, $at);
        $spec = json_decode($json, true);
        if (!is_array($spec)) {
            fwrite(STDERR, "---DELIVERABLE--- block is not a JSON object: " . json_last_error_msg() . "\n");
            exit(65);
        }
    }

    $lines = preg_split('/\n/', trim($rest));
    $title = trim((string) array_shift($lines));
    $body = trim(implode("\n", $lines));
    if ($title === '' || $body === '') {
        fwrite(STDERR, "need a title line and a body after the front matter\n");
        exit(65);
    }

    $moduleId = (int) ($meta['module'] ?? 0);
    $module = \Anglerfish\Models\Weekly::module($moduleId);
    if (!$module) {
        fwrite(STDERR, "front matter needs module: 1-12\n");
        exit(64);
    }
    $deliverable = (string) ($meta['deliverable'] ?? '');
    if (!isset(\Anglerfish\Models\Weekly::CONTAINERS[$deliverable])) {
        fwrite(STDERR, "front matter needs deliverable: "
            . implode(', ', array_keys(\Anglerfish\Models\Weekly::CONTAINERS)) . "\n");
        exit(64);
    }
    if ($spec !== null && (string) ($spec['container'] ?? $deliverable) !== $deliverable) {
        fwrite(STDERR, "deliverable spec says container {$spec['container']} but front matter says {$deliverable}\n");
        exit(65);
    }
    $specJson = $spec !== null ? json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $model = (string) ($meta['model'] ?? 'claude-code');
    $words = str_word_count(strip_tags($body));

    $existing = (int) ($meta['composition'] ?? 0);
    if ($existing) {
        $row = Database::one('SELECT id, post_id, format FROM af_compositions WHERE id = ?', [$existing]);
        if (!$row || $row['format'] !== 'weekly') {
            fwrite(STDERR, "composition #{$existing} is not a weekly composition\n");
            exit(65);
        }
        Database::run(
            'UPDATE af_compositions
                SET title=?, subtitle=?, body=?, deliverable_spec=?, module_id=?, deliverable=?,
                    angle=COALESCE(NULLIF(?, ""), angle), model=?, status="done",
                    error=NULL, finished_at=NOW()
              WHERE id=?',
            [$title, (string) ($meta['subtitle'] ?? ''), $body, $specJson, $moduleId,
             $deliverable, (string) ($meta['angle'] ?? ''), $model, $existing]);
        // A rewrite of an already-promoted post updates the post too, since the
        // handoff screen reads posts, not compositions.
        if ($row['post_id']) {
            Database::run(
                'UPDATE af_posts SET title=?, subtitle=?, body=?, deliverable_spec=? WHERE id=?',
                [$title, (string) ($meta['subtitle'] ?? ''), $body, $specJson, (int) $row['post_id']]);
        }
        echo "composition #{$existing} updated — module {$module['id']} ({$module['title']}), "
           . "{$deliverable}, {$words} words" . ($spec ? ', deliverable spec attached' : ', no deliverable spec')
           . ($row['post_id'] ? ", post #{$row['post_id']} updated too" : '') . "\n"
           . "  review: /compose/{$existing}\n";
        exit(0);
    }

    $id = Database::insert(
        'INSERT INTO af_compositions (subject_type, subject_id, format, module_id, deliverable,
                                   deliverable_spec, angle, audience, length, voice_preset,
                                   extra, model, status, title, subtitle, body, finished_at)
         VALUES ("freeform", NULL, "weekly", ?, ?, ?, NULLIF(?, ""), "practitioners", "long",
                 "bizorca_press", NULLIF(?, ""), ?, "done", ?, ?, ?, NOW())',
        [$moduleId, $deliverable, $specJson, (string) ($meta['angle'] ?? ''),
         (string) ($meta['extra'] ?? ''), $model, $title, (string) ($meta['subtitle'] ?? ''), $body]);

    echo "composition #{$id} filed — module {$module['id']} ({$module['title']}), "
       . "{$deliverable}, {$words} words" . ($spec ? ', deliverable spec attached' : ', no deliverable spec') . "\n"
       . "  review: /compose/{$id}   (promotion assigns the sequence number)\n";
    exit(0);
}

/**
 * The weekly desk, from the command line.
 *
 *   php cli.php modules
 *   php cli.php weekly --module=3 --deliverable=worksheet [--angle="..."]
 *                      [--source=concept:412] [--audience=practitioners]
 *                      [--extra="..."] [--no-expand]
 *
 * Same path as the /weekly screen: it creates a composition and queues the
 * compose job. Nothing generates here — run `php cli.php work` (or let the
 * page-load tick do it) and then promote the result.
 */
if ($cmd === 'modules') {
    printf("%-3s %-26s %-12s %-6s %s\n", '#', 'MODULE', 'COHORT', 'POSTS', 'ARTIFACT');
    foreach (Database::all(
        'SELECT m.*, (SELECT COUNT(*) FROM af_posts p
                       WHERE p.format = "weekly" AND p.module_id = m.id) AS n
           FROM af_modules m ORDER BY m.id') as $m) {
        printf("%-3d %-26s %-12s %-6d %s%s\n", $m['id'],
            mb_substr($m['title'], 0, 26), $m['cohort'], $m['n'],
            mb_substr($m['artifact'], 0, 48),
            (int) $m['stalls'] === 1 ? '  [stall point]' : '');
    }
    exit(0);
}

/**
 * php cli.php sources --module=8 [--limit=20]
 *
 * The mapped library material for a module, with the ids --source wants.
 * Without this the CLI path dead-ends: you can pick a module and a container
 * from the terminal but have no way to find anything to write from, which
 * sends you back to the browser for the one value you needed.
 */
if ($cmd === 'sources') {
    $moduleId = (int) ($opts['module'] ?? 0);
    $module = \Anglerfish\Models\Weekly::module($moduleId);
    if (!$module) {
        fwrite(STDERR, "--module=N is required and must be 1-12. See: php cli.php modules\n");
        exit(64);
    }
    $rows = \Anglerfish\Models\Weekly::sourcesForModule(
        $moduleId, max(1, (int) ($opts['limit'] ?? 20)));
    if (!$rows) {
        echo "Nothing mapped to module {$moduleId} yet.\n";
        exit(0);
    }
    printf("Module %d — %s\n\n", $module['id'], $module['title']);
    printf("%-22s %-5s %s\n", '--source', 'CONF', 'TITLE');
    foreach ($rows as $r) {
        printf("%-22s %-5.2f %s\n",
            $r['subject_type'] . ':' . $r['subject_id'],
            (float) $r['confidence'],
            mb_substr((string) $r['title'], 0, 74));
    }
    exit(0);
}

if ($cmd === 'weekly') {
    $moduleId = (int) ($opts['module'] ?? 0);
    $module = \Anglerfish\Models\Weekly::module($moduleId);
    if (!$module) {
        fwrite(STDERR, "--module=N is required and must be 1-12. See: php cli.php modules\n");
        exit(64);
    }

    $deliverable = (string) ($opts['deliverable'] ?? '');
    if (!isset(\Anglerfish\Models\Weekly::CONTAINERS[$deliverable])) {
        fwrite(STDERR, "--deliverable must be one of: "
            . implode(', ', array_keys(\Anglerfish\Models\Weekly::CONTAINERS)) . "\n");
        exit(64);
    }

    // --source=concept:412 — same subject types the compose picker offers.
    $type = 'freeform';
    $id = 0;
    if (!empty($opts['source']) && is_string($opts['source'])
        && str_contains($opts['source'], ':')) {
        [$type, $rawId] = explode(':', $opts['source'], 2);
        $id = (int) $rawId;
    }
    $subject = $id ? \Anglerfish\Models\Composition::subject($type, $id) : null;
    if ($id && !$subject) {
        fwrite(STDERR, "No such source: {$opts['source']}\n");
        exit(65);
    }

    $compId = \Anglerfish\Models\Composition::create([
        'subject_type' => $subject ? $type : 'freeform',
        'subject_id'   => $subject ? $id : 0,
        'format'       => 'weekly',
        'module_id'    => $moduleId,
        'deliverable'  => $deliverable,
        'angle'        => trim((string) ($opts['angle'] ?? '')),
        'audience'     => (string) ($opts['audience'] ?? 'practitioners'),
        'length'       => 'long',
        'voice_preset' => 'bizorca_press',
        'extra'        => trim((string) ($opts['extra'] ?? '')),
        'expand'       => empty($opts['no-expand']),
    ], $subject);

    echo "composition #$compId queued — module {$module['id']} ({$module['title']}), "
       . "$deliverable\n"
       . "  run:  php cli.php work --types=compose\n"
       . "  then: /compose/$compId to review and promote\n";
    exit(0);
}


/**
 * php cli.php images --book=the-90-day-coach [--redo] [--limit=N] [--dry-run]
 *
 * One infographic per chapter, ordered in batch at half price. Nothing is
 * generated by this command: it art-directs each chapter as a job, then one
 * submit job builds a single Gemini batch out of the directions. Watch it with
 * `php cli.php batches` and review the result at /library/<slug>/images.
 */
if ($cmd === 'images') {
    $price = \Anglerfish\Services\GeminiBatch::PRICE_PER_IMAGE;
    $cfg = require $root . '/config/app.php';
    $model = (string) ($cfg['gemini']['image_model'] ?? 'gemini-3-pro-image');
    $redo = !empty($opts['redo']);

    // --all orders the whole library. Chapters are grouped by book and planned
    // per book rather than as one flat run, so every batch still belongs to a
    // book and the per-book contact sheet keeps working unchanged.
    if (!empty($opts['all'])) {
        $rows = \Anglerfish\Models\ImageBatch::allChapters($redo);
        if (isset($opts['limit'])) {
            $rows = array_slice($rows, 0, max(1, (int) $opts['limit']));
        }
        if (!$rows) {
            echo "Nothing to order — every press-scope chapter with a summary is done.\n";
            exit(0);
        }

        $byBook = [];
        foreach ($rows as $r) {
            $byBook[(int) $r['book_id']][] = $r;
        }
        printf("%d chapters across %d books, about $%.2f in batch "
             . "(against $%.2f interactive)\n\n",
            count($rows), count($byBook), count($rows) * $price, count($rows) * 0.134);
        foreach ($byBook as $chapters) {
            printf("  %-46s %3d\n",
                mb_substr((string) $chapters[0]['book_title'], 0, 46), count($chapters));
        }

        if (!empty($opts['dry-run'])) {
            echo "\ndry run — nothing queued\n";
            exit(0);
        }

        $batches = 0;
        foreach ($byBook as $bookId => $chapters) {
            $batches += count(\Anglerfish\Models\ImageBatch::plan(
                $bookId, $chapters, $model));
        }
        printf("\nqueued %d batches, %d art-direction jobs\n", $batches, count($rows));
        echo "  the cron drains this on its own; watch with:\n"
           . "    php cli.php batches | head -20\n"
           . "    php cli.php status\n"
           . "  review:  /review\n";
        exit(0);
    }

    // --retry re-orders images whose direction survived but whose draw failed,
    // reusing the stored prompt. No Anthropic call, which matters when that is
    // the resource that ran out.
    if (!empty($opts['retry'])) {
        $rows = \Anglerfish\Models\ImageBatch::retryable();
        if (isset($opts['limit'])) {
            $rows = array_slice($rows, 0, max(1, (int) $opts['limit']));
        }
        if (!$rows) {
            echo "Nothing to retry — no rejected image has a prompt and a subject "
               . "still missing an image.\n";
            exit(0);
        }

        $byBook = [];
        foreach ($rows as $r) {
            $byBook[(int) $r['book_id']][] = $r;
        }
        printf("%d image%s to redraw from existing directions, about $%.2f\n\n",
            count($rows), count($rows) === 1 ? '' : 's', count($rows) * $price);
        foreach ($byBook as $group) {
            printf("  %-46s %3d\n",
                mb_substr((string) $group[0]['book_title'], 0, 46), count($group));
        }

        if (!empty($opts['dry-run'])) {
            echo "\ndry run — nothing queued\n";
            exit(0);
        }

        $batches = 0;
        foreach ($byBook as $bookId => $group) {
            $batches += count(\Anglerfish\Models\ImageBatch::planFromPrompts(
                $bookId, $group, $model));
        }
        printf("\nqueued %d batches, no art direction needed\n", $batches);
        echo "  submit:  php cli.php work --types=image_batch_submit\n"
           . "  poll:    php cli.php work --types=image_batch_poll\n";
        exit(0);
    }

    $slug = (string) ($opts['book'] ?? '');
    $book = Database::one('SELECT id, slug, title FROM af_books WHERE slug = ?', [$slug]);
    if (!$book) {
        fwrite(STDERR, "--book=<slug> is required (or --all, or --retry). See /library.\n");
        exit(64);
    }

    $chapters = \Anglerfish\Models\ImageBatch::chapters((int) $book['id'], $redo);
    if (isset($opts['limit'])) {
        $chapters = array_slice($chapters, 0, max(1, (int) $opts['limit']));
    }
    if (!$chapters) {
        echo "Nothing to order for {$book['slug']} — every extracted chapter with a "
           . "summary already has an image ordered. Use --redo to order anyway.\n";
        exit(0);
    }

    printf("%s\n%d chapter%s, about $%.2f in batch (against $%.2f interactive)\n\n",
        $book['title'], count($chapters), count($chapters) === 1 ? '' : 's',
        count($chapters) * $price, count($chapters) * 0.134);
    foreach ($chapters as $c) {
        printf("  %-10s %s\n", mb_substr((string) $c['number_label'], 0, 10),
            mb_substr((string) $c['title'], 0, 66));
    }

    if (!empty($opts['dry-run'])) {
        echo "\ndry run — nothing queued\n";
        exit(0);
    }

    $ids = \Anglerfish\Models\ImageBatch::plan((int) $book['id'], $chapters, $model);

    echo "\nqueued batch " . implode(', ', array_map(static fn($i) => "#$i", $ids)) . "\n"
       . "  direct:  php cli.php work --types=art_direct --max=3   (~30s each)\n"
       . "  submit:  php cli.php work --types=image_batch_submit\n"
       . "  poll:    php cli.php work --types=image_batch_poll\n"
       . "  watch:   php cli.php batches\n"
       . "  review:  /review\n";
    exit(0);
}

/** php cli.php batches [--book=slug] — what every batch is waiting on. */
if ($cmd === 'batches') {
    $where = '';
    $args = [];
    if (!empty($opts['book'])) {
        $where = ' WHERE b.slug = ?';
        $args[] = (string) $opts['book'];
    }
    $rows = Database::all(
        "SELECT ib.*, b.slug,
                (SELECT COUNT(*) FROM af_assets a
                  WHERE a.batch_id = ib.id AND a.status = 'pending') AS waiting,
                (SELECT COUNT(*) FROM af_jobs j
                  WHERE j.type = 'art_direct'
                    AND j.status IN ('queued','leased','running')
                    AND CAST(JSON_EXTRACT(j.payload, '$.batch_id') AS UNSIGNED) = ib.id
                ) AS directing
           FROM af_image_batches ib
           JOIN af_books b ON b.id = ib.subject_id" . $where
        . ' ORDER BY ib.id DESC LIMIT 20', $args);

    if (!$rows) {
        echo "No image batches yet.\n";
        exit(0);
    }
    printf("%-5s %-26s %-11s %-5s %-5s %-5s %-7s %s\n",
        'ID', 'BOOK', 'STATUS', 'REQ', 'GOT', 'WAIT', 'DIRECT', 'REMOTE');
    foreach ($rows as $r) {
        printf("%-5d %-26s %-11s %-5d %-5d %-5d %-7d %s\n",
            $r['id'], mb_substr((string) $r['slug'], 0, 26), $r['status'],
            (int) $r['request_count'], (int) $r['applied_count'], (int) $r['waiting'],
            (int) $r['directing'],
            mb_substr((string) ($r['remote_state'] ?: $r['remote_name'] ?: ''), 0, 30));
        if ($r['error']) {
            echo '      ' . mb_substr((string) $r['error'], 0, 100) . "\n";
        }
    }
    exit(0);
}

/**
 * php cli.php qa-export [--book=slug] [--limit=N]
 *
 * Unreviewed, unchecked infographics as JSON lines, for worker/image_qa.py.
 * Read-only.
 */
if ($cmd === 'qa-export') {
    $rows = \Anglerfish\Models\Review::qaExport(
        isset($opts['book']) ? (string) $opts['book'] : null,
        (int) ($opts['limit'] ?? 50));
    foreach ($rows as $r) {
        echo json_encode([
            'id'            => (int) $r['id'],
            'subject_type'  => $r['subject_type'],
            'subject_id'    => (int) $r['subject_id'],
            'title'         => $r['subject_title'],
            'book'          => $r['subject_context'],
            'slug'          => $r['subject_slug'],
            'note'          => $r['subject_note'],
            'web_path'      => $r['web_path'],
            'format'        => $r['format'],
            'spec'          => json_decode((string) $r['spec_json'], true),
            'prompt'        => $r['prompt_snapshot'],
            'prior_fails'   => $r['prior_fails'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    }
    fwrite(STDERR, count($rows) . " infographic(s) exported\n");
    exit(0);
}

/**
 * php cli.php qa-apply [--dry-run] < verdicts.jsonl
 *
 * One {"id":N,"verdict":"pass|fail|unsure","notes":"..."} per line. pass keeps,
 * fail rejects and re-directs (until the subject has failed twice), unsure
 * leaves the image in /review flagged. --dry-run validates and changes nothing.
 */
if ($cmd === 'qa-apply') {
    $tally = [];
    $n = 0;
    while (($line = fgets(STDIN)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $v = json_decode($line, true);
        if (!is_array($v) || !isset($v['id'], $v['verdict'])) {
            fwrite(STDERR, "bad line: " . mb_substr($line, 0, 120) . "\n");
            $tally['bad-line'] = ($tally['bad-line'] ?? 0) + 1;
            continue;
        }
        $n++;
        $verdict = (string) $v['verdict'];
        $notes = (string) ($v['notes'] ?? '');
        if (!empty($opts['dry-run'])) {
            $r = in_array($verdict, \Anglerfish\Models\Review::QA_VERDICTS, true)
                ? "would-$verdict" : 'bad-verdict';
        } else {
            $r = \Anglerfish\Models\Review::qaApply((int) $v['id'], $verdict, $notes);
        }
        $tally[$r] = ($tally[$r] ?? 0) + 1;
        printf("%-6d %-7s %-12s %s\n", (int) $v['id'], $verdict, $r, mb_substr($notes, 0, 90));
    }
    ksort($tally);
    echo "\n$n verdict(s): " . implode(', ', array_map(
        static fn($k, $c) => "$c $k", array_keys($tally), $tally)) . "\n";
    if (($tally['redrawn'] ?? 0) > 0) {
        echo "redraws are queued as art_direct jobs; the cron drains them, or:\n"
           . "  php cli.php work --types=art_direct --max=5\n";
    }
    exit(0);
}

if ($cmd !== 'work') {
    fwrite(STDERR, "usage: php cli.php [work|status|modules|sources|weekly|weekly-import|images|batches|qa-export|qa-apply]"
        . " [--max=N] [--types=a,b]\n");
    exit(64);
}

$max = max(1, (int) ($opts['max'] ?? 5));
$types = isset($opts['types'])
    ? array_values(array_intersect(explode(',', (string) $opts['types']), $serverTypes))
    : $serverTypes;

if (!$types) {
    exit(0);
}

// A single lock, so overlapping cron ticks cannot double-process.
//
// `--lock=<name>` gives a caller its own lock file, which is how several
// drainers run at once. That is safe rather than reckless: Job::lease() claims
// rows with FOR UPDATE SKIP LOCKED, so concurrent workers never hand out the
// same job — the flock exists to stop the *cron* stacking ticks on itself, not
// to enforce one worker. Art direction is a 30-second network call, so a serial
// drain of 475 of them is four hours and four parallel drains is one.
$lockName = preg_replace('/[^a-z0-9-]/i', '', (string) ($opts['lock'] ?? '')) ?: 'cli';
$lock = fopen(AF_STORAGE . '/.' . $lockName . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);                       // previous tick still running; nothing to do
}

// A cron tick has to finish inside its minute; a drain invoked by hand does
// not, and cutting it off at 45 seconds just re-queues work it could have done.
$window = max(5, (int) ($opts['window'] ?? 45));

$done = $failed = 0;
$started = time();

foreach (Job::lease($types, $max, 600) as $job) {
    // Leave headroom inside the execution window rather than being killed
    // mid-job and leaving the lease dangling.
    if (time() - $started > $window) {
        Job::defer((int) $job['id'], $job['lease_token'], 'deferred: cron window');
        continue;
    }

    try {
        $data = ServerJobs::run($job['type'], $job['payload'] ?? []);
        Database::run(
            "UPDATE af_jobs SET status='done', finished_at=NOW(), lease_token=NULL,
                             lease_expires_at=NULL, error=NULL
              WHERE id=? AND lease_token=?",
            [(int) $job['id'], $job['lease_token']]
        );
        ServerJobs::apply($job['type'], $data);
        $done++;
        echo "ok   {$job['type']} #{$job['id']}\n";
    } catch (Throwable $e) {
        Job::fail((int) $job['id'], $job['lease_token'],
            get_class($e) . ': ' . $e->getMessage());
        $failed++;
        echo "FAIL {$job['type']} #{$job['id']} {$e->getMessage()}\n";
    }
}

flock($lock, LOCK_UN);
if ($done || $failed) {
    echo "done=$done failed=$failed in " . (time() - $started) . "s\n";
}
