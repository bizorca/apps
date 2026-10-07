<?php
/**
 * Apply pending migrations: the shared ones in private_html/migrations, then
 * each tool's own <tool>/migrations. Every file runs once, tracked in
 * schema_migrations, so this is safe to run after every deploy.
 *
 *   php private_html/bin/migrate.php            apply
 *   php private_html/bin/migrate.php --status   list, change nothing
 *
 * Tools live at <repo>/<tool>/ locally and private_html/<tool>/ on the server.
 * MySQL 8.4 is not MariaDB: no ADD COLUMN IF NOT EXISTS. Write plain DDL and
 * let the tracking table make it idempotent.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$db = tl_db();
$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    name       VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

// Shared first: tool tables reference users.
$sets = ['core' => TL_PRIVATE . '/migrations'];
foreach ([TL_PRIVATE . '/*/migrations', TL_PRIVATE . '/../*/migrations'] as $pattern) {
    foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $dir) {
        $tool = basename(dirname($dir));
        if ($tool !== 'private_html' && !isset($sets[$tool])) {
            $sets[$tool] = $dir;
        }
    }
}

$done   = array_flip($db->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
$status = in_array('--status', $argv, true);

foreach ($sets as $tool => $dir) {
    foreach (glob($dir . '/*.sql') ?: [] as $file) {
        $name = $tool . '/' . basename($file);
        if (isset($done[$name])) {
            echo "  done     {$name}\n";
            continue;
        }
        if ($status) {
            echo "  PENDING  {$name}\n";
            continue;
        }
        $sql = (string) file_get_contents($file);
        // Statements end with ';' at end of line. DDL commits implicitly in
        // MySQL, so a failure part-way leaves earlier statements applied; the
        // CREATE TABLE IF NOT EXISTS style makes a rerun pick up from there.
        foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
            $body = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
            if ($body !== '') {
                $db->exec($body);
            }
        }
        $db->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
        echo "  applied  {$name}\n";
    }
}
