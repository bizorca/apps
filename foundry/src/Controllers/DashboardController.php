<?php

namespace Bizorca\Consulting\Controllers;

use Bizorca\Consulting\Models\Application;
use Bizorca\Consulting\Models\Engagement;

class DashboardController
{
    public function index(): void
    {
        require_auth();
        $user = current_user();

        $engagements = Engagement::forClient($user['id']);
        $application = Application::findByUser($user['id']);

        render('dashboard/client', compact('user', 'engagements', 'application'));
    }
}
