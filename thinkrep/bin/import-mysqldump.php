<?php
/**
 * One-time import of the old thinkrep.bizorca.com MySQL database (a mysqldump
 * of it) into the shared tools database.
 *
 *   php thinkrep/bin/import-mysqldump.php /path/to/thinkrep-live.sql [--dry-run]
 *
 * How, given that a Cloudways app's database user cannot CREATE DATABASE:
 *   1. The dump is loaded into temporary staging tables in the SAME database,
 *      renamed trimp_<table>, with their foreign keys stripped.
 *   2. Everything is copied into the tr_ tables in one transaction: users are
 *      matched into the shared users table by email (an existing tools account
 *      is reused); ThinkRep ids are kept, so every reference stays valid.
 *   3. The staging tables are dropped, whatever happened.
 *
 * The original app signed in through Bizorca SSO only and stored no password,
 * so a NEW shared account gets an unusable random hash: the person signs in
 * the first time through "Forgot your password?".
 *
 * Content (models, scenarios, choices, mashup mappings, the template cohort)
 * already arrives with migration 002. Only scenarios the seed lacks (approved
 * submissions, company packs) are copied; a seeded scenario whose live text
 * differs is reported, not overwritten. Template cohorts are not copied:
 * enrollments in them are pointed at the seeded template of the same name.
 *
 * Refuses to run if any tr_ table already holds user data.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-mysqldump.php /path/to/dump.sql [--dry-run]\n");
    exit(1);
}

foreach ([__DIR__ . '/../../private_html/includes', __DIR__ . '/../../includes'] as $dir) {
    if (is_file($dir . '/bootstrap.php')) {
        require $dir . '/bootstrap.php';
        break;
    }
}

$db = tl_db();

const TR_TABLES = [
    'users', 'mental_models', 'scenarios', 'scenario_choices', 'responses', 'confidence_logs',
    'blindspot_cache', 'scenario_submissions', 'hindsight_reflections', 'mashup_responses',
    'scenario_correct_models', 'teams', 'team_members', 'team_challenges', 'companies',
    'company_members', 'scenario_packs', 'cohorts', 'cohort_scenarios', 'cohort_enrollments',
    'benchmarks',
];

// ── refuse on a non-empty install ─────────────────────────────────────────────
$userData = ['profiles', 'responses', 'mashup_responses', 'confidence_logs', 'teams', 'companies',
             'scenario_submissions', 'cohort_enrollments', 'hindsight_reflections'];
foreach ($userData as $t) {
    if ((int) $db->query("SELECT COUNT(*) FROM tr_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "tr_{$t} already has rows. This import is for an install with no user data; stopping.\n");
        exit(1);
    }
}
if ((int) $db->query('SELECT COUNT(*) FROM tr_cohorts WHERE is_template = 0')->fetchColumn() > 0) {
    fwrite(STDERR, "tr_cohorts already has non-template cohorts; stopping.\n");
    exit(1);
}

// ── 1. stage the dump ─────────────────────────────────────────────────────────
function stage_name(string $t): string { return 'trimp_' . $t; }

function drop_staging(PDO $db): void {
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (TR_TABLES as $t) {
        $db->exec('DROP TABLE IF EXISTS `' . stage_name($t) . '`');
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

drop_staging($db);

$sql = (string) file_get_contents($src);
$names = implode('|', array_map('preg_quote', TR_TABLES));
// Statements in a mysqldump end with ';' at end of line; values never contain
// a raw newline (mysqldump escapes them as \n), so this split is exact.
$statements = preg_split('/;\s*\n/', $sql);
$staged = 0;
try {
    foreach ($statements as $stmt) {
        $stmt = trim(preg_replace('~^(--.*|/\*!.*\*/)$~m', '', $stmt));
        if ($stmt === '') {
            continue;
        }
        if (preg_match('/^CREATE TABLE `(' . $names . ')`/', $stmt)) {
            // Staging copies need no constraints, and the original constraint
            // names (responses_ibfk_1...) must not collide with anything.
            $stmt = preg_replace('/^\s*(CONSTRAINT|FOREIGN KEY)\b.*\n?/m', '', $stmt);
            $stmt = preg_replace('/,\s*\n(\s*\))/', "\n$1", $stmt);   // the comma left before ')'
            $stmt = preg_replace('/^CREATE TABLE `(' . $names . ')`/', 'CREATE TABLE `trimp_$1`', $stmt);
            $db->exec($stmt);
            $staged++;
        } elseif (preg_match('/^INSERT INTO `(' . $names . ')`/', $stmt)) {
            $db->exec(preg_replace('/^INSERT INTO `(' . $names . ')`/', 'INSERT INTO `trimp_$1`', $stmt));
        }
        // Everything else (SET, LOCK TABLES, DROP, GTID lines) is skipped.
    }
    if ($staged !== count(TR_TABLES)) {
        throw new RuntimeException("expected " . count(TR_TABLES) . " tables in the dump, found {$staged}");
    }
} catch (Throwable $e) {
    drop_staging($db);
    fwrite(STDERR, 'Staging failed: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "  staged {$staged} tables\n";

// ── 2. copy ───────────────────────────────────────────────────────────────────
function rows(PDO $db, string $table, string $where = '1=1'): array {
    return $db->query('SELECT * FROM `' . stage_name($table) . "` WHERE {$where} ORDER BY id")->fetchAll();
}

/** Insert rows into tr_<table>, keeping only columns the target has. */
function put(PDO $db, string $table, array $rows, array $map = []): int {
    if (!$rows) {
        echo "  tr_{$table}: 0\n";
        return 0;
    }
    static $colsCache = [];
    $target = "tr_{$table}";
    $colsCache[$target] ??= $db->query("SHOW COLUMNS FROM `{$target}`")->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_values(array_intersect(array_keys($rows[0]), $colsCache[$target]));
    $ins  = $db->prepare(sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $target,
        implode(', ', array_map(fn($c) => "`{$c}`", $cols)), implode(', ', array_fill(0, count($cols), '?'))));
    foreach ($rows as $r) {
        foreach ($map as $col => $fn) {
            if (array_key_exists($col, $r) && $r[$col] !== null) {
                $r[$col] = $fn($r[$col]);
            }
        }
        $ins->execute(array_map(fn($c) => $r[$c], $cols));
    }
    echo "  {$target}: " . count($rows) . "\n";
    return count($rows);
}

$db->beginTransaction();
try {
    // users -> shared users + tr_profiles
    $userMap = [];
    $noPassword = 0;
    $profiles = [];
    // Live data that belongs to a user, by the column holding the user id. A
    // user who never signed in through SSO and owns none of it is a
    // placeholder (live had "System", the creator of the built-in template)
    // and is not imported into the shared account.
    $owns = function (int $uid) use ($db): bool {
        $refs = ['responses' => 'user_id', 'mashup_responses' => 'user_id', 'confidence_logs' => 'user_id',
                 'hindsight_reflections' => 'user_id', 'scenario_submissions' => 'user_id', 'teams' => 'created_by',
                 'team_members' => 'user_id', 'team_challenges' => 'started_by', 'companies' => 'admin_user_id',
                 'company_members' => 'user_id', 'scenario_packs' => 'created_by', 'cohort_enrollments' => 'user_id'];
        foreach ($refs as $t => $col) {
            if ((int) $db->query('SELECT COUNT(*) FROM ' . stage_name($t) . " WHERE {$col} = {$uid}")->fetchColumn() > 0) {
                return true;
            }
        }
        return (int) $db->query('SELECT COUNT(*) FROM ' . stage_name('cohorts') . " WHERE is_template = 0 AND created_by = {$uid}")->fetchColumn() > 0;
    };
    foreach (rows($db, 'users') as $u) {
        if ($u['sso_user_id'] === null && !$owns((int) $u['id'])) {
            echo "  user {$u['id']} ({$u['name']}): placeholder with no sign-in and no data, skipped\n";
            continue;
        }
        $email = strtolower(trim($u['email']));
        $find  = $db->prepare('SELECT id FROM users WHERE email = ?');
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id) {
            echo "  user {$u['id']} -> existing tools account {$id}\n";
        } else {
            $name = trim((string) $u['name']) !== '' ? trim($u['name']) : ucfirst((string) strtok($email, '@'));
            // No password existed (SSO only): an unusable hash, reset by email.
            $db->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
               ->execute([$email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $name, $u['created_at']]);
            $id = $db->lastInsertId();
            $noPassword++;
            echo "  user {$u['id']} -> new tools account {$id} (no password yet: Forgot your password)\n";
        }
        $userMap[(int) $u['id']] = (int) $id;
        $profiles[] = [
            'user_id' => (int) $id, 'role_title' => $u['role_title'], 'industry' => $u['industry'],
            'onboarded_at' => $u['onboarded_at'], 'is_admin' => (int) $u['is_admin'],
            'timezone' => $u['timezone'], 'created_at' => $u['created_at'],
        ];
    }
    $mapUser = function ($v) use ($userMap) {
        return $userMap[(int) $v] ?? throw new RuntimeException("unknown user id {$v}");
    };
    put($db, 'profiles', $profiles);

    // content the seed lacks: models, then scenarios (approved submissions, packs)
    $have = fn(string $t) => array_flip($db->query("SELECT id FROM tr_{$t}")->fetchAll(PDO::FETCH_COLUMN));
    $haveModels = $have('mental_models');
    put($db, 'mental_models', array_values(array_filter(rows($db, 'mental_models'), fn($r) => !isset($haveModels[$r['id']]))));

    put($db, 'companies', rows($db, 'companies'), ['admin_user_id' => $mapUser]);
    put($db, 'scenario_packs', rows($db, 'scenario_packs'), ['created_by' => $mapUser]);

    $haveScen = $have('scenarios');
    $newScen  = array_values(array_filter(rows($db, 'scenarios'), fn($r) => !isset($haveScen[$r['id']])));
    put($db, 'scenarios', $newScen);
    $newIds = array_column($newScen, 'id');
    $inNew  = $newIds ? 'scenario_id IN (' . implode(',', array_map('intval', $newIds)) . ')' : '1=0';
    put($db, 'scenario_choices', rows($db, 'scenario_choices', $inNew));
    put($db, 'scenario_correct_models', rows($db, 'scenario_correct_models', $inNew));

    // seeded scenarios whose live text differs: report, never overwrite
    $diff = $db->query('SELECT s.id FROM ' . stage_name('scenarios') . ' s JOIN tr_scenarios t ON t.id = s.id
                        WHERE NOT (s.title <=> t.title AND s.situation <=> t.situation AND s.ideal_reasoning <=> t.ideal_reasoning
                                   AND s.correct_model_id <=> t.correct_model_id AND s.is_active <=> t.is_active)')
               ->fetchAll(PDO::FETCH_COLUMN);
    if ($diff) {
        echo '  NOTE: live scenarios differ from the seed and were left as seeded: ' . implode(', ', $diff) . "\n";
    }

    put($db, 'teams', rows($db, 'teams'), ['created_by' => $mapUser]);
    put($db, 'team_members', rows($db, 'team_members'), ['user_id' => $mapUser]);
    put($db, 'team_challenges', rows($db, 'team_challenges'), ['started_by' => $mapUser]);
    put($db, 'company_members', rows($db, 'company_members'), ['user_id' => $mapUser]);

    // cohorts: templates come from the seed; enrollments in a live template are
    // pointed at the seeded template with the same name.
    $cohortMap = [];
    foreach (rows($db, 'cohorts', 'is_template = 1') as $c) {
        $s = $db->prepare('SELECT id FROM tr_cohorts WHERE is_template = 1 AND name = ? ORDER BY id LIMIT 1');
        $s->execute([$c['name']]);
        $cohortMap[(int) $c['id']] = (int) ($s->fetchColumn() ?: throw new RuntimeException("no seeded template named {$c['name']}"));
    }
    $owned = rows($db, 'cohorts', 'is_template = 0');
    foreach ($owned as $c) {
        if ((int) $db->query('SELECT COUNT(*) FROM tr_cohorts WHERE id = ' . (int) $c['id'])->fetchColumn()) {
            throw new RuntimeException("cohort id {$c['id']} collides with a seeded cohort");
        }
        $cohortMap[(int) $c['id']] = (int) $c['id'];
    }
    put($db, 'cohorts', $owned, ['created_by' => $mapUser]);
    $ownedIds = array_column($owned, 'id');
    $cs = $ownedIds ? rows($db, 'cohort_scenarios', 'cohort_id IN (' . implode(',', array_map('intval', $ownedIds)) . ')') : [];
    foreach ($cs as &$r) { unset($r['id']); }   // seeded rows own the low ids; let these auto-number
    unset($r);
    put($db, 'cohort_scenarios', $cs);
    put($db, 'cohort_enrollments', rows($db, 'cohort_enrollments'), [
        'user_id'   => $mapUser,
        'cohort_id' => fn($v) => $cohortMap[(int) $v] ?? throw new RuntimeException("unknown cohort {$v}"),
    ]);

    put($db, 'responses', rows($db, 'responses'), ['user_id' => $mapUser]);
    put($db, 'hindsight_reflections', rows($db, 'hindsight_reflections'), ['user_id' => $mapUser]);
    put($db, 'mashup_responses', rows($db, 'mashup_responses'), ['user_id' => $mapUser]);
    put($db, 'confidence_logs', rows($db, 'confidence_logs'), ['user_id' => $mapUser]);
    put($db, 'blindspot_cache', rows($db, 'blindspot_cache'), ['user_id' => $mapUser]);
    put($db, 'scenario_submissions', rows($db, 'scenario_submissions'), ['user_id' => $mapUser]);
    put($db, 'benchmarks', rows($db, 'benchmarks'));

    if ($dry) {
        $db->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $db->commit();
        echo "Imported. {$noPassword} new account(s) have no password and must use Forgot your password.\n";
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Rolled back: ' . $e->getMessage() . "\n");
    drop_staging($db);
    exit(1);
}

// ── 3. clean up ───────────────────────────────────────────────────────────────
drop_staging($db);
echo "  staging tables dropped\n";
