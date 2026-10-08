<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Database;
use Anglerfish\Core\View;
use Anglerfish\Models\Composition;
use Anglerfish\Models\Job;
use Anglerfish\Services\ArtDirection;
use Anglerfish\Services\ImagePrompt;

/**
 * Standalone image generation from a piece of source material (SPEC §9.5).
 *
 * The handler and the assets table have been able to do this for a while; what
 * was missing was any way to ask for it. The prompt is shown and editable
 * before firing because each shot costs $0.134 and the fired text is
 * snapshotted onto the asset.
 */
final class GenerateController
{
    /** Subject types an asset can hang off — matches the assets enum. */
    private const TYPES = ['concept', 'artifact', 'chapter', 'post', 'book'];

    public function form(): void
    {
        $type = (string) ($_GET['subject_type'] ?? '');
        $id   = (int) ($_GET['subject_id'] ?? 0);
        if (!in_array($type, self::TYPES, true) || !$id) {
            flash('error', 'Pick something to illustrate first.');
            redirect('/library');
        }

        // Structure decides the shape of the picture, so changing it has to
        // rebuild the draft prompt rather than just annotate it.
        $structure = (string) ($_GET['structure'] ?? 'flow');
        if (!isset(ImagePrompt::STRUCTURES[$structure])) {
            $structure = 'flow';
        }

        // Which publication's copyright goes in the footer. An explicit choice
        // on the query string wins; otherwise a post supplies its own, so the
        // operator is not re-picking it on every visit.
        $attribution = (string) ($_GET['attribution'] ?? '');
        if (!isset(ImagePrompt::ATTRIBUTIONS[$attribution])) {
            $attribution = $type === 'post'
                ? (string) (Database::value(
                    'SELECT attribution FROM af_posts WHERE id = ?', [$id])
                    ?: ImagePrompt::DEFAULT_ATTRIBUTION)
                : ImagePrompt::DEFAULT_ATTRIBUTION;
        }
        if (!isset(ImagePrompt::ATTRIBUTIONS[$attribution])) {
            $attribution = ImagePrompt::DEFAULT_ATTRIBUTION;
        }

        $subject = Composition::subject($type, $id);
        if (!$subject) {
            flash('error', 'That source no longer exists.');
            redirect('/library');
        }

        // Where "back" goes. A post's image is generated as part of the
        // publishing workflow, so it must return there rather than to /library.
        $back = $type === 'post'
            ? '/posts/' . rawurlencode((string) Database::value(
                'SELECT slug FROM af_posts WHERE id = ?', [$id]))
            : null;

        // Art direction is opt-in per render. The template is instant and free;
        // the directed prompt costs about a cent and produces a composition
        // built for this concept rather than the same shape every time.
        $directed = null;
        $prompt = ImagePrompt::draft($subject, 'infographic', $structure);

        // A post can arrive with a direction already written and reviewed in the
        // draft file (§9.5b, migration 011). Prefer it: it cost a pass over the
        // drafts to write and it has been read by a human, which is more than
        // either the template or a fresh Opus call can claim. An explicit
        // ?direct=1 still overrides, so a stored direction can be re-rolled
        // without editing the draft. Only honoured when the structure has not
        // been switched away from the stored one, since changing the shape is
        // exactly the case where the old composition no longer applies.
        $storedStructure = null;
        if ($type === 'post') {
            $stored = Database::one(
                'SELECT gemini_structure, gemini_direction FROM af_posts WHERE id = ?', [$id]) ?? [];
            $storedStructure = $stored['gemini_structure'] ?? null;
            if (empty($_GET['direct']) && !empty($stored['gemini_direction'])
                && (!isset($_GET['structure']) || $structure === $storedStructure)) {
                $d = json_decode((string) $stored['gemini_direction'], true);
                if (is_array($d)) {
                    $structure = $storedStructure ?: $structure;
                    $directed = $d + ['_model' => 'authored'];
                    $prompt = ImagePrompt::fromDirection($directed, 'infographic',
                        ImagePrompt::sourceLine($subject), $attribution);
                }
            }
        }

        if (!empty($_GET['direct'])) {
            try {
                $res = ArtDirection::forSubject($subject);
                $directed = $res['data'] + ['_model' => $res['model']];
                $prompt = ImagePrompt::fromDirection($directed, 'infographic',
                    ImagePrompt::sourceLine($subject), $attribution);
            } catch (\Throwable $e) {
                flash('error', 'Art direction failed, showing the template prompt: '
                    . $e->getMessage());
            }
        }

        View::render('generate-form', [
            'back'         => $back,
            'subject_type' => $type,
            'subject_id'   => $id,
            'subject'      => $subject,
            'structure'    => $structure,
            'attribution'  => $attribution,
            'directed'     => $directed,
            'prompt'       => $prompt,
            'existing'     => Database::all(
                'SELECT id, version, status, web_path, created_at
                   FROM af_assets
                  WHERE subject_type=? AND subject_id=? AND kind="infographic"
                  ORDER BY version DESC LIMIT 6', [$type, $id]),
        ], 'Generate image');
    }

    public function create(): void
    {
        $type = (string) ($_POST['subject_type'] ?? '');
        $id   = (int) ($_POST['subject_id'] ?? 0);
        $prompt = trim((string) ($_POST['prompt'] ?? ''));

        if (!in_array($type, self::TYPES, true) || !$id || mb_strlen($prompt) < 30) {
            flash('error', 'That needs a subject and a prompt of at least 30 characters.');
            redirect('/generate?subject_type=' . urlencode($type) . '&subject_id=' . $id);
        }

        Job::enqueue('generate_image', $type, $id, [
            'subject_type' => $type,
            'subject_id'   => $id,
            'target'       => 'infographic',
            'prompt'       => $prompt,
            'aspect_ratio' => (string) ($_POST['aspect_ratio'] ?? '16:9'),
            'image_size'   => '2K',
        ], 1);

        flash('ok', 'Image queued — about $0.13. It appears here when it lands.');
        redirect('/generate?subject_type=' . urlencode($type) . '&subject_id=' . $id);
    }
}
