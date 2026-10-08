<?php

namespace Bizorca\Pod\Controllers;

use Bizorca\Pod\Auth\Session;
use Bizorca\Pod\Core\Database;

class MemberController
{
    private function requireAuth(): void
    {
        if (!Session::isLoggedIn()) {
            tl_require_login();
        }
    }

    public function index(): void
    {
        $this->requireAuth();

        $members = Database::fetchAll(
            // The original joined user_app_admins (the login server's per-app
            // ADMIN table, empty on live), so the directory was always empty.
            'SELECT id, first_name, last_name, bio, avatar_url, created_at
             FROM pd_users
             WHERE is_active = 1
             ORDER BY first_name ASC'
        );

        $user = Session::user();
        render('members/index', compact('members', 'user'));
    }
}
