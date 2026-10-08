<?php
/**
 * Find Foundry's private files and load its config. First require of index.php.
 *
 *   repo       foundry/public/       ->  foundry/{includes,src}
 *   Cloudways  public_html/foundry/  ->  private_html/foundry/{includes,src}
 *
 * Only this file knows the two layouts apart; everything after uses FD_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/foundry'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('FD_ROOT', realpath($dir));
        break;
    }
}

if (!defined('FD_ROOT')) {
    http_response_code(500);
    exit('Foundry configuration not found. Check the deploy.');
}

require_once FD_ROOT . '/includes/config.php';
