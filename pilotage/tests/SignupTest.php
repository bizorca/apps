<?php

declare(strict_types=1);

/**
 * Self-serve firm creation, and the marketing site's host boundary.
 *
 * Two things here are worth more than the rest of the file:
 *
 *   1. A failed create() must leave NOTHING behind. A tenant row with no owner
 *      is unreachable — nobody can sign in to it — and it permanently burns a
 *      slug, since an address someone else has taken can never be reclaimed.
 *   2. The address rules are the same rules Tenant::parseHost enforces on the
 *      way in. If signup ever accepts something the host parser rejects, a firm
 *      gets an address that resolves to a 404 and there is no way to fix it.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Billing;
use Bizorca\Pilotage\Services\Provisioning;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_users', 'pl_client_orgs', 'pl_tenants', 'pl_auth_attempts', 'users'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

/** @return array<string,mixed> */
$valid = static fn (array $over = []): array => $over + [
    'firm_name' => 'Harbourline Advisory',
    'slug'      => 'harbourline',
];

/**
 * The owner is the signed-in Bizorca Tools account (a `users` row): signup no
 * longer collects a name, email or password of its own.
 *
 * @return array{id:int,email:string,name:string}
 */
$account = static function (string $email, string $name) use ($db): array {
    $db->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)')
       ->execute([$email, password_hash('correct horse battery staple', PASSWORD_DEFAULT), $name]);
    return ['id' => (int) $db->lastInsertId(), 'email' => $email, 'name' => $name];
};
$dana = $account('dana@harbourline.test', 'Dana Reyes');


T::group('Turning a practice name into an address');

T::same('harbourline-advisory', Provisioning::suggestSlug('Harbourline Advisory'), 'spaces become hyphens');
T::same('harbourline', Provisioning::suggestSlug('Harbourline LLC'), 'a trailing LLC is dropped — it reads as noise in a URL');
T::same('o-brien', Provisioning::suggestSlug("O'Brien & Co."), 'punctuation collapses, and the trailing "Co." goes with the other legal furniture');
T::same('north-star', Provisioning::normaliseSlug('  North  Star  '), 'runs of separators collapse to one hyphen');
T::same('acme', Provisioning::normaliseSlug('---ACME---'), 'leading and trailing hyphens are stripped');
T::same('', Provisioning::normaliseSlug('!!!'), 'a name with nothing usable in it yields nothing');
T::ok(strlen(Provisioning::normaliseSlug(str_repeat('a', 200))) === 63, 'and it can never exceed a DNS label');

// The contract that matters: whatever normalise produces must be something the
// host parser will later accept. A mismatch here strands a firm on a 404.
foreach (['Harbourline Advisory', "O'Brien & Co.", 'North  Star', '  Acme  '] as $name) {
    $slug = Provisioning::normaliseSlug($name);
    T::ok(
        $slug !== '' && Tenant::isValidSlug($slug),
        'a normalised "' . $name . '" is a slug Tenant::parseHost accepts'
    );
}


T::group('Validation, before anything is written');

T::same([], Provisioning::problems($valid()), 'a complete, well-formed submission has no problems');

T::ok(isset(Provisioning::problems($valid(['firm_name' => '  ']))['firm_name']), 'a blank practice name is refused');
T::same([], Provisioning::problems($valid(['name' => '', 'email' => 'x', 'password' => ''])),
    'owner name, email and password are not signup fields any more — the signed-in account supplies them');

T::ok(isset(Provisioning::problems($valid(['slug' => 'a']))['slug']), 'a one-character address is refused');

// Stray punctuation is normalised away rather than rejected — the field's job
// is to get someone to a working address, not to grade their typing. What is
// stored is always the normalised form, which is what the redirect lands on.
T::same([], Provisioning::problems($valid(['slug' => '-lead-'])), 'a stray leading hyphen is normalised away, not refused');
T::same('lead', Provisioning::normaliseSlug('-lead-'), 'and what gets stored is the cleaned form');

/**
 * Punycode. `Tenant::isValidSlug` rejects the `xn--` ACE prefix but never sees
 * it, because normalisation collapses the double hyphen first — so the guard
 * has to look at the raw input. Both halves are asserted: the intent, and the
 * accident that would otherwise be the only thing enforcing it.
 */
T::ok(isset(Provisioning::problems($valid(['slug' => 'xn--80ak6aa92e']))['slug']), 'a punycode address is refused — homograph attacks on tenant addresses are not something we are ready to reason about');
T::ok(!Provisioning::slugAvailable('xn--80ak6aa92e'), 'and the availability check agrees, so the form cannot say yes while the server says no');
T::ok(!str_starts_with(Provisioning::normaliseSlug('xn--80ak6aa92e'), 'xn--'), 'normalisation would have destroyed the ACE prefix anyway — no real punycode can reach a host');

foreach (['www', 'admin', 'api', 'login', 'billing', 'support'] as $reserved) {
    T::ok(isset(Provisioning::problems($valid(['slug' => $reserved]))['slug']), 'the reserved address "' . $reserved . '" cannot be taken');
}

T::ok(!Provisioning::slugAvailable('admin'), 'slugAvailable agrees about reserved addresses');
T::ok(!Provisioning::slugAvailable('a'), 'and about malformed ones');
T::ok(Provisioning::slugAvailable('harbourline'), 'an unused, well-formed address is available');


T::group('Spam defences run before validation');

