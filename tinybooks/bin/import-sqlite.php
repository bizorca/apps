<?php
/**
 * One-time import of a TinyBooks SQLite database into the shared MySQL database.
 *
 *   php tinybooks/bin/import-sqlite.php /path/to/tinybooks.sqlite [--dry-run]
 *
 * Users are matched into the shared users table by email; an existing tools
 * account is reused, otherwise one is created carrying the old bcrypt hash, so
 * people sign in with their existing TinyBooks password. TinyBooks' own is_admin
 * is NOT carried over: it meant "can add TinyBooks users", and the shared
 * is_admin is site-wide. Company access comes across as tb_user_companies.
 *
 * TinyBooks' rows keep their original ids (the tb_ tables must be empty, which
 * is checked), so every company/account/transaction/line reference stays valid.
 * The settings table is not imported: its only key was the Anthropic API key,
 * now a server setting.
 *
 * Books are checked BEFORE anything is written: every amount must be whole
 * cents (MySQL stores DECIMAL(15,2); a REAL like 10.005 would be rounded, so it
 * stops instead) and every date a real Y-m-d date. After writing, each
 * account's debit and credit totals are compared with the SQLite source to the
 * cent. Any problem rolls the whole import back.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-sqlite.php /path/to/tinybooks.sqlite [--dry-run]\n");
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
    'companies', 'user_companies', 'accounts', 'transactions', 'transaction_lines',
    'budgets', 'reconciliations', 'reconciliation_items',
];
$liteTables = array_flip($lite->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN));

foreach ($tables as $t) {
    if ((int) $my->query("SELECT COUNT(*) FROM tb_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "tb_{$t} already has rows. This import is for an empty install; stopping.\n");
        exit(1);
    }
}

// ── validate the books before touching MySQL ────────────────────────────────
$problems = [];
$cents = function ($v): bool {
    if ($v === null) return true;
    $c = (float) $v * 100;
    return abs($c - round($c)) < 1e-6 && abs((float) $v) < 1e13;
};
$isDate = function ($d): bool {
    return is_string($d) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
};
$money = ['transaction_lines' => ['debit', 'credit'], 'budgets' => ['amount'], 'reconciliations' => ['ending_balance']];
foreach ($money as $t => $cols) {
    if (!isset($liteTables[$t])) continue;
    foreach ($lite->query("SELECT * FROM {$t}") as $r) {
        foreach ($cols as $c) {
            if (!$cents($r[$c])) {
                $problems[] = "{$t} id {$r['id']}: {$c} = {$r[$c]} is not whole cents";
            }
        }
    }
}
foreach (['transactions' => 'date', 'reconciliations' => 'statement_date'] as $t => $c) {
    if (!isset($liteTables[$t])) continue;
    foreach ($lite->query("SELECT id, {$c} FROM {$t}") as $r) {
        if (!$isDate($r[$c])) {
            $problems[] = "{$t} id {$r['id']}: {$c} = '{$r[$c]}' is not a Y-m-d date";
        }
    }
}
if ($problems) {
    fwrite(STDERR, "Not importing; fix these in the SQLite file first:\n  " . implode("\n  ", $problems) . "\n");
    exit(1);
}

if (isset($liteTables['settings'])) {
    $keys = $lite->query("SELECT key FROM settings")->fetchAll(PDO::FETCH_COLUMN);
    if ($keys) {
        echo "  settings not imported (keys: " . implode(', ', $keys) . "). The API key is now ANTHROPIC_API_KEY in .env.php.\n";
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
            $name = trim((string) $u['name']) !== '' ? trim((string) $u['name']) : ucfirst((string) strtok($email, '@'));
            $my->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
               ->execute([$email, $u['password_hash'], $name, $u['created_at'] ?? date('Y-m-d H:i:s')]);
            $id = $my->lastInsertId();
            echo "  user {$u['id']} -> new tools account {$id}\n";
        }
        $userMap[(int) $u['id']] = (int) $id;
    }

    // ── tool tables, ids preserved, user ids remapped ───────────────────────
    $nullMoney = 0;
    foreach ($tables as $t) {
        if (!isset($liteTables[$t])) continue;
        $rows = $lite->query("SELECT * FROM {$t} ORDER BY " . ($t === 'user_companies' ? 'user_id, company_id' : 'id'))->fetchAll();
        if (!$rows) {
            echo "  tb_{$t}: 0\n";
            continue;
        }
        $cols = array_keys($rows[0]);
        $ins  = $my->prepare(sprintf('INSERT INTO tb_%s (%s) VALUES (%s)', $t,
            implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
            implode(', ', array_fill(0, count($cols), '?'))));
        $parents = [];
        foreach ($rows as $r) {
            if ($t === 'user_companies') {
                $r['user_id'] = $userMap[(int) $r['user_id']] ?? throw new RuntimeException("user_companies: unknown user {$r['user_id']}");
            }
            if ($t === 'transactions' && $r['created_by'] !== null) {
                $r['created_by'] = $userMap[(int) $r['created_by']] ?? null;
            }
            if ($t === 'transaction_lines') {
                foreach (['debit', 'credit'] as $c) {
                    if ($r[$c] === null) { $r[$c] = 0; $nullMoney++; }
                }
            }
            // An account can name a parent with a higher id: insert without it, link after.
            if ($t === 'accounts' && $r['parent_id'] !== null) {
                $parents[(int) $r['id']] = (int) $r['parent_id'];
                $r['parent_id'] = null;
            }
            $ins->execute(array_values($r));
        }
        foreach ($parents as $child => $parent) {
            $my->prepare('UPDATE tb_accounts SET parent_id = ? WHERE id = ?')->execute([$parent, $child]);
        }
        echo "  tb_{$t}: " . count($rows) . "\n";
    }
    if ($nullMoney) {
        echo "  {$nullMoney} NULL debit/credit values stored as 0.00 (SUM treated them as 0 before too)\n";
    }

    // ── verify: every account's totals, to the cent ─────────────────────────
    $sum = 'SELECT tl.account_id, ROUND(SUM(tl.debit) * 100) AS d, ROUND(SUM(tl.credit) * 100) AS c, COUNT(*) AS n
              FROM %s tl GROUP BY tl.account_id ORDER BY tl.account_id';
    $before = [];
    if (isset($liteTables['transaction_lines'])) {
        foreach ($lite->query(sprintf($sum, 'transaction_lines')) as $r) {
            $before[(int) $r['account_id']] = [(int) $r['d'], (int) $r['c'], (int) $r['n']];
        }
    }
    $after = [];
    foreach ($my->query(sprintf($sum, 'tb_transaction_lines')) as $r) {
        $after[(int) $r['account_id']] = [(int) $r['d'], (int) $r['c'], (int) $r['n']];
    }
    if ($before !== $after) {
        throw new RuntimeException('account totals differ after import: ' . json_encode(['sqlite' => $before, 'mysql' => $after]));
    }
    $dTotal = array_sum(array_column($after, 0)) / 100;
    $cTotal = array_sum(array_column($after, 1)) / 100;
    printf("  verified: %d accounts, debits %s, credits %s, identical to the source to the cent\n",
        count($after), number_format($dTotal, 2), number_format($cTotal, 2));

    $orphans = $my->query('SELECT u.email FROM users u WHERE u.id IN (' . (implode(',', $userMap) ?: '0') . ')
                           AND NOT EXISTS (SELECT 1 FROM tb_user_companies uc WHERE uc.user_id = u.id)')->fetchAll(PDO::FETCH_COLUMN);
    if ($orphans) {
        echo "  note: no company access for " . implode(', ', $orphans) . " (add them under TinyBooks Settings)\n";
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
