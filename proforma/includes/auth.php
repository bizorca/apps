<?php
declare(strict_types=1);

/*
 * Proforma's auth, now a thin layer over the shared tools account
 * (private_html/includes/auth.php). Sign-in, registration and sign-out live at
 * /account/; Proforma's own login, register, logout and SSO pages are gone.
 * The function names stay so the pages did not need rewriting.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function startSession(): void {
    tl_session();   // resume only; never mints a cookie for an anonymous visitor (share links)
}

function requireAuth(): void {
    tl_require_login();
}

/** The signed-in user in the shape Proforma's pages expect, or null. */
function currentUser(): ?array {
    $u = tl_user();
    if (!$u) return null;
    return [
        'id'         => (int)$u['id'],
        'email'      => $u['email'],
        'name'       => $u['name'],
        'is_admin'   => (int)$u['is_admin'],
        'created_at' => $u['created_at'],
    ];
}

/** The signed-in user's id, or 0. Replaces reads of $_SESSION['user_id']. */
function currentUserId(): int {
    return (int)(tl_user()['id'] ?? 0);
}
