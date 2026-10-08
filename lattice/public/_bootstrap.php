<?php
/**
 * Find Lattice's private files and load its config. First require on every page.
 *
 *   repo       lattice/public/      ->  lattice/includes
 *   Cloudways  public_html/lattice/ ->  private_html/lattice/includes
 *
 * Only this file knows the two layouts apart; everything after uses LT_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/lattice'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('LT_ROOT', realpath($dir));
        break;
    }
}

if (!defined('LT_ROOT')) {
    http_response_code(500);
    exit('Lattice configuration not found. Check the deploy.');
}

require_once LT_ROOT . '/includes/bootstrap.php';
