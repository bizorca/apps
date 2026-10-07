<?php
require_once __DIR__ . '/config.php';

/*
 * The shared MySQL handle (tl_db(): native prepares, UTC), kept under its old
 * name so every page works unchanged. The schema lives in
 * migrations/001_tinybooks.sql and is applied by private_html/bin/migrate.php;
 * the old db_install()/db_installed() first-run path is gone.
 *
 * Money columns are DECIMAL(15,2), which PDO returns as strings ("125.00").
 * PHP's numeric-string arithmetic and comparisons handle that, but never
 * compare an amount with === or treat "0.00" as falsy: it is a non-empty string.
 */
function db(): PDO {
    return tl_db();
}
