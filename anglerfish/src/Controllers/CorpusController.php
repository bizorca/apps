<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\View;
use Anglerfish\Models\Clipping;
use Anglerfish\Models\Corpus;

/**
 * The Brain corpora. Two tiers, because the index is here and the bodies are
 * not: metadata search answers instantly, body search is a job (SPEC §6.3).
 */
final class CorpusController
{
    public function index(): void
    {
        $q     = trim((string) ($_GET['q'] ?? ''));
        $brain = (string) ($_GET['brain'] ?? 'both');
        if (!in_array($brain, ['mb', 'dk', 'both'], true)) {
            $brain = 'both';
        }
        $type  = trim((string) ($_GET['type'] ?? '')) ?: null;

        $results = $q !== '' || $type ? Corpus::search($q, $brain, $type) : [];
        $deep    = isset($_GET['deep']) ? Corpus::deep((int) $_GET['deep']) : null;

        View::render('corpus', [
            'q' => $q, 'brain' => $brain, 'type' => $type,
            'results' => $results,
            'counts' => Corpus::counts(),
            'types' => Corpus::contentTypes(),
            'deep' => $deep,
        ], 'Corpus');
    }

    /** POST /corpus/deep — queue a body search on the Mac. */
    public function deep(): void
    {
        $q     = trim((string) ($_POST['q'] ?? ''));
        $brain = (string) ($_POST['brain'] ?? 'both');
        if (!in_array($brain, ['mb', 'dk', 'both'], true)) {
            $brain = 'both';
        }

        if (mb_strlen($q) < 3) {
            flash('error', 'Give the body search at least three characters.');
            redirect('/corpus?q=' . urlencode($q));
        }

        $id = Corpus::queueDeep($q, $brain);
        flash('ok', 'Body search queued — results appear here once the worker picks it up.');
        redirect('/corpus?q=' . urlencode($q) . '&brain=' . $brain . '&deep=' . $id);
    }

    /**
     * POST /corpus/clip — keep an excerpt.
     *
     * A grep hit is a snippet inside one of 38,086 files, so there was nothing
     * for the composer to point at. Saving it makes it a row, with its own
     * provenance, that can be written from later.
     */
    public function clip(): void
    {
        $brain = (string) ($_POST['brain'] ?? '');
        if (!in_array($brain, ['mb', 'dk'], true)) {
            flash('error', 'That clipping had no brain attached.');
            redirect('/corpus');
        }

        try {
            Clipping::save([
                'brain'    => $brain,
                'path'     => (string) ($_POST['path'] ?? ''),
                'body'     => (string) ($_POST['body'] ?? ''),
                'title'    => (string) ($_POST['title'] ?? ''),
                'note'     => (string) ($_POST['note'] ?? ''),
                'line_ref' => (string) ($_POST['line_ref'] ?? ''),
                'query'    => (string) ($_POST['query'] ?? ''),
            ]);
            flash('ok', 'Clipped. It is now a source you can compose from.');
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }

        // Rebuilt from validated params rather than the Referer header, which
        // the client controls and would make this an open redirect.
        $back = '/corpus?brain=' . $brain;
        if (($q = trim((string) ($_POST['q'] ?? ''))) !== '') {
            $back .= '&q=' . urlencode($q);
        }
        if ($deep = (int) ($_POST['deep'] ?? 0)) {
            $back .= '&deep=' . $deep;
        }
        redirect($back);
    }
}
