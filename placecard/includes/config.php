<?php
declare(strict_types=1);

/*
 * Placecard, as a tool on tools.bizorca.com/placecard.
 *
 * PC_ROOT is set by public/_bootstrap.php (or a CLI script). The account and
 * database come from the shared core in private_html/includes: one users
 * table, one MySQL database, Placecard's tables prefixed pc_. Placecard has no
 * secrets of its own any more: the JWT secret went with JWTs (the API issues
 * opaque bearer tokens stored hashed in pc_api_tokens) and the database
 * credentials are the shared ones.
 */

if (!defined('PC_ROOT')) {
    define('PC_ROOT', dirname(__DIR__));
}

foreach ([PC_ROOT . '/../includes', PC_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

// The original ran PHP in UTC on SiteGround and stored event times as the
// wall-clock time typed into the form. Keep PHP in UTC so a dinner entered as
// 19:00 still shows as 7:00 PM, and tl_db() keeps MySQL's NOW() in UTC too.
date_default_timezone_set('UTC');

define('PC_BASE', '/placecard');                 // URL prefix for every web link and redirect
define('PC_API_BASE', PC_BASE . '/api/?r=');     // API routes: /placecard/api/?r=/events
define('APP_NAME', 'placecard');

// Headers the original set in .htaccess, which this server never reads.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

define('INTERESTS', [
    'Cooking', 'Travel', 'Music', 'Film', 'Books', 'Art', 'Hiking',
    'Photography', 'Wine', 'Coffee', 'Tech', 'Startups', 'Fitness',
    'Yoga', 'Writing', 'Design', 'History', 'Science', 'Comedy', 'Theater'
]);

define('DINING_PREFS', [
    'Local cuisine', 'Fine dining', 'Casual spots', 'Street food',
    'Vegetarian-friendly', 'Seafood', 'No restrictions',
    'Early dinner (6–7pm)', 'Late dinner (8pm+)', 'No alcohol'
]);

define('CITIES', [
    'chiang-mai'    => ['name' => 'Chiang Mai',     'country' => 'Thailand',       'emoji' => '🇹🇭'],
    'port-townsend' => ['name' => 'Port Townsend',  'country' => 'United States',  'emoji' => '🇺🇸'],
]);

require_once PC_ROOT . '/src/Response.php';
require_once PC_ROOT . '/src/Auth.php';
require_once PC_ROOT . '/src/Profile.php';
require_once PC_ROOT . '/src/Router.php';
require_once PC_ROOT . '/src/controllers/AuthController.php';
require_once PC_ROOT . '/src/controllers/CityController.php';
require_once PC_ROOT . '/src/controllers/RestaurantController.php';
require_once PC_ROOT . '/src/controllers/EventController.php';
require_once PC_ROOT . '/src/controllers/UserController.php';

/** A web path ('/dashboard?city=x') under the tool's base: '/placecard/dashboard.php?city=x'. */
function pcUrl(string $path): string
{
    if ($path === '/' || $path === '') {
        return PC_BASE . '/';
    }
    $q = '';
    if (($i = strpos($path, '?')) !== false) {
        $q = substr($path, $i);
        $path = substr($path, 0, $i);
    }
    return PC_BASE . '/' . trim($path, '/') . '.php' . $q;
}

function pcRedirect(string $path): never
{
    header('Location: ' . pcUrl($path));
    exit;
}
