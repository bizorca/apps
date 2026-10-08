<?php
/**
 * Find Placecard's private files and load its config. First require on every page.
 *
 *   repo       placecard/public/          ->  placecard/includes, placecard/src
 *   Cloudways  public_html/placecard/     ->  private_html/placecard/...
 *
 * Only this file knows the two layouts apart; everything after uses PC_ROOT.
 * public/api/index.php requires this file one level up.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/placecard'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('PC_ROOT', realpath($dir));
        break;
    }
}

if (!defined('PC_ROOT')) {
    http_response_code(500);
    exit('Placecard configuration not found. Check the deploy.');
}

require_once PC_ROOT . '/includes/config.php';
