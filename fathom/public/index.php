<?php
/**
 * Fathom front controller. Every route arrives here: /fathom/?r=/boards/5
 * today, or /fathom/boards/5 if clean URLs are ever enabled (see
 * FM_CLEAN_URLS in includes/config.php).
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

fm_dispatch();
