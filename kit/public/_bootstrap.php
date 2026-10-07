<?php
/**
 * Find Kit's private files and boot the app. First require on every page.
 *
 *   repo       kit/public/      ->  kit/includes, kit/templates
 *   Cloudways  public_html/kit/ ->  private_html/kit/includes, .../templates
 *
 * Only this file knows the two layouts apart; everything after uses KIT_ROOT.
 * KIT_PUBLIC is this directory, which asset() needs for cache-busting: under
 * /kit, DOCUMENT_ROOT no longer points at Kit's own public files.
 */

declare(strict_types=1);

define('KIT_PUBLIC', __DIR__);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/kit'] as $dir) {
    if (is_file($dir . '/includes/bootstrap.php')) {
        define('KIT_ROOT', realpath($dir));
        break;
    }
}

if (!defined('KIT_ROOT')) {
    http_response_code(500);
    exit('Kit configuration not found. Check the deploy.');
}

require_once KIT_ROOT . '/includes/bootstrap.php';
