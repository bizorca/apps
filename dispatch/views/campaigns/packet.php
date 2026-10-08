<?php
use Dispatch\Core\View;
$title = $campaign['name'] . ' — Submission Packet';
// $campaign, $actionItems (joined with venue info), $venues
?>

<!-- Header -->
<div class="border-b-2 border-slate-900 pb-4 mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= View::e($campaign['name']) ?></h1>
    <p class="text-slate-600 mt-1">Submission Packet · Generated <?= date('F j, Y') ?></p>
</div>

<!-- Event Details -->
<div class="mb-8">
    <h2 class="text-base font-bold text-slate-900 mb-3 uppercase tracking-wider text-sm">Event Details</h2>
    <table class="text-sm w-full">
        <tr>
            <td class="text-slate-500 font-medium w-32 py-0.5">Date</td>
            <td class="text-slate-900 font-semibold"><?= View::date($campaign['event_date'], 'l, F j, Y') ?></td>
        </tr>
        <?php if ($campaign['event_time']): ?>
        <tr>
            <td class="text-slate-500 font-medium py-0.5">Time</td>
            <td class="text-slate-900"><?= date('g:i A', strtotime($campaign['event_time'])) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($campaign['location']): ?>
        <tr>
            <td class="text-slate-500 font-medium py-0.5">Location</td>
            <td class="text-slate-900"><?= View::e($campaign['location']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($campaign['asset_link']): ?>
        <tr>
            <td class="text-slate-500 font-medium py-0.5">Assets</td>
            <td class="text-slate-900"><a href="<?= View::e($campaign['asset_link']) ?>" class="text-indigo-700 underline"><?= View::e($campaign['asset_link']) ?></a></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<?php if ($campaign['description']): ?>
<div class="mb-8">
    <h2 class="text-sm font-bold text-slate-900 mb-2 uppercase tracking-wider">Description / Base Copy</h2>
    <div class="text-sm text-slate-700 leading-relaxed border border-slate-200 rounded-lg p-4 bg-slate-50">
        <?= nl2br(View::e($campaign['description'])) ?>
    </div>
</div>
<?php endif; ?>

<!-- Venue Submissions -->
<h2 class="text-sm font-bold text-slate-900 mb-4 uppercase tracking-wider">Submission Targets (<?= count($actionItems) ?>)</h2>

<?php foreach ($actionItems as $item):
    $isOverdue   = $item['status'] === 'pending' && $item['due_date'] < date('Y-m-d');
    $statusLabel = match($item['status']) {
        'complete'    => 'Complete',
        'confirmed'   => 'Confirmed',
        'submitted'   => 'Submitted',
        'rejected'    => 'Rejected',
        'no_response' => 'No Response',
        'skipped'     => 'Skipped',
        default       => ($isOverdue ? 'Overdue' : 'Pending'),
    };
    $assetReqs = json_decode($item['asset_requirements'] ?? '[]', true) ?: [];
?>
<div class="mb-5 border border-slate-200 rounded-xl p-4 <?= $item['status'] === 'complete' || $item['status'] === 'confirmed' ? 'bg-emerald-50/50' : '' ?>">
    <div class="flex items-start justify-between gap-4 mb-3">
        <div>
            <p class="font-bold text-slate-900"><?= View::e($item['venue_name']) ?></p>
            <p class="text-xs text-slate-500 mt-0.5">
                Due: <?= View::date($item['due_date'], 'F j, Y') ?>
                · <?= ucfirst(str_replace('_', ' ', $item['submission_method'] ?? '')) ?>
            </p>
        </div>
        <span class="text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0 <?= match($item['status']) {
            'complete', 'confirmed' => 'bg-emerald-100 text-emerald-700',
            'submitted'             => 'bg-amber-100 text-amber-700',
            'rejected', 'no_response' => 'bg-red-100 text-red-700',
            'skipped'               => 'bg-slate-100 text-slate-600',
            default                 => ($isOverdue ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'),
        } ?>">
            <?= $statusLabel ?>
        </span>
    </div>

    <div class="text-sm space-y-1">
        <?php if ($item['submission_url']): ?>
        <p><span class="text-slate-500 font-medium">URL:</span> <a href="<?= View::e($item['submission_url']) ?>" class="text-indigo-700 underline break-all"><?= View::e($item['submission_url']) ?></a></p>
        <?php endif; ?>
        <?php if ($item['submission_email']): ?>
        <p><span class="text-slate-500 font-medium">Email:</span> <?= View::e($item['submission_email']) ?></p>
        <?php endif; ?>
        <?php if ($item['contact_name'] || $item['contact_email']): ?>
        <p><span class="text-slate-500 font-medium">Contact:</span> <?= View::e(implode(' — ', array_filter([$item['contact_name'], $item['contact_email']]))) ?></p>
        <?php endif; ?>
        <?php if (!empty($assetReqs)): ?>
        <p class="text-slate-500 font-medium mt-2">Assets required:</p>
        <ul class="list-disc list-inside ml-2 text-slate-700 space-y-0.5">
            <?php foreach ($assetReqs as $asset): ?>
            <li><?= View::e($asset['type']) ?><?= !empty($asset['specs']) ? ' — ' . View::e($asset['specs']) : '' ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($item['notes']): ?>
        <p class="text-slate-500 mt-2"><?= View::e($item['notes']) ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<div class="mt-8 pt-4 border-t border-slate-200 text-xs text-slate-400 text-center no-print">
    Generated by Dispatch · <?= date('F j, Y \a\t g:i A') ?>
</div>
