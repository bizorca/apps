<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Database;
use Anglerfish\Core\View;
use Anglerfish\Models\Post;

/** Drafts and the copy-paste handoff to Substack (SPEC §11.4). */
final class PostController
{
    private const FORMATS = ['before_noon', 'gut_check', 'steal_this',
                             'the_upgrade', 'the_protocol', 'one_number'];

    public function index(): void
    {
        $format = (string) ($_GET['format'] ?? '');
        $status = (string) ($_GET['status'] ?? '');
        $q      = trim((string) ($_GET['q'] ?? ''));

        $where = ['1'];
        $params = [];
        if (in_array($format, self::FORMATS, true)) {
            $where[] = 'p.format = ?';
            $params[] = $format;
        }
        if (in_array($status, ['draft', 'queued', 'scheduled', 'published', 'retired'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(p.title LIKE ? OR p.body LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        $sql = implode(' AND ', $where);

        $posts = Database::all("
            SELECT p.id, p.number, p.slug, p.format, p.title, p.status, p.published_at,
                   k.title AS kit_title,
                   EXISTS(SELECT 1 FROM af_triage t WHERE t.subject_type='post'
                            AND t.subject_id=p.id AND t.mark='write_about') AS queued
              FROM af_posts p LEFT JOIN af_kits k ON k.id = p.kit_id
             WHERE $sql ORDER BY p.number LIMIT 500", $params);

        $counts = Database::all(
            'SELECT format, COUNT(*) n FROM af_posts GROUP BY format ORDER BY n DESC');
        $statuses = Database::all(
            'SELECT status, COUNT(*) n FROM af_posts GROUP BY status');

        View::render('posts', compact('posts', 'counts', 'statuses', 'format', 'status', 'q'),
            'Posts');
    }

    public function show(string $slug): void
    {
        $post = Database::one('SELECT * FROM af_posts WHERE slug = ?', [$slug]);
        if (!$post) {
            http_response_code(404);
            View::render('error', ['code' => 404, 'message' => 'No such post.'], 'Not found');
            return;
        }

        $post['html']      = Post::toHtml((string) $post['body']);
        $post['warnings']  = Post::warnings((string) $post['body']);
        $post['suggested'] = Post::suggestSubtitle((string) $post['body']);
        $post['marks']     = Database::all(
            "SELECT mark FROM af_triage WHERE subject_type='post' AND subject_id=?",
            [(int) $post['id']]);
        // Header images for this post. subject_type 'post' has always been
        // in the assets enum; nothing ever surfaced it.
        $images = Database::all(
            "SELECT id, version, status, web_path, created_at, model
               FROM af_assets
              WHERE subject_type='post' AND subject_id=? AND kind='infographic'
              ORDER BY version DESC", [(int) $post['id']]);


        View::render('post', [
            'images' => $images,'post' => $post], $post['title']);
    }

    /** Save edits made on the handoff screen. */
    public function update(string $slug): void
    {
        $post = Database::one('SELECT id FROM af_posts WHERE slug = ?', [$slug]);
        if (!$post) {
            redirect('/posts');
        }
        $id = (int) $post['id'];

        if (($_POST['action'] ?? '') === 'publish') {
            Post::markPublished($id, $_POST['substack_url'] ?? null, $_POST['published_at'] ?? null);
            flash('ok', 'Marked published. Cleared from the write queue.');
            redirect('/posts/' . rawurlencode($slug));
        }

        // Validated against the list rather than trusted: this ends up in an
        // image footer, and an unknown key would silently fall back to the
        // wrong publication's copyright.
        $attribution = (string) ($_POST['attribution'] ?? '');
        if (!isset(\Anglerfish\Services\ImagePrompt::ATTRIBUTIONS[$attribution])) {
            $attribution = \Anglerfish\Services\ImagePrompt::DEFAULT_ATTRIBUTION;
        }

        Database::run(
            'UPDATE af_posts SET title = ?, subtitle = NULLIF(?, ""), body = ?, angle = NULLIF(?, ""),
                              attribution = ?
              WHERE id = ?',
            [mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 500),
             trim((string) ($_POST['subtitle'] ?? '')),
             (string) ($_POST['body'] ?? ''),
             trim((string) ($_POST['angle'] ?? '')),
             $attribution,
             $id]
        );
        flash('ok', 'Saved.');
        redirect('/posts/' . rawurlencode($slug));
    }
}
