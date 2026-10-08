<?php
/**
 * Find Commonweal's private files and load its config. First require on every page.
 *
 *   repo       commonweal/public/      ->  commonweal/includes, commonweal/templates
 *   Cloudways  public_html/commonweal/ ->  private_html/commonweal/includes, .../templates
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/commonweal'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('CW_ROOT', realpath($dir));
        break;
    }
}

if (!defined('CW_ROOT')) {
    http_response_code(500);
    exit('Commonweal configuration not found. Check the deploy.');
}

require_once CW_ROOT . '/includes/config.php';
