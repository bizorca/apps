<?php

declare(strict_types=1);

/**
 * Build (or rebuild) the scratch schema the test suite runs against.
 *
 *   php tests/schema.php            drop + create pilotage_tools_test, apply migrations
 *   TEST_DB_NAME=x php tests/schema.php
 *
 * It applies what production has, in the order production has it: the shared
 * core migrations (the `users` table every pl_users.account_id points at),
 * then Pilotage's own, split into statements exactly the way the platform's
 * private_html/bin/migrate.php splits them, so a migration that would break on
 * deploy breaks here first.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

$config = test_config();
$name = (string) $config['db']['name'];

if (!preg_match('/^[a-z0-9_]+$/', $name) || $name === (string) tl_env('DB_NAME', '')) {
    fwrite(STDERR, "Refusing to rebuild '{$name}': not a scratch schema name.\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['db']['host'], (int) $config['db']['port']),
    (string) $config['db']['user'],
    (string) $config['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
$pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$name}`");

$files = array_merge(
    glob(dirname(__DIR__, 2) . '/private_html/migrations/*.sql') ?: [],
    glob(dirname(__DIR__) . '/migrations/*.sql') ?: []
);

foreach ($files as $file) {
    foreach (preg_split('/;\s*$/m', (string) file_get_contents($file)) as $stmt) {
        $body = trim((string) preg_replace('/^\s*--.*$/m', '', $stmt));
        if ($body !== '') {
            $pdo->exec($body);
        }
    }
    echo '  applied  ' . basename(dirname($file, 2)) . '/' . basename($file) . "\n";
}

echo "Schema {$name} ready.\n";
