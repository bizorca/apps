<?php
/**
 * One-time import of the old kit.bizorca.com SQLite database into the shared
 * MySQL database.
 *
 *   php kit/bin/import-sqlite.php /path/to/kit.sqlite [--dry-run]
 *
 * Users are matched into the shared users table by email; an existing tools
 * account is reused as-is. Kit accounts signed in only through Bizorca SSO and
 * have no password, so a new account gets an unusable random hash: the person
 * sets a password with "Forgot your password?" on first visit. Kit's is_admin
 * mirrored login.bizorca.com's admin flag and gated nothing in Kit, so it is
 * not carried into the shared (site-wide) is_admin.
 *
 * Kit's own rows keep their original ids (the kit_ tables must be empty, which
 * the script checks), so every client_id / assessment_id reference stays valid.
 * All of it runs in one transaction: everything or nothing.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-sqlite.php /path/to/kit.sqlite [--dry-run]\n");
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
$tables = ['clients', 'assessments', 'progress_entries'];

foreach ($tables as $t) {
    if ((int) $my->query("SELECT COUNT(*) FROM kit_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "kit_{$t} already has rows. This import is for an empty install; stopping.\n");
        exit(1);
    }
}

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
                $name = ucfirst((string) strtok($email, '@'));
            }
            // Unusable: no one knows this password, so the only way in is a reset.
            $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $my->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
               ->execute([$email, $hash, $name, $u['created_at']]);
            $id = $my->lastInsertId();
            echo "  user {$u['id']} -> new tools account {$id} (no password yet: use Forgot your password)\n";
        }
        $userMap[(int) $u['id']] = (int) $id;
    }

    // ── tool tables, ids preserved, user_id remapped ───────────────────────
    foreach ($tables as $t) {
        $rows = $lite->query("SELECT * FROM {$t}")->fetchAll();
        if (!$rows) {
            echo "  kit_{$t}: 0\n";
            continue;
        }
        $cols = array_keys($rows[0]);
        $sql  = sprintf('INSERT INTO kit_%s (%s) VALUES (%s)', $t,
            implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
            implode(', ', array_fill(0, count($cols), '?')));
        $ins = $my->prepare($sql);
        foreach ($rows as $r) {
            if (array_key_exists('user_id', $r)) {
                $r['user_id'] = $userMap[(int) $r['user_id']] ?? throw new RuntimeException("{$t}: unknown user {$r['user_id']}");
            }
            $ins->execute(array_values($r));
        }
        echo "  kit_{$t}: " . count($rows) . "\n";
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
