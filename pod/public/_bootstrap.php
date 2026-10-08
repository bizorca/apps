<?php
/**
 * Find Pod's private files and load its config. First require of index.php.
 *
 *   repo       pod/public/       ->  pod/{includes,src}
 *   Cloudways  public_html/pod/  ->  private_html/pod/{includes,src}
 *
 * Only this file knows the two layouts apart; everything after uses PD_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/pod'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('PD_ROOT', realpath($dir));
        break;
    }
}

if (!defined('PD_ROOT')) {
    http_response_code(500);
    exit('Pod configuration not found. Check the deploy.');
}

require_once PD_ROOT . '/includes/config.php';