T::same('honeypot', Provisioning::rejectReason($valid([Provisioning::HONEYPOT_FIELD => 'http://spam']), null), 'anything in the honeypot is a bot');
T::same('too_fast', Provisioning::rejectReason($valid(['_t' => time()]), null), 'a form filled in under three seconds is a bot');
T::same(null, Provisioning::rejectReason($valid(['_t' => time() - 60]), null), 'a person taking a minute over it is not');
T::same(null, Provisioning::rejectReason($valid(), null), 'a submission with no timestamp at all is allowed through to validation');


T::group('Creating a firm');

$firm = Provisioning::create($valid(), $dana);

$tenant = $db->query("SELECT * FROM pl_tenants WHERE id = " . (int) $firm['tenant_id'])->fetch();

T::same('harbourline', (string) $tenant['slug'], 'the tenant carries the chosen address');
T::same('Harbourline Advisory', (string) $tenant['name'], 'and the practice name');
T::same('trial', (string) $tenant['status'], 'a new firm starts on trial');
T::same('trialing', (string) $tenant['billing_status'], 'and trialing, not active — nobody has paid');
T::ok($tenant['trial_ends_at'] !== null, 'the trial clock is started, so the date is there when beta ends');

$daysLeft = (int) round((strtotime((string) $tenant['trial_ends_at']) - time()) / 86400);
T::same(Billing::TRIAL_DAYS, $daysLeft, 'and it runs for exactly the advertised number of days');

$users = new UserRepository((int) $firm['tenant_id']);
$owner = $users->find((int) $firm['user_id']);

T::same('firm_owner', (string) $owner['role'], 'the signer-up is the firm owner');
T::same('active', (string) $owner['status'], 'active immediately — an owner who must accept their own invitation is a dead end');
T::same(null, $owner['client_org_id'], 'and firm-side, never attached to a client org');
T::same('dana@harbourline.test', (string) $owner['email'], 'the email is stored lowercase');
T::same($dana['id'], (int) $owner['account_id'], 'the owner membership is bound to the signed-in tools account');
T::same(null, $owner['password_hash'], 'and no per-firm password is written');
T::same((int) $owner['id'], (int) $users->findByAccountId($dana['id'])['id'], 'so signing in with that account finds the owner');
T::same('Dana Reyes', (string) $owner['name'], 'the owner name comes from the account');

T::same(1, count($users->firmSide()), 'exactly one person exists in the new firm');

// No handoff link: the owner is already signed in on the same host. The
// controller starts the Pilotage session and sends them here.
T::ok(str_contains($firm['entry_url'], '/f/harbourline/2fa/setup'),
    'the entry URL is the new firm\'s two-factor enrolment, the first thing an owner must do');
T::ok(str_contains($firm['entry_url'], PL_BASE . '/'), 'under the Pilotage path on this host');

T::throws(InvalidArgumentException::class,
    static fn () => Provisioning::create($valid(['slug' => 'noaccount']), ['id' => 0, 'email' => '', 'name' => '']),
    'there is no creating a workspace without a signed-in account');
T::ok($db->query("SELECT 1 FROM pl_tenants WHERE slug = 'noaccount'")->fetch() === false,
    'and the refusal writes nothing');


T::group('The address is now taken');

T::ok(!Provisioning::slugAvailable('harbourline'), 'slugAvailable says so');
T::ok(isset(Provisioning::problems($valid())['slug']), 'and a second signup on the same address is refused');


T::group('A failed create leaves nothing behind');

/**
 * The failure that matters: a tenant row with no owner. Nobody can sign in to
 * it and the slug it holds can never be reclaimed, so half a firm is strictly
 * worse than no firm.
 */
$before = (int) $db->query('SELECT COUNT(*) c FROM pl_tenants')->fetch()['c'];

$threw = false;
try {
    // An owner email the UserRepository will refuse outright, after the
    // tenant row has already been inserted inside the transaction.
    Provisioning::create($valid(['slug' => 'orphanwatch']), ['id' => $dana['id'], 'email' => 'not an email at all', 'name' => 'Dana']);
} catch (Throwable) {
    $threw = true;
}

T::ok($threw, 'the failure surfaces rather than being swallowed');
T::same($before, (int) $db->query('SELECT COUNT(*) c FROM pl_tenants')->fetch()['c'], 'and no tenant row survives it');
T::ok(
    $db->query("SELECT 1 FROM pl_tenants WHERE slug = 'orphanwatch'")->fetch() === false,
    'so the address is not burned by a signup that never completed'
);

$orphans = (int) $db->query(
    'SELECT COUNT(*) c FROM pl_tenants t
     LEFT JOIN pl_users u ON u.tenant_id = t.id AND u.role = "firm_owner"
     WHERE u.id IS NULL'
)->fetch()['c'];

T::same(0, $orphans, 'no tenant anywhere is left without an owner');


T::group('Two firms cannot share an address');

$dup = false;
try {
    // Bypasses problems() deliberately: this is the race where two people pick
    // the same address in the same second and only the UNIQUE index can settle
    // it. The controller catches this and re-renders the form.
    Provisioning::create($valid(), $account('someone@else.test', 'Someone Else'));
} catch (PDOException) {
    $dup = true;
}

T::ok($dup, 'the UNIQUE index refuses the duplicate, whatever the form thought');
T::same(1, (int) $db->query("SELECT COUNT(*) c FROM pl_tenants WHERE slug = 'harbourline'")->fetch()['c'], 'and only one firm holds it');
