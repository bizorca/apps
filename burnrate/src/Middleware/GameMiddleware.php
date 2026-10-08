<?php

namespace App\Middleware;

use App\Core\App;
use App\Core\Helpers;

class GameMiddleware
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function handle(): bool
    {
        // The original redirected to /login here, a route that never existed.
        tl_require_login();
        $this->app->owner();

        if (!$this->app->playerId()) {
            $this->app->session->flash('error', 'Please select a player first.');
            Helpers::redirect('/account');
            return false;
        }

        return true;
    }
}
