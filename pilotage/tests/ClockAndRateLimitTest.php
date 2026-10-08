<?php

declare(strict_types=1);

/**
 * One clock, and rate limiting that actually engages.
 *
 * The first group exists because of a real bug: PHP computed window
 * boundaries with date() while MySQL wrote timestamps with NOW(), on a
 * different timezone. Every window query compared values seven hours apart,
 * so rate limiting counted zero attempts and never fired. Nothing in a
 * PHP-only test suite would have noticed.
 *
 * If the first group ever fails, assume every time-windowed feature in the
 * application is quietly broken.
 */

use Bizorca\Pilotage\Auth\RateLimiter;
use Bizorca\Pilotage\Core\Database;

$db = Database::conn();

T::group('Clocks agree');

$phpNow = time();
$sqlNow = strtotime((string) $db->query('SELECT NOW() AS n')->fetch()['n']);

T::ok(abs($phpNow - $sqlNow) <= 2, 'PHP time() and MySQL NOW() agree within 2 seconds');

$tz = (string) $db->query("SELECT @@session.time_zone AS tz")->fetch()['tz'];
T::same('+00:00', $tz, 'MySQL session timezone is pinned to UTC');
T::same('UTC', date_default_timezone_get(), 'PHP default timezone is UTC');

// A row written by MySQL must fall inside a window PHP considers "recent".
$db->exec("DELETE FROM pl_auth_attempts WHERE identifier = 'clock@test'");
RateLimiter::record('login', 'clock@test', '127.0.0.1', false);

$stmt = $db->prepare(
    "SELECT COUNT(*) c FROM pl_auth_attempts
     WHERE identifier = 'clock@test' AND created_at >= :s"
);
$stmt->execute(['s' => date('Y-m-d H:i:s', time() - 60)]);
T::same(1, (int) $stmt->fetch()['c'], 'a row MySQL just wrote is inside a PHP-computed 60-second window');


T::group('Rate limiting engages at the threshold');

$db->exec("DELETE FROM pl_auth_attempts WHERE identifier IN ('limit@test','ip@test','clock@test')");

// login: 5 per identifier per 15 minutes.
for ($i = 1; $i <= 4; $i++) {
    RateLimiter::record('login', 'limit@test', '10.0.0.1', false);
    T::ok(!RateLimiter::tooManyAttempts('login', 'limit@test', '10.0.0.1'), 'not limited after ' . $i . ' failure(s)');
}

RateLimiter::record('login', 'limit@test', '10.0.0.1', false);
T::ok(RateLimiter::tooManyAttempts('login', 'limit@test', '10.0.0.1'), 'limited at the 5th failure');
T::ok(RateLimiter::retryAfter('login', 'limit@test') > 0, 'retryAfter reports a positive wait');
T::ok(RateLimiter::retryAfter('login', 'limit@test') <= 900, 'retryAfter never exceeds the window');

T::group('Rate limiting — successes do not count, and clearing works');

$db->exec("DELETE FROM pl_auth_attempts WHERE identifier = 'success@test'");
for ($i = 0; $i < 8; $i++) {
    RateLimiter::record('login', 'success@test', '10.0.0.2', true);
}
T::ok(!RateLimiter::tooManyAttempts('login', 'success@test', '10.0.0.2'), 'successful attempts never trip the limit');

RateLimiter::clear('login', 'limit@test');
T::ok(!RateLimiter::tooManyAttempts('login', 'limit@test', '10.0.0.1'), 'clearing after a success releases the lock');
T::same(0, RateLimiter::retryAfter('login', 'limit@test'), 'retryAfter is zero once cleared');

T::group('Rate limiting — identifiers and IPs are independent');

$db->exec("DELETE FROM pl_auth_attempts WHERE identifier LIKE 'spray%'");

// magic_link: 3 per identifier, 20 per IP. Spraying distinct addresses from
// one IP must eventually trip the IP budget even though no identifier does.
for ($i = 0; $i < 20; $i++) {
    RateLimiter::record('magic_link', 'spray' . $i . '@test', '10.0.0.9', false);
}
T::ok(!RateLimiter::tooManyAttempts('magic_link', 'spray-fresh@test', null), 'a fresh identifier alone is not limited');
T::ok(RateLimiter::tooManyAttempts('magic_link', 'spray-fresh@test', '10.0.0.9'), 'the spraying IP is limited');
T::ok(!RateLimiter::tooManyAttempts('magic_link', 'spray-fresh@test', '10.0.0.8'), 'a different IP is unaffected');

