<?php

declare(strict_types=1);

/**
 * The original api/index.php route table, as a function that ends by throwing
 * a PcResponse (see Response.php). Routes, methods and messages are unchanged.
 *
 * PcRequest::$body is the decoded JSON body when the web pages call in-process;
 * the API leaves it null and controllers read php://input as before.
 */
final class PcRequest
{
    public static ?array $body = null;

    public static function json(): array
    {
        if (self::$body !== null) {
            return self::$body;
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) {
            Response::error('Request body must be valid JSON');
        }
        return $data;
    }
}

function pc_route(string $method, string $uri): never
{
    $db   = tl_db();
    $auth = new Auth();

    // ---- Auth -------------------------------------------------------
    if ($uri === '/auth/register' && $method === 'POST') {
        (new AuthController($db, $auth))->register();
    }

    if ($uri === '/auth/login' && $method === 'POST') {
        (new AuthController($db, $auth))->login();
    }

    // ---- Cities -----------------------------------------------------
    if ($uri === '/cities' && $method === 'GET') {
        (new CityController($db))->index();
    }

    // ---- Restaurants ------------------------------------------------
    if ($uri === '/restaurants' && $method === 'GET') {
        (new RestaurantController($db))->index();
    }

    if (preg_match('#^/restaurants/([^/]+)$#', $uri, $m) && $method === 'GET') {
        (new RestaurantController($db))->show($m[1]);
    }

    // ---- Events -----------------------------------------------------
    if ($uri === '/events' && $method === 'GET') {
        (new EventController($db, $auth))->index();
    }

    if ($uri === '/events' && $method === 'POST') {
        (new EventController($db, $auth))->create();
    }

    if (preg_match('#^/events/([^/]+)$#', $uri, $m) && $method === 'GET') {
        (new EventController($db, $auth))->show($m[1]);
    }

    if (preg_match('#^/events/([^/]+)$#', $uri, $m) && $method === 'DELETE') {
        (new EventController($db, $auth))->delete($m[1]);
    }

    if (preg_match('#^/events/([^/]+)/rsvp$#', $uri, $m) && $method === 'POST') {
        (new EventController($db, $auth))->rsvp($m[1]);
    }

    if (preg_match('#^/events/([^/]+)/rsvp$#', $uri, $m) && $method === 'DELETE') {
        (new EventController($db, $auth))->cancelRsvp($m[1]);
    }

    // ---- Users ------------------------------------------------------
    if ($uri === '/users/me' && $method === 'GET') {
        (new UserController($db, $auth))->me();
    }

    if ($uri === '/users/me' && $method === 'PUT') {
        (new UserController($db, $auth))->update();
    }

    if ($uri === '/users/me/events' && $method === 'GET') {
        (new UserController($db, $auth))->myEvents();
    }

    // ---- Health check -----------------------------------------------
    if ($uri === '/health' && $method === 'GET') {
        Response::success(['status' => 'ok', 'timestamp' => date('c')]);
    }

    // ---- Root -------------------------------------------------------
    if (($uri === '/' || $uri === '') && $method === 'GET') {
        Response::success([
            'name'    => 'Placecard API',
            'version' => '1.0',
            'status'  => 'ok',
            'endpoints' => [
                'POST /api/auth/register',
                'POST /api/auth/login',
                'GET  /api/cities',
                'GET  /api/restaurants?city_id=',
                'GET  /api/events?city_id=',
                'POST /api/events',
                'GET  /api/events/{id}',
                'POST /api/events/{id}/rsvp',
                'DELETE /api/events/{id}/rsvp',
                'GET  /api/users/me',
                'PUT  /api/users/me',
                'GET  /api/users/me/events',
            ],
        ]);
    }

    // ---- 404 --------------------------------------------------------
    Response::notFound("No route for $method $uri");
}
