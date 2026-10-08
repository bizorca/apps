<?php
/**
 * Find Burn Rate's private files and load its config. First require of index.php.
 *
 *   repo       burnrate/public/       ->  burnrate/{includes,src}
 *   Cloudways  public_html/burnrate/  ->  private_html/burnrate/{includes,src}
 *
 * Only this file knows the two layouts apart; everything after uses BR_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/burnrate'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('BR_ROOT', realpath($dir));
        break;
    }
}

if (!defined('BR_ROOT')) {
    http_response_code(500);
    exit('Burn Rate configuration not found. Check the deploy.');
}

require_once BR_ROOT . '/includes/config.php';
