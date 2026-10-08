<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Api;
use Anglerfish\Core\Database;
use Anglerfish\Core\Session;
use Anglerfish\Core\View;

/**
 * Triage (SPEC §11.1). The keyboard row is what turns a 400-item backlog from
 * a folder into something you can burn down in an evening.
 */
final class TriageController
{
    private const SUBJECTS = ['book', 'chapter', 'concept', 'artifact', 'candidate', 'asset', 'post'];
    private const MARKS    = ['favorite', 'read', 'write_about', 'meh'];

    /** POST /triage — toggles a mark. Answers JSON so the row updates in place. */
    public function mark(): void
    {
        $type = (string) ($_POST['subject_type'] ?? '');
        $id   = (int) ($_POST['subject_id'] ?? 0);
        $mark = (string) ($_POST['mark'] ?? '');

        if (!in_array($type, self::SUBJECTS, true) || !in_array($mark, self::MARKS, true) || !$id) {
            Api::json(['ok' => false, 'error' => 'Bad subject or mark.'], 400);
        }

        $existing = Database::value(
            'SELECT id FROM af_triage WHERE subject_type=? AND subject_id=? AND mark=?',
            [$type, $id, $mark]
        );

        if ($existing) {
            Database::run('DELETE FROM af_triage WHERE id=?', [(int) $existing]);
            $state = false;
        } else {
            // meh and write_about are opposite intents; setting one clears the other.
            if ($mark === 'write_about' || $mark === 'meh') {
                $other = $mark === 'meh' ? 'write_about' : 'meh';
                Database::run(
                    'DELETE FROM af_triage WHERE subject_type=? AND subject_id=? AND mark=?',
                    [$type, $id, $other]
                );
            }
            Database::run(
                'INSERT INTO af_triage (subject_type, subject_id, mark) VALUES (?, ?, ?)',
                [$type, $id, $mark]
            );
            $state = true;
        }

        Api::json([
            'ok' => true, 'mark' => $mark, 'on' => $state,
            'queue' => (int) Database::value(
                "SELECT COUNT(*) FROM af_triage WHERE mark='write_about'"),
        ]);
    }

    /** GET /queue — everything marked "Write About This". */
    public function queue(): void
    {
        $rows = Database::all("
            SELECT t.subject_type, t.subject_id, t.created_at,
                   COALESCE(c.title, a.title, p.title, b.title) AS title,
                   COALESCE(c.body, a.intro, LEFT(p.body,200))  AS body,
                   COALESCE(cb.slug, ab.slug, bb.slug)          AS book_slug,
                   COALESCE(cb.title, ab.title, bb.title)       AS book_title,
                   c.kind AS concept_kind, a.type AS artifact_type, p.format AS post_format
              FROM af_triage t
              LEFT JOIN af_concepts  c ON t.subject_type='concept'  AND c.id=t.subject_id
              LEFT JOIN af_artifacts a ON t.subject_type='artifact' AND a.id=t.subject_id
              LEFT JOIN af_posts     p ON t.subject_type='post'     AND p.id=t.subject_id
              LEFT JOIN af_books     b ON t.subject_type='book'     AND b.id=t.subject_id
              LEFT JOIN af_books    cb ON cb.id=c.book_id
              LEFT JOIN af_books    ab ON ab.id=a.book_id
              LEFT JOIN af_books    bb ON bb.id=b.id
             WHERE t.mark='write_about'
             ORDER BY t.created_at DESC");

        View::render('queue', ['rows' => $rows], 'Write queue');
    }
}
