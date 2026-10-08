<?php
declare(strict_types=1);

/**
 * The original web pages called the API over HTTP with curl and a JWT. They
 * now call the same controllers in-process: same route table, same response
 * envelope, no network hop. A non-null $token means "as the signed-in shared
 * account" (getToken() returns a placeholder); null means anonymous, as for
 * the original's auth/* calls.
 */
function api(string $method, string $endpoint, array $data = [], ?string $token = null): array
{
    $path  = '/' . ltrim($endpoint, '/');
    $query = [];
    if (($q = strpos($path, '?')) !== false) {
        parse_str(substr($path, $q + 1), $query);
        $path = substr($path, 0, $q);
    }

    $savedGet  = $_GET;
    $savedBody = PcRequest::$body;
    $savedUser = Auth::$webUserId;

    $_GET            = $query;
    PcRequest::$body = $data;
    $u               = $token !== null ? tl_user() : null;
    Auth::$webUserId = $u ? (int) $u['id'] : null;

    try {
        pc_route(strtoupper($method), $path);
    } catch (PcResponse $r) {
        return $r->body;
    } catch (PDOException $e) {
        error_log('Placecard DB error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    } finally {
        $_GET            = $savedGet;
        PcRequest::$body = $savedBody;
        Auth::$webUserId = $savedUser;
    }
}
