<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\Auth;
use Dispatch\Core\Url;

/**
 * Sign-in, registration and password resets belong to the shared tools account
 * (/account/). Dispatch's old auth routes stay as doors into it, so marketing
 * links and old bookmarks still land somewhere sensible, and each one comes
 * back to the Dispatch dashboard afterwards.
 */
class AuthController extends BaseController
{
    private function toAccount(string $page): never
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        header('Location: /account/' . $page . '?next=' . rawurlencode(Url::to('/dashboard')));
        exit;
    }

    public function showLogin(array $params = []): void      { $this->toAccount('login.php'); }
    public function showRegister(array $params = []): void   { $this->toAccount('register.php'); }
    public function showForgot(array $params = []): void     { $this->toAccount('forgot.php'); }

    /** The sidebar's Log out button posts here; it signs out of every tool. */
    public function logout(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/dashboard');
        }
        tl_logout();
        $this->redirect('/');
    }
}
