<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\Auth;
use Dispatch\Core\Request;
use Dispatch\Models\User;

class ProfileController extends BaseController
{
    public function show(array $params = []): void
    {
        $user = Auth::user();
        $this->render('profile/show', [
            'title' => 'My Profile',
            'user'  => $user,
        ]);
    }

    public function update(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/profile');
        }

        $userId       = Auth::userId();
        $notif        = Request::post('notification_preference', 'daily');
        $remindDays   = (int) Request::post('remind_days_before', 3);

        // Name, email and password live on the shared account (/account/settings.php).
        User::update($userId, [
            'notification_preference' => in_array($notif, ['daily', 'weekly', 'none'], true) ? $notif : 'daily',
            'remind_days_before'      => in_array($remindDays, [1, 2, 3, 5, 7, 14], true) ? $remindDays : 3,
        ]);

        $this->flash('success', 'Profile updated.');
        $this->redirect('/profile');
    }
}
