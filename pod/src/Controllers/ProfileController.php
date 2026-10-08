<?php

namespace Bizorca\Pod\Controllers;

use Bizorca\Pod\Auth\Session;
use Bizorca\Pod\Core\Database;

class ProfileController
{
    private function requireAuth(): void
    {
        if (!Session::isLoggedIn()) {
            tl_require_login();
        }
    }

    public function show(string $userId): void
    {
        $this->requireAuth();

        $target = Database::fetchOne(
            'SELECT id, first_name, last_name, bio, avatar_url, created_at FROM pd_users WHERE id = ? AND is_active = 1',
            [$userId]
        );

        if (!$target) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Member not found.']);
            return;
        }

        $user = Session::user();

        $posts = Database::fetchAll(
            'SELECT fp.id, fp.title, fp.created_at, fc.name AS category_name, fc.slug AS category_slug
             FROM pd_forum_posts fp
             JOIN pd_forum_categories fc ON fc.id = fp.category_id
             WHERE fp.user_id = ?
             ORDER BY fp.created_at DESC
             LIMIT 10',
            [$userId]
        );

        render('profile/show', compact('target', 'user', 'posts'));
    }

    public function edit(): void
    {
        $this->requireAuth();

        $user    = Session::user();
        $profile = Database::fetchOne('SELECT * FROM pd_users WHERE id = ?', [$user['id']]);

        render('profile/edit', [
            'user'    => $user,
            'profile' => $profile,
            'error'   => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ]);
    }

    public function update(): void
    {
        $this->requireAuth();
        csrf_verify();

        $user      = Session::user();
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $bio       = trim($_POST['bio'] ?? '');
        $avatarUrl = trim($_POST['avatar_url'] ?? '');

        if (!$firstName) {
            Session::flash('error', 'First name is required.');
            redirect(url('profile/edit'));
            return;
        }

        // Only http(s): the original accepted any string, javascript: included,
        // and printed it as an <img src>.
        if ($avatarUrl !== '' && safe_url($avatarUrl) === '') {
            Session::flash('error', 'The photo URL must start with http:// or https://.');
            redirect(url('profile/edit'));
        }

        // The name is the shared tools account's (one name for every tool).
        Database::query('UPDATE users SET name = ? WHERE id = ?', [trim($firstName . ' ' . $lastName), $user['id']]);
        Database::query(
            'UPDATE pd_profiles SET bio = ?, avatar_url = ? WHERE user_id = ?',
            [$bio, $avatarUrl, $user['id']]
        );

        Session::flash('success', 'Profile updated.');
        redirect(url('profile/edit'));
    }
}
