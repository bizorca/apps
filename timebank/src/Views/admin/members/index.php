<?php $pageTitle = 'Members'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Members</h1>
        <p class="text-sm text-gray-500 mt-1"><?= number_format((int)($members['total'] ?? 0)) ?> total</p>
    </div>
    <a href="<?= url('/admin/members/create') ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Member
    </a>
</div>

<!-- Filter bar -->
<form method="GET" action="<?= url('/admin/members') ?>"
      class="bg-white rounded-2xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
            <?= route_field('/admin/members') ?>
    <div class="flex-1 min-w-[180px]">
        <input type="text" name="search" value="<?= e($filters['search'] ?? '') ?>"
               placeholder="Search name or email..."
               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
    </div>
    <select name="role" class="px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white">
        <option value="">All roles</option>
        <option value="member"      <?= ($filters['role'] ?? '') === 'member'      ? 'selected' : '' ?>>Member</option>
        <option value="admin"       <?= ($filters['role'] ?? '') === 'admin'       ? 'selected' : '' ?>>Admin</option>
        <option value="super_admin" <?= ($filters['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
    </select>
    <select name="status" class="px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white">
        <option value="">All statuses</option>
        <option value="active"   <?= ($filters['status'] ?? '') === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        <option value="pending"  <?= ($filters['status'] ?? '') === 'pending'  ? 'selected' : '' ?>>Pending Approval</option>
    </select>
    <button type="submit"
            class="px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors">
        Filter
    </button>
    <?php if (!empty(array_filter($filters ?? []))): ?>
        <a href="<?= url('/admin/members') ?>"
           class="px-5 py-2.5 bg-gray-100 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
            Clear
        </a>
    <?php endif; ?>
</form>

<!-- Members table -->
<?php if (!empty($members['data'])): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[750px]">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Member</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Balance</th>
                        <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Joined</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($members['data'] as $m): ?>
                        <?php
                        $isActive   = !empty($m['is_active']);
                        $isApproved = !empty($m['is_approved']);
                        if (!$isApproved) {
                            $statusLabel = 'Pending';
                            $statusStyle = 'bg-amber-50 text-amber-700';
                        } elseif ($isActive) {
                            $statusLabel = 'Active';
                            $statusStyle = 'bg-teal-50 text-teal-700';
                        } else {
                            $statusLabel = 'Inactive';
                            $statusStyle = 'bg-gray-100 text-gray-500';
                        }
                        $roleStyles = match($m['role'] ?? 'member') {
                            'super_admin' => 'bg-purple-100 text-purple-700',
                            'admin'       => 'bg-blue-100 text-blue-700',
                            default       => 'bg-gray-100 text-gray-600',
                        };
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($m['avatar_path'])): ?>
                                        <img src="<?= e(media_url($m['avatar_path'])) ?>" alt="" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                                    <?php else: ?>
                                        <div class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-semibold text-sm flex-shrink-0">
                                            <?= e(mb_strtoupper(mb_substr($m['first_name'] ?? 'M', 0, 1))) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm">
                                            <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-sm"><?= e($m['email'] ?? '') ?></td>
                            <td class="px-5 py-3.5">
                                <span class="text-xs font-medium px-2.5 py-0.5 rounded-full <?= $roleStyles ?>">
                                    <?= e(str_replace('_', ' ', ucfirst($m['role'] ?? 'member'))) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right text-sm font-medium text-gray-700">
                                <?= number_format((float)($m['balance'] ?? 0), 2) ?>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="text-xs font-medium px-2.5 py-0.5 rounded-full <?= $statusStyle ?>">
                                    <?= $statusLabel ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">
                                <?= e(date('M j, Y', strtotime($m['created_at'] ?? ''))) ?>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?= url('/admin/members/' . ($m['id'] ?? '')) ?>"
                                       class="text-xs text-teal-600 hover:text-teal-800 font-medium transition-colors">View</a>
                                    <form method="POST" action="<?= url('/admin/members/' . ($m['id'] ?? '') . '/toggle') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                                class="text-xs <?= $isActive ? 'text-red-500 hover:text-red-700' : 'text-teal-600 hover:text-teal-800' ?> font-medium transition-colors">
                                            <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <?php if (!$isApproved): ?>
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
    </div>

    <!-- Pagination -->
    <?php if (($members['pages'] ?? 1) > 1): ?>
        <div class="flex items-center justify-center gap-2">
            <?php
            $currentPage = (int)($members['current'] ?? 1);
            $totalPages  = (int)($members['pages'] ?? 1);
            $baseParams  = array_filter($filters ?? []);
            ?>
            <?php if ($currentPage > 1): ?>
                <a href="<?= url('/admin/members?' . http_build_query(array_merge($baseParams, ['page' => $currentPage - 1]))) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">&lsaquo; Prev</a>
            <?php endif; ?>
            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                <a href="<?= url('/admin/members?' . http_build_query(array_merge($baseParams, ['page' => $p]))) ?>"
                   class="px-3 py-2 text-sm rounded-lg transition-colors <?= $p === $currentPage ? 'bg-teal-600 text-white font-semibold' : 'text-gray-600 bg-white border border-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= url('/admin/members?' . http_build_query(array_merge($baseParams, ['page' => $currentPage + 1]))) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Next &rsaquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No members match the current filters.</p>
    </div>
<?php endif; ?>
