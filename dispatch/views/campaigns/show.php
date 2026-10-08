<?php
use Dispatch\Core\View;
// $campaign, $actionItems, $venues, $allUsers, $estimatedTotal, $actualTotal
$isPastEvent = $campaign['event_date'] < date('Y-m-d');
?>
<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <a href="<?= $_base ?>/campaigns" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1 mb-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            All Campaigns
        </a>
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-slate-900"><?= View::e($campaign['name']) ?></h1>
            <span class="bg-indigo-100 text-indigo-700 text-sm font-medium px-3 py-1 rounded-full"><?= View::date($campaign['event_date']) ?></span>
        </div>
        <?php if ($campaign['location']): ?>
        <p class="text-slate-500 text-sm mt-1">📍 <?= View::e($campaign['location']) ?></p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-2 flex-shrink-0">
        <a href="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/packet" class="inline-flex items-center gap-1.5 px-3 py-2 border border-slate-200 text-slate-600 text-sm font-medium rounded-xl hover:bg-slate-50 transition-colors" title="Print Packet">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Packet
        </a>
        <a href="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/clone" class="inline-flex items-center gap-1.5 px-3 py-2 border border-slate-200 text-slate-600 text-sm font-medium rounded-xl hover:bg-slate-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            Clone
        </a>
        <a href="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/edit" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 text-slate-700 text-sm font-medium rounded-xl hover:bg-slate-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit
        </a>
        <?php if ($isPastEvent && $campaign['status'] === 'active'): ?>
        <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/archive" onsubmit="return confirm('Archive this campaign?')">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 text-slate-600 text-sm font-medium rounded-xl hover:bg-slate-50 transition-colors">
                Archive
            </button>
        </form>
        <?php endif; ?>
        <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/delete" onsubmit="return confirm('Delete this campaign and all its action items?')">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 border border-red-200 text-red-600 text-sm font-medium rounded-xl hover:bg-red-50 transition-colors">
                Delete
            </button>
        </form>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <!-- Action Items -->
    <div class="lg:col-span-2 space-y-4">

        <!-- Post-event notes (past events only) -->
        <?php if ($isPastEvent): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Post-Event Notes</h3>
            <?php if ($campaign['post_event_notes'] || $campaign['post_event_rating']): ?>
            <?php if ($campaign['post_event_rating']): ?>
            <div class="flex items-center gap-1 mb-2">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <svg class="w-4 h-4 <?= $i <= $campaign['post_event_rating'] ? 'text-amber-400' : 'text-slate-200' ?>" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php if ($campaign['post_event_notes']): ?>
            <p class="text-sm text-slate-600 leading-relaxed"><?= nl2br(View::e($campaign['post_event_notes'])) ?></p>
            <?php endif; ?>
            <details class="mt-3">
                <summary class="text-xs text-indigo-600 cursor-pointer font-medium">Edit notes</summary>
                <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>" class="mt-3 space-y-3">
                    <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                    <input type="hidden" name="name" value="<?= View::e($campaign['name']) ?>">
                    <input type="hidden" name="event_date" value="<?= View::e($campaign['event_date']) ?>">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Rating (1–5)</label>
                        <input type="number" name="post_event_rating" min="1" max="5" value="<?= View::e($campaign['post_event_rating'] ?? '') ?>"
                            class="w-24 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div>
                        <textarea name="post_event_notes" rows="3"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-400"><?= View::e($campaign['post_event_notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700">Save</button>
                </form>
            </details>
            <?php else: ?>
            <p class="text-xs text-slate-400 mb-3">How did it go? Add notes for next time.</p>
            <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>">
                <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                <input type="hidden" name="name" value="<?= View::e($campaign['name']) ?>">
                <input type="hidden" name="event_date" value="<?= View::e($campaign['event_date']) ?>">
                <div class="mb-3">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Rating (1–5)</label>
                    <input type="number" name="post_event_rating" min="1" max="5" placeholder="4"
                        class="w-24 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <textarea name="post_event_notes" rows="3" placeholder="What worked, what to improve next time..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-400 mb-3"></textarea>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700">Save Notes</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Action Plan -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-slate-900">Action Plan</h2>
                    <p class="text-xs text-slate-400 mt-0.5"><?= count($actionItems) ?> submission tasks total</p>
                </div>
                <?php if (!empty($allUsers) && !empty($actionItems)): ?>
                <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/assign-all" class="flex items-center gap-2">
                    <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                    <select name="assigned_to_user_id" class="px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <option value="">Unassigned</option>
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= View::e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="px-2.5 py-1.5 bg-violet-600 text-white text-xs font-semibold rounded-lg hover:bg-violet-700 whitespace-nowrap">Assign all</button>
                </form>
                <?php endif; ?>
            </div>

            <?php if (empty($actionItems)): ?>
            <div class="p-12 text-center">
                <p class="text-slate-500 mb-4">No action items yet. Edit this campaign to add venues.</p>
                <a href="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/edit" class="text-indigo-600 font-semibold text-sm">Add Venues →</a>
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-50">
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
                    $badgeClass = match(true) {
                        $item['status'] === 'complete' || $item['status'] === 'confirmed' => 'bg-emerald-100 text-emerald-700',
                        $item['status'] === 'submitted'                                   => 'bg-amber-100 text-amber-700',
                        $item['status'] === 'rejected' || $item['status'] === 'no_response' => 'bg-red-100 text-red-700',
                        $item['status'] === 'skipped'                                     => 'bg-slate-100 text-slate-600',
                        $isOverdue                                                        => 'bg-red-100 text-red-700',
                        default                                                           => 'bg-blue-100 text-blue-700',
                    };
                    $isDone = in_array($item['status'], ['complete', 'confirmed', 'skipped']);
                    $assetReqs = json_decode($item['asset_requirements'] ?? '[]', true) ?: [];
                ?>
                <div class="px-6 py-4 <?= $isDone ? 'opacity-60' : '' ?>">
                    <div class="flex items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-slate-900 text-sm <?= $isDone ? 'line-through text-slate-500' : '' ?>">
                                    <?= View::e($item['venue_name']) ?>
                                </span>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $badgeClass ?>">
                                    <?= $statusLabel ?>
                                </span>
                                <?php if ($item['assigned_to_name']): ?>
                                <span class="text-xs text-violet-600 bg-violet-50 px-2 py-0.5 rounded-full font-medium">
                                    <?= View::e($item['assigned_to_name']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Due: <strong><?= View::date($item['due_date']) ?></strong>
                                <span class="mx-1">·</span>
                                <?= View::relativeDate($item['due_date']) ?>
                            </p>

                            <!-- Submission link -->
                            <?php if (!empty($item['submission_url'])): ?>
                            <a href="<?= View::e($item['submission_url']) ?>" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium mt-1 inline-flex items-center gap-1">
                                Submit here
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                            <?php elseif (!empty($item['submission_email'])): ?>
                            <a href="mailto:<?= View::e($item['submission_email']) ?>" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium mt-1 inline-block">
                                <?= View::e($item['submission_email']) ?>
                            </a>
                            <?php endif; ?>

                            <!-- Contact info -->
                            <?php if ($item['contact_name'] || $item['contact_email']): ?>
                            <p class="text-xs text-slate-400 mt-1">
                                Contact: <?php if ($item['contact_name']): ?><span class="text-slate-600"><?= View::e($item['contact_name']) ?></span><?php endif; ?>
                                <?php if ($item['contact_email']): ?> · <a href="mailto:<?= View::e($item['contact_email']) ?>" class="text-indigo-600 hover:text-indigo-700"><?= View::e($item['contact_email']) ?></a><?php endif; ?>
                            </p>
                            <?php endif; ?>

                            <!-- Asset requirements -->
                            <?php if (!empty($assetReqs)): ?>
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                <?php foreach ($assetReqs as $asset): ?>
                                <span class="inline-flex items-center bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-md">
                                    <?= View::e($asset['type']) ?><?= !empty($asset['specs']) ? ': ' . View::e($asset['specs']) : '' ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Target publication date -->
                            <?php if (!empty($item['target_publication_date'])): ?>
                            <p class="text-xs text-slate-400 mt-1">
                                Target issue: <strong class="text-slate-600"><?= View::date($item['target_publication_date']) ?></strong>
                            </p>
                            <?php endif; ?>

                            <!-- Budget row -->
                            <?php if ($item['estimated_cost'] !== null || $item['actual_cost'] !== null): ?>
                            <p class="text-xs text-slate-400 mt-1">
                                <?php if ($item['estimated_cost'] !== null): ?>Est: $<?= number_format((float)$item['estimated_cost'], 2) ?><?php endif; ?>
                                <?php if ($item['actual_cost'] !== null): ?> · Actual: $<?= number_format((float)$item['actual_cost'], 2) ?><?php endif; ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex-shrink-0 flex flex-col gap-1.5">
                            <?php if (in_array($item['status'], ['pending', 'overdue']) || $isOverdue): ?>
                            <div class="flex gap-1.5">
                                <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/<?= $item['id'] ?>/submit">
                                    <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                    <button type="submit" class="px-2.5 py-1.5 bg-amber-500 text-white text-xs font-semibold rounded-lg hover:bg-amber-600 transition-colors whitespace-nowrap">Submitted</button>
                                </form>
                                <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/<?= $item['id'] ?>/complete">
                                    <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                    <button type="submit" class="px-2.5 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors">Done</button>
                                </form>
                                <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/<?= $item['id'] ?>/skip">
                                    <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                    <button type="submit" class="px-2.5 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors">Skip</button>
                                </form>
                            </div>
                            <?php elseif ($item['status'] === 'submitted'): ?>
                            <div class="flex gap-1.5">
                                <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/<?= $item['id'] ?>/confirm">
                                    <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                    <button type="submit" class="px-2.5 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors">Confirmed</button>
                                </form>
                                <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/<?= $item['id'] ?>/reject">
                                    <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                    <button type="submit" class="px-2.5 py-1.5 bg-red-100 text-red-600 text-xs font-medium rounded-lg hover:bg-red-200 transition-colors">Rejected</button>
                                </form>
                                <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/<?= $item['id'] ?>/no-response">
                                    <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                    <button type="submit" class="px-2.5 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors whitespace-nowrap">No Reply</button>
                                </form>
                            </div>
                            <?php elseif (in_array($item['status'], ['complete', 'confirmed', 'rejected', 'no_response', 'skipped'])): ?>
                            <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/<?= $item['id'] ?>/reopen">
                                <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                <button type="submit" class="px-2.5 py-1.5 bg-slate-100 text-slate-500 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors">Reopen</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Expandable: Assignment + Cost -->
                    <details class="mt-2">
                        <summary class="text-xs text-slate-400 cursor-pointer hover:text-slate-600 select-none">More options</summary>
                        <div class="mt-3 grid sm:grid-cols-2 gap-3 pl-1">
                            <!-- Assign to user -->
                            <?php if (!empty($allUsers)): ?>
                            <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/<?= $item['id'] ?>/assign" class="flex items-center gap-2">
                                <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                <select name="assigned_to_user_id" class="flex-1 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($allUsers as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= (int)($item['assigned_to_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= View::e($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="px-2 py-1.5 bg-violet-600 text-white text-xs font-semibold rounded-lg hover:bg-violet-700">Assign</button>
                            </form>
                            <?php endif; ?>

                            <!-- Costs -->
                            <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/<?= $item['id'] ?>/costs" class="flex items-center gap-2">
                                <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                <input type="number" name="estimated_cost" step="0.01" min="0" placeholder="Est $"
                                    value="<?= $item['estimated_cost'] !== null ? View::e($item['estimated_cost']) : '' ?>"
                                    class="w-20 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                <input type="number" name="actual_cost" step="0.01" min="0" placeholder="Act $"
                                    value="<?= $item['actual_cost'] !== null ? View::e($item['actual_cost']) : '' ?>"
                                    class="w-20 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                <button type="submit" class="px-2 py-1.5 bg-slate-600 text-white text-xs font-semibold rounded-lg hover:bg-slate-700">Save costs</button>
                            </form>

                            <!-- Target publication date -->
                            <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/action-items/<?= $item['id'] ?>/publication-date" class="flex items-center gap-2 sm:col-span-2">
                                <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                <label class="text-xs text-slate-500 whitespace-nowrap">Target issue:</label>
                                <input type="date" name="target_publication_date"
                                    value="<?= View::e($item['target_publication_date'] ?? '') ?>"
                                    class="flex-1 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                <button type="submit" class="px-2 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700">Set</button>
                            </form>
                        </div>
                    </details>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Budget totals -->
            <?php if ($estimatedTotal > 0 || $actualTotal > 0): ?>
            <div class="px-6 py-3 border-t border-slate-100 bg-slate-50 flex items-center gap-6 text-sm">
                <?php if ($estimatedTotal > 0): ?>
                <span class="text-slate-500">Estimated: <strong class="text-slate-900">$<?= number_format($estimatedTotal, 2) ?></strong></span>
                <?php endif; ?>
                <?php if ($actualTotal > 0): ?>
                <span class="text-slate-500">Actual: <strong class="text-slate-900">$<?= number_format($actualTotal, 2) ?></strong></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Documents -->
        <div class="bg-white rounded-2xl border border-slate-200">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Documents</h2>
                <a href="<?= $_base ?>/documents" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">View all</a>
            </div>

            <?php if (!empty($documents)): ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($documents as $doc): ?>
                <?php include DP_ROOT . '/views/documents/_row.php'; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Upload form -->
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50">
                <details>
                    <summary class="text-xs text-slate-500 cursor-pointer hover:text-slate-700 font-medium select-none">+ Add document</summary>
                    <form method="POST" action="<?= $_base ?>/documents" enctype="multipart/form-data" class="mt-3 space-y-3">
                        <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                        <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                        <input type="text" name="name" required placeholder="Document name"
                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <textarea name="content" rows="3" placeholder="Paste text copy here (optional if uploading a file)..."
                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs resize-y focus:outline-none focus:ring-1 focus:ring-indigo-400"></textarea>
                        <div class="flex items-center gap-3 flex-wrap">
                            <input type="file" name="document_file"
                                accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.doc,.docx,.txt,.rtf"
                                class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <label class="flex items-center gap-1.5 cursor-pointer select-none">
                                <input type="checkbox" name="is_template" value="1" class="w-3.5 h-3.5 text-indigo-600 border-slate-300 rounded">
                                <span class="text-xs text-slate-600">Share as template</span>
                            </label>
                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700">Save</button>
                        </div>
                    </form>
                </details>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-5">
        <!-- Campaign Details -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Campaign Details</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-xs text-slate-400 font-medium">Event Date</dt>
                    <dd class="text-sm text-slate-900 mt-0.5"><?= View::date($campaign['event_date'], 'l, F j, Y') ?></dd>
                </div>
                <?php if ($campaign['event_time']): ?>
                <div>
                    <dt class="text-xs text-slate-400 font-medium">Time</dt>
                    <dd class="text-sm text-slate-900 mt-0.5"><?= date('g:i A', strtotime($campaign['event_time'])) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($campaign['location']): ?>
                <div>
                    <dt class="text-xs text-slate-400 font-medium">Location</dt>
                    <dd class="text-sm text-slate-900 mt-0.5"><?= View::e($campaign['location']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($campaign['asset_link']): ?>
                <div>
                    <dt class="text-xs text-slate-400 font-medium">Assets</dt>
                    <dd class="mt-0.5">
                        <a href="<?= View::e($campaign['asset_link']) ?>" target="_blank" rel="noopener" class="text-sm text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1 font-medium">
                            Open asset folder
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    </dd>
                </div>
                <?php endif; ?>
                <div>
                    <dt class="text-xs text-slate-400 font-medium">Status</dt>
                    <dd class="mt-0.5"><span class="bg-emerald-100 text-emerald-700 text-xs font-medium px-2 py-0.5 rounded-full"><?= $campaign['status'] ?></span></dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 font-medium">Progress</dt>
                    <dd class="mt-1.5">
                        <?php
                        $total = count($actionItems);
                        $done  = count(array_filter($actionItems, fn($a) => in_array($a['status'], ['complete', 'confirmed'])));
                        $pct   = $total > 0 ? round(($done / $total) * 100) : 0;
                        ?>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-slate-200 rounded-full h-2">
                                <div class="bg-emerald-500 rounded-full h-2 transition-all" style="width:<?= $pct ?>%"></div>
                            </div>
                            <span class="text-xs text-slate-500"><?= $done ?>/<?= $total ?></span>
                        </div>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Targeted Venues -->
        <?php if (!empty($venues)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Targeted Venues (<?= count($venues) ?>)</h3>
            <div class="space-y-2">
                <?php foreach ($venues as $v): ?>
                <div class="flex items-center gap-2 text-sm">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full flex-shrink-0"></span>
                    <span class="text-slate-700"><?= View::e($v['name']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($campaign['description']): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Description</h3>
            <p class="text-sm text-slate-600 leading-relaxed"><?= nl2br(View::e($campaign['description'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>
