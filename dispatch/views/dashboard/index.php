<?php
use Dispatch\Core\View;
$title = 'Dashboard';
// $stats: ['overdue'=>int, 'due_today'=>int, 'due_this_week'=>int, 'completed_this_month'=>int]
// $dueToday: array of action items due today or overdue
// $upcoming: array of action items due in next 7 days
// $campaigns: array of active campaigns
// $flyers: array of active flyer locations
?>

<!-- Stats cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <!-- Overdue -->
    <div class="bg-white rounded-2xl border <?= ($stats['overdue'] > 0) ? 'border-red-200 bg-red-50' : 'border-slate-200' ?> p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider <?= ($stats['overdue'] > 0) ? 'text-red-500' : 'text-slate-400' ?>">Overdue</span>
            <div class="w-8 h-8 <?= ($stats['overdue'] > 0) ? 'bg-red-100' : 'bg-slate-100' ?> rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 <?= ($stats['overdue'] > 0) ? 'text-red-500' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold <?= ($stats['overdue'] > 0) ? 'text-red-600' : 'text-slate-900' ?>"><?= $stats['overdue'] ?></div>
        <div class="text-xs text-slate-500 mt-1">need attention</div>
    </div>

    <!-- Due Today -->
    <div class="bg-white rounded-2xl border <?= ($stats['due_today'] > 0) ? 'border-amber-200 bg-amber-50' : 'border-slate-200' ?> p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider <?= ($stats['due_today'] > 0) ? 'text-amber-600' : 'text-slate-400' ?>">Due Today</span>
            <div class="w-8 h-8 <?= ($stats['due_today'] > 0) ? 'bg-amber-100' : 'bg-slate-100' ?> rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 <?= ($stats['due_today'] > 0) ? 'text-amber-600' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold <?= ($stats['due_today'] > 0) ? 'text-amber-600' : 'text-slate-900' ?>"><?= $stats['due_today'] ?></div>
        <div class="text-xs text-slate-500 mt-1">to submit today</div>
    </div>

    <!-- Due This Week -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">This Week</span>
            <div class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-slate-900"><?= $stats['due_this_week'] ?></div>
        <div class="text-xs text-slate-500 mt-1">upcoming deadlines</div>
    </div>

    <!-- Completed This Month -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Done This Month</span>
            <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-slate-900"><?= $stats['completed_this_month'] ?></div>
        <div class="text-xs text-slate-500 mt-1">submissions completed</div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <!-- Left: Action Items -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Urgent: Due Today & Overdue -->
        <?php if (!empty($dueToday)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                    Needs Attention Now
                    <span class="bg-red-100 text-red-600 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($dueToday) ?></span>
                </h2>
            </div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($dueToday as $item):
                    $isOverdue = $item['due_date'] < date('Y-m-d');
                ?>
                <div class="px-6 py-4 flex items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start gap-2 flex-wrap">
                            <span class="font-semibold text-slate-900 text-sm"><?= View::e($item['venue_name']) ?></span>
                            <?php if ($isOverdue): ?>
                                <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-0.5 rounded-full">Overdue</span>
                            <?php else: ?>
                                <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-0.5 rounded-full">Due Today</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <?= View::e($item['campaign_name']) ?> · Event: <?= View::date($item['event_date']) ?>
                        </p>
                        <?php if (!empty($item['asset_requirements'])): ?>
                            <?php $assets = json_decode($item['asset_requirements'], true) ?? []; ?>
                            <?php if (!empty($assets)): ?>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <?php foreach ($assets as $asset): ?>
                                <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-md">
                                    <?= View::e($asset['type']) ?><?= !empty($asset['specs']) ? ': ' . View::e($asset['specs']) : '' ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="flex items-center gap-3 mt-2">
                            <?php if (!empty($item['submission_url'])): ?>
                            <a href="<?= View::e($item['submission_url']) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                Open Portal
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                            <?php elseif (!empty($item['submission_email'])): ?>
                            <a href="mailto:<?= View::e($item['submission_email']) ?>" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                Email: <?= View::e($item['submission_email']) ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="POST" action="<?= $_base ?>/campaigns/<?= $item['campaign_id'] ?>/action-items/<?= $item['id'] ?>/complete" class="flex-shrink-0">
                        <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            Done
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Upcoming this week -->
        <?php if (!empty($upcoming)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="font-semibold text-slate-900">Coming Up This Week</h2>
            </div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($upcoming as $item): ?>
                <div class="px-6 py-3.5 flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-800 text-sm"><?= View::e($item['venue_name']) ?></span>
                            <span class="text-xs text-slate-400">·</span>
                            <span class="text-xs text-slate-500"><?= View::e($item['campaign_name']) ?></span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">Due <?= View::date($item['due_date']) ?> · Event <?= View::date($item['event_date']) ?></p>
                    </div>
                    <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full whitespace-nowrap">
                        <?= View::relativeDate($item['due_date']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($dueToday) && empty($upcoming)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="text-5xl mb-4">🎉</div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">All caught up!</h3>
            <p class="text-slate-500 text-sm mb-6">No upcoming submission deadlines in the next 7 days.</p>
            <a href="<?= $_base ?>/campaigns/create" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors">
                + New Campaign
            </a>
        </div>
        <?php endif; ?>

        <!-- Follow-Up Reminders -->
        <?php if (!empty($followUps)): ?>
        <div class="bg-white rounded-2xl border border-amber-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-amber-100 flex items-center justify-between bg-amber-50/50">
                <h2 class="font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2 h-2 bg-amber-400 rounded-full"></span>
                    Follow-Up Reminders
                    <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($followUps) ?></span>
                </h2>
                <span class="text-xs text-slate-400">Awaiting confirmation</span>
            </div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($followUps as $item): ?>
                <div class="px-6 py-4 flex items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-slate-900 text-sm"><?= View::e($item['venue_name']) ?></span>
                            <span class="bg-amber-100 text-amber-700 text-xs font-semibold px-2 py-0.5 rounded-full">Submitted</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <?= View::e($item['campaign_name']) ?> · Follow-up due: <strong><?= View::date($item['follow_up_date']) ?></strong>
                        </p>
                        <?php if (!empty($item['submission_url'])): ?>
                        <a href="<?= View::e($item['submission_url']) ?>" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium mt-1 inline-block">Check status →</a>
                        <?php elseif (!empty($item['contact_email'])): ?>
                        <a href="mailto:<?= View::e($item['contact_email']) ?>" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium mt-1 inline-block">Email contact →</a>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                        <form method="POST" action="<?= $_base ?>/campaigns/<?= $item['campaign_id'] ?>/action-items/<?= $item['id'] ?>/confirm">
                            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                            <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors">Confirmed</button>
                        </form>
                        <form method="POST" action="<?= $_base ?>/campaigns/<?= $item['campaign_id'] ?>/action-items/<?= $item['id'] ?>/no-response">
                            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                            <button type="submit" class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors">No Response</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Assigned to Me -->
        <?php if (!empty($assigned)): ?>
        <div class="bg-white rounded-2xl border border-violet-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-violet-100 bg-violet-50/50">
                <h2 class="font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2 h-2 bg-violet-500 rounded-full"></span>
                    Assigned to Me
                    <span class="bg-violet-100 text-violet-700 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($assigned) ?></span>
                </h2>
            </div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($assigned as $item): ?>
                <div class="px-6 py-3.5 flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-slate-800 text-sm"><?= View::e($item['venue_name']) ?></span>
                            <span class="text-xs text-slate-400">·</span>
                            <span class="text-xs text-slate-500"><?= View::e($item['campaign_name']) ?></span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">Due <?= View::date($item['due_date']) ?></p>
                    </div>
                    <a href="<?= $_base ?>/campaigns/<?= $item['campaign_id'] ?>" class="text-xs font-medium text-violet-600 hover:text-violet-700 flex-shrink-0">
                        View →
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: Sidebar -->
    <div class="space-y-6">
        <!-- Campaigns -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900 text-sm">Active Campaigns</h2>
                <a href="<?= $_base ?>/campaigns/create" class="text-xs text-indigo-600 font-semibold hover:text-indigo-700">+ New</a>
            </div>
            <?php if (!empty($campaigns)): ?>
            <div class="divide-y divide-slate-50">
                <?php foreach (array_slice($campaigns, 0, 5) as $c): ?>
                <a href="<?= $_base ?>/campaigns/<?= $c['id'] ?>" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
                    <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900 truncate"><?= View::e($c['name']) ?></p>
                        <p class="text-xs text-slate-400"><?= View::date($c['event_date']) ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="px-5 py-3 border-t border-slate-100">
                <a href="<?= $_base ?>/campaigns" class="text-xs text-indigo-600 font-medium hover:text-indigo-700">View all campaigns →</a>
            </div>
            <?php else: ?>
            <div class="px-5 py-8 text-center">
                <p class="text-sm text-slate-400 mb-3">No campaigns yet</p>
                <a href="<?= $_base ?>/campaigns/create" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Create your first →</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Active Flyers -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900 text-sm">Active Flyers</h2>
                <a href="<?= $_base ?>/flyers/create" class="text-xs text-indigo-600 font-semibold hover:text-indigo-700">+ Log</a>
            </div>
            <?php if (!empty($flyers)): ?>
            <div class="divide-y divide-slate-50">
                <?php foreach (array_slice($flyers, 0, 4) as $flyer): ?>
                <div class="px-5 py-3 flex items-center gap-3">
                    <div class="w-2 h-2 bg-emerald-400 rounded-full flex-shrink-0"></div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900 truncate"><?= View::e($flyer['location_name']) ?></p>
                        <?php if ($flyer['posted_at']): ?>
                        <p class="text-xs text-slate-400">Posted <?= View::date($flyer['posted_at'], 'M j') ?> · <?= $flyer['quantity'] ?> flyer<?= $flyer['quantity'] !== 1 ? 's' : '' ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="px-5 py-3 border-t border-slate-100">
                <a href="<?= $_base ?>/flyers" class="text-xs text-indigo-600 font-medium hover:text-indigo-700">View all locations →</a>
            </div>
            <?php else: ?>
            <div class="px-5 py-8 text-center">
                <p class="text-sm text-slate-400 mb-3">No flyers tracked yet</p>
                <a href="<?= $_base ?>/flyers/create" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Log a location →</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
