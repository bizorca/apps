<?php

namespace Bizorca\Pod\Controllers;

use Bizorca\Pod\Auth\Session;
use Bizorca\Pod\Core\Database;

class CourseController
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

        $courses = Database::fetchAll(
            'SELECT c.*, COUNT(l.id) AS lesson_count
             FROM pd_courses c
             LEFT JOIN pd_lessons l ON l.course_id = c.id AND l.is_published = 1
             WHERE c.is_published = 1
             GROUP BY c.id
             ORDER BY c.sort_order ASC'
        );

        $user = Session::user();

        render('courses/index', compact('courses', 'user'));
    }

    public function show(string $courseSlug): void
    {
        $this->requireAuth();

        $course = Database::fetchOne(
            'SELECT * FROM pd_courses WHERE slug = ? AND is_published = 1',
            [$courseSlug]
        );

        if (!$course) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Course not found.']);
            return;
        }

        $lessons = Database::fetchAll(
            'SELECT * FROM pd_lessons WHERE course_id = ? AND is_published = 1 ORDER BY sort_order ASC',
            [$course['id']]
        );

        $user = Session::user();

        $completedIds = array_column(
            Database::fetchAll(
                'SELECT lesson_id FROM pd_lesson_progress WHERE user_id = ?',
                [$user['id']]
            ),
            'lesson_id'
        );

        render('courses/show', compact('course', 'lessons', 'completedIds', 'user'));
    }

    public function lesson(string $courseSlug, string $lessonSlug): void
    {
        $this->requireAuth();

        $course = Database::fetchOne(
            'SELECT * FROM pd_courses WHERE slug = ? AND is_published = 1',
            [$courseSlug]
        );

        if (!$course) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Course not found.']);
            return;
        }

        $lesson = Database::fetchOne(
            'SELECT * FROM pd_lessons WHERE course_id = ? AND slug = ? AND is_published = 1',
            [$course['id'], $lessonSlug]
        );

        if (!$lesson) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Lesson not found.']);
            return;
        }

        $user = Session::user();

        $completed = Database::fetchOne(
            'SELECT id FROM pd_lesson_progress WHERE user_id = ? AND lesson_id = ?',
            [$user['id'], $lesson['id']]
        );

        // All lessons for sidebar navigation
        $allLessons = Database::fetchAll(
            'SELECT id, title, slug FROM pd_lessons WHERE course_id = ? AND is_published = 1 ORDER BY sort_order ASC',
            [$course['id']]
        );

        render('courses/lesson', compact('course', 'lesson', 'completed', 'allLessons', 'user'));
    }

    public function markComplete(string $courseSlug, string $lessonSlug): void
    {
        $this->requireAuth();
        csrf_verify();

        // Published only, like the pages: the original let anyone mark an
        // unpublished lesson of an unpublished course complete.
        $course = Database::fetchOne('SELECT id FROM pd_courses WHERE slug = ? AND is_published = 1', [$courseSlug]);
        if (!$course) {
            redirect(url('courses'));
            return;
        }

        $lesson = Database::fetchOne(
            'SELECT id FROM pd_lessons WHERE course_id = ? AND slug = ? AND is_published = 1',
            [$course['id'], $lessonSlug]
        );

        if ($lesson) {
            $user = Session::user();
            Database::query(
                'INSERT IGNORE INTO pd_lesson_progress (user_id, lesson_id) VALUES (?, ?)',
                [$user['id'], $lesson['id']]
            );
        }

        redirect(url("courses/{$courseSlug}/{$lessonSlug}"));
    }
}
