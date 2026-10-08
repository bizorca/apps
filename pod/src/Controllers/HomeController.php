<?php

namespace Bizorca\Pod\Controllers;

use Bizorca\Pod\Auth\Session;
use Bizorca\Pod\Core\Database;

class HomeController
{
    public function index(): void
    {
        if (!Session::isLoggedIn()) {
            tl_require_login();
        }

        $user = Session::user();

        // Recent forum posts
        $recentPosts = Database::fetchAll(
            'SELECT fp.*, u.first_name, u.last_name, fc.name AS category_name, fc.slug AS category_slug
             FROM pd_forum_posts fp
             JOIN pd_users u ON u.id = fp.user_id
             JOIN pd_forum_categories fc ON fc.id = fp.category_id
             ORDER BY fp.created_at DESC
             LIMIT 5'
        );

        // Upcoming events
        $upcomingEvents = Database::fetchAll(
            'SELECT * FROM pd_events WHERE is_published = 1 AND starts_at >= NOW() ORDER BY starts_at ASC LIMIT 3'
        );

        // Latest courses
        $courses = Database::fetchAll(
            'SELECT c.*, COUNT(l.id) AS lesson_count
             FROM pd_courses c
             LEFT JOIN pd_lessons l ON l.course_id = c.id AND l.is_published = 1
             WHERE c.is_published = 1
             GROUP BY c.id
             ORDER BY c.sort_order ASC
             LIMIT 3'
        );

        render('home', compact('user', 'recentPosts', 'upcomingEvents', 'courses'));
    }
}
