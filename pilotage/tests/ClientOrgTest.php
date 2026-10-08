<?php

declare(strict_types=1);

/**
 * M3: client organizations, contacts, the timeline, and the two permission
 * qualifiers this module owes.
 */

use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Qualifiers;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientContactRepository;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Timeline;

$db = Database::conn();

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_org_events', 'pl_client_contacts', 'pl_magic_links', 'pl_invitations',
          'pl_user_sessions', 'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active'),(2,'northstar','Northstar','active')");

$usersA = new UserRepository(1);
$coachId = $usersA->create(['email' => 'coach@acme.test', 'name' => 'A Coach', 'role' => 'coach', 'status' => 'active']);
$coach2Id = $usersA->create(['email' => 'coach2@acme.test', 'name' => 'Other Coach', 'role' => 'coach', 'status' => 'active']);

$orgsA = new ClientOrgRepository(1);
$orgsB = new ClientOrgRepository(2);


T::group('Organizations — the full record');

$alphaId = $orgsA->createOrg([
    'name'            => 'Alpha Manufacturing',
    'legal_name'      => 'Alpha Manufacturing LLC',
    'entity_type'     => 'llc',
    'industry_naics'  => '332710',
    'employee_count'  => 42,
    'revenue_band'    => '1m_5m',
    'fiscal_year_end' => '12-31',
    'website'         => 'https://alpha.test',
    'city'            => 'Port Townsend',
    'region'          => 'WA',
    'country'         => 'US',
    'situation'       => 'Margin compression since the new line went in.',
    'status'          => 'prospect',
    'owner_user_id'   => $coachId,
]);

$alpha = $orgsA->find($alphaId);
T::same('Alpha Manufacturing LLC', $alpha['legal_name'], 'legal name stored');
T::same('llc', $alpha['entity_type'], 'entity type stored');
T::same('12-31', $alpha['fiscal_year_end'], 'fiscal year end stored as MM-DD');
T::same(42, (int) $alpha['employee_count'], 'employee count stored');
T::same(5, (int) $alpha['team_invite_cap'], 'default team invite cap applied');

T::group('Organizations — validation');

T::throws(InvalidArgumentException::class, static fn () => $orgsA->createOrg(['name' => '']), 'name is required');
T::throws(InvalidArgumentException::class, static fn () => $orgsA->createOrg(['name' => 'X', 'entity_type' => 'wat']), 'unknown entity type refused');
T::throws(InvalidArgumentException::class, static fn () => $orgsA->createOrg(['name' => 'X', 'revenue_band' => 'squillions']), 'unknown revenue band refused');
T::throws(InvalidArgumentException::class, static fn () => $orgsA->createOrg(['name' => 'X', 'industry_naics' => 'abc']), 'non-numeric NAICS refused');
T::throws(InvalidArgumentException::class, static fn () => $orgsA->createOrg(['name' => 'X', 'fiscal_year_end' => '31-12']), 'MM-DD order enforced');
T::throws(InvalidArgumentException::class, static fn () => $orgsA->createOrg(['name' => 'X', 'fiscal_year_end' => '13-01']), 'month 13 refused');
T::throws(InvalidArgumentException::class, static fn () => $orgsA->createOrg(['name' => 'X', 'country' => 'USA']), 'three-letter country refused');
T::same([], ClientOrgRepository::problems(['name' => 'Fine', 'fiscal_year_end' => '06-30']), 'a valid record has no problems');

$normalized = ClientOrgRepository::normalize(['name' => '  Beta  ', 'website' => 'beta.test', 'city' => '', 'country' => 'us']);
T::same('Beta', $normalized['name'], 'whitespace trimmed');
T::same('https://beta.test', $normalized['website'], 'bare domain gets a scheme');
T::same(null, $normalized['city'], 'empty string becomes null, not blank');
T::same('US', $normalized['country'], 'country upper-cased');

T::group('Organizations — prospect to active');

T::same('prospect', $orgsA->find($alphaId)['status'], 'starts as a prospect');
T::ok($orgsA->convert($alphaId), 'prospect converts');
$alpha = $orgsA->find($alphaId);
T::same('active', $alpha['status'], 'now active');
T::ok($alpha['converted_at'] !== null, 'conversion is stamped');
T::ok(!$orgsA->convert($alphaId), 'converting twice does nothing');

T::ok($orgsA->archive($alphaId), 'archives');
T::ok($orgsA->find($alphaId)['archived_at'] !== null, 'archival is stamped');
T::ok(!$orgsA->archive($alphaId), 'archiving twice does nothing');

// Back to active for the rest of the tests.
$orgsA->update($alphaId, ['status' => 'active']);

T::group('Organizations — tenant isolation holds on the new methods');

