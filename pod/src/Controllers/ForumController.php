<?php

namespace Bizorca\Pod\Controllers;

use Bizorca\Pod\Auth\Session;
use Bizorca\Pod\Core\Database;
use Bizorca\Pod\Services\NotificationService;

class ForumController
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

        $categories = Database::fetchAll(
            'SELECT fc.*,
                    COUNT(fp.id) AS post_count,
                    MAX(fp.created_at) AS last_post_at
             FROM pd_forum_categories fc
             LEFT JOIN pd_forum_posts fp ON fp.category_id = fc.id
             GROUP BY fc.id
             ORDER BY fc.sort_order ASC'
        );

        $user = Session::user();

        // Unread counts per category
        $readMap = [];
        $reads = Database::fetchAll('SELECT category_id, last_read_at FROM pd_forum_category_reads WHERE user_id = ?', [$user['id']]);
        foreach ($reads as $r) {
            $readMap[$r['category_id']] = $r['last_read_at'];
        }
        foreach ($categories as &$cat) {
            $lastRead = $readMap[$cat['id']] ?? '2000-01-01';
            $unread   = Database::fetchOne(
                'SELECT COUNT(*) AS n FROM pd_forum_posts WHERE category_id = ? AND created_at > ?',
                [$cat['id'], $lastRead]
            );
            $cat['unread_count'] = (int)($unread['n'] ?? 0);
        }
        unset($cat);

        // Recent posts across all categories
        $recentPosts = Database::fetchAll(
            'SELECT fp.*, u.first_name, u.last_name, fc.name AS category_name, fc.slug AS category_slug
             FROM pd_forum_posts fp
             JOIN pd_users u ON u.id = fp.user_id
             JOIN pd_forum_categories fc ON fc.id = fp.category_id
             ORDER BY COALESCE(fp.last_reply_at, fp.created_at) DESC
             LIMIT 20'
        );

        render('forum/index', compact('categories', 'recentPosts', 'user'));
    }

    public function category(string $slug): void
    {
        $this->requireAuth();

        $category = Database::fetchOne(
            'SELECT * FROM pd_forum_categories WHERE slug = ?',
            [$slug]
        );

        if (!$category) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Category not found.']);
            return;
        }

        $posts = Database::fetchAll(
            'SELECT fp.*, u.first_name, u.last_name
             FROM pd_forum_posts fp
             JOIN pd_users u ON u.id = fp.user_id
             WHERE fp.category_id = ?
             ORDER BY fp.is_pinned DESC, COALESCE(fp.last_reply_at, fp.created_at) DESC',
            [$category['id']]
        );

        $user = Session::user();

        render('forum/category', compact('category', 'posts', 'user'));

        // Update category read timestamp
        Database::query(
            'INSERT INTO pd_forum_category_reads (user_id, category_id, last_read_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE last_read_at = NOW()',
            [$user['id'], $category['id']]
        );
    }

    public function showPost(string $postId): void
    {
        $this->requireAuth();

        $post = Database::fetchOne(
            'SELECT fp.*, u.first_name, u.last_name, fc.name AS category_name, fc.slug AS category_slug
             FROM pd_forum_posts fp
             JOIN pd_users u ON u.id = fp.user_id
             JOIN pd_forum_categories fc ON fc.id = fp.category_id
             WHERE fp.id = ?',
            [$postId]
        );

        if (!$post) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Post not found.']);
            return;
        }

        $replies = Database::fetchAll(
            'SELECT fr.*, u.first_name, u.last_name, u.is_staff
             FROM pd_forum_replies fr
             JOIN pd_users u ON u.id = fr.user_id
             WHERE fr.post_id = ?
             ORDER BY fr.created_at ASC',
            [$postId]
        );

        $user = Session::user();

        $reactions = Database::fetchAll(
            'SELECT emoji, COUNT(*) AS count, MAX(CASE WHEN user_id = ? THEN 1 ELSE 0 END) AS reacted
             FROM pd_forum_reactions WHERE post_id = ? GROUP BY emoji',
            [$user['id'], $postId]
        );

        render('forum/thread', compact('post', 'replies', 'reactions', 'user'));
    }

    public function createPostForm(): void
    {
        $this->requireAuth();

        $categories = Database::fetchAll('SELECT * FROM pd_forum_categories ORDER BY sort_order ASC');
        $selected   = $_GET['category'] ?? '';
        $user       = Session::user();

        render('forum/create', compact('categories', 'selected', 'user'));
    }

    public function createPost(): void
    {
        $this->requireAuth();
        csrf_verify();

        $user       = Session::user();
        $title      = trim($_POST['title'] ?? '');
        $body       = trim($_POST['body'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);

        if (!$title || !$body || !$categoryId) {
            Session::flash('error', 'All fields are required.');
            redirect(url('forum/new'));
        }

        $category = Database::fetchOne('SELECT id FROM pd_forum_categories WHERE id = ?', [$categoryId]);
        if (!$category) {
            Session::flash('error', 'Invalid category.');
            redirect(url('forum/new'));
        }

        $postId = Database::insert(
            'INSERT INTO pd_forum_posts (user_id, category_id, title, body) VALUES (?, ?, ?, ?)',
            [$user['id'], $categoryId, $title, $body]
        );

        NotificationService::notifyMentions($body, $user, "/forum/post/{$postId}");

        redirect(url("forum/post/{$postId}"));
    }

    public function reply(string $postId): void
    {
        $this->requireAuth();
        csrf_verify();

        $post = Database::fetchOne(
            'SELECT * FROM pd_forum_posts WHERE id = ?',
            [$postId]
        );

        if (!$post || $post['is_locked']) {
            Session::flash('error', 'This thread is locked or does not exist.');
            redirect(url("forum/post/{$postId}"));
        }

        $user = Session::user();
        $body = trim($_POST['body'] ?? '');

        if (!$body) {
            Session::flash('error', 'Reply cannot be empty.');
            redirect(url("forum/post/{$postId}"));
        }

        Database::insert(
            'INSERT INTO pd_forum_replies (post_id, user_id, body) VALUES (?, ?, ?)',
            [$postId, $user['id'], $body]
        );

        Database::query(
            'UPDATE pd_forum_posts SET reply_count = reply_count + 1, last_reply_at = NOW() WHERE id = ?',
            [$postId]
        );

        // Notifications
        $fullPost = Database::fetchOne(
            'SELECT fp.*, u.first_name, u.last_name FROM pd_forum_posts fp JOIN pd_users u ON u.id = fp.user_id WHERE fp.id = ?',
            [$postId]
        );
        NotificationService::notifyForumReply($fullPost, $user);
        NotificationService::notifyMentions($body, $user, "/forum/post/{$postId}");

        redirect(url("forum/post/{$postId}") . '#bottom');
    }

    public function search(): void
    {
        $this->requireAuth();

        $q       = trim($_GET['q'] ?? '');
        $results = [];

        if (strlen($q) >= 2) {
            $like    = '%' . $q . '%';
            $results = Database::fetchAll(
                'SELECT fp.*, u.first_name, u.last_name, fc.name AS category_name, fc.slug AS category_slug
                 FROM pd_forum_posts fp
                 JOIN pd_users u ON u.id = fp.user_id
                 JOIN pd_forum_categories fc ON fc.id = fp.category_id
                 WHERE fp.title LIKE ? OR fp.body LIKE ?
                 ORDER BY fp.created_at DESC
                 LIMIT 30',
                [$like, $like]
            );
        }

        $user = Session::user();
        render('forum/search', compact('q', 'results', 'user'));
    }

    public function react(string $postId): void
    {
        $this->requireAuth();
        csrf_verify();

        $emoji   = $_POST['emoji'] ?? '';
        $allowed = ['👍', '❤️', '😂', '😮'];

        if (!in_array($emoji, $allowed, true)) {
            redirect(url("forum/post/{$postId}"));
            return;
        }

        // The original had no such check (and no foreign key), so reactions
        // could be stored against posts that do not exist.
        if (!Database::fetchOne('SELECT id FROM pd_forum_posts WHERE id = ?', [$postId])) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Post not found.']);
            return;
        }

        $user     = Session::user();
        $existing = Database::fetchOne(
            'SELECT id FROM pd_forum_reactions WHERE post_id = ? AND user_id = ? AND emoji = ?',
            [$postId, $user['id'], $emoji]
        );

        if ($existing) {
            Database::query('DELETE FROM pd_forum_reactions WHERE id = ?', [$existing['id']]);
        } else {
            Database::query(
                'INSERT IGNORE INTO pd_forum_reactions (post_id, user_id, emoji) VALUES (?, ?, ?)',
                [$postId, $user['id'], $emoji]
            );
        }

        redirect(url("forum/post/{$postId}"));
    }
}
