<?php

declare(strict_types=1);

/**
 * Public intake (FR-3.6).
 *
 * The whole surface is unauthenticated, so most of these are refusals. The
 * one row that ever existed in Foundry's applications table was spam, which
 * is the entire reason the spam checks exist rather than being deferred.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientContactRepository;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Intake;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_intake_submissions', 'pl_org_events', 'pl_client_contacts',
          'pl_client_orgs', 'pl_users', 'pl_auth_attempts', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id,slug,name,status,intake_enabled) VALUES (1,'bizorca','Bizorca','active',1),(2,'other','Other','active',0)");

$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@bizorca.test', 'name' => 'A Coach', 'role' => 'firm_owner', 'status' => 'active']);

$server = ['REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'Mozilla/5.0'];

$good = [
    'name'            => 'Dana Reyes',
    'email'           => 'dana@alpha.test',
    'company_name'    => 'Alpha Manufacturing',
    'situation'       => 'Margin has been slipping for three quarters and I cannot see why.',
    'desired_outcome' => 'Know my numbers well enough to sleep.',
    'revenue_band'    => '1m_5m',
    'employee_count'  => '42',
    'timeline'        => 'next month',
    '_t'              => time() - 60,
];


T::group('The form is off unless a firm turns it on');

T::ok(Intake::isOpen(['intake_enabled' => 1]), 'an enabled form is open');
T::ok(!Intake::isOpen(['intake_enabled' => 0]), 'a disabled one is not');
T::ok(!Intake::isOpen([]), 'and a tenant with no setting at all is closed — a public form should never appear by default');


T::group('Validation');

T::same([], Intake::problems($good), 'a complete submission is fine');

$missingName = $good; unset($missingName['name']);
T::ok(Intake::problems($missingName) !== [], 'a name is required');

$badEmail = $good; $badEmail['email'] = 'not-an-address';
T::ok(Intake::problems($badEmail) !== [], 'a working email is required — it is the only way to reply');

$noCompany = $good; $noCompany['company_name'] = '  ';
T::ok(Intake::problems($noCompany) !== [], 'a business name is required');

$noSituation = $good; $noSituation['situation'] = '';
T::ok(Intake::problems($noSituation) !== [], 'the situation is required — it is the part that is actually read');

$badBand = $good; $badBand['revenue_band'] = 'squillions';
T::ok(Intake::problems($badBand) !== [], 'an unknown revenue band is refused');


T::group('Spam defences');

// Honeypot.
$trap = $good;
$trap[Intake::HONEYPOT_FIELD] = 'http://spam.example';
$r = Intake::submit(1, $trap, $server);
T::same(null, $r['id'], 'a filled honeypot yields no usable submission');
T::ok($r['silent'], 'and is handled silently');
T::same([], $r['problems'], 'with no error shown — telling a bot it was caught only helps it');

$spamRow = $db->query("SELECT status FROM pl_intake_submissions ORDER BY id DESC LIMIT 1")->fetch();
T::same('spam', $spamRow['status'], 'but it IS recorded, flagged as spam, so a pattern is visible later');

// Filled too fast.
$fast = $good;
$fast['_t'] = time();
$r = Intake::submit(1, $fast, $server);
T::same(null, $r['id'], 'a form completed instantly is not a person reading questions');
T::ok($r['silent'], 'also silent');

// A human pausing over it is fine.
$slow = $good;
$slow['_t'] = time() - 240;
$r = Intake::submit(1, $slow, $server);
T::ok($r['id'] !== null, 'four minutes of thinking is accepted');

// No timestamp at all (a client that stripped the field) is not punished.
$noTime = $good;
unset($noTime['_t']);
$r = Intake::submit(1, $noTime, ['REMOTE_ADDR' => '203.0.113.6']);
T::ok($r['id'] !== null, 'a missing timestamp is not treated as spam');


T::group('A real submission is captured whole');

$db->exec('TRUNCATE TABLE pl_intake_submissions');
$r = Intake::submit(1, $good, $server);
T::ok($r['id'] !== null, 'accepted');

$row = Intake::find(1, (int) $r['id']);
T::same('Alpha Manufacturing', $row['company_name'], 'company captured');
T::same('dana@alpha.test', $row['email'], 'email lower-cased and captured');
T::same(42, (int) $row['employee_count'], 'employee count parsed as a number');
T::same('1m_5m', $row['revenue_band'], 'revenue band kept');
T::same('new', $row['status'], 'and it lands as new');
T::ok($row['ip'] !== null, 'the address is recorded');
T::ok($row['seconds_to_fill'] !== null, 'as is how long it took');

$junkNumber = $good;
$junkNumber['employee_count'] = 'lots';
$junkNumber['_t'] = time() - 30;
$r2 = Intake::submit(1, $junkNumber, ['REMOTE_ADDR' => '203.0.113.7']);
T::same(null, Intake::find(1, (int) $r2['id'])['employee_count'], 'a non-numeric staff count becomes null rather than zero');


T::group('Accepting creates the prospect and the contact');

$submissionId = (int) $r['id'];
$orgId = Intake::accept(1, $submissionId, $coachId);

$orgs = new ClientOrgRepository(1);
$org = $orgs->find($orgId);

T::ok($org !== null, 'a client organization exists');
T::same('Alpha Manufacturing', $org['name'], 'named from the submission');
T::same('prospect', $org['status'], 'as a PROSPECT — accepting an enquiry is not signing an engagement');
T::same($coachId, (int) $org['owner_user_id'], 'assigned to the reviewer');
T::ok(str_contains((string) $org['situation'], 'Margin has been slipping'), 'their own words carried across');
T::ok(str_contains((string) $org['situation'], 'What good looks like'), 'along with what they want');

$contacts = new ClientContactRepository(1);
$roster = $contacts->forOrg($orgId);
T::same(1, count($roster), 'one contact created');
T::same('Dana Reyes', $roster[0]['name'], 'the person who filled the form');
T::same('none', $roster[0]['portal_access'], 'with NO portal access — that comes after a conversation');
T::same(1, (int) $roster[0]['is_primary'], 'and as the primary contact');

$after = Intake::find(1, $submissionId);
T::same('accepted', $after['status'], 'the submission is marked accepted');
T::same($orgId, (int) $after['client_org_id'], 'and linked to what it became');

T::same($orgId, Intake::accept(1, $submissionId, $coachId), 'accepting twice returns the same org rather than duplicating');


T::group('Declining leaves a record and creates nothing');

$r3 = Intake::submit(1, $good + ['company_name' => 'Not A Fit Ltd', '_t' => time() - 30], ['REMOTE_ADDR' => '203.0.113.8']);
$before = count($orgs->all());
T::ok(Intake::decline(1, (int) $r3['id'], $coachId, 'Outside what we do.'), 'declining works');
T::same($before, count($orgs->all()), 'and creates no client record');

$declined = Intake::find(1, (int) $r3['id']);
T::same('declined', $declined['status'], 'the submission is marked declined');
T::ok(str_contains((string) $declined['review_note'], 'Outside what we do'), 'with the reason kept');
T::ok(!Intake::decline(1, (int) $r3['id'], $coachId), 'declining twice does nothing');


T::group('The queue hides spam by default');

$pending = Intake::pending(1);
foreach ($pending as $p) {
    T::ok(in_array((string) $p['status'], ['new', 'reviewing'], true), 'only unhandled submissions are queued');
}
T::ok(count(Intake::pending(1, true)) > count($pending), 'and everything is available when asked for');


T::group('Tenant isolation');

T::same(null, Intake::find(2, $submissionId), "tenant B cannot read tenant A's enquiry");
T::same([], Intake::pending(2), 'nor its queue');
T::same(0, Intake::countNew(2), 'nor its count');
T::ok(!Intake::decline(2, $submissionId, $coachId), 'nor decline it');
T::throws(RuntimeException::class,
    static fn () => Intake::accept(2, $submissionId, $coachId),
    "nor accept it into their own book");
