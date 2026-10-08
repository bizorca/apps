<?php

declare(strict_types=1);

/**
 * Test runner.
 *
 *   php tests/run.php              run everything
 *   php tests/run.php Host         run files matching "Host"
 *
 * Database-backed suites need TEST_DB_NAME to exist and migrations applied:
 *   mysql -u root -e "CREATE DATABASE pilotage_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
 *   php migrate.php --db=pilotage_test
 */

require __DIR__ . '/bootstrap.php';

use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Database;

Config::load(test_config());

$filter = $argv[1] ?? null;

$files = glob(__DIR__ . '/*Test.php') ?: [];
sort($files, SORT_STRING);

if ($filter !== null) {
    $files = array_values(array_filter(
        $files,
        static fn (string $f): bool => stripos(basename($f), $filter) !== false
    ));
}

if ($files === []) {
    fwrite(STDERR, "No test files matched.\n");
    exit(1);
}

// Pure suites run without a database. Only complain about a missing schema if
// a suite actually needs one.
$needsDb = false;
foreach ($files as $file) {
    if (str_contains((string) file_get_contents($file), 'Database::conn()')) {
        $needsDb = true;
        break;
    }
}

if ($needsDb) {
    $dbName = (string) Config::get('db.name');
    try {
        Database::conn()->query('SELECT 1 FROM pl_tenants LIMIT 1');
    } catch (Throwable $e) {
        fwrite(STDERR, "\nDatabase-backed tests need the test schema.\n\n");
        fwrite(STDERR, "  php tests/schema.php        (creates {$dbName}: core users + every pl_ migration)\n\n");
        fwrite(STDERR, "Reason: " . $e->getMessage() . "\n\n");
        exit(1);
    }
}

echo "\nPilotage — Phase 0 test suite\n";
echo str_repeat('=', 60) . "\n";

foreach ($files as $file) {
    echo "\n" . basename($file) . "\n";
    require $file;
}

exit(T::summary());
