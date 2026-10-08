<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Database;
use Anglerfish\Core\View;
use Anglerfish\Models\ImageBatch;
use Anglerfish\Services\GeminiBatch;

/**
 * The contact sheet: one book's chapters, their infographics, and the two
 * verbs that matter — reject and requeue.
 *
 * Reviewing 494 images one asset page at a time is the real bottleneck in batch
 * generation, not the money. A sheet shows the whole book at once, which is the
 * only way to notice that three chapters got the same metaphor.
 *
 * Reject and requeue are deliberately separate. Rejecting says the picture is
 * wrong and costs nothing; requeueing orders another one at $0.067 with a fresh
 * art direction, which is where variety comes from — the same summary directed
 * twice does not produce the same composition.
 */
final class ImagesController
{
    public function sheet(string $slug): void
    {
        $book = self::book($slug);

        View::render('contact-sheet', [
            'book'     => $book,
            'rows'     => ImageBatch::sheet((int) $book['id']),
            'tally'    => ImageBatch::tally((int) $book['id']),
            'batches'  => ImageBatch::forBook((int) $book['id']),
            'unshot'   => count(ImageBatch::chapters((int) $book['id'])),
            'price'    => GeminiBatch::PRICE_PER_IMAGE,
        ], $book['title'] . ' — images');
    }

    /** POST /library/{slug}/images — order the missing ones. */
    public function start(string $slug): void
    {
        $book = self::book($slug);
        $chapters = ImageBatch::chapters((int) $book['id'],
            redo: !empty($_POST['redo']));

        if (!$chapters) {
            flash('ok', 'Every chapter with a summary already has an image ordered.');
            redirect('/library/' . rawurlencode($slug) . '/images');
        }

        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        $ids = ImageBatch::plan((int) $book['id'], $chapters,
            (string) ($cfg['gemini']['image_model'] ?? 'gemini-3-pro-image'));

        flash('ok', sprintf(
            '%d chapter%s queued in %d batch%s — about $%.2f. Art direction runs first, '
            . 'then Gemini answers within 24 hours.',
            count($chapters), count($chapters) === 1 ? '' : 's',
            count($ids), count($ids) === 1 ? '' : 'es',
            count($chapters) * GeminiBatch::PRICE_PER_IMAGE));
        redirect('/library/' . rawurlencode($slug) . '/images');
    }

    /** POST /library/{slug}/images/act — reject or requeue one chapter. */
    public function act(string $slug): void
    {
        $book = self::book($slug);
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        $chapterId = (int) ($_POST['chapter_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');

        if ($assetId) {
            // Scoped to this book's chapters, so a stray id cannot reject
            // somebody else's asset.
            Database::run(
                "UPDATE af_assets SET status = 'rejected'
                  WHERE id = ? AND subject_type = 'chapter'
                    AND subject_id IN (SELECT id FROM af_chapters WHERE book_id = ?)",
                [$assetId, (int) $book['id']]);
        }

        if ($action === 'requeue' && $chapterId) {
            $chapter = Database::one(
                'SELECT id, ord, number_label, title, core_idea, summary
                   FROM af_chapters WHERE id = ? AND book_id = ?',
                [$chapterId, (int) $book['id']]);
            if (!$chapter) {
                flash('error', 'That chapter is not in this book.');
                redirect('/library/' . rawurlencode($slug) . '/images');
            }
            $cfg = require dirname(__DIR__, 2) . '/config/app.php';
            ImageBatch::plan((int) $book['id'], [$chapter],
                (string) ($cfg['gemini']['image_model'] ?? 'gemini-3-pro-image'));
            flash('ok', 'Requeued with a fresh art direction — $'
                . number_format(GeminiBatch::PRICE_PER_IMAGE, 3) . '.');
        } else {
            flash('ok', 'Rejected.');
        }

        redirect('/library/' . rawurlencode($slug) . '/images');
    }

    private static function book(string $slug): array
    {
        $book = Database::one('SELECT id, slug, title FROM af_books WHERE slug = ?', [$slug]);
        if (!$book) {
            flash('error', 'No such book.');
            redirect('/library');
        }
        return $book;
    }
}
