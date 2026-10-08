<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Database;
use Anglerfish\Core\View;
use Anglerfish\Models\Job;

/** The Vault: 20 kits, their companion tools, and the rendered sheets. */
final class KitController
{
    public function index(): void
    {
        $kits = Database::all('
            SELECT k.*,
                   (SELECT COUNT(*) FROM af_artifacts a WHERE a.kit_id=k.id) AS tools,
                   (SELECT COUNT(*) FROM af_posts p WHERE p.kit_id=k.id)     AS posts
              FROM af_kits k ORDER BY k.number');

        $tools = Database::all("
            SELECT a.id, a.kit_id, a.type, a.title,
                   (SELECT COUNT(*) FROM af_artifact_items i WHERE i.artifact_id=a.id) AS items,
                   (SELECT s.id FROM af_assets s
                     WHERE s.subject_type='artifact' AND s.subject_id=a.id
                       AND s.target='tool_pdf' AND s.status='ready'
                     ORDER BY s.version DESC LIMIT 1)                     AS asset_id
              FROM af_artifacts a WHERE a.kit_id IS NOT NULL ORDER BY a.kit_id, a.type");

        $byKit = [];
        foreach ($tools as $t) {
            $byKit[(int) $t['kit_id']][] = $t;
        }

        View::render('kits', ['kits' => $kits, 'byKit' => $byKit], 'The Vault');
    }

    /** POST /render — queue a deterministic render. Costs nothing to run. */
    public function render(): void
    {
        $type   = (string) ($_POST['subject_type'] ?? '');
        $id     = (int) ($_POST['subject_id'] ?? 0);
        $target = (string) ($_POST['target'] ?? '');

        $allowed = ['tool_pdf', 'one_number', 'the_upgrade', 'the_protocol', 'stat',
                    'weekly_deliverable'];
        if (!$id || !in_array($type, ['artifact', 'post'], true)
            || !in_array($target, $allowed, true)) {
            flash('error', 'Bad render request.');
            redirect($_POST['back'] ?? '/kits');
        }

        $job = Job::enqueueUnique('render', $type, $id, [
            'subject_type' => $type, 'subject_id' => $id, 'target' => $target,
        ], 3);

        flash('ok', $job
            ? "Render queued. Run: angler.py --once --types render"
            : 'A render for this item is already queued.');
        redirect($_POST['back'] ?? '/kits');
    }
}
