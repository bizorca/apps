<?php
/**
 * Find TinyBooks' private files and load its config. First require on every page.
 *
 *   repo       tinybooks/public/      ->  tinybooks/includes
 *   Cloudways  public_html/tinybooks/ ->  private_html/tinybooks/includes
 *
 * Only this file knows the two layouts apart; everything after uses TB_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/tinybooks'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('TB_ROOT', realpath($dir));
        break;
    }
}

if (!defined('TB_ROOT')) {
    http_response_code(500);
    exit('TinyBooks configuration not found. Check the deploy.');
}

require_once TB_ROOT . '/includes/config.php';
