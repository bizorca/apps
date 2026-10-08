<?php

declare(strict_types=1);

/**
 * Cross-tenant isolation.
 *
 * This is the suite that matters. Spec §6: "Tenant isolation verified by an
 * automated test suite that attempts cross-tenant access on every route.
 * Multi-tenant leaks are the failure mode that ends this product; test for
 * them mechanically."
 *
 * Every test here is an attack. Tenant A holds a repository and tries to read,
 * modify, or destroy tenant B's data by every route the base class exposes.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\QueryException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\TenantScopeException;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;

$db = Database::conn();

// Fresh slate. Order matters: users reference client_orgs reference tenants.
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
$db->exec('TRUNCATE TABLE pl_users');
$db->exec('TRUNCATE TABLE pl_client_orgs');
$db->exec('TRUNCATE TABLE pl_tenants');
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$mkTenant = static function (string $slug, string $name) use ($db): int {
    $stmt = $db->prepare("INSERT INTO pl_tenants (slug, name, status) VALUES (:s, :n, 'active')");
    $stmt->execute(['s' => $slug, 'n' => $name]);
    return (int) $db->lastInsertId();
};

$tenantA = $mkTenant('acme', 'Acme Advisory');
$tenantB = $mkTenant('northstar', 'Northstar Coaching');

$repoA = new ClientOrgRepository($tenantA);
$repoB = new ClientOrgRepository($tenantB);

$orgA1 = $repoA->insert(['name' => 'Alpha Manufacturing', 'status' => 'active']);
$orgA2 = $repoA->insert(['name' => 'Beta Dental', 'status' => 'prospect']);
$orgB1 = $repoB->insert(['name' => 'Gamma Logistics', 'status' => 'active']);
$orgB2 = $repoB->insert(['name' => 'Delta Physio', 'status' => 'active']);


T::group('Reads are scoped');

T::same(2, $repoA->count(), 'A sees only its own two orgs');
T::same(2, $repoB->count(), 'B sees only its own two orgs');
T::same(null, $repoA->find($orgB1), "A cannot find B's org by id");
T::same(null, $repoB->find($orgA1), "B cannot find A's org by id");
T::ok($repoA->find($orgA1) !== null, 'A can find its own org');

$namesA = array_column($repoA->all(), 'name');
sort($namesA);
T::same(['Alpha Manufacturing', 'Beta Dental'], $namesA, 'all() returns only A rows');

T::same(null, $repoA->firstWhere(['name' => 'Gamma Logistics']), "firstWhere cannot reach across to B");
T::same(0, $repoA->count(['name' => 'Gamma Logistics']), 'count() cannot reach across to B');
T::ok(!$repoA->exists(['name' => 'Delta Physio']), 'exists() cannot reach across to B');

T::same(1, $repoA->count(['status' => 'active']), 'conditions compose with the tenant scope');
T::same(2, $repoB->count(['status' => 'active']), 'B active count unaffected by A');

// IN () conditions must stay scoped too.
T::same(1, $repoA->count(['id' => [$orgA1, $orgB1, $orgB2]]), 'IN () filters out other tenants ids');
T::same(0, $repoA->count(['id' => []]), 'empty IN () matches nothing rather than erroring');


T::group('Writes are scoped');

T::same(0, $repoA->update($orgB1, ['name' => 'PWNED']), "A's update on B's row matches zero rows");
T::same('Gamma Logistics', $repoB->find($orgB1)['name'], "B's row is untouched after A's update attempt");

T::same(1, $repoA->update($orgA1, ['name' => 'Alpha Mfg']), 'A can update its own row');
T::same('Alpha Mfg', $repoA->find($orgA1)['name'], 'update applied');

T::same(0, $repoA->delete($orgB2), "A's delete on B's row removes nothing");
T::same(2, $repoB->count(), 'B still has both orgs after A tried to delete one');


T::group('Scope cannot be overridden by the caller');

// Supplying a foreign tenant_id must not place the row in that tenant.
$smuggled = $repoA->insert(['name' => 'Smuggled Co', 'tenant_id' => $tenantB, 'status' => 'active']);
$row = $db->query('SELECT tenant_id FROM pl_client_orgs WHERE id = ' . (int) $smuggled)->fetch();
T::same($tenantA, (int) $row['tenant_id'], 'insert() ignores a caller-supplied tenant_id');
T::same(3, $repoA->count(), 'smuggled row landed in A');
T::same(2, $repoB->count(), 'smuggled row did not land in B');

// And update must not be able to move a row to another tenant.
$repoA->update($smuggled, ['tenant_id' => $tenantB, 'name' => 'Still As']);
$row = $db->query('SELECT tenant_id FROM pl_client_orgs WHERE id = ' . (int) $smuggled)->fetch();
T::same($tenantA, (int) $row['tenant_id'], 'update() cannot move a row to another tenant');

// Non-writable columns are dropped rather than written.
$repoA->update($orgA2, ['name' => 'Beta Dental Group', 'id' => 99999, 'created_at' => '1999-01-01 00:00:00']);
T::ok($repoA->find($orgA2) !== null, 'row keeps its id after an attempt to rewrite it');
T::same('Beta Dental Group', $repoA->find($orgA2)['name'], 'writable column still applied');

T::throws(
    QueryException::class,
    static fn () => $repoA->count(['tenant_id' => $tenantB]),
    'tenant_id cannot be passed as a query condition'
);


T::group('Malformed input is refused, not interpolated');

T::throws(QueryException::class, static fn () => $repoA->all(['name; DROP TABLE pl_users --' => 'x']), 'SQL in a column name rejected');
T::throws(QueryException::class, static fn () => $repoA->all(['NAME' => 'x']), 'uppercase column name rejected');
T::throws(QueryException::class, static fn () => $repoA->all([], 'name; DROP TABLE pl_users'), 'SQL in ORDER BY rejected');
T::throws(QueryException::class, static fn () => $repoA->all([], 'name sideways'), 'bogus ORDER BY direction rejected');
T::throws(QueryException::class, static fn () => $repoA->all([], 'name asc, id desc'), 'multi-column ORDER BY rejected');
T::throws(QueryException::class, static fn () => $repoA->all([], null, 0), 'zero LIMIT rejected');
T::throws(QueryException::class, static fn () => $repoA->insert(['nope' => 1]), 'insert with no writable columns rejected');

// A legitimate ORDER BY still works.
T::same('Alpha Mfg', $repoA->all([], 'name asc', 1)[0]['name'], 'valid ORDER BY and LIMIT work');


T::group('A repository cannot exist without a tenant');

Tenant::reset();
T::throws(
    TenantScopeException::class,
    static fn () => new ClientOrgRepository(),
    'constructing with no resolved tenant throws'
);
T::throws(
    TenantScopeException::class,
    static fn () => new ClientOrgRepository(0),
    'constructing with tenant id 0 throws'
);
T::throws(
    TenantScopeException::class,
    static fn () => new ClientOrgRepository(-1),
    'constructing with a negative tenant id throws'
);

// And once a tenant IS resolved, the default constructor picks it up.
Tenant::setCurrent(['id' => $tenantB, 'slug' => 'northstar', 'name' => 'Northstar Coaching']);
$implicit = new ClientOrgRepository();
T::same($tenantB, $implicit->tenantId(), 'default constructor adopts the resolved tenant');
T::same(2, $implicit->count(), 'implicitly scoped repository sees only its tenant');
Tenant::reset();


T::group('Cascade behaviour');

// Deleting a tenant must take its data with it — orphaned tenant-owned rows
// would be invisible to every scoped query and live forever.
$db->exec('DELETE FROM pl_tenants WHERE id = ' . (int) $tenantB);
$orphans = (int) $db->query('SELECT COUNT(*) AS c FROM pl_client_orgs WHERE tenant_id = ' . (int) $tenantB)->fetch()['c'];
T::same(0, $orphans, "deleting a tenant cascades to its client orgs");

$survivors = (int) $db->query('SELECT COUNT(*) AS c FROM pl_client_orgs WHERE tenant_id = ' . (int) $tenantA)->fetch()['c'];
T::same(3, $survivors, "the other tenant's rows survive");


T::group('Every tenant-owned table is actually attached to a tenant');

/**
 * The census. CLAUDE.md says a table nobody attacks here is a table nobody has
 * checked, and per-table tests do not scale — a module shipped six months from
 * now will not think to come back and add one.
 *
 * So enumerate instead of enumerate-by-hand: ask the schema which tables carry
 * a tenant_id, and hold every one of them to the same rule. A new tenant-owned
 * table is covered the moment it is created, and a new table that forgets its
 * cascade fails on the next run rather than at the point someone deletes a
 * firm and discovers its rows outlived it — invisible to every scoped query,
 * and immortal.
 */
