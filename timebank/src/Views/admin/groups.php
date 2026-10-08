<?php $pageTitle = 'Groups'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Groups</h1>
        <p class="text-sm text-gray-500 mt-1"><?= count($groups ?? []) ?> total groups</p>
    </div>
    <a href="<?= url('/groups/create') ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Group
    </a>
</div>

<?php if (!empty($groups)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Group Name</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Members</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Created By</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Created</th>
                    <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($groups as $g): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <a href="<?= url('/groups/' . ($g['id'] ?? '')) ?>"
                               class="font-medium text-gray-800 hover:text-teal-700 transition-colors">
                                <?= e($g['name'] ?? '') ?>
                            </a>
                            <?php if (!empty($g['description'])): ?>
                                <p class="text-xs text-gray-400 mt-0.5"><?= e(truncate($g['description'], 60)) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-right text-gray-600"><?= number_format((int)($g['member_count'] ?? 0)) ?></td>
                        <td class="px-5 py-3.5 text-gray-600">
                            <a href="<?= url('/admin/members/' . ($g['created_by'] ?? '')) ?>"
                               class="hover:text-teal-700 transition-colors">
                                <?= e($g['creator_name'] ?? '') ?>
                            </a>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">
                            <?= e(date('M j, Y', strtotime($g['created_at'] ?? ''))) ?>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span class="text-xs font-medium px-2.5 py-0.5 rounded-full <?= !empty($g['is_active']) ? 'bg-teal-50 text-teal-700' : 'bg-gray-100 text-gray-500' ?>">
                                <?= !empty($g['is_active']) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="<?= url('/groups/' . ($g['id'] ?? '')) ?>"
                                   class="text-xs text-teal-600 hover:text-teal-800 font-medium transition-colors">View</a>
                                <form method="POST" action="<?= url('/admin/groups/' . ($g['id'] ?? '') . '/delete') ?>"
                                      onsubmit="return confirm('Delete this group? Members will lose access to group messages.')">
                                    <?= csrf_field() ?>
                                    <button type="submit"
                                            class="text-xs text-red-500 hover:text-red-700 font-medium transition-colors">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No groups created yet.</p>
    </div>
<?php endif; ?>
