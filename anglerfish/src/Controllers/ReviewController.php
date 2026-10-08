<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\View;
use Anglerfish\Models\Review;
use Anglerfish\Services\GeminiBatch;

/**
 * The image review queue — every infographic in the system, one per row.
 *
 * Separate from the per-book contact sheet on purpose. The sheet is for judging
 * a book as a set: thirteen thumbnails at once, to notice that three chapters
 * got the same metaphor. This is for working through a backlog one decision at
 * a time, at a size where the labels are legible, without caring which book the
 * next row comes from.
 */
final class ReviewController
{
    private const FILTERS = ['unreviewed', 'flagged', 'kept', 'rejected', 'waiting', 'all'];

    public function index(): void
    {
        $filter = (string) ($_GET['filter'] ?? 'unreviewed');
        if (!in_array($filter, self::FILTERS, true)) {
            $filter = 'unreviewed';
        }
        $q = Review::queue($filter, (int) ($_GET['page'] ?? 1));

        View::render('review', [
            'filter'  => $filter,
            'filters' => self::FILTERS,
            'rows'    => $q['rows'],
            'total'   => $q['total'],
            'page'    => $q['page'],
            'pages'   => $q['pages'],
            'counts'  => Review::counts(),
            'price'   => 0.134,
        ], 'Image review');
    }

    /**
     * POST /review/act — resolve one row.
     *
     * Returns JSON to a fetch and a redirect to a plain form post, so the page
     * works with JavaScript off and does not reload the whole list (and every
     * image on it) for each decision when it is on.
     */
    public function act(): void
    {
        $id = (int) ($_POST['asset_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        $ok = true;
        $message = '';

        switch ($action) {
            case 'keep':
                Review::keep($id);
                $message = 'Kept.';
                break;
            case 'reject':
                Review::reject($id);
                $message = 'Rejected.';
                break;
            case 'redraw':
                $ok = Review::redraw($id);
                $message = $ok
                    ? 'Rejected — redrawing with a fresh art direction, about $0.14.'
                    : 'That image no longer exists.';
                break;
            default:
                $ok = false;
                $message = 'Unknown action.';
        }

        if (self::wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok, 'message' => $message,
                              'counts' => Review::counts()]);
            exit;
        }

        flash($ok ? 'ok' : 'error', $message);
        redirect('/review?filter=' . urlencode((string) ($_POST['filter'] ?? 'unreviewed'))
            . '&page=' . (int) ($_POST['page'] ?? 1));
    }

    /**
     * POST /review/bulk — keep everything on the page that is still unreviewed.
     *
     * The one bulk action worth having. A page of images that are all fine is
     * common, and twenty clicks to say so is what stops people using a queue.
     * There is deliberately no bulk reject: rejecting is a judgement per image,
     * and a bulk one would mostly be a misclick.
     */
    public function bulk(): void
    {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        foreach ($ids as $id) {
            if ($id) {
                Review::keep($id);
            }
        }
        flash('ok', count($ids) . ' kept.');
        redirect('/review?filter=' . urlencode((string) ($_POST['filter'] ?? 'unreviewed')));
    }

    private static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }
}
