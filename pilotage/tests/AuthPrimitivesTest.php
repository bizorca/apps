<?php

declare(strict_types=1);

/**
 * Auth primitives: password hashing, selector/verifier tokens, and TOTP.
 * Pure — no database.
 */

use Bizorca\Pilotage\Auth\Password;
use Bizorca\Pilotage\Auth\Token;
use Bizorca\Pilotage\Auth\TwoFactor;

T::group('Password');

$hash = Password::hash('correct horse battery staple');
T::ok(str_starts_with($hash, '$2y$'), 'bcrypt hash produced');
T::ok(Password::verify('correct horse battery staple', $hash), 'correct password verifies');
T::ok(!Password::verify('wrong horse battery staple', $hash), 'wrong password rejected');
T::ok(!Password::verify('anything', null), 'null hash (magic-link-only user) rejects without throwing');
T::ok(!Password::verify('anything', ''), 'empty hash rejects');
T::ok(Password::hash('correct horse battery staple') !== $hash, 'each hash is uniquely salted');

T::throws(InvalidArgumentException::class, static fn () => Password::hash('short'), 'too-short password refused');
T::throws(InvalidArgumentException::class, static fn () => Password::hash(str_repeat('a', 73)), 'over-72-byte password refused rather than silently truncated');
T::same([], Password::problems('a-perfectly-fine-password'), 'acceptable password has no problems');
T::ok(Password::problems('tiny') !== [], 'short password reports a problem');

// The 72-byte truncation trap: without the length check these two would be
// the same password as far as bcrypt is concerned.
$long = str_repeat('a', 72);
$hashLong = Password::hash($long);
T::ok(Password::verify($long, $hashLong), '72-byte password works');
T::throws(InvalidArgumentException::class, static fn () => Password::hash($long . 'DIFFERENT'), 'anything past 72 bytes is refused, so truncation collisions cannot happen');


T::group('Tokens (selector/verifier)');

$token = Token::create();
T::ok(str_contains($token['plaintext'], '.'), 'token has two halves');
T::same(32, strlen($token['selector']), 'selector is 32 hex chars');
T::same(64, strlen($token['verifier_hash']), 'verifier hash is sha256');
T::ok(!str_contains($token['verifier_hash'], explode('.', $token['plaintext'])[1]), 'stored hash is not the verifier itself');

[$sel, $ver] = Token::split($token['plaintext']);
T::same($token['selector'], $sel, 'split returns the selector');
T::ok(Token::verify($ver, $token['verifier_hash']), 'verifier checks against the stored hash');
// Flip the first character to a guaranteed-different one, so the "tamper" is
// never accidentally a no-op when the verifier already starts with that digit.
$tampered = ($ver[0] === '0' ? '1' : '0') . substr($ver, 1);
T::ok($tampered !== $ver, 'tampered verifier really differs');
T::ok(!Token::verify($tampered, $token['verifier_hash']), 'tampered verifier rejected');

T::same(null, Token::split('nodot'), 'token with no separator rejected');
T::same(null, Token::split('a.b.c'), 'token with two separators rejected');
T::same(null, Token::split('short.' . str_repeat('a', 64)), 'wrong-length selector rejected');
T::same(null, Token::split(str_repeat('a', 32) . '.short'), 'wrong-length verifier rejected');
T::same(null, Token::split(str_repeat('z', 32) . '.' . str_repeat('z', 64)), 'non-hex token rejected');
T::same(null, Token::split(''), 'empty token rejected');

T::ok(Token::create()['selector'] !== Token::create()['selector'], 'selectors are unique');


T::group('TOTP');

// RFC 6238 test vector: secret "12345678901234567890" in base32.
$rfcSecret = TwoFactor::base32Encode('12345678901234567890');
T::same('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $rfcSecret, 'base32 encoding matches the RFC test key');
T::same('12345678901234567890', TwoFactor::base32Decode($rfcSecret), 'base32 round-trips');

// RFC 6238 Appendix B, SHA-1 rows.
// RFC prints 8-digit codes; we take the low 6.
T::same('287082', TwoFactor::codeNow($rfcSecret, 59),          'RFC vector T=59 (94287082)');
T::same('081804', TwoFactor::codeNow($rfcSecret, 1111111109),  'RFC vector T=1111111109 (07081804)');
T::same('050471', TwoFactor::codeNow($rfcSecret, 1111111111),  'RFC vector T=1111111111 (14050471)');
T::same('005924', TwoFactor::codeNow($rfcSecret, 1234567890),  'RFC vector T=1234567890 (89005924)');
T::same('279037', TwoFactor::codeNow($rfcSecret, 2000000000),  'RFC vector T=2000000000 (69279037)');
T::same('353130', TwoFactor::codeNow($rfcSecret, 20000000000), 'RFC vector T=20000000000 (65353130)');

$secret = TwoFactor::generateSecret();
T::same(32, strlen($secret), '160-bit secret is 32 base32 chars');
T::ok(TwoFactor::base32Decode($secret) !== '', 'generated secret decodes');

$now = 1700000000;
$code = TwoFactor::codeNow($secret, $now);
T::ok(TwoFactor::verify($secret, $code, null, $now) !== null, 'current code verifies');
T::ok(TwoFactor::verify($secret, $code, null, $now + 30) !== null, 'code from one step ago still accepted (drift window)');
T::ok(TwoFactor::verify($secret, $code, null, $now - 30) !== null, 'code one step ahead accepted (drift window)');
T::same(null, TwoFactor::verify($secret, $code, null, $now + 120), 'code well outside the window rejected');
T::same(null, TwoFactor::verify($secret, '000000', null, $now), 'wrong code rejected');
T::same(null, TwoFactor::verify($secret, 'abcdef', null, $now), 'non-numeric code rejected');
T::same(null, TwoFactor::verify($secret, '12345', null, $now), 'five-digit code rejected');
T::ok(TwoFactor::verify($secret, ' ' . $code . ' ', null, $now) !== null, 'whitespace around the code tolerated');

// Replay: a code is good exactly once.
$counter = TwoFactor::verify($secret, $code, null, $now);
T::ok($counter !== null, 'first use of a code succeeds');
T::same(null, TwoFactor::verify($secret, $code, $counter, $now), 'same code refused a second time');

$uri = TwoFactor::provisioningUri($secret, 'coach@acme.test', 'Acme Advisory');
T::ok(str_starts_with($uri, 'otpauth://totp/'), 'provisioning URI has the right scheme');
T::ok(str_contains($uri, 'issuer=Acme%20Advisory'), 'issuer is the firm name, not Pilotage');
T::ok(str_contains($uri, 'secret=' . $secret), 'provisioning URI carries the secret');

$codes = TwoFactor::generateRecoveryCodes();
T::same(10, count($codes), 'ten recovery codes generated');
T::same(10, count(array_unique($codes)), 'recovery codes are unique');

T::same('', TwoFactor::base32Decode('not-valid-base32!'), 'invalid base32 decodes to empty');
T::throws(InvalidArgumentException::class, static fn () => TwoFactor::codeNow('!!!!'), 'invalid secret refused');
