<?php

/**
 * Burn Rate - Front Controller
 * "The only financial game where losing is winning"
 *
 * Routes arrive as /burnrate/?r=/path (see BR_CLEAN_URLS in includes/config.php).
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use App\Core\App;
use App\Controllers\AccountController;
use App\Controllers\GameController;
use App\Controllers\AdminController;
use App\Controllers\PageController;
use App\Middleware\AuthMiddleware;
use App\Middleware\GameMiddleware;
use App\Middleware\AdminMiddleware;

// The original turned display_errors on in production. Errors go to the log.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Security headers, formerly nowhere (the original's .htaccess only rewrote).
// No CSP: the pages load Tailwind, Chart.js and Alpine from CDNs and use
// inline scripts, so a policy would need 'unsafe-inline' and 'unsafe-eval'.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

try {
    $app = new App();
    $router = $app->router;

    // Public marketing pages
    $router->get('/', [PageController::class, 'home']);
    $router->get('/about', [PageController::class, 'about']);
    $router->get('/features', [PageController::class, 'features']);
    $router->get('/pricing', [PageController::class, 'pricing']);

    // Sign-in is the shared tools account now. Old SSO and logout addresses
    // still resolve, to the shared pages.
    $router->get('/sso/callback', [PageController::class, 'signIn']);
    $router->get('/login', [PageController::class, 'signIn']);
    $router->get('/register', [PageController::class, 'register']);
    $router->get('/logout', [PageController::class, 'signOut']);

    // Account routes (requires auth)
    $router->get('/account', [AccountController::class, 'dashboard'], [AuthMiddleware::class]);
    $router->post('/account/player/create', [AccountController::class, 'createPlayer'], [AuthMiddleware::class]);
    $router->get('/account/player/{id}/play', [AccountController::class, 'selectPlayer'], [AuthMiddleware::class]);
    $router->post('/account/player/{id}/delete', [AccountController::class, 'deletePlayer'], [AuthMiddleware::class]);
    $router->get('/account/top10', [AccountController::class, 'top10'], [AuthMiddleware::class]);

    // Game routes (requires auth + player selected)
    $router->get('/game', [GameController::class, 'index'], [GameMiddleware::class]);
    $router->post('/game/action', [GameController::class, 'action'], [GameMiddleware::class]);
    $router->get('/game/do/{code}', [GameController::class, 'doAction'], [GameMiddleware::class]);

    // Property actions (mansions & islands)
    $router->post('/game/re/offer', [GameController::class, 'propertyBuy'], [GameMiddleware::class]);
    $router->post('/game/re/sell', [GameController::class, 'propertySell'], [GameMiddleware::class]);

    // Toy actions (supercars, yachts, jets, etc.)
    $router->post('/game/stock/buy', [GameController::class, 'toyBuy'], [GameMiddleware::class]);
    $router->post('/game/stock/sell', [GameController::class, 'toySell'], [GameMiddleware::class]);

    // Investment actions (hedge funds, crypto, startups)
    $router->post('/game/biz/start', [GameController::class, 'investmentMake'], [GameMiddleware::class]);
    $router->post('/game/biz/liquidate', [GameController::class, 'investmentLiquidate'], [GameMiddleware::class]);

    // Chart data API
    $router->get('/api/chart/{type}', [GameController::class, 'chartData'], [GameMiddleware::class]);

    // Admin routes
    $router->get('/admin', [AdminController::class, 'dashboard'], [AdminMiddleware::class]);
    $router->get('/admin/owners', [AdminController::class, 'owners'], [AdminMiddleware::class]);
    $router->get('/admin/players', [AdminController::class, 'players'], [AdminMiddleware::class]);
    $router->get('/admin/gamelogs', [AdminController::class, 'gameLogs'], [AdminMiddleware::class]);
    $router->get('/admin/settings', [AdminController::class, 'settings'], [AdminMiddleware::class]);
    $router->post('/admin/settings', [AdminController::class, 'saveSettings'], [AdminMiddleware::class]);
    $router->get('/admin/users/create', [AdminController::class, 'createUserForm'], [AdminMiddleware::class]);
    $router->post('/admin/users/create', [AdminController::class, 'createUser'], [AdminMiddleware::class]);

    $app->run();
} catch (\Throwable $e) {
    error_log('Burn Rate: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!DOCTYPE html><title>Burn Rate</title><p style="font-family:sans-serif;padding:2rem">Something went wrong. Even our money pit has limits. Please try again.</p>';
}
