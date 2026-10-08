<?php

namespace Anglerfish\Controllers;

/**
 * No landing page by design (SPEC §4): the root goes to the dashboard. Signing
 * in and out belongs to the shared tools account (/account/*).
 */
final class AuthController
{
    public function index(): void
    {
        redirect('/dashboard');
    }

    /** The shared account's own sign-out page asks once more and does it. */
    public function logout(): void
    {
        header('Location: /account/logout.php');
        exit;
    }
}