T::group('Rate limiting — window expiry and pruning');

$db->exec("DELETE FROM pl_auth_attempts WHERE identifier = 'aged@test'");
for ($i = 0; $i < 6; $i++) {
    RateLimiter::record('login', 'aged@test', '10.0.0.3', false);
}
T::ok(RateLimiter::tooManyAttempts('login', 'aged@test', '10.0.0.3'), 'limited while attempts are fresh');

// Age them past the 15-minute window using MySQL's own clock.
$db->exec("UPDATE pl_auth_attempts SET created_at = DATE_SUB(NOW(), INTERVAL 16 MINUTE) WHERE identifier = 'aged@test'");
T::ok(!RateLimiter::tooManyAttempts('login', 'aged@test', '10.0.0.3'), 'attempts outside the window no longer count');

$pruned = RateLimiter::prune(900);
T::ok($pruned >= 6, 'prune removes aged attempts');

T::throws(
    InvalidArgumentException::class,
    static fn () => RateLimiter::tooManyAttempts('not_a_real_action', 'x', null),
    'an unknown action is a programming error, not a free pass'
);

$db->exec("DELETE FROM pl_auth_attempts WHERE identifier LIKE '%@test'");


T::group('Client IP behind Cloudflare');

use Bizorca\Pilotage\Core\ClientIp;

// A direct request: no header, use REMOTE_ADDR.
T::same('203.0.113.9', ClientIp::resolve(['REMOTE_ADDR' => '203.0.113.9']), 'a direct request uses REMOTE_ADDR');
T::same(null, ClientIp::resolve([]), 'no address at all resolves to null');

// From Cloudflare: trust the header.
T::same('198.51.100.7', ClientIp::resolve([
    'REMOTE_ADDR' => '162.158.1.1',            // a real Cloudflare range
    'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
]), 'behind Cloudflare, CF-Connecting-IP is used');

// The attack this guard exists for: spoofing the header from anywhere else.
T::same('203.0.113.9', ClientIp::resolve([
    'REMOTE_ADDR' => '203.0.113.9',            // NOT Cloudflare
    'HTTP_CF_CONNECTING_IP' => '10.0.0.1',
]), 'a spoofed header from a non-Cloudflare address is ignored');

T::same('162.158.1.1', ClientIp::resolve([
    'REMOTE_ADDR' => '162.158.1.1',
    'HTTP_CF_CONNECTING_IP' => 'not-an-ip',
]), 'a malformed header falls back rather than poisoning the key');

T::ok(ClientIp::isCloudflare('162.158.1.1'), '162.158.0.0/15 is Cloudflare');
T::ok(ClientIp::isCloudflare('104.16.0.1'), '104.16.0.0/13 is Cloudflare');
T::ok(ClientIp::isCloudflare('2606:4700::1'), 'and the v6 range too');
T::ok(!ClientIp::isCloudflare('203.0.113.9'), 'a documentation address is not');
T::ok(!ClientIp::isCloudflare('8.8.8.8'), 'nor is Google DNS');

T::ok(ClientIp::inRange('192.168.1.5', '192.168.1.0/24'), 'CIDR maths: inside a /24');
T::ok(!ClientIp::inRange('192.168.2.5', '192.168.1.0/24'), 'and outside it');
T::ok(ClientIp::inRange('10.0.0.1', '10.0.0.0/8'), 'a /8 with a byte-aligned mask');
T::ok(!ClientIp::inRange('11.0.0.1', '10.0.0.0/8'), 'and just outside it');
T::ok(ClientIp::inRange('192.168.1.130', '192.168.1.128/25'), 'a non-byte-aligned /25');
T::ok(!ClientIp::inRange('192.168.1.126', '192.168.1.128/25'), 'and below its boundary');
T::ok(!ClientIp::inRange('not-an-ip', '10.0.0.0/8'), 'garbage is not in any range');
