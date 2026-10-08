<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

use Bizorca\Pilotage\Core\Database;

/**
 * Server-side session records (FR-2.4).
 *
 * PHP's own session holds one thing: our session id. Everything authoritative
 * — who you are, which tenant, when it expires — lives in pl_user_sessions, so a
 * session can be revoked server-side, listed back to the user, and audited.
 *
 * Two clocks:
 *   - absolute: 30 days from creation, for everyone
 *   - idle:     12 hours since last activity, firm-side users only
 *
 * The asymmetry is deliberate. Firm-side staff hold the keys to every client
 * in the book, so a forgotten laptop matters more. A business owner checking
 * their commitments once a week should not be logged out for the crime of
 * having a life.
 */
final class Session
{
    public const ABSOLUTE_LIFETIME = 30 * 24 * 3600;  // 30 days
    public const IDLE_LIFETIME     = 12 * 3600;       // 12 hours, firm-side only

    /**
     * $_SESSION[KEY][tenant_id] = session row id.
     *
     * On tools.bizorca.com one PHP session (the shared `tools_session` cookie)
     * serves every tool and every firm, so Pilotage keeps one row id PER FIRM
     * under its own key. Before, a cookie scoped to the firm's subdomain did
     * that separation; the per-tenant key and the tenant check in current()
     * now do it.
     */
    private const KEY = 'pl_sid';

    /** @var array<string,mixed>|null Request-scoped cache. */
    private static ?array $cached = null;
    private static bool $loaded = false;

    /**
     * Begin an authenticated session.
     *
     * @return string The session id.
     */
    public static function start(
        array $user,
        int $tenantId,
        ?string $ip = null,
        ?string $userAgent = null,
        ?int $impersonatorId = null
    ): string {
        $id = bin2hex(random_bytes(32));

        $isFirmSide = \Bizorca\Pilotage\Repositories\UserRepository::isFirmSide((string) $user['role']);

        $stmt = Database::conn()->prepare(
            'INSERT INTO pl_user_sessions
                (id, tenant_id, user_id, ip, user_agent, is_firm_side, impersonator_id, expires_at)
             VALUES (:id, :tid, :uid, :ip, :ua, :fs, :imp, :exp)'
        );

        $stmt->execute([
            'id'  => $id,
            'tid' => $tenantId,
            'uid' => (int) $user['id'],
            'ip'  => $ip === null ? null : RateLimiter::packIp($ip),
            'ua'  => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
            'fs'  => $isFirmSide ? 1 : 0,
            'imp' => $impersonatorId,
            'exp' => date('Y-m-d H:i:s', time() + self::ABSOLUTE_LIFETIME),
        ]);

        // The shared session already exists (the person is signed in to their
        // tools account); make sure it is open before writing to it.
        if (function_exists('tl_session')) {
            tl_session(true);
        }

        // New session id on privilege change — defeats fixation.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        if (!isset($_SESSION[self::KEY]) || !is_array($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [];
        }
        $_SESSION[self::KEY][$tenantId] = $id;
        Csrf::rotate();

        self::$cached = null;
        self::$loaded = false;

