<?php
/**
 * Find Astrology's private files and load its config. First require on every page.
 *
 *   repo       astrology/public/            ->  astrology/includes, astrology/templates
 *   Cloudways  public_html/astrology/       ->  private_html/astrology/includes, .../templates
 *
 * Only this file knows the two layouts apart; everything after uses AS_ROOT.
 */

declare(strict_types=1);

if (!defined('AS_ROOT')) {
    foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/astrology'] as $dir) {
        if (is_file($dir . '/includes/config.php')) {
            define('AS_ROOT', realpath($dir));
            break;
        }
    }
}

if (!defined('AS_ROOT')) {
    http_response_code(500);
    exit('Astrology configuration not found. Check the deploy.');
}

require_once AS_ROOT . '/includes/config.php';