$gammaId = $orgsB->createOrg(['name' => 'Gamma Logistics', 'status' => 'active']);
T::same(null, $orgsA->find($gammaId), "A cannot read B's org");
T::same([], $orgsA->search('Gamma'), 'search does not cross tenants');
T::ok(!$orgsA->convert($gammaId), "convert cannot reach another tenant's row");
T::ok(!$orgsA->archive($gammaId), "archive cannot reach another tenant's row");
T::same(1, count($orgsA->search('Alpha')), 'search finds own rows');
T::same(1, count($orgsA->ownedBy($coachId)), 'ownedBy is scoped');
T::same(0, count($orgsA->ownedBy($coach2Id)), 'ownedBy filters by owner');

$counts = $orgsA->countsByStatus();
T::same(1, $counts['active'], 'status counts are scoped to the tenant');


T::group('Contacts — a contact is not a user');

$contactsA = new ClientContactRepository(1);

$cfoId = $contactsA->createContact([
    'client_org_id' => $alphaId,
    'name'          => 'Dana Reyes',
    'email'         => 'Dana@alpha.test',
    'title'         => 'CFO',
    'portal_access' => 'none',
    'is_primary'    => 1,
]);

$cfo = $contactsA->find($cfoId);
T::same('dana@alpha.test', $cfo['email'], 'email lower-cased');
T::same(null, $cfo['user_id'], 'a contact with no portal access has no user');
T::same(1, (int) $cfo['is_primary'], 'primary flag set');

$bookkeeperId = $contactsA->createContact([
    'client_org_id' => $alphaId,
    'name'          => 'Sam Okafor',
    'title'         => 'Bookkeeper',
    'portal_access' => 'none',
]);
T::same(null, $contactsA->find($bookkeeperId)['email'], 'a contact may have no email at all');

T::group('Contacts — validation');

T::throws(InvalidArgumentException::class,
    static fn () => $contactsA->createContact(['client_org_id' => $alphaId, 'name' => '']),
    'contact name required');
T::throws(InvalidArgumentException::class,
    static fn () => $contactsA->createContact(['name' => 'No Org']),
    'contact must belong to an organization');
T::throws(InvalidArgumentException::class,
    static fn () => $contactsA->createContact(['client_org_id' => $alphaId, 'name' => 'X', 'email' => 'nope']),
    'invalid email refused');
T::throws(InvalidArgumentException::class,
    static fn () => $contactsA->createContact(['client_org_id' => $alphaId, 'name' => 'X', 'portal_access' => 'owner']),
    'portal access without an email refused');
T::throws(InvalidArgumentException::class,
    static fn () => $contactsA->createContact(['client_org_id' => $alphaId, 'name' => 'X', 'email' => 'x@a.test', 'portal_access' => 'admin']),
    'unknown access level refused');

