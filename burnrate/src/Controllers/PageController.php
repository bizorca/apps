<?php

namespace App\Controllers;

class PageController extends BaseController
{
    public function home(): void
    {
        if ($this->app->isLoggedIn()) {
            $this->redirect('/account');
            return;
        }
        $this->render('pages/home');
    }

    public function about(): void
    {
        $this->render('pages/about');
    }

    public function features(): void
    {
        $this->render('pages/features');
    }

    /** Old /login and /sso/callback addresses: the shared sign-in, back to the account page. */
    public function signIn(): void
    {
        header('Location: /account/login.php?next=' . rawurlencode(url('/account')));
        exit;
    }

    /** The marketing CTAs linked /register, a route the original never had (404). */
    public function register(): void
    {
        header('Location: /account/register.php?next=' . rawurlencode(url('/account')));
        exit;
    }

    /** Signing out of Burn Rate signs out of every tool; the shared page confirms first. */
    public function signOut(): void
    {
        header('Location: /account/logout.php');
        exit;
    }

    public function pricing(): void
    {
        $this->render('pages/pricing', [
            'tiers' => $this->app->config['membership'],
        ]);
    }
}
