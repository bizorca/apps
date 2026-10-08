<?php
use Dispatch\Core\View;
$title = 'Triage Queue';
// $pending, $underReview, $processed
?>
<!-- Tabs -->
<div class="flex gap-1 mb-6 bg-slate-100 p-1 rounded-xl w-fit">
    <?php
    $tabs = [
        ['id' => 'pending',     'label' => 'Pending',      'count' => count($pending),     'color' => 'amber'],
        ['id' => 'reviewing',   'label' => 'Under Review',  'count' => count($underReview), 'color' => 'blue'],
        ['id' => 'processed',   'label' => 'Processed',     'count' => count($processed),   'color' => 'slate'],
    ];
    foreach ($tabs as $tab):
    ?>
    <button onclick="showTab('<?= $tab['id'] ?>')" id="tab-<?= $tab['id'] ?>"
        class="tab-btn px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2">
        <?= $tab['label'] ?>
        <?php if ($tab['count'] > 0): ?>
        <span class="bg-<?= $tab['color'] ?>-200 text-<?= $tab['color'] ?>-700 text-xs font-bold px-1.5 py-0.5 rounded-full"><?= $tab['count'] ?></span>
        <?php endif; ?>
    </button>
    <?php endforeach; ?>
</div>

<?php
$renderSubmissions = function(array $submissions, string $showActions = 'pending') use ($_base) {
    if (empty($submissions)):
?>
    <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">
        <p class="text-4xl mb-3">📭</p>
        <p class="text-sm">Nothing here</p>
    </div>
<?php return; endif; ?>
<div class="space-y-3">
    <?php foreach ($submissions as $s):
        $statusColors = ['pending' => 'amber', 'under_review' => 'blue', 'approved' => 'emerald', 'rejected' => 'red'];
        $color = $statusColors[$s['status']] ?? 'slate';
    ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <h3 class="font-semibold text-slate-900"><?= \Dispatch\Core\View::e($s['venue_name']) ?></h3>
                    <span class="text-xs font-semibold bg-<?= $color ?>-100 text-<?= $color ?>-700 px-2 py-0.5 rounded-full"><?= ucwords(str_replace('_', ' ', $s['status'])) ?></span>
                </div>
                <div class="grid md:grid-cols-2 gap-1 text-xs text-slate-500 mb-3">
                    <span>Submitted by: <strong><?= \Dispatch\Core\View::e($s['submitter_name']) ?></strong></span>
                    <span>Date: <?= \Dispatch\Core\View::date($s['created_at']) ?></span>
                    <?php if ($s['submission_url']): ?>
                    <span class="md:col-span-2">URL: <a href="<?= \Dispatch\Core\View::e($s['submission_url']) ?>" target="_blank" class="text-indigo-500 hover:text-indigo-600"><?= \Dispatch\Core\View::e($s['submission_url']) ?></a></span>
                    <?php endif; ?>
                </div>
                <?php if ($s['justification']): ?>
                <p class="text-xs text-slate-600 bg-slate-50 rounded-lg p-3 leading-relaxed"><?= \Dispatch\Core\View::e($s['justification']) ?></p>
                <?php endif; ?>
            </div>
            <a href="<?= $_base ?>/admin/submissions/<?= $s['id'] ?>" class="flex-shrink-0 px-4 py-2 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-xl hover:bg-indigo-100 transition-colors">
                Review →
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php }; ?>

<div id="tab-content-pending"><?php $renderSubmissions($pending, 'pending'); ?></div>
<div id="tab-content-reviewing" class="hidden"><?php $renderSubmissions($underReview, 'under_review'); ?></div>
<div id="tab-content-processed" class="hidden"><?php $renderSubmissions($processed, 'processed'); ?></div>

<script>
function showTab(id) {
    ['pending','reviewing','processed'].forEach(t => {
        document.getElementById('tab-content-' + t).classList.add('hidden');
        const btn = document.getElementById('tab-' + t);
        btn.classList.remove('bg-white', 'shadow-sm', 'text-slate-900');
        btn.classList.add('text-slate-500');
    });
    document.getElementById('tab-content-' + id).classList.remove('hidden');
    const active = document.getElementById('tab-' + id);
    active.classList.add('bg-white', 'shadow-sm', 'text-slate-900');
    active.classList.remove('text-slate-500');
}
showTab('pending');
</script>
