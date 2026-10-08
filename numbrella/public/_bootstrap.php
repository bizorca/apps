<?php
/**
 * Find Numbrella's private files and load its config. First require on every page.
 *
 *   repo       numbrella/public/  ->  numbrella/includes, numbrella/templates
 *   Cloudways  public_html/numbrella/  ->  private_html/numbrella/includes, .../templates
 *
 * Only this file knows the two layouts apart; everything after uses NB_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/numbrella'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('NB_ROOT', realpath($dir));
        break;
    }
}

if (!defined('NB_ROOT')) {
    http_response_code(500);
    exit('Numbrella configuration not found. Check the deploy.');
}

require_once NB_ROOT . '/includes/config.php';
