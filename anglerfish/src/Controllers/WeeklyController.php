<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Database;
use Anglerfish\Core\View;
use Anglerfish\Models\Composition;
use Anglerfish\Models\Weekly;

/**
 * The weekly desk: create a weekly post against a curriculum module.
 *
 * Deliberately a separate screen from /compose rather than a seventh entry in
 * that form's format select. A weekly post needs a module and a deliverable
 * container, the compose form has nowhere to ask for either, and a weekly post
 * created without them is a post that cannot be tagged, sequenced or compiled
 * into the workbook. Generation itself reuses the compose pipeline unchanged —
 * this screen only gathers the extra fields.
 */
final class WeeklyController
{
    public function index(): void
    {
        $modules  = Weekly::modules();
        $moduleId = (int) ($_GET['module'] ?? 0);

        // An unknown module falls through to the default rather than rendering
        // a form with no module panel — that form can only fail on submit, and
        // it looks like a broken page rather than a bad URL.
        if ($moduleId && !Weekly::module($moduleId)) {
            $moduleId = 0;
        }

        // Default to the module with the least coverage, breaking ties by
        // dependency order. Opening on module 1 every time would mean the
        // early modules got written four times over and module 11 never.
        if (!$moduleId && $modules) {
            $coverage = Weekly::coverage();
            $moduleId = (int) $modules[0]['id'];
            $fewest = PHP_INT_MAX;
            foreach ($modules as $m) {
                $n = $coverage[(int) $m['id']] ?? 0;
                if ($n < $fewest) {
                    $fewest = $n;
                    $moduleId = (int) $m['id'];
                }
            }
        }

        View::render('weekly', [
            'modules'   => $modules,
            'coverage'  => Weekly::coverage(),
            'moduleId'  => $moduleId,
            'module'    => Weekly::module($moduleId),
            'sources'   => $moduleId ? Weekly::sourcesForModule($moduleId) : [],
            'mapped'    => Weekly::mappedCount(),
            'posts'     => Weekly::posts(),
            'nextSeq'   => Weekly::nextSequence(),
            'recent'    => Database::all(
                "SELECT id, status, title, created_at FROM af_compositions
                  WHERE format = 'weekly' ORDER BY id DESC LIMIT 8"),
        ], 'Weekly');
    }

    public function create(): void
    {
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        if (!Weekly::module($moduleId)) {
            flash('error', 'Pick a module. A weekly post without one cannot be tagged or compiled.');
            redirect('/weekly');
        }

        $deliverable = (string) ($_POST['deliverable'] ?? '');
        if (!isset(Weekly::CONTAINERS[$deliverable])) {
            flash('error', 'Pick one of the four deliverable containers.');
            redirect('/weekly?module=' . $moduleId);
        }

        $type    = (string) ($_POST['subject_type'] ?? 'freeform');
        $id      = (int) ($_POST['subject_id'] ?? 0);
        $subject = $id ? Composition::subject($type, $id) : null;

        $audience = (string) ($_POST['audience'] ?? 'practitioners');
        if (!isset(Composition::AUDIENCES[$audience])) {
            $audience = 'practitioners';
        }

        $compId = Composition::create([
            'subject_type' => $subject ? $type : 'freeform',
            'subject_id'   => $subject ? $id : 0,
            'format'       => 'weekly',
            'module_id'    => $moduleId,
            'deliverable'  => $deliverable,
            'angle'        => trim((string) ($_POST['angle'] ?? '')),
            'audience'     => $audience,
            // Stored for the column's sake; Compose overrides it for weekly,
            // which has one working band of its own.
            'length'       => 'long',
            'voice_preset' => 'bizorca_press',
            'extra'        => trim((string) ($_POST['extra'] ?? '')),
            'expand'       => !empty($_POST['expand']),
        ], $subject);

        redirect('/compose/' . $compId);
    }
}
