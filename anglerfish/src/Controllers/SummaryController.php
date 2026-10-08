<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Database;
use Anglerfish\Core\View;
use Anglerfish\Models\Job;
use Anglerfish\Services\BookSummary;

/**
 * Whole-book summary, and the audiobook script made from it (SPEC §11.5).
 *
 * The outline is rebuilt on every request rather than stored. It is assembled
 * from concepts that already exist, so it is instant, always current, and there
 * is no stale copy to reconcile after a re-extraction.
 */
final class SummaryController
{
    public function show(string $slug): void
    {
        $outline = BookSummary::outline($slug);
        if (!$outline) {
            $exists = Database::value('SELECT id FROM af_books WHERE slug = ?', [$slug]);
            View::render('error', [
                'code' => 404,
                'message' => $exists
                    ? 'Nothing extracted from this book yet, so there is nothing to summarise.'
                    : 'No such book.',
            ], 'Not found');
            return;
        }

        $script = Database::one(
            "SELECT * FROM af_book_summaries
              WHERE book_id = ? AND kind = 'audiobook_script'",
            [(int) $outline['book']['id']]
        );

        View::render('summary', [
            'o'      => $outline,
            'book'   => $outline['book'],
            'script' => $script,
            // A script generated before the concepts changed is stale, and the
            // only way to know is to compare what it was built from.
            'stale'  => $script && $script['source_sha']
                        && $script['source_sha'] !== $outline['sha'],
        ], 'Summary — ' . $outline['book']['title']);
    }

    /** Raw markdown, straight to a file. No job, no wait. */
    public function download(string $slug): void
    {
        $outline = BookSummary::outline($slug);
        if (!$outline) {
            http_response_code(404);
            exit('Nothing to summarise.');
        }
        $name = BookSummary::filename($outline['book'], 'md');
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($outline['markdown']));
        echo $outline['markdown'];
        exit;
    }

    /** The script, as plain text a narrator can read from. */
    public function downloadScript(string $slug): void
    {
        $row = Database::one(
            "SELECT s.*, b.slug FROM af_book_summaries s JOIN af_books b ON b.id = s.book_id
              WHERE b.slug = ? AND s.kind = 'audiobook_script' AND s.status = 'done'",
            [$slug]
        );
        if (!$row) {
            http_response_code(404);
            exit('No script yet.');
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="'
            . preg_replace('/[^a-z0-9]+/i', '-', strtolower($slug)) . '-script.txt"');
        echo $row['title'] . "\n\n" . $row['body'] . "\n";
        exit;
    }

    public function script(string $slug): void
    {
        $outline = BookSummary::outline($slug);
        if (!$outline) {
            flash('error', 'Nothing extracted from this book yet.');
            redirect('/library');
        }
        $bookId = (int) $outline['book']['id'];
        $minutes = max(3, min(45, (int) ($_POST['minutes'] ?? 12)));

        Database::run(
            "INSERT INTO af_book_summaries (book_id, kind, status)
             VALUES (?, 'audiobook_script', 'queued')
             ON DUPLICATE KEY UPDATE status='queued', error=NULL, finished_at=NULL",
            [$bookId]
        );

        Job::enqueue('audiobook_script', 'book', $bookId, [
            'slug'    => $slug,
            'minutes' => $minutes,
        ], 1);

        flash('ok', "Script queued — about {$minutes} minutes of narration. "
            . 'It appears here when it lands.');
        redirect('/library/' . rawurlencode($slug) . '/summary');
    }
}