        return $id;
    }

    /**
     * The current session record, or null.
     *
     * Validates both clocks, and touches last_seen_at at most once a minute so
     * a busy page does not write on every request.
     */
    public static function current(): ?array
    {
        if (self::$loaded) {
            return self::$cached;
        }

        self::$loaded = true;
        self::$cached = null;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $tenantId = \Bizorca\Pilotage\Core\Tenant::currentId();
        if ($tenantId === null) {
            return null;
        }

        $id = $_SESSION[self::KEY][$tenantId] ?? null;
        if (!is_string($id) || strlen($id) !== 64 || !ctype_xdigit($id)) {
            return null;
        }

        // The row must belong to the firm this request is for. The per-tenant
        // key already implies it; checking it in SQL means a tampered or
        // misfiled id can never carry one firm's session into another.
        $stmt = Database::conn()->prepare(
            'SELECT s.*, u.email, u.name, u.role, u.status, u.client_org_id, u.account_id,
                    imp.account_id AS impersonator_account_id
             FROM pl_user_sessions s
             JOIN pl_users u ON u.id = s.user_id AND u.tenant_id = s.tenant_id
             LEFT JOIN pl_users imp ON imp.id = s.impersonator_id AND imp.tenant_id = s.tenant_id
             WHERE s.id = :id AND s.tenant_id = :tid LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tid' => $tenantId]);
        $row = $stmt->fetch();

        if ($row === false) {
            unset($_SESSION[self::KEY][$tenantId]);
            return null;
        }

        /**
         * Bound to the shared account. A Pilotage session is only good while
         * the browser is signed in to the tools account the membership points
         * at — the person who acts, which during impersonation is the staff
         * member doing it. Signing out of the tools account, or a different
         * account signing in, ends every firm session at once.
         */
        $account = function_exists('tl_user') ? tl_user() : null;
        $expected = $row['impersonator_id'] !== null
            ? $row['impersonator_account_id']
            : $row['account_id'];

        if ($account === null || $expected === null || (int) $expected !== (int) $account['id']) {
            self::revoke($id);
            unset($_SESSION[self::KEY][$tenantId]);
            return null;
        }

        if ($row['revoked_at'] !== null) {
            return null;
        }

        // The user may have been disabled since the session started.
        if ($row['status'] !== 'active') {
            self::revoke($id);
            return null;
        }

        $now = time();

        if (strtotime((string) $row['expires_at']) <= $now) {
            self::revoke($id);
            return null;
        }

        if ((int) $row['is_firm_side'] === 1) {
            $idleSince = $now - strtotime((string) $row['last_seen_at']);
            if ($idleSince > self::IDLE_LIFETIME) {
                self::revoke($id);
                return null;
            }
        }

        // Impersonation is capped at 60 minutes regardless of session clocks.
        if ($row['impersonator_id'] !== null && !Impersonation::isActive((string) $id)) {
            self::revoke($id);
            return null;
        }

        if ($now - strtotime((string) $row['last_seen_at']) > 60) {
            $touch = Database::conn()->prepare('UPDATE pl_user_sessions SET last_seen_at = NOW() WHERE id = :id');
            $touch->execute(['id' => $id]);
        }

        self::$cached = $row;

        return $row;
    }

    /** The authenticated user row, or null. */
    public static function user(): ?array
    {
        $session = self::current();

        if ($session === null) {
            return null;
        }

        return [
            'id'            => (int) $session['user_id'],
            'tenant_id'     => (int) $session['tenant_id'],
            'client_org_id' => $session['client_org_id'] === null ? null : (int) $session['client_org_id'],
            'email'         => $session['email'],
            'name'          => $session['name'],
            'role'          => $session['role'],
            'status'        => $session['status'],
        ];
    }

    public static function isImpersonated(): bool
    {
        $session = self::current();
        return $session !== null && $session['impersonator_id'] !== null;
    }

    public static function revoke(string $id): void
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_user_sessions SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        self::$cached = null;
        self::$loaded = false;
    }

    /** Log out here. */
    public static function destroy(): void
    {
        // Revokes every Pilotage session this browser holds, across every
        // firm. It does NOT end the shared tools session; the logout route
        // does that separately, because signing out of Pilotage on a shared
        // site means signing out of the site.
        if (session_status() === PHP_SESSION_ACTIVE) {
            foreach ((array) ($_SESSION[self::KEY] ?? []) as $id) {
                if (is_string($id)) {
                    self::revoke($id);
                }
            }
            unset($_SESSION[self::KEY]);
        }

        self::$cached = null;
        self::$loaded = false;
    }

    /** Log out everywhere. Used on password change and on admin disable. */
    public static function revokeAllForUser(int $tenantId, int $userId): int
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_user_sessions SET revoked_at = NOW()
             WHERE tenant_id = :tid AND user_id = :uid AND revoked_at IS NULL'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);

        return $stmt->rowCount();
    }

    /** @return array<int,array<string,mixed>> Active sessions, for a "where am I signed in" screen. */
    public static function activeForUser(int $tenantId, int $userId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT id, ip, user_agent, created_at, last_seen_at, expires_at
             FROM pl_user_sessions
             WHERE tenant_id = :tid AND user_id = :uid AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY last_seen_at DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);

        return $stmt->fetchAll();
    }

    /** Housekeeping for the cron tick. */
    public static function prune(): int
    {
        $stmt = Database::conn()->prepare(
            'DELETE FROM pl_user_sessions
             WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
                OR (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 7 DAY))'
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    /** Test seam. */
    public static function resetCache(): void
    {
        self::$cached = null;
        self::$loaded = false;
    }
}
