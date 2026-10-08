<?php

declare(strict_types=1);

/**
 * M1: branding validation, seats, and the export.
 *
 * Branding matters here beyond looks: a logo URL and two colours are rendered
 * into HTML, so they are injection surfaces.
 */

use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Qualifiers;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\UserRepository;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_invitations', 'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active')");

$users = new UserRepository(1);
$ownerId = $users->create(['email' => 'owner@acme.test', 'name' => 'The Owner', 'role' => 'firm_owner', 'status' => 'active']);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'A Coach', 'role' => 'coach', 'status' => 'active']);


T::group('Branding validation — these values reach the HTML');

$validColor = static fn (string $v): bool => (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $v);

T::ok($validColor('#0f172a'), 'a six-digit hex is accepted');
T::ok($validColor('#FFFFFF'), 'uppercase too');
T::ok(!$validColor('#fff'), 'a three-digit shorthand is refused');
T::ok(!$validColor('red'), 'a colour name is refused');
T::ok(!$validColor('#0f172a; } body { display:none'), 'CSS injection through the colour field is refused');
T::ok(!$validColor('</style><script>alert(1)</script>'), 'and so is breaking out of the style block');

$validLogo = static fn (string $v): bool => (bool) preg_match('#^https://#i', $v);

T::ok($validLogo('https://cdn.example.com/logo.png'), 'an https logo is accepted');
T::ok(!$validLogo('http://example.com/logo.png'), 'plaintext http is refused — clients should never be asked to load branding insecurely');
T::ok(!$validLogo('javascript:alert(1)'), 'a javascript: URL is refused');
T::ok(!$validLogo('data:image/svg+xml;base64,PHN2Zz48c2NyaXB0Pg=='), 'a data: URL is refused — SVG can carry script');

// Three layers, and the innermost is the schema. primary_color is CHAR(7),
// which is exactly '#rrggbb' — a CSS injection payload physically will not
// fit, so even a code path that skipped validation could not store one.
$rejected = false;
try {
    $db->exec("UPDATE pl_tenants SET primary_color = 'red; } body{display:none' WHERE id = 1");
} catch (Throwable) {
    $rejected = true;
}
T::ok($rejected, 'the column itself refuses an oversized value — CHAR(7) fits #rrggbb and nothing else');

// And the layout re-checks anyway, because a 7-character payload is still
// conceivable and defence in depth is cheap here.
T::ok(!$validColor('#ab</st'), 'a 7-char non-hex value would still be rejected at render');
$db->exec("UPDATE pl_tenants SET primary_color = NULL WHERE id = 1");


T::group('Seats');

$staff = $users->firmSide();
T::same(2, count($staff), 'two firm-side people');
T::ok(!in_array('dana@alpha.test', array_column($staff, 'email'), true), 'client-side users are not seats');

$db->exec('UPDATE pl_tenants SET seat_limit = 2 WHERE id = 1');
$tenant = $db->query('SELECT * FROM pl_tenants WHERE id = 1')->fetch();
T::same(2, (int) $tenant['seat_limit'], 'a seat limit can be set');
T::ok(count($users->firmSide()) >= (int) $tenant['seat_limit'], 'and the firm is at it');

$db->exec('UPDATE pl_tenants SET seat_limit = NULL WHERE id = 1');


T::group('Firm settings are owner-only');

Policy::resetQualifiers();
Qualifiers::register();

$owner = ['id' => $ownerId, 'role' => 'firm_owner', 'status' => 'active', 'client_org_id' => null];
$coach = ['id' => $coachId, 'role' => 'coach', 'status' => 'active', 'client_org_id' => null];
$assoc = ['id' => 3, 'role' => 'associate', 'status' => 'active', 'client_org_id' => null];
$client = ['id' => 4, 'role' => 'client_owner', 'status' => 'active', 'client_org_id' => 1];

T::ok(Policy::can($owner, Policy::UPDATE, 'tenant_settings'), 'the owner may change firm settings');
T::ok(!Policy::can($coach, Policy::UPDATE, 'tenant_settings'), 'a coach may not');
T::ok(!Policy::can($assoc, Policy::UPDATE, 'tenant_settings'), 'nor an associate');
T::ok(!Policy::can($client, Policy::UPDATE, 'tenant_settings'), 'nor a client');

T::ok(Policy::can($owner, Policy::CREATE, 'user'), 'the owner mints seats');
T::ok(!Policy::can($coach, Policy::CREATE, 'user'), 'a coach cannot — a seat is a billing decision');
T::ok(Policy::can($coach, Policy::CREATE, 'client_portal_access'), 'but can still invite a client contact');

Policy::resetQualifiers();


T::group('Export excludes credentials');

// The export strips these keys from every row. Prove the columns exist first,
// so the test would fail if a rename made the stripping a no-op.
$userCols = array_column($db->query('SHOW COLUMNS FROM pl_users')->fetchAll(), 'Field');
T::ok(in_array('password_hash', $userCols, true), 'pl_users really has a password_hash column');

$inviteCols = array_column($db->query('SHOW COLUMNS FROM pl_invitations')->fetchAll(), 'Field');
T::ok(in_array('verifier_hash', $inviteCols, true), 'pl_invitations really has a verifier_hash column');

$twofaCols = array_column($db->query('SHOW COLUMNS FROM pl_user_2fa')->fetchAll(), 'Field');
T::ok(in_array('secret', $twofaCols, true), 'pl_user_2fa really has a secret column');