$schema = (string) Bizorca\Pilotage\Core\Config::get('db.name');

$owned = $db->query(
    "SELECT t.TABLE_NAME
     FROM information_schema.TABLES t
     JOIN information_schema.COLUMNS c
       ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
      AND c.COLUMN_NAME = 'tenant_id'
     WHERE t.TABLE_SCHEMA = " . $db->quote($schema) . "
     ORDER BY t.TABLE_NAME"
)->fetchAll(PDO::FETCH_COLUMN);

T::ok(count($owned) > 50, 'the census found the schema (' . count($owned) . ' tenant-owned tables)');

/**
 * Two deliberate exceptions, both for the same reason: the record has to
 * outlive the thing it records.
 *
 *   pl_audit_log     — an audit trail that vanishes with what it audits is not
 *                       an audit trail. Nullable soft reference, no FK at all.
 *                       Retention (FR-14.x) removes those rows on a schedule
 *                       someone chose.
 *   pl_billing_events — a chargeback can arrive two months after a firm
 *                       leaves, and "what exactly did Stripe tell us, and when"
 *                       is the only useful answer to it. ON DELETE SET NULL, so
 *                       the row survives with its tenant link severed — which
 *                       also means it can never be read back through a scoped
 *                       query, so it cannot leak.
 *
 * Both are asserted below rather than merely skipped. An exemption nobody
 * checks is indistinguishable from an oversight.
 */
