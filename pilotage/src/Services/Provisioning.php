<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Auth\RateLimiter;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Repositories\UserRepository;

/**
 * Self-serve firm creation (FR-1.3).
 *
 * ---------------------------------------------------------------------------
 * THE ONE PLACE A TENANT IS BORN.
 *
 * Everything downstream — every repository, every policy check, every scoped
 * query — assumes a tenant row and its owning user were created together and
 * correctly. There is exactly one code path that does that, and this is it.
 * A second one would eventually disagree with this one about some detail
 * (status, trial clock, role) and produce a firm that is subtly broken in a
 * way nothing tests.
 *
 * WHAT MUST BE TRUE WHEN THIS RETURNS
 *
 *   - `pl_tenants` has a row with status 'trial' and a started trial clock.
 *   - That tenant has exactly one active `firm_owner`, bound (account_id) to
 *     the Bizorca Tools account that created it.
 *   - Nothing else exists. The starter playbook and session templates are NOT
 *     installed here — that is the onboarding step on the dashboard, and it is
 *     a deliberate act by the owner rather than something that happens to them.
 *
 * WHY IT IS ONE TRANSACTION
 *
 * A tenant with no owner is unreachable: nobody can sign in to it, and its
 * slug is burned forever because RESERVED_SLUGS-style permanence applies to
 * taken slugs too (see Tenant). Half a firm is worse than no firm, so the
 * whole thing commits or none of it does.
 *
 * WHO OWNS IT: THE SIGNED-IN TOOLS ACCOUNT.
 *
 * On tools.bizorca.com signup no longer asks for a name, email and password:
 * the owner is the Bizorca Tools account already signed in, and their sign-in
 * (and its recovery) is the shared /account pages'. The rate limits below are
 * what stands between this endpoint and someone farming workspace addresses.
 * ---------------------------------------------------------------------------
 */
final class Provisioning
{
    /** A human never sees this field, so anything in it came from a bot. */
    public const HONEYPOT_FIELD = 'company_website_url';

    /** Nobody fills a five-field form honestly in under three seconds. */
    public const MIN_SECONDS = 3;

    /** Rate-limit bucket. Registered in RateLimiter::LIMITS. */
    public const ACTION = 'signup';

    /**
     * Validate without writing anything.
     *
     * Separated from create() so the controller can re-render the form with
     * every problem at once, rather than making someone discover them one
     * submission at a time.
     *
     * @param array<string,mixed> $input
     * @return array<string,string> field => problem, in form order
     */
    public static function problems(array $input): array
    {
        $problems = [];

        if (trim((string) ($input['firm_name'] ?? '')) === '') {
            $problems['firm_name'] = 'Your practice needs a name — it is what your clients will see.';
        }

        $slug = self::normaliseSlug((string) ($input['slug'] ?? ''));

        if ($slug === '') {
            $problems['slug'] = 'Pick an address for your workspace.';
        } elseif (self::looksPunycode((string) ($input['slug'] ?? ''))) {
            $problems['slug'] = 'That address is not available.';
        } elseif (!Tenant::isValidSlug($slug)) {
            $problems['slug'] = 'Use 2–63 letters, numbers and hyphens, starting and ending with a letter or number.';
        } elseif (in_array($slug, Tenant::RESERVED_SLUGS, true)) {
            $problems['slug'] = 'That address is reserved. Try another.';
        } elseif (self::slugTaken($slug)) {
            $problems['slug'] = 'That address is already taken.';
        }

        // Name, email and password are the signed-in Bizorca Tools account's:
        // they were checked when that account was made, and signup no longer
        // asks for them.

        return $problems;
    }

