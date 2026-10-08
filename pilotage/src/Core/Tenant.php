<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Tenant resolution from the route.
 *
 * Pilotage was subdomain-tenanted (firmname.pilotagehq.com). On
 * tools.bizorca.com a firm is the route prefix /f/<slug>, so this class turns
 * that slug into either a tenant, the landing area (no prefix), or a
 * rejection — before any database work happens.
 *
 * Slug parsing is deliberately strict. A permissive parser here is a
 * cross-tenant vulnerability, not a UX inconvenience.
 */
final class Tenant
{
    /** Resolution outcomes returned by parseSlug(). */
    public const APEX     = 'apex';     // no /f/ prefix: the Pilotage landing area, no tenant
    public const TENANT   = 'tenant';   // a candidate tenant slug
    public const RESERVED = 'reserved'; // a slug we keep for ourselves
    public const INVALID  = 'invalid';  // malformed label
    
    /**
     * Subdomains that can never be a tenant slug.
     *
     * THIS LIST CAN ONLY GROW, AND ADDING TO IT IS NOT FREE.
     *
     * A reserved label resolves to a 404 (parseSlug returns RESERVED, and
     * resolve() refuses it), so adding a slug that a live firm already holds
     * takes that firm off the internet. Before extending this list, check it
     * against the tenants that actually exist — `TenantIsolationTest` and
     * `HostResolutionTest` assert the invariant, but neither can see
     * production. It was checked when this list was grown on 2026-08-08.
     *
     * Being generous here is right, because the reverse is impossible:
     * reclaiming a slug after a firm has taken it breaks every link they have
     * ever sent a client, and self-serve signup (FR-1.6) means anyone can take
     * one at any hour without a human noticing.
     */
    public const RESERVED_SLUGS = [
        // --- infrastructure and protocol names -------------------------------
        'www', 'app', 'apps', 'admin', 'api', 'mail', 'email', 'smtp', 'imap',
        'pop', 'ftp', 'sftp', 'ssh', 'webmail', 'ns', 'ns1', 'ns2', 'ns3',
        'mx', 'dns', 'cdn', 'static', 'assets', 'media', 'img', 'images',
        'js', 'css', 'fonts', 'files', 'file', 'download', 'downloads',
        'upload', 'uploads', 'storage', 'db', 'database', 'sql', 'redis',
        'cache', 'queue', 'worker', 'workers', 'cron', 'tick', 'health',
        'ping', 'robots', 'sitemap', 'favicon', 'well-known',

        // --- identity and access --------------------------------------------
        'login', 'logout', 'signin', 'sign-in', 'signup', 'sign-up', 'auth',
        'oauth', 'sso', 'saml', 'scim', 'token', 'tokens', 'verify', 'reset',
        'password', 'passwords', 'invite', 'invites', 'register', 'join',
        'account', 'accounts', 'profile', 'session', 'sessions', 'security',
        'mfa', 'totp', '2fa',

        // --- commerce --------------------------------------------------------
        'billing', 'pay', 'payment', 'payments', 'pricing', 'price', 'prices',
        'plan', 'plans', 'checkout', 'subscribe', 'subscription', 'invoice',
        'invoices', 'receipt', 'receipts', 'trial', 'free', 'upgrade',
        'refund', 'refunds', 'stripe',

        // --- content and marketing surfaces ----------------------------------
        'about', 'faq', 'faqs', 'contact', 'support', 'help', 'helpdesk',
        'docs', 'documentation', 'guide', 'guides', 'resources', 'learn',
        'academy', 'training', 'webinar', 'webinars', 'events', 'community',
        'forum', 'forums', 'blog', 'news', 'press', 'media-kit', 'brand',
        'careers', 'jobs', 'status', 'changelog', 'roadmap', 'marketing',
        'welcome', 'onboarding', 'start', 'home', 'main', 'site', 'sites',
        'public', 'private', 'shared', 'search',

        // --- legal ------------------------------------------------------------
        'legal', 'terms', 'privacy', 'policy', 'policies', 'gdpr', 'dpa',
        'cookies', 'compliance', 'abuse', 'dmca', 'trust',

        // --- environments ------------------------------------------------------
        'go', 'link', 'links', 't', 'r', 'dev', 'develop', 'development',
        'staging', 'stage', 'test', 'testing', 'qa', 'uat', 'prod',
        'production', 'preview', 'beta', 'alpha', 'canary', 'demo', 'sandbox',
        'internal', 'system', 'root', 'tmp', 'temp', 'backup', 'backups',
        'archive', 'logs', 'log', 'monitor', 'monitoring', 'metrics', 'stats',
        'analytics', 'debug',

        // --- role addresses ----------------------------------------------------
        'postmaster', 'webmaster', 'hostmaster', 'noreply', 'no-reply',
        'notifications', 'alerts', 'info', 'sales', 'hello', 'team',

        // --- our own names ------------------------------------------------------
        // NOT 'bizorca' — that is a live tenant (the house practice), and
        // reserving it would 404 the firm this product was built for.
        'pilotage', 'pilotagehq', 'getpilotage', 'platform', 'hq',

        /**
         * Methodology names.
         *
         * These are other people's trademarks, and a firm sitting on
         * `eos.pilotagehq.com` looks like an official affiliation we do not
         * have. Cheap to reserve now; a trademark conversation later.
         */
        'eos', 'traction', 'scalingup', 'scaling-up', 'rockefeller', 'pinnacle',
        'stratop', 'gazelles', 'l10', 'level10', 'ids', 'vto', '3hag', 'grow',
        'okr', 'okrs', 'kpi', 'scorecard',

        // --- generic enough that one firm should not own them -------------------
        'my', 'portal', 'client', 'clients', 'customer', 'customers',
        'coach', 'coaches', 'coaching', 'advisor', 'advisors', 'advisory',
        'consultant', 'consultants', 'consulting', 'mentor', 'mentors',
        'mentoring', 'mastermind', 'masterminds', 'peer', 'peers',
        'firm', 'firms', 'practice', 'practices', 'partner', 'partners',
        'associate', 'associates', 'group', 'company', 'business',
        'workspace', 'dashboard', 'console', 'engagement', 'engagements',
        'playbook', 'playbooks',
    ];

