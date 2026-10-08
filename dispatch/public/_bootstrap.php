<?php
/**
 * Find Dispatch's private files and load its config. First require of index.php.
 *
 *   repo       dispatch/public/       ->  dispatch/{includes,src,views}
 *   Cloudways  public_html/dispatch/  ->  private_html/dispatch/{includes,src,views}
 *
 * Only this file knows the two layouts apart; everything after uses DP_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/dispatch'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('DP_ROOT', realpath($dir));
        break;
    }
}

if (!defined('DP_ROOT')) {
    http_response_code(500);
    exit('Dispatch configuration not found. Check the deploy.');
}

require_once DP_ROOT . '/includes/config.php';