// The schema backs this up independently of the application check.
$rawFailed = false;
try {
    $db->exec("INSERT INTO pl_client_contacts (tenant_id, client_org_id, name, portal_access)
               VALUES (1, " . (int) $alphaId . ", 'Schema Bypass', 'owner')");
} catch (Throwable) {
    $rawFailed = true;
}
T::ok($rawFailed, 'the CHECK constraint refuses portal access with no email even in raw SQL');

T::group('Contacts — one primary per organization');

$opsId = $contactsA->createContact([
    'client_org_id' => $alphaId,
    'name'          => 'Jo Fletcher',
    'email'         => 'jo@alpha.test',
    'title'         => 'Ops',
    'portal_access' => 'member',
    'is_primary'    => 1,
]);

T::same(1, (int) $contactsA->find($opsId)['is_primary'], 'new primary is primary');
T::same(0, (int) $contactsA->find($cfoId)['is_primary'], 'the previous primary was demoted');

$primaries = 0;
foreach ($contactsA->forOrg($alphaId) as $c) {
    $primaries += (int) $c['is_primary'];
}
T::same(1, $primaries, 'exactly one primary contact remains');

T::group('Contacts — seats and the invite cap');

T::same(1, $contactsA->seatsUsed($alphaId), 'only granted access counts toward seats');
$contactsA->createContact([
    'client_org_id' => $alphaId, 'name' => 'Kim Lau', 'email' => 'kim@alpha.test', 'portal_access' => 'owner',
]);
T::same(2, $contactsA->seatsUsed($alphaId), 'granting another seat increments the count');
T::same(3, count($contactsA->forOrg($alphaId)) - 1, 'contacts without access still appear on the roster');

T::group('Contacts — uniqueness and isolation');

T::throws(Exception::class,
    static fn () => $contactsA->createContact(['client_org_id' => $alphaId, 'name' => 'Dupe', 'email' => 'dana@alpha.test']),
    'the same email cannot appear twice in one organization');

$contactsB = new ClientContactRepository(2);
T::same(null, $contactsB->find($cfoId), "tenant B cannot read tenant A's contact");
T::same([], $contactsB->forOrg($alphaId), "tenant B gets nothing for tenant A's org");

$linked = $contactsA->linkUser($cfoId, $coachId);
T::ok($linked, 'a contact can be linked to a user');
T::ok(!$contactsA->linkUser($cfoId, $coach2Id), 'an already-linked contact is not relinked');


T::group('Timeline');

Timeline::record(1, $alphaId, Timeline::ORG_CREATED, 'Alpha Manufacturing added', ['id' => $coachId, 'name' => 'A Coach']);
Timeline::record(1, $alphaId, Timeline::CONTACT_ADDED, 'Dana Reyes added as CFO', ['id' => $coachId, 'name' => 'A Coach']);
Timeline::record(1, $alphaId, 'note.private', 'Owner seems anxious about the bank covenant', ['id' => $coachId, 'name' => 'A Coach'], null, null, false);

$all = Timeline::forOrg(1, $alphaId, false);
T::same(3, count($all), 'coach-side view shows every event');

$clientView = Timeline::forOrg(1, $alphaId, true);
T::same(2, count($clientView), 'client-side view hides coach-only events');

foreach ($clientView as $event) {
    T::ok((int) $event['client_visible'] === 1, 'every client-visible event is flagged visible');
}

T::same('note.private', $all[0]['event_type'], 'newest event first');
T::same('A Coach', $all[0]['actor_label'], 'actor label preserved on the event');

T::same([], Timeline::forOrg(2, $alphaId, false), 'timeline does not cross tenants');

$recent = Timeline::recentForTenant(1);
T::same(3, count($recent), 'tenant-wide feed returns events');
T::same('Alpha Manufacturing', $recent[0]['org_name'], 'tenant-wide feed carries the org name');


T::group('Qualifiers — M3 pays what it owes');

Policy::resetQualifiers();
Qualifiers::register();

// Assert only what M3 owns. An exact assertion on the whole unresolved list
// would break every time a LATER module pays off one of its own qualifiers,
// which is a passing test failing for a good change — the most useless kind.
// PolicyTest asserts the full set the matrix declares.
$stillOwed = Policy::unresolvedQualifiers();
T::ok(!in_array('own_org', $stillOwed, true), 'own_org is now resolved');
T::ok(!in_array('assigned', $stillOwed, true), 'assigned is now resolved');
T::ok(!in_array('own', $stillOwed, true), 'own is now resolved');

$clientOwner = ['id' => 90, 'role' => 'client_owner', 'status' => 'active', 'client_org_id' => $alphaId];
$otherOwner  = ['id' => 91, 'role' => 'client_owner', 'status' => 'active', 'client_org_id' => 999];
$coach       = ['id' => $coachId, 'role' => 'coach', 'status' => 'active', 'client_org_id' => null];
$associate   = ['id' => 77, 'role' => 'associate', 'status' => 'active', 'client_org_id' => null];

// Firm seats vs client portal access are separate objects. Conflating them
// made a coach unable to invite their own client's CFO — caught by wiring the
// real workflow, not by the matrix tests.
T::ok(Policy::can($clientOwner, Policy::CREATE, 'client_portal_access', ['client_org_id' => $alphaId]), 'a client owner may invite into their own org');
T::ok(!Policy::can($otherOwner, Policy::CREATE, 'client_portal_access', ['client_org_id' => $alphaId]), 'a client owner may not invite into another org');
T::ok(!Policy::can($clientOwner, Policy::CREATE, 'client_portal_access', null), 'no context means no grant');
T::ok(!Policy::can($clientOwner, Policy::CREATE, 'client_portal_access', ['client_org_id' => null]), 'an object with no org denies');

T::ok(Policy::can($coach, Policy::CREATE, 'client_portal_access'), 'a coach CAN grant a client contact portal access');
T::ok(Policy::can($associate, Policy::CREATE, 'client_portal_access'), 'an associate can too');
T::ok(!Policy::can($coach, Policy::CREATE, 'user'), 'but a coach cannot mint a firm seat');
T::ok(!Policy::can($associate, Policy::CREATE, 'user'), 'nor can an associate');
T::ok(!Policy::can($clientOwner, Policy::CREATE, 'user'), 'nor can a client owner');
T::ok(Policy::can(['id'=>5,'role'=>'firm_owner','status'=>'active'], Policy::CREATE, 'user'), 'only the firm owner mints firm seats');
T::ok(!Policy::can($associate, Policy::DELETE, 'client_portal_access'), 'an associate cannot revoke access');

T::ok(Policy::can($associate, Policy::READ, 'engagement', ['assigned_user_ids' => [77, 78]]), 'an assigned associate may read the engagement');
T::ok(!Policy::can($associate, Policy::READ, 'engagement', ['assigned_user_ids' => [78]]), 'an unassigned associate may not');
T::ok(Policy::can($associate, Policy::READ, 'engagement', ['owner_user_id' => 77]), 'owner_user_id also satisfies assigned');
T::ok(!Policy::can($associate, Policy::READ, 'engagement', []), 'empty context denies');

T::ok(Policy::can($coach, Policy::UPDATE, 'engagement', ['owner_user_id' => $coachId]), 'a coach may update their own engagement');
T::ok(!Policy::can($coach, Policy::UPDATE, 'engagement', ['owner_user_id' => $coach2Id]), "and not another coach's");
T::ok(Policy::can($clientOwner, Policy::READ, 'engagement', ['client_org_id' => $alphaId]), 'a client owner reads their own engagement via own');

Policy::resetQualifiers();
