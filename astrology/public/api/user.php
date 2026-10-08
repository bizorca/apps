<?php
/**
 * GET /api/user
 * Returns the authenticated user's info and plan.
 */
define('AS_API', true);
require dirname(__DIR__) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';

['user' => $user, 'entitlement' => $entitlement] = requireAPIAuth();

jsonResponse([
    'id'                 => $user['id'],
    'name'               => $user['name'],
    'email'              => $user['email'],
    'plan'               => apiHasAccess($entitlement) ? 'premium' : 'free',
    'primary_profile_id' => $user['primary_profile_id'],
]);