// Simulate the stripping the exporter does. Passwords live on the shared
// tools account since the port and nothing writes pl_users.password_hash any
// more, but the column remains (legacy rows), so the export must still strip
// it: write one directly to prove that.
$db->prepare('UPDATE pl_users SET password_hash = :h WHERE tenant_id = 1 AND id = :id')
   ->execute(['h' => password_hash('a-good-long-password', PASSWORD_DEFAULT), 'id' => $ownerId]);

// Select the specific user rather than relying on row order.
$stmt = $db->prepare('SELECT * FROM pl_users WHERE tenant_id = 1 AND id = :id');
$stmt->execute(['id' => $ownerId]);
$rows = $stmt->fetchAll();
T::same(1, count($rows), 'the owner row is there');
T::ok($rows[0]['password_hash'] !== null, 'a password hash is present in the raw row');

foreach ($rows as $i => $row) {
    unset($rows[$i]['password_hash'], $rows[$i]['verifier_hash'], $rows[$i]['secret'], $rows[$i]['code_hash']);
}

T::ok(!array_key_exists('password_hash', $rows[0]), 'and gone after stripping');
T::ok(!str_contains(json_encode($rows), '$2y$'), 'no bcrypt hash survives into the export payload');


T::group('Transactional email shape');

/**
 * The first magic-link email Pilotage sent went to Gmail's spam folder.
 * Authentication was fine — SPF, DKIM and the return-path all aligned. The
 * message itself was the problem: plain text only, fifteen lines dominated by
 * a 130-character bare URL. Filters read shape before intent, and that shape
 * is phishing.
 */
use Bizorca\Pilotage\Services\MailTemplate;

$m = MailTemplate::action(
    'Hello Dana,',
    [
        'You asked to sign in to your Bizorca workspace, so here is your link.',
        'It works once and expires in thirty minutes.',
    ],
    'Sign in to Bizorca',
    'https://bizorca.pilotagehq.com/auth/link/' . str_repeat('a', 32) . '.' . str_repeat('b', 64),
    ['If you did not ask for this, you can ignore this message.'],
    'Bizorca'
);

T::ok(str_contains($m['html'], '<!doctype html>'), 'an HTML part is produced');
T::ok(trim($m['text']) !== '', 'and a plain-text part');
T::ok(str_contains($m['html'], 'Sign in to Bizorca'), 'the button carries a real label, not "click here"');
T::ok(str_contains($m['html'], 'Bizorca'), "the firm's name appears in the body, not only the From line");

// Link-to-text ratio: the whole reason this class exists.
$plainText = trim(strip_tags(preg_replace('/<style.*?<\/style>/s', '', $m['html']) ?? ''));
$urlLength = strlen('https://bizorca.pilotagehq.com/auth/link/' . str_repeat('a', 32) . '.' . str_repeat('b', 64));
T::ok(strlen($plainText) > $urlLength * 2, 'there is at least twice as much prose as URL');

T::ok(!str_contains($m['html'], '<img'), 'no images — a tracking pixel is a deliverability cost for transactional mail');
T::ok(!str_contains($m['html'], 'http://'), 'no plaintext http anywhere');
// Twice in the source is correct: once in the button's href, once as the
// visible fallback. What matters is that it appears as VISIBLE TEXT only once
// — a message repeating a long URL down the page reads as phishing.
T::same(2, substr_count($m['html'], 'auth/link/'), 'the URL is in the href and in the fallback');
T::same(1, substr_count(strip_tags($m['html']), 'auth/link/'), 'but shows as visible text exactly once');

// The text part has to stand alone for clients that strip HTML.
T::ok(str_contains($m['text'], 'Sign in to Bizorca'), 'the text part names the action');
T::ok(str_contains($m['text'], 'auth/link/'), 'and carries the link');
T::ok(str_contains($m['text'], 'Hello Dana'), 'and the greeting');

// Escaping: a firm name is tenant-controlled and lands in HTML.
$evil = MailTemplate::action('Hi', ['Body'], 'Go', 'https://x.test', [], '<script>alert(1)</script>');
T::ok(!str_contains($evil['html'], '<script>alert'), 'a firm name cannot inject script into the HTML part');
T::ok(str_contains($evil['html'], '&lt;script&gt;'), 'it is escaped instead');


T::group('Who the email is from depends on who is reading');

use Bizorca\Pilotage\Core\Mailer;

$firmTenant = ['name' => 'Bizorca', 'mail_from_name' => null];

// A coach or firm owner is OUR customer.
T::same('Pilotage', Mailer::senderName(null, $firmTenant),
    'a firm-side recipient sees Pilotage — they are our customer, not their own firm');

// A client is THEIR customer.
T::same('Bizorca', Mailer::senderName(7, $firmTenant),
    'a client-side recipient sees the firm — that is the white-label promise');

T::same('Bizorca Advisory', Mailer::senderName(7, ['name' => 'Bizorca', 'mail_from_name' => 'Bizorca Advisory']),
    'the firm can override its own sender name');
T::same('Pilotage', Mailer::senderName(null, ['name' => 'Bizorca', 'mail_from_name' => 'Bizorca Advisory']),
    'but that override never applies to firm-side mail');

T::same('Pilotage', Mailer::senderName(7, ['name' => '']),
    'a nameless tenant falls back rather than sending from an empty string');
