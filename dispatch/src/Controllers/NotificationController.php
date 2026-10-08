<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\Auth;
use Dispatch\Models\Notification;

class NotificationController extends BaseController
{
    public function index(array $params = []): void
    {
        $userId        = Auth::userId();
        $notifications = Notification::findByUser($userId, 30);

        // Mark all as read now that the user is viewing the list
        Notification::markAllRead($userId);

        $this->render('notifications/index', [
            'title'         => 'Notifications',
            'notifications' => $notifications,
        ]);
    }

    public function markAllRead(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/notifications');
        }

        Notification::markAllRead(Auth::userId());
        $this->redirect('/notifications');
    }
}
