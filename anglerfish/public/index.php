<?php
/**
 * Anglerfish Press — front controller, at tools.bizorca.com/anglerfish/.
 *
 * Private: Jassen's own production desk. Every page requires a site admin on
 * the shared tools account (tl_require_admin), and nothing public links here.
 * The one exception is the worker API (/api/worker/*), which the Mac worker
 * calls with a bearer token and no session; Api::authorize() gates it.
 *
 * ROUTING. This Cloudways app has no try_files fallback, so every route travels
 * in the query string: /anglerfish/?r=/library/some-book. url() builds them.
 */

declare(strict_types=1);

// bin/smoke.php defines AF_ROOT itself and then runs this file per route.
foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/anglerfish'] as $_dir) {
    if (!defined('AF_ROOT') && is_file($_dir . '/includes/boot.php')) {
        define('AF_ROOT', realpath($_dir));
    }
}
unset($_dir);
if (!defined('AF_ROOT')) {
    http_response_code(500);
    exit('Anglerfish configuration not found. Check the deploy.');
}
require_once AF_ROOT . '/includes/boot.php';

use Anglerfish\Core\Router;
use Anglerfish\Core\Session;
use Anglerfish\Controllers\AuthController;
use Anglerfish\Controllers\DashboardController;
use Anglerfish\Controllers\ComposeController;
use Anglerfish\Controllers\CorpusController;
use Anglerfish\Controllers\GenerateController;
use Anglerfish\Controllers\ImagesController;
use Anglerfish\Controllers\ReviewController;
use Anglerfish\Controllers\KitController;
use Anglerfish\Controllers\PostController;
use Anglerfish\Controllers\SummaryController;
use Anglerfish\Controllers\TickController;
use Anglerfish\Controllers\LibraryController;
use Anglerfish\Controllers\TriageController;
use Anglerfish\Controllers\VideoController;
use Anglerfish\Controllers\WeeklyController;
use Anglerfish\Controllers\WorkerController;

$config = require AF_ROOT . '/config/app.php';

if ($config['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');

$route = af_route();

// The worker is a daemon with a bearer token, not a browser with a session.
// Everything else is the admin's alone, and the gate runs before any routing so
// that not even a 404 page answers an outsider.
if (!str_starts_with($route, '/api/worker/')) {
    tl_require_admin();
    Session::start();
}

$router = new Router();

$router->get('/',           [AuthController::class, 'index']);
$router->post('/logout',    [AuthController::class, 'logout']);
$router->get('/dashboard',  [DashboardController::class, 'index']);
$router->get('/compose',           [ComposeController::class, 'form']);
$router->post('/compose',          [ComposeController::class, 'create']);
$router->get('/compose/{id}',      [ComposeController::class, 'show']);
$router->get('/compose/{id}/status', [ComposeController::class, 'status']);
$router->post('/compose/{id}/promote', [ComposeController::class, 'promote']);
$router->get('/weekly',            [WeeklyController::class, 'index']);
$router->post('/weekly',           [WeeklyController::class, 'create']);
$router->get('/videos',            [VideoController::class, 'index']);
$router->get('/videos/{number}/script.txt', [VideoController::class, 'download']);
$router->get('/videos/{number}',   [VideoController::class, 'show']);
$router->post('/videos/{number}',  [VideoController::class, 'update']);
$router->get('/corpus',            [CorpusController::class, 'index']);
$router->post('/corpus/deep',      [CorpusController::class, 'deep']);
$router->post('/corpus/clip',      [CorpusController::class, 'clip']);
$router->get('/generate',          [GenerateController::class, 'form']);
$router->post('/generate',         [GenerateController::class, 'create']);
$router->get('/posts',             [PostController::class, 'index']);
$router->get('/posts/{slug}',      [PostController::class, 'show']);
$router->post('/posts/{slug}',     [PostController::class, 'update']);
$router->get('/kits',              [KitController::class, 'index']);
$router->post('/render',           [KitController::class, 'render']);
$router->get('/queue',             [TriageController::class, 'queue']);
$router->post('/triage',           [TriageController::class, 'mark']);
$router->post('/tick',             [TickController::class, 'tick'], csrf: false);
$router->get('/library',           [LibraryController::class, 'index']);
$router->get('/library/{slug}',    [LibraryController::class, 'show']);
$router->get('/media/cover/{id}',  [LibraryController::class, 'cover']);
$router->get('/media/asset/{id}',  [LibraryController::class, 'asset']);
$router->post('/library/scope', [LibraryController::class, 'setScope']);
$router->post('/library/{slug}/chapters', [LibraryController::class, 'saveChapters']);
$router->get('/review',            [ReviewController::class, 'index']);
$router->post('/review/act',       [ReviewController::class, 'act']);
$router->post('/review/bulk',      [ReviewController::class, 'bulk']);
$router->get('/library/{slug}/images',     [ImagesController::class, 'sheet']);
$router->post('/library/{slug}/images',    [ImagesController::class, 'start']);
$router->post('/library/{slug}/images/act', [ImagesController::class, 'act']);
$router->get('/library/{slug}/summary',    [SummaryController::class, 'show']);
$router->get('/library/{slug}/summary.md', [SummaryController::class, 'download']);
$router->get('/library/{slug}/script.txt', [SummaryController::class, 'downloadScript']);
$router->post('/library/{slug}/script',    [SummaryController::class, 'script']);

// Worker API — bearer token, no session, no CSRF (SPEC §13).
$router->api('/api/worker/enqueue',                  [WorkerController::class, 'enqueue']);
$router->api('/api/worker/extractions',               [WorkerController::class, 'extractions']);
$router->api('/api/worker/fetch',                     [WorkerController::class, 'fetch']);
$router->api('/api/worker/asset',                     [WorkerController::class, 'asset']);
$router->api('/api/worker/cover',                     [WorkerController::class, 'cover']);
$router->api('/api/worker/content',                   [WorkerController::class, 'content']);
$router->api('/api/worker/lease',                    [WorkerController::class, 'lease']);
$router->api('/api/worker/jobs/{id}/heartbeat',      [WorkerController::class, 'heartbeat']);
$router->api('/api/worker/jobs/{id}/result',         [WorkerController::class, 'result']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $route);
