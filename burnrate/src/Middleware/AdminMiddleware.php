<?php

namespace App\Middleware;

use App\Core\App;
use App\Core\Helpers;

/**
 * Burn Rate admin = a site admin (users.is_admin) or br_owners.IsAdmin.
 *
 * The original compared MemberLevel to admin_member_level (65536). Finishing a
 * round raises MemberLevel one tier, so five completed games made any player
 * an admin, with the settings page and user creation that come with it.
 */
class AdminMiddleware
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function handle(): bool
    {
        tl_require_login();

        if (!$this->app->isAdmin()) {
            $this->app->session->flash('error', 'You do not have permission to access the admin area.');
            Helpers::redirect('/account');
            return false;
        }

        return true;
    }
}
