<?php

namespace App\Middleware;

use App\Core\App;

/**
 * Signed in with the shared tools account? The original sent visitors to the
 * Bizorca SSO authorize endpoint; tl_require_login() sends them to
 * /account/login.php and brings them back to the page they asked for.
 */
class AuthMiddleware
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function handle(): bool
    {
        tl_require_login();
        $this->app->owner();   // first visit: create the br_owners row
        return true;
    }
}
