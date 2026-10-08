<?php
/**
 * Find TimeBank's private files and load its config. First require of index.php.
 *
 *   repo       timebank/public/       ->  timebank/{includes,src}
 *   Cloudways  public_html/timebank/  ->  private_html/timebank/{includes,src}
 *
 * Only this file knows the two layouts apart; everything after uses TM_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/timebank'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('TM_ROOT', realpath($dir));
        break;
    }
}

if (!defined('TM_ROOT')) {
    http_response_code(500);
    exit('TimeBank configuration not found. Check the deploy.');
}

require_once TM_ROOT . '/includes/config.php';
