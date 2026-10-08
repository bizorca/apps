<?php
use Dispatch\Core\View;
$title = 'Venue Library';
// $grouped: ['type' => [venues]], $types: type label map
?>
<div class="flex items-center justify-between mb-6">
    <p class="text-slate-500 text-sm"><?= array_sum(array_map('count', $grouped)) ?> active venues</p>
    <a href="<?= $_base ?>/venues/suggest" class="inline-flex items-center gap-2 px-4 py-2.5 border border-indigo-300 text-indigo-600 text-sm font-semibold rounded-xl hover:bg-indigo-50 transition-colors">
        + Suggest a Venue
    </a>
</div>

<?php
$typeLabels = [
    'digital_social'   => ['icon' => '📱', 'label' => 'Social Media'],
    'digital_calendar' => ['icon' => '🗓', 'label' => 'Online Calendars & Listings'],
    'print'            => ['icon' => '📰', 'label' => 'Print Publications'],
    'radio'            => ['icon' => '📻', 'label' => 'Radio & Podcast'],
    'physical'         => ['icon' => '📌', 'label' => 'Physical Posting Locations'],
    'email_newsletter' => ['icon' => '📧', 'label' => 'Email Newsletters'],
    'other'            => ['icon' => '📋', 'label' => 'Other'],
];
?>

<div class="space-y-8">
    <?php foreach ($typeLabels as $type => ['icon' => $icon, 'label' => $label]):
        if (empty($grouped[$type])) continue;
    ?>
    <div>
        <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900 mb-4">
            <span><?= $icon ?></span> <?= $label ?>
            <span class="text-sm font-normal text-slate-400">(<?= count($grouped[$type]) ?>)</span>
        </h2>
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php foreach ($grouped[$type] as $venue): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 hover:border-indigo-200 hover:shadow-sm transition-all">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <h3 class="font-semibold text-slate-900 text-sm leading-tight"><?= View::e($venue['name']) ?></h3>
                    <span class="flex-shrink-0 bg-indigo-50 text-indigo-600 text-xs font-bold px-2 py-0.5 rounded-full whitespace-nowrap">
                        <?= $venue['lead_time_days'] ?>d lead
                    </span>
                </div>

                <?php if ($venue['notes']): ?>
                <p class="text-xs text-slate-500 mb-3 leading-relaxed"><?= View::e($venue['notes']) ?></p>
                <?php endif; ?>

                <?php
                $assetReqs = json_decode($venue['asset_requirements'] ?? '[]', true) ?: [];
                if (!empty($assetReqs)):
                ?>
                <div class="mb-3">
                    <p class="text-xs font-semibold text-slate-500 mb-1.5">Required assets:</p>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($assetReqs as $asset): ?>
                        <span class="bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-md"><?= View::e($asset['type']) ?><?= !empty($asset['specs']) ? ': ' . View::e($asset['specs']) : '' ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100">
                    <?php if ($venue['submission_url']): ?>
                    <a href="<?= View::e($venue['submission_url']) ?>" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-700">
                        Submit Portal →
                    </a>
                    <?php elseif ($venue['submission_email']): ?>
                    <a href="mailto:<?= View::e($venue['submission_email']) ?>"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-700">
                        <?= View::e($venue['submission_email']) ?>
                    </a>
                    <?php endif; ?>

                    <?php if ($venue['suggested_by_name']): ?>
                    <span class="ml-auto text-xs text-slate-400 italic">by <?= View::e($venue['suggested_by_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
