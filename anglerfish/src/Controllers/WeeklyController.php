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

        // Runs the way a post gets written in Claude Code: sources chosen from
        // the library mapped to the module, supporting detail always pulled, the
        // topic and the download container left to the composer. The operator
        // picks the module and can change the container afterwards on the draft.
        $sources = Weekly::autoSources($moduleId);
        $first   = $sources[0] ?? null;
        $subject = $first ? Composition::subject($first['subject_type'], $first['subject_id']) : null;

        $compId = Composition::create([
            'subject_type' => $subject ? $first['subject_type'] : 'freeform',
            'subject_id'   => $subject ? $first['subject_id'] : 0,
            'sources'      => array_slice($sources, 1),
            'format'       => 'weekly',
            'module_id'    => $moduleId,
            'deliverable'  => null,
            'angle'        => '',
            'audience'     => 'practitioners',
            // Stored for the column's sake; Compose overrides it for weekly,
            // which has one working band of its own.
            'length'       => 'long',
            'voice_preset' => 'bizorca_press',
            'extra'        => trim((string) ($_POST['extra'] ?? '')),
            'expand'       => true,
        ], $subject);

        redirect('/compose/' . $compId);
    }
}
