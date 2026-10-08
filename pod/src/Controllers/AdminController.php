<?php

namespace Bizorca\Pod\Controllers;

use Bizorca\Pod\Auth\Session;
use Bizorca\Pod\Core\Database;
use Bizorca\Pod\Services\ZoomService;

class AdminController
{
    private function requireAdmin(): void
    {
        if (!Session::isLoggedIn() || !Session::isAdmin()) {
            http_response_code(403);
            render('error', ['code' => 403, 'message' => 'Admin access required.']);
            exit;
        }
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function dashboard(): void
    {
        $this->requireAdmin();

        $stats = [
            'users'        => (int)(Database::fetchOne('SELECT COUNT(*) AS n FROM pd_users')['n'] ?? 0),
            'open_tickets' => (int)(Database::fetchOne('SELECT COUNT(*) AS n FROM pd_tickets WHERE status = \'open\'')['n'] ?? 0),
            'courses'      => (int)(Database::fetchOne('SELECT COUNT(*) AS n FROM pd_courses')['n'] ?? 0),
            'events'       => (int)(Database::fetchOne('SELECT COUNT(*) AS n FROM pd_events WHERE starts_at >= NOW()')['n'] ?? 0),
        ];

        $openTickets = Database::fetchAll(
            'SELECT t.*, u.first_name, u.last_name
             FROM pd_tickets t
             JOIN pd_users u ON u.id = t.user_id
             WHERE t.status != \'closed\'
             ORDER BY FIELD(t.status, \'open\', \'answered\'), t.updated_at DESC
             LIMIT 10'
        );

        $user = Session::user();

        render('admin/dashboard', compact('stats', 'openTickets', 'user'));
    }

    // ─── Courses ─────────────────────────────────────────────────────────────

    public function courses(): void
    {
        $this->requireAdmin();

        $courses = Database::fetchAll(
            'SELECT c.*, COUNT(l.id) AS lesson_count
             FROM pd_courses c
             LEFT JOIN pd_lessons l ON l.course_id = c.id
             GROUP BY c.id
             ORDER BY c.sort_order ASC'
        );

        $user = Session::user();
        render('admin/courses', compact('courses', 'user'));
    }

    public function createCourse(): void
    {
        $this->requireAdmin();
        csrf_verify();

        $title = trim($_POST['title'] ?? '');
        $slug  = trim($_POST['slug'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $thumb = trim($_POST['thumbnail_url'] ?? '');
        $order = (int)($_POST['sort_order'] ?? 0);

        if (!$title || !$slug) {
            Session::flash('error', 'Title and slug are required.');
            redirect(url('admin/courses'));
        }

        // The original let the unique key throw: a duplicate slug was a 500.
        if (Database::fetchOne('SELECT id FROM pd_courses WHERE slug = ?', [$slug])) {
            Session::flash('error', 'A course with that slug already exists.');
            redirect(url('admin/courses'));
        }

        Database::insert(
            'INSERT INTO pd_courses (title, slug, description, thumbnail_url, sort_order) VALUES (?, ?, ?, ?, ?)',
            [$title, $slug, $desc, $thumb, $order]
        );

        redirect(url('admin/courses'));
    }

    public function toggleCourse(string $courseId): void
    {
        $this->requireAdmin();
        csrf_verify();

        Database::query(
            'UPDATE pd_courses SET is_published = NOT is_published WHERE id = ?',
            [$courseId]
        );

        redirect(url('admin/courses'));
    }

    public function deleteCourse(string $courseId): void
    {
        $this->requireAdmin();
        csrf_verify();

        Database::query('DELETE FROM pd_courses WHERE id = ?', [$courseId]);
        redirect(url('admin/courses'));
    }

    // ─── Lessons ─────────────────────────────────────────────────────────────

    public function lessonForm(string $courseId): void
    {
        $this->requireAdmin();

        $course = Database::fetchOne('SELECT * FROM pd_courses WHERE id = ?', [$courseId]);
        if (!$course) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Course not found.']);
            return;
        }

        $lessons = Database::fetchAll(
            'SELECT * FROM pd_lessons WHERE course_id = ? ORDER BY sort_order ASC',
            [$courseId]
        );

        $user = Session::user();
        render('admin/lesson', compact('course', 'lessons', 'user'));
    }

    public function createLesson(string $courseId): void
    {
        $this->requireAdmin();
        csrf_verify();

        $course = Database::fetchOne('SELECT id FROM pd_courses WHERE id = ?', [$courseId]);
        if (!$course) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Course not found.']);
            return;
        }

        $title    = trim($_POST['title'] ?? '');
        $slug     = trim($_POST['slug'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $order    = (int)($_POST['sort_order'] ?? 0);

        if (!$title || !$slug) {
            Session::flash('error', 'Title and slug are required.');
            redirect(url("admin/courses/{$courseId}/lessons"));
        }

        if (Database::fetchOne('SELECT id FROM pd_lessons WHERE course_id = ? AND slug = ?', [$courseId, $slug])) {
            Session::flash('error', 'This course already has a lesson with that slug.');
            redirect(url("admin/courses/{$courseId}/lessons"));
        }

        $embed = $videoUrl ? parseVideoEmbed($videoUrl) : '';

        Database::insert(
            'INSERT INTO pd_lessons (course_id, title, slug, video_embed, content, sort_order, is_published) VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$courseId, $title, $slug, $embed, $content, $order]
        );

        redirect(url("admin/courses/{$courseId}/lessons"));
    }

    public function deleteLesson(string $lessonId): void
    {
        $this->requireAdmin();
        csrf_verify();

        $lesson = Database::fetchOne('SELECT course_id FROM pd_lessons WHERE id = ?', [$lessonId]);

        if (!$lesson) {
            redirect(url('admin/courses'));
            return;
        }

        Database::query('DELETE FROM pd_lessons WHERE id = ?', [$lessonId]);

        redirect(url('admin/courses/' . $lesson['course_id'] . '/lessons'));
    }

    public function moveLessonUp(string $lessonId): void
    {
        $this->requireAdmin();
        csrf_verify();

        $lesson = Database::fetchOne('SELECT * FROM pd_lessons WHERE id = ?', [$lessonId]);
        if (!$lesson) {
            redirect(url('admin/courses'));
            return;
        }

        $prev = Database::fetchOne(
            'SELECT * FROM pd_lessons WHERE course_id = ? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1',
            [$lesson['course_id'], $lesson['sort_order']]
        );

        if ($prev) {
            Database::query('UPDATE pd_lessons SET sort_order = ? WHERE id = ?', [$prev['sort_order'], $lessonId]);
            Database::query('UPDATE pd_lessons SET sort_order = ? WHERE id = ?', [$lesson['sort_order'], $prev['id']]);
        }

        redirect(url("admin/courses/{$lesson['course_id']}/lessons"));
    }

    public function moveLessonDown(string $lessonId): void
    {
        $this->requireAdmin();
        csrf_verify();

        $lesson = Database::fetchOne('SELECT * FROM pd_lessons WHERE id = ?', [$lessonId]);
        if (!$lesson) {
            redirect(url('admin/courses'));
            return;
        }

        $next = Database::fetchOne(
            'SELECT * FROM pd_lessons WHERE course_id = ? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1',
            [$lesson['course_id'], $lesson['sort_order']]
        );

        if ($next) {
            Database::query('UPDATE pd_lessons SET sort_order = ? WHERE id = ?', [$next['sort_order'], $lessonId]);
            Database::query('UPDATE pd_lessons SET sort_order = ? WHERE id = ?', [$lesson['sort_order'], $next['id']]);
        }

        redirect(url("admin/courses/{$lesson['course_id']}/lessons"));
    }

    // ─── Forum ────────────────────────────────────────────────────────────────

    public function forum(): void
    {
        $this->requireAdmin();

        $categories = Database::fetchAll('SELECT * FROM pd_forum_categories ORDER BY sort_order ASC');
        $user       = Session::user();

        render('admin/forum', compact('categories', 'user'));
    }

    public function createCategory(): void
    {
        $this->requireAdmin();
        csrf_verify();

        $name  = trim($_POST['name'] ?? '');
        $slug  = trim($_POST['slug'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $order = (int)($_POST['sort_order'] ?? 0);

        if (!$name || !$slug) {
            Session::flash('error', 'Name and slug are required.');
            redirect(url('admin/forum'));
        }

        if (Database::fetchOne('SELECT id FROM pd_forum_categories WHERE slug = ?', [$slug])) {
            Session::flash('error', 'A category with that slug already exists.');
            redirect(url('admin/forum'));
        }

        Database::insert(
            'INSERT INTO pd_forum_categories (name, slug, description, sort_order) VALUES (?, ?, ?, ?)',
            [$name, $slug, $desc, $order]
        );

        redirect(url('admin/forum'));
    }

    public function deleteCategory(string $categoryId): void
    {
        $this->requireAdmin();
        csrf_verify();

        Database::query('DELETE FROM pd_forum_categories WHERE id = ?', [$categoryId]);
        redirect(url('admin/forum'));
    }

    public function deletePost(string $postId): void
    {
        $this->requireAdmin();
        csrf_verify();

        Database::query('DELETE FROM pd_forum_posts WHERE id = ?', [$postId]);
        redirect(url('forum'));
    }

    public function pinPost(string $postId): void
    {
        $this->requireAdmin();
        csrf_verify();

        Database::query(
            'UPDATE pd_forum_posts SET is_pinned = NOT is_pinned WHERE id = ?',
            [$postId]
        );

        redirect(url("forum/post/{$postId}"));
    }

    public function lockPost(string $postId): void
    {
        $this->requireAdmin();
        csrf_verify();

        Database::query(
            'UPDATE pd_forum_posts SET is_locked = NOT is_locked WHERE id = ?',
            [$postId]
        );

        redirect(url("forum/post/{$postId}"));
    }

    // ─── Events ──────────────────────────────────────────────────────────────

    public function events(): void
    {
        $this->requireAdmin();

        $events = Database::fetchAll('SELECT * FROM pd_events ORDER BY starts_at DESC');
        $user   = Session::user();

        render('admin/events', compact('events', 'user'));
    }

    public function createEvent(): void
    {
        $this->requireAdmin();
        csrf_verify();

        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $startsAt   = trim($_POST['starts_at'] ?? '');
        $endsAt     = trim($_POST['ends_at'] ?? '');
        $zoomNeeded = !empty($_POST['create_zoom']);

        if (!$title || !$startsAt || !$endsAt) {
            Session::flash('error', 'Title and dates are required.');
            redirect(url('admin/events'));
        }

        // A date PHP cannot parse threw an uncaught exception (500) in the original.
        $start = \DateTime::createFromFormat('Y-m-d\TH:i', $startsAt) ?: \DateTime::createFromFormat('Y-m-d H:i:s', $startsAt);
        $end   = \DateTime::createFromFormat('Y-m-d\TH:i', $endsAt) ?: \DateTime::createFromFormat('Y-m-d H:i:s', $endsAt);
        if (!$start || !$end) {
            Session::flash('error', 'Enter the dates as date and time.');
            redirect(url('admin/events'));
        }
        $startsAt = $start->format('Y-m-d H:i:s');
        $endsAt   = $end->format('Y-m-d H:i:s');

        if ($start >= $end) {
            Session::flash('error', 'End time must be after start time.');
            redirect(url('admin/events'));
        }

        $zoomMeetingId = null;
        $zoomJoinUrl   = null;

        if ($zoomNeeded && !ZoomService::configured()) {
            // The live server has no Zoom credentials, so the original always
            // failed here with Zoom's raw OAuth error.
            Session::flash('error', 'Could not create Zoom meeting: Zoom is not configured (PD_ZOOM_* keys).');
            redirect(url('admin/events'));
        }

        if ($zoomNeeded) {
            try {
                $duration = (int)(($end->getTimestamp() - $start->getTimestamp()) / 60);

                $zoom          = (new ZoomService())->createMeeting($title, $startsAt, $duration);
                $zoomMeetingId = $zoom['meeting_id'];
                $zoomJoinUrl   = $zoom['join_url'];
            } catch (\RuntimeException $e) {
                Session::flash('error', 'Could not create Zoom meeting: ' . $e->getMessage());
                redirect(url('admin/events'));
            }
        }

        Database::insert(
            'INSERT INTO pd_events (title, description, starts_at, ends_at, zoom_meeting_id, zoom_join_url, is_published)
             VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$title, $desc, $startsAt, $endsAt, $zoomMeetingId, $zoomJoinUrl]
        );

        redirect(url('admin/events'));
    }

    public function deleteEvent(string $eventId): void
    {
        $this->requireAdmin();
        csrf_verify();

        $event = Database::fetchOne('SELECT zoom_meeting_id FROM pd_events WHERE id = ?', [$eventId]);

        if ($event && $event['zoom_meeting_id'] && ZoomService::configured()) {
            try {
                (new ZoomService())->deleteMeeting($event['zoom_meeting_id']);
            } catch (\RuntimeException) {
                // Non-fatal
            }
        }

        Database::query('DELETE FROM pd_events WHERE id = ?', [$eventId]);
        redirect(url('admin/events'));
    }

    public function eventRsvps(string $eventId): void
    {
        $this->requireAdmin();

        $event = Database::fetchOne('SELECT * FROM pd_events WHERE id = ?', [$eventId]);
        if (!$event) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Event not found.']);
            return;
        }

        $rsvps = Database::fetchAll(
            'SELECT er.created_at AS rsvped_at, u.first_name, u.last_name, u.email
             FROM pd_event_rsvps er
             JOIN pd_users u ON u.id = er.user_id
             WHERE er.event_id = ?
             ORDER BY er.created_at ASC',
            [$eventId]
        );

        $user = Session::user();
        render('admin/event-rsvps', compact('event', 'rsvps', 'user'));
    }

    // ─── Bulk Email ───────────────────────────────────────────────────────────

    public function bulkEmail(): void
    {
        $this->requireAdmin();

        // The original counted rows in user_app_admins (the login server's
        // per-app ADMIN table, empty on live), so "all members" was always 0.
        $countRow = Database::fetchOne('SELECT COUNT(*) AS n FROM pd_users WHERE is_active = 1');
        $memberCount = (int)($countRow['n'] ?? 0);

        $user = Session::user();
        render('admin/bulk-email', compact('memberCount', 'user'));
    }

    public function sendBulkEmail(): void
    {
        $this->requireAdmin();
        csrf_verify();

        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body'] ?? '');

        if (!$subject || !$body) {
            Session::flash('error', 'Subject and body are required.');
            redirect(url('admin/email'));
            return;
        }

        $members = Database::fetchAll('SELECT email FROM pd_users WHERE is_active = 1');

        $sent = 0;
        foreach ($members as $member) {
            if (tl_mail($member['email'], $subject, $body)) {
                $sent++;
            }
        }

        Session::flash('success', "Email sent to {$sent} member(s).");
        redirect(url('admin/email'));
    }

    // ─── Staff / Users ────────────────────────────────────────────────────────

    public function users(): void
    {
        $this->requireAdmin();

        $users = Database::fetchAll('SELECT * FROM pd_users ORDER BY created_at DESC');
        $user  = Session::user();

        render('admin/users', compact('users', 'user'));
    }

    public function toggleStaff(string $userId): void
    {
        $this->requireAdmin();
        csrf_verify();

        $target = Database::fetchOne('SELECT id, is_admin FROM pd_users WHERE id = ?', [$userId]);
        if (!$target || $target['is_admin']) {
            redirect(url('admin/users'));
        }

        Database::query(
            'UPDATE pd_profiles SET is_staff = NOT is_staff WHERE user_id = ?',
            [$userId]
        );

        redirect(url('admin/users'));
    }

    public function toggleAccess(string $userId): void
    {
        $this->requireAdmin();
        csrf_verify();

        $target = Database::fetchOne('SELECT id, is_admin FROM pd_users WHERE id = ?', [$userId]);
        if (!$target || $target['is_admin']) {
            redirect(url('admin/users'));
        }

        Database::query(
            'UPDATE pd_profiles SET is_active = NOT is_active WHERE user_id = ?',
            [$userId]
        );

        redirect(url('admin/users'));
    }

    public function editUser(string $userId): void
    {
        $this->requireAdmin();

        $target = Database::fetchOne('SELECT * FROM pd_users WHERE id = ?', [$userId]);
        if (!$target) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'User not found.']);
            return;
        }

        $notes = Database::fetchAll(
            'SELECT un.*, a.first_name AS admin_first, a.last_name AS admin_last
             FROM pd_user_notes un
             LEFT JOIN pd_users a ON a.id = un.admin_id
             WHERE un.user_id = ?
             ORDER BY un.created_at DESC',
            [$userId]
        );

        $user = Session::user();
        render('admin/user-edit', [
            'target'  => $target,
            'notes'   => $notes,
            'canEditAccount' => self::isSiteAdmin(),
            'user'    => $user,
            'error'   => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ]);
    }

    public function updateUser(string $userId): void
    {
        $this->requireAdmin();
        csrf_verify();

        $target = Database::fetchOne('SELECT id FROM pd_users WHERE id = ?', [$userId]);
        if (!$target) {
            redirect(url('admin/users'));
        }

        // Name and email belong to the shared tools account (they are the
        // sign-in for every tool), so only a site admin may change them.
        if (!self::isSiteAdmin()) {
            Session::flash('error', 'Only a site admin can change an account\'s name or email.');
            redirect(url("admin/users/{$userId}/edit"));
        }

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));

