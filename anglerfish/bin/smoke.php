<?php
/**
 * Route smoke test. Fakes a session and runs the real front controller for
 * every GET route, reporting the byte count or the exception with file:line.
 *
 * Written after a bare /library 500'd in production: the scope check tested the
 * coalesced value but assigned the raw one, so only the URL *without* a query
 * string broke — and every test at the time happened to pass one.
 *
 * Usage:  php bin/smoke.php            (from private_html/anglerfish on the server)
 *         php bin/smoke.php /library   (single route)
 */

$ROUTES = [
    '/', '/dashboard', '/library', '/library?scope=press', '/library?scope=intake',
    '/library?scope=reference', '/library?scope=all', '/library?scope=bogus',
    '/library?q=money', '/queue', '/kits', '/nonexistent-route',
    '/posts', '/posts?format=gut_check', '/posts?format=bogus',
    '/posts?status=published', '/posts?q=referral',
    '/corpus', '/corpus?q=referral', '/corpus?q=pricing&brain=dk',
    '/corpus?q=zzzznomatch', '/corpus?type=transcript', '/corpus?deep=999999',
    '/compose', '/compose?subject_type=freeform', '/compose/1', '/compose/999999',
    '/compose/1/status',
    '/compose?pick_type=big_idea', '/compose?pick_type=chapter',
    '/compose?pick_type=tool', '/compose?pick_type=clipping',
    '/compose?pick_type=post', '/compose?pick_type=bogus',
    '/compose?pick_type=big_idea&pick=referral',
    '/weekly', '/weekly?module=1', '/weekly?module=9', '/weekly?module=12',
    '/weekly?module=99', '/weekly?module=bogus',
    '/generate', '/generate?subject_type=concept&subject_id=1',
    '/generate?subject_type=bogus&subject_id=1',
    '/library/big-ideas-dk-referrals/summary',
    '/library/big-ideas-dk-referrals/summary.md',
    '/library/big-ideas-dk-referrals/script.txt',
    '/library/definitely-not-a-book/summary',
    '/review', '/review?filter=kept', '/review?filter=rejected',
    '/review?filter=all', '/review?filter=bogus', '/review?filter=all&page=999',
    '/library/the-90-day-coach/images',
    '/library/definitely-not-a-book/images',
];

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$app = dirname(__DIR__);
// Server: private_html/anglerfish -> public_html/anglerfish. Repo: anglerfish/public.
$docroot = dirname($app, 2) . '/public_html/anglerfish';
if (!is_file("$docroot/index.php")) {
    $docroot = "$app/public";
}

define('AF_ROOT', $app);
require "$app/includes/boot.php";
$pdo = \Anglerfish\Core\Database::pdo();

// A site admin to sign in as: every page is admin-only.
$adminId = (int) $pdo->query('SELECT id FROM users WHERE is_admin = 1 ORDER BY id LIMIT 1')->fetchColumn();
if (!$adminId) {
    fwrite(STDERR, "No site admin in users; nothing can sign in.\n");
    exit(1);
}
if ($slug = $pdo->query('SELECT slug FROM af_books ORDER BY id LIMIT 1')->fetchColumn()) {
    $ROUTES[] = '/library/' . $slug;
}
$ROUTES[] = '/library/definitely-not-a-book';

if ($pslug = $pdo->query('SELECT slug FROM af_posts ORDER BY number LIMIT 1')->fetchColumn()) {
    $ROUTES[] = '/posts/' . $pslug;
}
$ROUTES[] = '/posts/not-a-real-post';

// One route per process. The app calls exit() on redirects and on API
// responses, so running them in a single process stops at the first one.
if (($argv[1] ?? '') === '') {
    $fail = 0;
    foreach ($ROUTES as $uri) {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
             . ' ' . escapeshellarg($uri) . ' 2>&1';
        $out = trim((string) shell_exec($cmd));
        echo ($out === '' ? sprintf("  WARN  %-38s empty output (redirected?)", $uri) : $out), "\n";
        // Match the harness's own marker line, not the word anywhere in the
        // body: a book section titled "Why business owners FAIL at referrals"
        // is page content, not a route failure.
        if (preg_match('/^  FAIL  /m', $out)) {
            $fail++;
        }
    }
    echo $fail ? "\n$fail route(s) failed\n" : "\nall " . count($ROUTES) . " routes ok\n";
    exit($fail ? 1 : 0);
}

$routes = [$argv[1]];
$fail = 0;

foreach ($routes as $uri) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = 'tools.bizorca.com';
    $_GET = [];
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $_GET);
    $_GET['r'] = (string) parse_url($uri, PHP_URL_PATH);
    $_SERVER['REQUEST_URI'] = '/anglerfish/?' . http_build_query($_GET);

    // Start the session BEFORE seeding it, and sign in as a site admin through
    // the shared account's own session key.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = ['tl_user_id' => $adminId, 'af_csrf' => 'smoke'];

    ob_start();
    try {
        require $docroot . '/index.php';
        $out = ob_get_clean();
        printf("  ok    %-38s %7d bytes\n", $uri, strlen($out));
    } catch (Throwable $e) {
        ob_end_clean();
        $fail++;
        printf("  FAIL  %-38s %s @ %s:%d\n", $uri, $e->getMessage(),
            basename($e->getFile()), $e->getLine());
    }
}

exit($fail ? 1 : 0);
