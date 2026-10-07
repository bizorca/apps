<?php
declare(strict_types=1);

/*
 * Kit's auth, now a thin layer over the shared tools account
 * (private_html/includes/auth.php). Sign-in, registration and sign-out live at
 * /account/; Kit's own login, logout and SSO pages are gone. The function names
 * stay so the pages did not need rewriting.
 *
 * Access is unchanged from kit.bizorca.com, where any Bizorca account on the
 * free plan got in: any tools account can use Kit. Clients stay private to the
 * advisor who created them, because every accessor in db.php filters on
 * user_id.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Resume an existing session only; anonymous visitors get no cookie. */
function startSession(): void
{
    tl_session();
}

/** The signed-in user in the shape Kit's pages expect, or null. */
function currentUser(): ?array
{
    $u = tl_user();
    if (!$u) return null;
    return [
        'id'         => (int) $u['id'],
        'email'      => $u['email'],
        'name'       => $u['name'],
        'is_admin'   => (int) $u['is_admin'],
        'created_at' => $u['created_at'],
    ];
}

function requireAuth(): array
{
    tl_require_login();
    return currentUser();
}

function userDisplayName(?array $user): string
{
    if (!$user) return '';
    $name = trim((string) ($user['name'] ?? ''));
    return $name !== '' ? $name : (string) ($user['email'] ?? '');
}

/** Shared sign-in / create-account URLs that come back to Kit afterwards. */
function kitLoginUrl(): string
{
    return '/account/login.php?next=' . rawurlencode(url('/dashboard.php'));
}

function kitRegisterUrl(): string
{
    return '/account/register.php?next=' . rawurlencode(url('/dashboard.php'));
}
