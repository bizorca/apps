<?php

declare(strict_types=1);

/**
 * Route resolution: how a request names its firm.
 *
 * Was HostResolutionTest. Pilotage was subdomain-tenanted; on
 * tools.bizorca.com a firm is the route prefix /f/<slug>, parsed by
 * pl_parse_route() and classified by Tenant::parseSlug(). The strictness the
 * host tests asserted carries over: a permissive slug parser is the same
 * cross-tenant vulnerability a permissive host parser was. The slug-validity
 * and reserved-list hygiene groups below are unchanged from the original.
 */

use Bizorca\Pilotage\Core\Tenant;

T::group('Route parsing — the firm is a route prefix');

T::same(['acme', '/'], pl_parse_route('/f/acme'), 'bare firm prefix is the firm home');
T::same(['acme', '/'], pl_parse_route('/f/acme/'), 'trailing slash is the firm home');
T::same(['acme', '/clients/3'], pl_parse_route('/f/acme/clients/3'), 'path below the prefix is the route');
T::same(['acme', '/clients'], pl_parse_route('/f/ACME/clients'), 'slug is lowercased');
T::same([null, '/pricing'], pl_parse_route('/pricing'), 'no prefix is the landing area, not a firm');
T::same([null, '/'], pl_parse_route('/'), 'root is the landing area');
T::same([null, '/f'], pl_parse_route('/f'), '/f alone names no firm');
T::same([null, '/fx/acme'], pl_parse_route('/fx/acme'), 'a lookalike prefix names no firm');

T::group('Slug classification — strict, like the host parser was');

T::same(Tenant::APEX, Tenant::parseSlug(null)['status'], 'no slug is the landing area');
T::same(Tenant::TENANT, Tenant::parseSlug('acme')['status'], 'plain slug is a tenant');
T::same('north-star', Tenant::parseSlug('north-star')['slug'], 'interior hyphen allowed');
T::same('a1', Tenant::parseSlug('a1')['slug'], 'two-character slug allowed');
foreach (['-acme' => 'leading hyphen', 'acme-' => 'trailing hyphen', 'ac_me' => 'underscore',
          'a' => 'single character', 'xn--80ak6aa92e' => 'punycode', 'acme.evil' => 'a dot',
          'acme%2fevil' => 'an encoded slash', '' => 'empty', str_repeat('a', 64) => 'over-long'] as $bad => $why) {
    T::same(Tenant::INVALID, Tenant::parseSlug($bad)['status'], $why . ' rejected');
}

T::group('Reserved slugs cannot be a firm');

foreach (['admin', 'api', 'mail', 'login', 'billing', 'support', 'status', 'app',
          'staging', 'noreply', 'www'] as $label) {
    T::same(Tenant::RESERVED, Tenant::parseSlug($label)['status'], $label . ' is not claimable');
}

T::group('Every URL helper lands on the same firm the router reads');

// Round trip: what tenant_url() emits, pl_current_route() + pl_parse_route()
// must read back as the same firm and path. A drift here sends people to the
// wrong firm or the landing page.
Tenant::setCurrent(['id' => 1, 'slug' => 'acme']);
foreach (['/', '/clients/3', '/login?redirect=%2Ftasks', '/engagements/2/report.csv'] as $p) {
    $u = tenant_url($p);
    $query = (string) parse_url($u, PHP_URL_QUERY);
    parse_str($query, $q);
    [$slug, $rest] = pl_parse_route((string) ($q['r'] ?? ''));
    T::same('acme', $slug, 'tenant_url(' . $p . ') carries the firm');
    T::same(strtok($p, '?'), $rest, 'and the path');
}
T::ok(str_contains(tenant_url('/login?redirect=%2Ftasks'), '&redirect=%2Ftasks'), 'a query on the path survives as its own parameter');
T::same('/pilotage/?r=/f/acme/clients#c', pl_route('/f/acme/clients#c'), 'a fragment stays last');
T::same(1, substr_count(tenant_url('/x'), '/f/'), 'the prefix appears once, never nested');
Tenant::reset();

T::group('Slug validation');

T::ok(Tenant::isValidSlug('acme'), 'acme valid');
T::ok(Tenant::isValidSlug('acme-partners'), 'acme-partners valid');
T::ok(!Tenant::isValidSlug('a'), 'single character invalid');
T::ok(!Tenant::isValidSlug('-acme'), 'leading hyphen invalid');
T::ok(!Tenant::isValidSlug('acme-'), 'trailing hyphen invalid');
T::ok(!Tenant::isValidSlug('ACME'), 'uppercase invalid (slugs are stored lowercase)');
T::ok(!Tenant::isValidSlug('xn--acme'), 'punycode invalid');
T::ok(!Tenant::isValidSlug(str_repeat('a', 64)), '64 characters invalid');
T::ok(Tenant::isValidSlug(str_repeat('a', 63)), '63 characters valid');


T::group('The reserved list — hygiene, because it can only ever grow');

/**
 * A reserved label 404s. Adding one that a live firm already holds takes that
 * firm off the internet, and there is no undo: reclaiming a slug breaks every
 * link they have ever sent a client. These assertions are the cheap half of
 * the check. The other half — comparing against the tenants that actually
 * exist in production — cannot be automated from here and has to be done by
 * hand before the list grows. It was, on 2026-08-08.
 */
$reserved = Tenant::RESERVED_SLUGS;

T::same(
    count($reserved),
    count(array_unique($reserved)),
    'no duplicates — a repeated entry means two people edited the list without reading it'
);

T::same(
    array_values($reserved),
    array_map('strtolower', array_values($reserved)),
    'every entry is lowercase, because that is how a route slug is compared'
);

// 't' and 'r' are the short-link labels and are shorter than a slug may be, so
// they are refused by isValidSlug before the reserved check ever runs. They stay
// on the list as belt and braces, and this asserts nothing else has crept in.
$tooShortButIntentional = ['t', 'r'];
$unreachable = array_values(array_filter(
    $reserved,
    static fn (string $s): bool => !Tenant::isValidSlug($s) && !in_array($s, $tooShortButIntentional, true)
));

T::same([], $unreachable, 'every reserved entry is a slug somebody could otherwise have taken');

// The house practice and the demo firm are live. This is the assertion that
// would have caught the mistake worth caring about.
foreach (['bizorca', 'acme'] as $liveTenant) {
    T::ok(
        !in_array($liveTenant, $reserved, true),
        'the live tenant "' . $liveTenant . '" is NOT reserved — reserving it would 404 a real firm'
    );
}

// Spot-checks that the growth actually took, across each group of the list.
foreach (['pricing', 'signup', 'eos', 'traction', 'coaching', 'advisors',
          'terms', 'privacy', 'notifications', 'pilotagehq'] as $label) {
    T::same(
        Tenant::RESERVED,
        Tenant::parseSlug($label)['status'],
        '"' . $label . '" cannot be claimed by a firm'
    );
}
