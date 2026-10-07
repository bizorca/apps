<?php
/**
 * One-time import of the old proforma.bizorca.com SQLite database into the
 * shared MySQL database.
 *
 *   php proforma/bin/import-sqlite.php /path/to/proforma.sqlite [--dry-run]
 *
 * Users are matched into the shared users table by email; an existing tools
 * account is reused, otherwise one is created carrying the old bcrypt hash, so
 * people sign in with their existing Proforma password. Proforma's own rows keep
 * their original ids (the pf_ tables must be empty, which the script checks), so
 * every business_id / payer_id / cpt_id reference stays valid untouched.
 *
 * All of it runs in one transaction: it imports everything or nothing.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-sqlite.php /path/to/proforma.sqlite [--dry-run]\n");
    exit(1);
}

foreach ([__DIR__ . '/../../private_html/includes', __DIR__ . '/../../includes'] as $dir) {
    if (is_file($dir . '/bootstrap.php')) {
        require $dir . '/bootstrap.php';
        break;
    }
}

$lite = new PDO('sqlite:' . $src, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$my = tl_db();

// Parent tables before children, so foreign keys hold at every insert.
$tables = [
    'businesses', 'expenses', 'class_schedules', 'revenue_streams', 'instructors',
    'insurance_payers', 'cpt_codes', 'payer_rates', 'staff_providers', 'monthly_actuals',
    'market_profiles', 'local_organizations', 'recommendations', 'recommendation_feedback',
    'api_cache', 'user_settings',
];

foreach ($tables as $t) {
    if ((int) $my->query("SELECT COUNT(*) FROM pf_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "pf_{$t} already has rows. This import is for an empty install; stopping.\n");
        exit(1);
    }
}

$liteTables = array_flip($lite->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN));

$my->beginTransaction();
try {
    // ── users → shared users, old id → new id ──────────────────────────────
    $userMap = [];
    foreach ($lite->query('SELECT * FROM users ORDER BY id') as $u) {
        $email = strtolower(trim($u['email']));
        $find  = $my->prepare('SELECT id FROM users WHERE email = ?');
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id) {
            echo "  user {$u['id']} -> existing tools account {$id}\n";
        } else {
            $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            if ($name === '') {
                $name = ucfirst(strtok($email, '@'));
            }
            $my->prepare('INSERT INTO users (email, password_hash, name, is_admin, created_at) VALUES (?, ?, ?, ?, ?)')
               ->execute([$email, $u['password_hash'], $name, (int) ($u['is_admin'] ?? 0), $u['created_at']]);
            $id = $my->lastInsertId();
            echo "  user {$u['id']} -> new tools account {$id}\n";
        }
        $userMap[(int) $u['id']] = (int) $id;
    }

    // ── tool tables, ids preserved, user_id remapped ───────────────────────
    foreach ($tables as $t) {
        if (!isset($liteTables[$t])) {
            continue;
        }
        $rows = $lite->query("SELECT * FROM {$t}")->fetchAll();
        if (!$rows) {
            echo "  pf_{$t}: 0\n";
            continue;
        }
        $cols = array_keys($rows[0]);
        $sql  = sprintf('INSERT INTO pf_%s (%s) VALUES (%s)', $t,
            implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
            implode(', ', array_fill(0, count($cols), '?')));
        $ins = $my->prepare($sql);
        foreach ($rows as $r) {
            if (array_key_exists('user_id', $r)) {
                $r['user_id'] = $userMap[(int) $r['user_id']] ?? throw new RuntimeException("{$t}: unknown user {$r['user_id']}");
            }
            $ins->execute(array_values($r));
        }
        echo "  pf_{$t}: " . count($rows) . "\n";
    }

    if ($dry) {
        $my->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $my->commit();
        echo "Imported.\n";
    }
} catch (Throwable $e) {
    $my->rollBack();
    fwrite(STDERR, 'Rolled back: ' . $e->getMessage() . "\n");
    exit(1);
}
