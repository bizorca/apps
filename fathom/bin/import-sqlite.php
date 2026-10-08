<?php
/**
 * One-time import of the old fathom.bizorca.com SQLite database
 * (laravel/database/database.sqlite) into the shared MySQL database.
 *
 *   php fathom/bin/import-sqlite.php /path/to/database.sqlite [--dry-run]
 *
 * Every Fathom user becomes a membership (fm_users) tied to a shared tools
 * account, matched by email. A new tools account gets the Fathom name, and an
 * unusable password hash when the Fathom user had none (Fathom signed in by
 * magic link or Bizorca SSO, so most had none): that person signs in via
 * "Forgot your password?". All other rows keep their UUIDs, so every
 * reference stays valid untouched.
 *
 * One transaction: everything or nothing. Refuses if any fm_ table has rows.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-sqlite.php /path/to/database.sqlite [--dry-run]\n");
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

// Parents before children, so every foreign key holds at insert time.
$tables = [
    'accounts', 'users', 'boards', 'columns', 'cards', 'tags', 'taggings', 'clients',
    'comments', 'reactions', 'assignments', 'steps', 'closures', 'watches', 'pins',
    'mentions', 'notifications', 'events', 'filters', 'accesses', 'magic_links',
    'exports', 'card_templates', 'card_template_tags', 'card_template_steps', 'client_cards',
];
$jsonCols = ['settings', 'params', 'metadata'];

foreach ($tables as $t) {
    if ((int) $my->query("SELECT COUNT(*) FROM fm_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "fm_{$t} already has rows. This import is for an empty install; stopping.\n");
        exit(1);
    }
}

$liteTables = array_flip($lite->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN));

/** Columns the MySQL table actually has. */
$myCols = function (string $table) use ($my): array {
    return $my->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
};

$noPassword = 0;
$my->beginTransaction();
try {
    foreach ($tables as $t) {
        if (!isset($liteTables[$t])) {
            echo "  fm_{$t}: (not in source)\n";
            continue;
        }
        $rows = $lite->query("SELECT * FROM {$t}")->fetchAll();

        if ($t === 'users') {
            foreach ($rows as $u) {
                $email = strtolower(trim((string) $u['email_address']));
                $find  = $my->prepare('SELECT id FROM users WHERE email = ?');
                $find->execute([$email]);
                $sharedId = $find->fetchColumn();
                if ($sharedId) {
                    echo "  user {$email}: existing tools account {$sharedId}\n";
                    // Earlier imports (Proforma) named accounts after the email's local
                    // part when they had no name. Fathom knows the real one; use it.
                    $placeholder = ucfirst((string) strtok($email, '@'));
                    $fathomName  = trim((string) $u['name']);
                    $upd = $my->prepare('UPDATE users SET name = ? WHERE id = ? AND name = ?');
                    $upd->execute([mb_substr($fathomName, 0, 100), $sharedId, $placeholder]);
                    if ($fathomName !== '' && $upd->rowCount() > 0) {
                        echo "    name '{$placeholder}' -> '{$fathomName}'\n";
                    }
                } else {
                    $hash = (string) ($u['password'] ?? '');
                    if (!str_starts_with($hash, '$2y$')) {
                        // Matches no password, ever: forces "Forgot your password?".
                        $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                        $noPassword++;
                    }
                    $my->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
                       ->execute([$email, $hash, mb_substr((string) $u['name'], 0, 100), $u['created_at'] ?? gmdate('Y-m-d H:i:s')]);
                    $sharedId = $my->lastInsertId();
                    echo "  user {$email}: new tools account {$sharedId}" . (str_starts_with((string) ($u['password'] ?? ''), '$2y$') ? '' : ' (no password yet: Forgot your password)') . "\n";
                }
                $my->prepare(
                    'INSERT INTO fm_users (id, user_id, account_id, role, is_sysop, avatar_path, notification_email,
                                           notification_digest, time_zone, created_at, updated_at, deleted_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $u['id'], $sharedId, $u['account_id'], $u['role'], (int) ($u['is_sysop'] ?? 0),
                    $u['avatar_path'] ?? null, $u['notification_email'] ?? null, (int) ($u['notification_digest'] ?? 0),
                    $u['time_zone'] ?? null, $u['created_at'] ?? null, $u['updated_at'] ?? null, $u['deleted_at'] ?? null,
                ]);
            }
            echo "  fm_users: " . count($rows) . "\n";
            continue;
        }

        if (!$rows) {
            echo "  fm_{$t}: 0\n";
            continue;
        }
        $keep = array_values(array_intersect(array_keys($rows[0]), $myCols("fm_{$t}")));
        $ins  = $my->prepare(sprintf(
            'INSERT INTO fm_%s (%s) VALUES (%s)',
            $t,
            implode(', ', array_map(fn($c) => "`{$c}`", $keep)),
            implode(', ', array_fill(0, count($keep), '?'))
        ));
        foreach ($rows as $r) {
            $vals = [];
            foreach ($keep as $c) {
                $v = $r[$c];
                if (in_array($c, $jsonCols, true) && ($v === '' || $v === null)) {
                    $v = $c === 'params' ? '{}' : null;
                }
                $vals[] = $v;
            }
            $ins->execute($vals);
        }
        echo "  fm_{$t}: " . count($rows) . "\n";
    }

    if ($dry) {
        $my->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $my->commit();
        echo "Imported. {$noPassword} new tools account(s) need \"Forgot your password?\" for their first sign-in.\n";
    }
} catch (Throwable $e) {
    $my->rollBack();
    fwrite(STDERR, 'Rolled back: ' . $e->getMessage() . "\n");
    exit(1);
}
