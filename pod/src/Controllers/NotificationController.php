<?php

namespace Bizorca\Pod\Controllers;

use Bizorca\Pod\Auth\Session;
use Bizorca\Pod\Core\Database;

class NotificationController
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

        $user = Session::user();

        $notifications = Database::fetchAll(
            'SELECT * FROM pd_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50',
            [$user['id']]
        );

        // Mark all read
        Database::query('UPDATE pd_notifications SET is_read = 1 WHERE user_id = ?', [$user['id']]);

        render('notifications/index', compact('notifications', 'user'));
    }
}
