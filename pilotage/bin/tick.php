<?php

declare(strict_types=1);

/**
 * The tick, from Cloudways cron.
 *
 *   php bin/tick.php five-minute
 *   php bin/tick.php nightly
 *   php bin/tick.php nightly --force     run even if not due (testing)
 *
 * On SiteGround the tick could only be triggered by page loads (no cron, and
 * background processes killed), so Core\Heartbeat fires it from the request
 * that finds it due. Cloudways has real cron, so this is now the primary
 * trigger and the page-load heartbeat is the backup. They cannot double-run:
 * both claim the mode through Heartbeat::claim(), the same row and window, and
 * whichever claims second finds it not due. Services\Tick stays the one
 * implementation of what a tick does.
 *
 * Lives in private_html/pilotage/bin on the server, outside the web root:
 * nginx on this app would happily execute anything under public_html.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

foreach ([$root . '/../includes', $root . '/../private_html/includes'] as $dir) {
    if (is_file($dir . '/bootstrap.php')) {
        require_once $dir . '/bootstrap.php';
        break;
    }
}

require $root . '/src/Core/autoload.php';

use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Heartbeat;
use Bizorca\Pilotage\Services\Tick;

Config::load(require $root . '/config/app.php');

$mode = $argv[1] ?? 'nightly';
$force = in_array('--force', $argv, true);

if (!in_array($mode, Tick::MODES, true)) {
    fwrite(STDERR, "Unknown mode '{$mode}'. One of: " . implode(', ', Tick::MODES) . "\n");
    exit(1);
}

if (!$force && !Heartbeat::claim($mode)) {
    echo '[' . date('c') . "] {$mode}: not due\n";
    exit(0);
}

Tick::run($mode, static function (string $line): void {
    echo '[' . date('c') . '] ' . $line . "\n";
});
