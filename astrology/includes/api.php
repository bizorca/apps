<?php
/**
 * API helpers for the Meridian mobile app.
 *
 * Include this (after config.php) in every public/api/ endpoint.
 * Provides: jsonResponse(), jsonError(), requireAPIAuth()
 */

// Always respond with JSON content type
header('Content-Type: application/json');

// Disable HTML error output for API routes
ini_set('display_errors', 0);

/**
 * Send a JSON success response and exit.
 */
function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Send a JSON error response and exit.
 */
function jsonError(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

/**
 * Validate the Bearer token from the Authorization header.
 * Returns ['user' => [...], 'entitlement' => string|null] or calls jsonError(401).
 *
 * Usage at top of any authenticated endpoint:
 *   ['user' => $user, 'entitlement' => $entitlement] = requireAPIAuth();
 */
function apiBearerToken(): ?string {
    // nginx passes Authorization through as HTTP_AUTHORIZATION; fall back to
    // getallheaders() for any server that strips it.
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) { $header = (string) $v; break; }
        }
    }
    return preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : null;
}

function requireAPIAuth(): array {
    $token = apiBearerToken();
    if ($token === null) {
        jsonError('Missing or invalid Authorization header', 401);
    }

    $db = getDB();

    // The person is the shared tools account; primary_profile_id lives in
    // as_members, which may not have a row yet for someone who only used the web.
    $stmt = $db->prepare("
        SELECT t.id AS token_id, t.entitlement AS token_entitlement,
               u.id AS user_id, u.name, u.email,
               m.primary_profile_id
        FROM as_api_tokens t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN as_members m ON m.user_id = u.id
        WHERE t.token = ?
          AND t.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        jsonError('Invalid or expired token', 401);
    }

    $db->prepare("UPDATE as_api_tokens SET last_used_at = NOW() WHERE id = ?")
       ->execute([$row['token_id']]);

    $user = [
        'id'                 => (int) $row['user_id'],
        'name'               => $row['name'],
        'email'              => $row['email'],
        'primary_profile_id' => $row['primary_profile_id'] ? (int) $row['primary_profile_id'] : null,
    ];

    return [
        'user'        => $user,
        'entitlement' => $row['token_entitlement'],
    ];
}

/** A fresh 90-day bearer token for a shared account. */
function issueAPIToken(int $userId): string {
    $db = getDB();
    $db->prepare("DELETE FROM as_api_tokens WHERE user_id = ? AND expires_at < NOW()")->execute([$userId]);
    $token = bin2hex(random_bytes(32));
    $db->prepare(
        "INSERT INTO as_api_tokens (user_id, token, entitlement, expires_at)
         VALUES (?, ?, NULL, DATE_ADD(NOW(), INTERVAL 90 DAY))"
    )->execute([$userId, $token]);
    $db->prepare('INSERT IGNORE INTO as_members (user_id) VALUES (?)')->execute([$userId]);
    return $token;
}

/**
 * Returns true if the entitlement grants premium access.
 */
function apiHasAccess(?string $entitlement): bool {
    return true; // open access — all features free while in beta
}