$exempt = ['pl_audit_log', 'pl_billing_events'];

foreach ($owned as $table) {
    if (in_array($table, $exempt, true)) {
        continue;
    }

    $cascades = (int) $db->query(
        "SELECT COUNT(*) AS c
         FROM information_schema.KEY_COLUMN_USAGE k
         JOIN information_schema.REFERENTIAL_CONSTRAINTS r
           ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
         WHERE k.TABLE_SCHEMA = " . $db->quote($schema) . "
           AND k.TABLE_NAME = " . $db->quote($table) . "
           AND k.COLUMN_NAME = 'tenant_id'
           AND k.REFERENCED_TABLE_NAME = 'pl_tenants'
           AND r.DELETE_RULE = 'CASCADE'"
    )->fetch()['c'];

    T::same(1, $cascades, "{$table}.tenant_id cascades from pl_tenants");
}

// And the exception is an exception on purpose, not an oversight nobody noticed.
$auditFk = (int) $db->query(
    "SELECT COUNT(*) AS c FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = " . $db->quote($schema) . "
       AND TABLE_NAME = 'pl_audit_log' AND COLUMN_NAME = 'tenant_id'
       AND REFERENCED_TABLE_NAME IS NOT NULL"
)->fetch()['c'];
T::same(0, $auditFk, 'the audit log deliberately has no tenant FK — it must outlive the tenant');

// Billing events DO carry an FK, and it is SET NULL rather than CASCADE.
$billingRule = $db->query(
    "SELECT r.DELETE_RULE
     FROM information_schema.KEY_COLUMN_USAGE k
     JOIN information_schema.REFERENTIAL_CONSTRAINTS r
       ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
     WHERE k.TABLE_SCHEMA = " . $db->quote($schema) . "
       AND k.TABLE_NAME = 'pl_billing_events' AND k.COLUMN_NAME = 'tenant_id'"
)->fetch();

T::same('SET NULL', $billingRule['DELETE_RULE'] ?? '',
    'billing history survives the firm with its tenant link severed — a chargeback outlives the account');

$billingNullable = $db->query(
    "SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = " . $db->quote($schema) . "
       AND TABLE_NAME = 'pl_billing_events' AND COLUMN_NAME = 'tenant_id'"
)->fetch();

T::same('YES', $billingNullable['IS_NULLABLE'] ?? '',
    'which is only possible because the column is nullable — and a NULL tenant_id can never be read back through a scoped query');

// The M10 tables specifically, because this is the module that just shipped.
foreach ([
    'pl_worksheets', 'pl_worksheet_fields', 'pl_worksheet_bands',
    'pl_worksheet_assignments', 'pl_worksheet_responses',
    'pl_worksheet_answers', 'pl_worksheet_subscores',
] as $table) {
    T::ok(in_array($table, $owned, true), "{$table} is in the census");
}