    /**
     * Create the firm and its owner.
     *
     * @param array<string,mixed> $input Already passed problems().
     * @return array{tenant_id:int, user_id:int, slug:string, entry_url:string}
     */
    /**
     * @param array<string,mixed> $account The signed-in tools account (users row): the owner.
     */
    public static function create(array $input, array $account, ?string $ip = null): array
    {
        $slug = self::normaliseSlug((string) ($input['slug'] ?? ''));
        $firmName = mb_substr(trim((string) ($input['firm_name'] ?? '')), 0, 255);
        $ownerName = mb_substr(trim((string) ($account['name'] ?? '')), 0, 255);
        $email = mb_strtolower(trim((string) ($account['email'] ?? '')));
        $accountId = (int) ($account['id'] ?? 0);

        if ($accountId <= 0 || $email === '') {
            throw new \InvalidArgumentException('Creating a workspace needs a signed-in tools account.');
        }

        $db = Database::conn();
        $db->beginTransaction();

        try {
            // The UNIQUE index on slug is the real arbiter. problems() checked
            // availability a moment ago, but two people can pick the same
            // address in the same second and only the database can settle it.
            $db->prepare(
                "INSERT INTO pl_tenants (slug, name, status, plan, billing_status)
                 VALUES (:slug, :name, 'trial', 'trial', 'trialing')"
            )->execute(['slug' => $slug, 'name' => $firmName]);

            $tenantId = (int) $db->lastInsertId();

            // Through the repository, because pl_users is tenant-owned and
            // that rule has no exceptions.
            $users = new UserRepository($tenantId);

            $userId = $users->create([
                'email'         => $email,
                'name'          => $ownerName,
                'role'          => 'firm_owner',
                'status'        => 'active',
                'client_org_id' => null,
            ]);

            $users->attachAccount($userId, $accountId);

            // Starts the clock that Entitlements reads. Nothing expires while
            // BILLING_BETA is on, but the date has to be there for the day it
            // is switched off.
            Billing::startTrial($tenantId);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // No handoff link any more: the owner is already signed in to the
        // shared account on this same host, so the caller starts the Pilotage
        // session directly and sends them in.
        return [
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
            'slug'      => $slug,
            'entry_url' => tenant_url('/2fa/setup', $slug),
        ];
    }

    /** Is this address free to take? Reserved and taken are both "no". */
    /**
     * Every firm a tools account is an active member of: slug, name, role.
     *
     * Deliberately not tenant-scoped, and the one such read of pl_users: it is
     * scoped to the ACCOUNT instead, so it can only ever return the caller's
     * own memberships. It answers "where can I go", which is a question about
     * a person, not about a firm.
     *
     * @return array<int,array{slug:string,name:string,role:string}>
     */
    public static function memberships(int $accountId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT t.slug, t.name, u.role
             FROM pl_users u
             JOIN pl_tenants t ON t.id = u.tenant_id
             WHERE u.account_id = :aid AND u.status = 'active' AND t.status IN ('trial', 'active')
             ORDER BY t.name"
        );
        $stmt->execute(['aid' => $accountId]);

        return $stmt->fetchAll();
    }

    public static function slugAvailable(string $raw): bool
    {
        if (self::looksPunycode($raw)) {
            return false;
        }

        $slug = self::normaliseSlug($raw);

        return $slug !== ''
            && Tenant::isValidSlug($slug)
            && !in_array($slug, Tenant::RESERVED_SLUGS, true)
            && !self::slugTaken($slug);
    }

    /**
     * Does the RAW input look like an internationalised domain label?
     *
     * `Tenant::isValidSlug` rejects the `xn--` ACE prefix, but it never sees it
     * here: normalisation collapses runs of separators, so `xn--80ak6aa92e`
     * arrives as `xn-80ak6aa92e` — a single hyphen, no longer an ACE prefix,
     * and perfectly valid. The stored address would be harmless (no resolver
     * or browser decodes a single-hyphen label as Unicode), so this is not a
     * live vulnerability. It is checked anyway because the *intent* — we are
     * not ready to reason about homograph attacks on tenant addresses — should
     * not survive only as a side effect of a regex somewhere else. Change the
     * normaliser and the guarantee would quietly disappear.
     */
    private static function looksPunycode(string $raw): bool
    {
        return str_starts_with(mb_strtolower(trim($raw)), 'xn--');
    }

    /**
     * A first guess at an address, from the practice name.
     *
     * Only ever a suggestion — the field stays editable, because a firm's own
     * sense of its short name beats anything derived from punctuation.
     */
    public static function suggestSlug(string $firmName): string
    {
        $slug = self::normaliseSlug($firmName);

        // Trailing legal furniture reads as noise in a URL.
        $slug = preg_replace('/-(llc|inc|ltd|limited|llp|plc|co|corp|group)$/', '', $slug) ?? $slug;
        $slug = trim($slug, '-');

        return mb_substr($slug, 0, 63);
    }

    /**
     * Lowercase, hyphenate, strip anything a DNS label cannot carry.
     *
     * Runs on user input before every check, so what gets validated is exactly
     * what would get stored.
     */
    public static function normaliseSlug(string $raw): string
    {
        $slug = mb_strtolower(trim($raw));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return mb_substr($slug, 0, 63);
    }

    /**
     * Spam and abuse checks, before any validation work.
     *
     * Mirrors Intake: a honeypot, a fill-time floor, and a rate limit. Returns
     * a reason string when the submission should be dropped, or null to carry
     * on. Bots are never told which check caught them.
     *
     * @param array<string,mixed> $input
     */
    public static function rejectReason(array $input, ?string $ip): ?string
    {
        if (trim((string) ($input[self::HONEYPOT_FIELD] ?? '')) !== '') {
            return 'honeypot';
        }

        $renderedAt = (int) ($input['_t'] ?? 0);

        if ($renderedAt > 0 && (time() - $renderedAt) < self::MIN_SECONDS) {
            return 'too_fast';
        }

        if ($ip !== null && RateLimiter::tooManyAttempts(self::ACTION, $ip, $ip)) {
            return 'rate_limited';
        }

        return null;
    }

    private static function slugTaken(string $slug): bool
    {
        $stmt = Database::conn()->prepare('SELECT 1 FROM pl_tenants WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() !== false;
    }
}