    /** Currently resolved tenant row, or null on apex/marketing requests. */
    private static ?array $current = null;

    /** True once resolve() has run, so a null current is distinguishable from "not yet resolved". */
    private static bool $resolved = false;

    /**
     * Classify a firm slug taken from the route prefix /f/<slug>.
     *
     * Pure: no DB, no globals. This replaces parseHost() from the subdomain
     * days, and keeps its strictness — a permissive slug parser is the same
     * vulnerability a permissive host parser was. null (no /f/ prefix) is the
     * Pilotage landing area, the old apex.
     *
     * @return array{status: string, slug: ?string}
     */
    public static function parseSlug(?string $slug): array
    {
        if ($slug === null) {
            return ['status' => self::APEX, 'slug' => null];
        }

        // pl_parse_route() lowercases; anything else here is not a slug.
        if (!self::isValidSlug($slug)) {
            return ['status' => self::INVALID, 'slug' => null];
        }

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            return ['status' => self::RESERVED, 'slug' => $slug];
        }

        return ['status' => self::TENANT, 'slug' => $slug];
    }

/**
     * DNS label rules, plus our own tightening: no punycode (we are not ready
     * to reason about homograph attacks on tenant slugs), and a 2-character
     * floor so single letters stay available for future platform use.
     */
    public static function isValidSlug(string $slug): bool
    {
        if (strlen($slug) < 2 || strlen($slug) > 63) {
            return false;
        }
        if (str_starts_with($slug, 'xn--')) {
            return false;
        }
        // Alphanumeric ends, hyphens allowed only in the interior.
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $slug);
    }

    /**
     * Resolve the firm for this request from its route slug, and make it the
     * scope everything else reads.
     *
     * An unknown slug, a reserved one and a suspended firm all throw the same
     * TenantNotFoundException, which renders a plain 404: never leak whether a
     * slug exists.
     */
    public static function resolveSlug(?string $slug): ?array
    {
        $parsed = self::parseSlug($slug);
        self::$resolved = true;

        if ($parsed['status'] === self::APEX) {
            self::$current = null;
            return null;
        }

        if ($parsed['status'] !== self::TENANT) {
            self::$current = null;
            throw new TenantNotFoundException(
                'Route slug did not resolve to a tenant (' . $parsed['status'] . ').'
            );
        }

        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_tenants WHERE slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $parsed['slug']]);
        $row = $stmt->fetch();

        if ($row === false) {
            self::$current = null;
            throw new TenantNotFoundException('No tenant for slug "' . $parsed['slug'] . '".');
        }

        if (!in_array($row['status'], ['trial', 'active'], true)) {
            self::$current = null;
            throw new TenantNotFoundException(
                'Tenant "' . $parsed['slug'] . '" is ' . $row['status'] . '.'
            );
        }

        self::$current = $row;

        return $row;
    }

    /** The resolved tenant row, or null on apex requests. */
    public static function current(): ?array
    {
        return self::$current;
    }

    /** The resolved tenant id, or null on apex requests. */
    public static function currentId(): ?int
    {
        return self::$current === null ? null : (int) self::$current['id'];
    }

    /**
     * The resolved tenant id, or a thrown exception. Use this anywhere a
     * missing tenant means the code has no business running.
     *
     * @throws TenantScopeException
     */
    public static function requireId(): int
    {
        $id = self::currentId();
        if ($id === null) {
            throw new TenantScopeException('This operation requires a resolved tenant.');
        }
        return $id;
    }

    /** Test seam: set the current tenant directly. */
    public static function setCurrent(?array $tenant): void
    {
        self::$current = $tenant;
        self::$resolved = true;
    }

    /** Test seam: forget the resolved tenant. */
    public static function reset(): void
    {
        self::$current = null;
        self::$resolved = false;
    }

    public static function isResolved(): bool
    {
        return self::$resolved;
    }
}
