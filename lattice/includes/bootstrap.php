<?php
/*
 * Required first by every page, via public/_bootstrap.php. Unlike the
 * original, it does not start a session: the shared session is resumed only
 * if the visitor already has one, so anonymous visitors get no cookie.
 */
if (!defined('LMS_LOADED')) define('LMS_LOADED', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