        if (!$firstName || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'First name and a valid email are required.');
            redirect(url("admin/users/{$userId}/edit"));
            return;
        }

        $existing = Database::fetchOne('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $userId]);
        if ($existing) {
            Session::flash('error', 'That email is already in use by another account.');
            redirect(url("admin/users/{$userId}/edit"));
            return;
        }

        Database::query(
            'UPDATE users SET name = ?, email = ? WHERE id = ?',
            [trim($firstName . ' ' . $lastName), $email, $userId]
        );

        Session::flash('success', 'User updated.');
        redirect(url("admin/users/{$userId}/edit"));
    }

    public function addNote(string $userId): void
    {
        $this->requireAdmin();
        csrf_verify();

        if (!Database::fetchOne('SELECT id FROM pd_users WHERE id = ?', [$userId])) {
            redirect(url('admin/users'));
        }

        $body = trim($_POST['body'] ?? '');
        if ($body) {
            $adminId = Session::user()['id'];
            Database::query(
                'INSERT INTO pd_user_notes (user_id, admin_id, body) VALUES (?, ?, ?)',
                [$userId, $adminId, $body]
            );
        }

        redirect(url("admin/users/{$userId}/edit"));
    }

    public function deleteNote(string $noteId): void
    {
        $this->requireAdmin();
        csrf_verify();

        $note = Database::fetchOne('SELECT user_id FROM pd_user_notes WHERE id = ?', [$noteId]);
        Database::query('DELETE FROM pd_user_notes WHERE id = ?', [$noteId]);

        // The original redirected to whatever HTTP_REFERER said: an open redirect.
        redirect($note ? url("admin/users/{$note['user_id']}/edit") : url('admin/users'));
    }

    private static function isSiteAdmin(): bool
    {
        return !empty(tl_user()['is_admin']);
    }
}
