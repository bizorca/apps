<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Api;
use Anglerfish\Core\Database;
use Anglerfish\Core\View;
use Anglerfish\Models\Composition;

/** The composer (SPEC §11.2). Generation runs as a job; the page waits on it. */
final class ComposeController
{
    public function form(): void
    {
        $type = (string) ($_GET['subject_type'] ?? 'freeform');
        $id   = (int) ($_GET['subject_id'] ?? 0);
        $subject = $id ? Composition::subject($type, $id) : null;

        // With no subject chosen, the form used to dead-end in freeform — the
        // nav link was the only way in and it could not reach any of the
        // material in the library. The picker is that missing doorway.
        $pick = trim((string) ($_GET['pick'] ?? ''));
        $pickType = (string) ($_GET['pick_type'] ?? 'big_idea');
        if (!isset(Composition::PICK_TYPES[$pickType])) {
            $pickType = 'big_idea';
        }

        View::render('compose-form', [
            'subject_type' => $subject ? $type : 'freeform',
            'subject_id'   => $subject ? $id : 0,
            'subject'      => $subject,
            'pick'         => $pick,
            'pickType'     => $pickType,
            'candidates'   => $subject ? ['rows' => [], 'total' => 0]
                                       : Composition::candidates($pickType, $pick),
            'recent'       => Database::all(
                'SELECT id, format, status, title, created_at FROM af_compositions
                  ORDER BY id DESC LIMIT 8'),
        ], 'Compose');
    }

    public function create(): void
    {
        $type = (string) ($_POST['subject_type'] ?? 'freeform');
        $id   = (int) ($_POST['subject_id'] ?? 0);
        $subject = $id ? Composition::subject($type, $id) : null;
        // The angle is optional. It was mandatory for book-sourced material to
        // stop the output drifting into book-report register, but every post is
        // hand-edited before it ships, which catches that far better than a
        // required field. With no angle the prompt forbids inventing anything
        // personal, so the draft stays honest rather than fabricating anecdotes.
        $angle = trim((string) ($_POST['angle'] ?? ''));

        $format = (string) ($_POST['format'] ?? 'before_noon');
        if (!isset(Composition::FORMATS[$format])) {
            $format = 'before_noon';
        }
        $audience = (string) ($_POST['audience'] ?? 'practitioners');
        if (!isset(Composition::AUDIENCES[$audience])) {
            $audience = 'practitioners';
        }
        $length = (string) ($_POST['length'] ?? 'medium');
        if (!isset(Composition::LENGTHS[$length])) {
            $length = 'medium';
        }

        $compId = Composition::create([
            'subject_type' => $subject ? $type : 'freeform',
            'subject_id'   => $subject ? $id : 0,
            'format'       => $format,
            'angle'        => $angle,
            'audience'     => $audience,
            'length'       => $length,
            'voice_preset' => 'bizorca_press',
            'extra'        => trim((string) ($_POST['extra'] ?? '')),
            'expand'       => !empty($_POST['expand']),
        ], $subject);

        redirect('/compose/' . $compId);
    }

    public function show(string $id): void
    {
        $c = Database::one('SELECT * FROM af_compositions WHERE id=?', [(int) $id]);
        if (!$c) {
            http_response_code(404);
            View::render('error', ['code' => 404, 'message' => 'No such composition.'], 'Not found');
            return;
        }
        // The citation panel. Fetched here rather than in the template so the
        // gate's verdict and the rows it was computed from cannot disagree.
        $pub = \Anglerfish\Models\Publication::byId((int) ($c['publication_id'] ?? 1));
        $cites = Database::all(
            'SELECT * FROM af_citations WHERE composition_id = ? ORDER BY verified_at IS NULL, id',
            [(int) $c['id']]);

        // DOIs present in the body that have never been through verify() at
        // all. Distinct from a row that failed: "not checked yet" and "checked
        // and does not exist" mean different things to whoever is reading.
        $known = array_map('strtolower', array_column($cites, 'doi'));
        $unchecked = array_values(array_diff(
            \Anglerfish\Services\Citations::extract((string) $c['body']), $known));

        View::render('compose-show', [
            'c'         => $c,
            'pub'       => $pub,
            'cites'     => $cites,
            'unchecked' => $unchecked,
            'gate'      => \Anglerfish\Models\Composition::canPromote($c),
        ], 'Composition');
    }

    /** Polled by the page while the job is pending. */
    public function status(string $id): void
    {
        $c = Database::one(
            'SELECT id, status, title, error, post_id FROM af_compositions WHERE id=?',
            [(int) $id]);
        Api::json($c ? ['ok' => true] + $c : ['ok' => false], $c ? 200 : 404);
    }

    public function promote(string $id): void
    {
        $slug = Composition::promote((int) $id);
        if (!$slug) {
            flash('error', 'Nothing to promote yet.');
            redirect('/compose/' . (int) $id);
        }
        flash('ok', 'Promoted to a draft. Edit it here, then copy it into Substack.');
        redirect('/posts/' . rawurlencode($slug));
    }
}
