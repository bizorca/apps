<?php

declare(strict_types=1);

/**
 * Who is calling a controller.
 *
 * The original issued HS256 JWTs. The port issues opaque random bearer tokens
 * stored hashed in pc_api_tokens (30-day life, like the old jwt_ttl), so a
 * token can be revoked and there is no signing secret to keep. The iOS app
 * treats the token as an opaque string, so nothing changes for it.
 *
 * Two callers:
 *   - the JSON API: requireAuth() reads "Authorization: Bearer <token>"; no
 *     cookies are read or set
 *   - the web pages: api() sets Auth::$webUserId to the signed-in shared
 *     account before dispatching, and requireAuth() returns it
 *
 * requireAuth() returns the shared users.id (int). Ids that leave the API are
 * the profile's public_id (the original user UUID), never users.id.
 */
class Auth
{
    public const TOKEN_TTL = 60 * 60 * 24 * 30;   // 30 days, as the JWTs were

    public static ?int $webUserId = null;

    public static function issueToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        tl_db()->prepare(
            'INSERT INTO pc_api_tokens (token_hash, user_id, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
        )->execute([hash('sha256', $token), $userId, self::TOKEN_TTL]);
        return $token;
    }

    public function requireAuth(): int
    {
        if (self::$webUserId !== null) {
            return self::$webUserId;
        }

        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) {
                    $header = (string) $v;
                }
            }
        }
        if (!str_starts_with($header, 'Bearer ')) {
            Response::unauthorized('Missing or malformed Authorization header');
        }

        $stmt = tl_db()->prepare(
            'SELECT t.user_id FROM pc_api_tokens t
               JOIN pc_profiles p ON p.user_id = t.user_id
              WHERE t.token_hash = ? AND t.expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([hash('sha256', substr($header, 7))]);
        $userId = $stmt->fetchColumn();
        if ($userId === false) {
            Response::unauthorized('Invalid or expired token');
        }

        return (int) $userId;
    }
}
