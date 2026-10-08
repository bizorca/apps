<?php
/**
 * Find ThinkRep's private files and load its config. First require on every page.
 *
 *   repo       thinkrep/public/  ->  thinkrep/includes, thinkrep/templates
 *   Cloudways  public_html/thinkrep/  ->  private_html/thinkrep/includes, .../templates
 *
 * Only this file knows the two layouts apart; everything after uses TR_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/thinkrep'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('TR_ROOT', realpath($dir));
        break;
    }
}

if (!defined('TR_ROOT')) {
    http_response_code(500);
    exit('ThinkRep configuration not found. Check the deploy.');
}

require_once TR_ROOT . '/includes/config.php';
