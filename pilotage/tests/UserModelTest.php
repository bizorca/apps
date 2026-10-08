<?php

declare(strict_types=1);

/**
 * Schema-level invariants on pl_users.
 *
 * The side-of-the-wall rule (firm-side vs client-side) is enforced by a CHECK
 * constraint rather than by application code, so it is tested by trying to
 * violate it directly in SQL — bypassing every guard the application has.
 */

use Bizorca\Pilotage\Core\Database;

$db = Database::conn();

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
$db->exec('TRUNCATE TABLE pl_users');
$db->exec('TRUNCATE TABLE pl_client_orgs');
$db->exec('TRUNCATE TABLE pl_tenants');
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO pl_tenants (id, slug, name, status) VALUES (1, 'acme', 'Acme Advisory', 'active')");
$db->exec("INSERT INTO pl_client_orgs (id, tenant_id, name, status) VALUES (1, 1, 'Alpha Manufacturing', 'active')");

$insert = static function (string $sql) use ($db): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (Throwable $e) {
        return false;
    }
};

T::group('Users — valid rows');

T::ok(
    $insert("INSERT INTO pl_users (tenant_id, client_org_id, email, name, role) VALUES (1, NULL, 'coach@acme.test', 'A Coach', 'coach')"),
    'firm-side coach with no client org accepted'
);
T::ok(
    $insert("INSERT INTO pl_users (tenant_id, client_org_id, email, name, role) VALUES (1, 1, 'owner@alpha.test', 'An Owner', 'client_owner')"),
    'client-side owner attached to a client org accepted'
);

T::group('Users — the side-of-the-wall CHECK holds');

T::ok(
    !$insert("INSERT INTO pl_users (tenant_id, client_org_id, email, name, role) VALUES (1, 1, 'bad1@acme.test', 'Bad', 'coach')"),
    'a coach cannot be attached to a client org'
);
T::ok(
    !$insert("INSERT INTO pl_users (tenant_id, client_org_id, email, name, role) VALUES (1, NULL, 'bad2@acme.test', 'Bad', 'client_owner')"),
    'a client owner cannot float free of a client org'
);
T::ok(
    !$insert("INSERT INTO pl_users (tenant_id, client_org_id, email, name, role) VALUES (1, NULL, 'bad3@acme.test', 'Bad', 'sponsor')"),
    'a sponsor cannot float free of a client org'
);
T::ok(
    !$insert("INSERT INTO pl_users (tenant_id, client_org_id, email, name, role) VALUES (1, 1, 'bad4@acme.test', 'Bad', 'firm_owner')"),
    'a firm owner cannot be attached to a client org'
);

T::group('Users — tenant is mandatory');

T::ok(
    !$insert("INSERT INTO pl_users (tenant_id, client_org_id, email, name, role) VALUES (NULL, 1, 'bad5@acme.test', 'Bad', 'client_owner')"),
    'a user cannot exist outside a tenant'
);

T::group('Users — identity uniqueness');

T::ok(
    !$insert("INSERT INTO pl_users (tenant_id, client_org_id, email, name, role) VALUES (1, NULL, 'coach@acme.test', 'Dupe', 'associate')"),
    'the same email cannot appear twice within one tenant'
);

$db->exec("INSERT INTO pl_tenants (id, slug, name, status) VALUES (2, 'northstar', 'Northstar', 'active')");
T::ok(
    $insert("INSERT INTO pl_users (tenant_id, client_org_id, email, name, role) VALUES (2, NULL, 'coach@acme.test', 'Same Person', 'coach')"),
    'the same email CAN appear in a different tenant'
);

T::group('Users — cascade');

$db->exec('DELETE FROM pl_client_orgs WHERE id = 1');
$remaining = (int) $db->query("SELECT COUNT(*) AS c FROM pl_users WHERE email = 'owner@alpha.test'")->fetch()['c'];
T::same(0, $remaining, 'deleting a client org removes its client-side users');

$coaches = (int) $db->query("SELECT COUNT(*) AS c FROM pl_users WHERE tenant_id = 1 AND client_org_id IS NULL")->fetch()['c'];
T::same(1, $coaches, 'firm-side users survive a client org deletion');
