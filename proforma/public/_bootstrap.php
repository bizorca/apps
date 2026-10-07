<?php
/**
 * Find Proforma's private files and load its config. First require on every page.
 *
 *   repo       proforma/public/  ->  proforma/includes, proforma/templates
 *   Cloudways  public_html/proforma/  ->  private_html/proforma/includes, .../templates
 *
 * Only this file knows the two layouts apart; everything after uses PF_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/proforma'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('PF_ROOT', realpath($dir));
        break;
    }
}

if (!defined('PF_ROOT')) {
    http_response_code(500);
    exit('Proforma configuration not found. Check the deploy.');
}

require_once PF_ROOT . '/includes/config.php';
