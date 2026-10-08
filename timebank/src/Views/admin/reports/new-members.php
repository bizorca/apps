<?php $pageTitle = 'New Members'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="<?= url('/admin/reports') ?>" class="hover:text-teal-600 transition-colors">Reports</a>
            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900">New Members</h1>
        <p class="text-sm text-gray-500 mt-1">
            Members who joined in the last 30 days
            <?php if (!empty($members)): ?>
                &mdash; <?= count($members) ?> new member<?= count($members) != 1 ? 's' : '' ?>
            <?php endif; ?>
        </p>
    </div>
    <?php if (!empty($members)): ?>
        <a href="<?= url('/admin/reports/new-members?export=csv') ?>"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-xl hover:bg-gray-900 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export CSV
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($members)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Member</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Joined</th>
                    <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Approval</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($members as $m): ?>
                    <?php $approved = !empty($m['is_approved']); ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-semibold text-sm flex-shrink-0">
                                    <?= e(mb_strtoupper(mb_substr($m['first_name'] ?? 'M', 0, 1))) ?>
                                </div>
                                <a href="<?= url('/admin/members/' . ($m['id'] ?? '')) ?>"
                                   class="font-medium text-gray-800 hover:text-teal-700 transition-colors">
                                    <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                                </a>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500"><?= e($m['email'] ?? '') ?></td>
                        <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap text-xs">
                            <?= e(date('F j, Y', strtotime($m['created_at'] ?? ''))) ?>
                            <span class="text-gray-400 ml-1">(<?= e(time_ago($m['created_at'] ?? '')) ?>)</span>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <?php if ($approved): ?>
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Approved
                                </span>
                            <?php else: ?>
                                <span class="inline-block text-xs font-medium text-amber-700 bg-amber-50 px-2.5 py-0.5 rounded-full">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="<?= url('/admin/members/' . ($m['id'] ?? '')) ?>"
                                   class="text-xs text-teal-600 hover:text-teal-800 font-medium transition-colors">View</a>
                                <?php if (!$approved): ?>
                                    <form method="POST" action="<?= url('/admin/members/' . ($m['id'] ?? '') . '/approve') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                                class="text-xs text-amber-600 hover:text-amber-800 font-medium transition-colors">
                                            Approve
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No new members in the last 30 days.</p>
    </div>
<?php endif; ?>
