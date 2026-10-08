<?php $pageTitle = 'Donations'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Donations</h1>
        <p class="text-sm text-gray-500 mt-1">Community fund contribution tracking</p>
    </div>
    <form method="POST" action="<?= url('/admin/donations/request-all') ?>"
          onsubmit="return confirm('Send a donation request email to all active members?')">
        <?= csrf_field() ?>
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Request from All Members
        </button>
    </form>
</div>

<!-- Filter -->
<form method="GET" action="<?= url('/admin/donations') ?>"
      class="bg-white rounded-2xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
            <?= route_field('/admin/donations') ?>
    <select name="status" class="px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white">
        <option value="">All statuses</option>
        <option value="pending"  <?= ($_GET['status'] ?? '') === 'pending'  ? 'selected' : '' ?>>Pending</option>
        <option value="paid"     <?= ($_GET['status'] ?? '') === 'paid'     ? 'selected' : '' ?>>Paid</option>
        <option value="forgiven" <?= ($_GET['status'] ?? '') === 'forgiven' ? 'selected' : '' ?>>Forgiven</option>
    </select>
    <button type="submit" class="px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors">Filter</button>
    <?php if (!empty($_GET['status'])): ?>
        <a href="<?= url('/admin/donations') ?>" class="px-5 py-2.5 bg-gray-100 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">Clear</a>
    <?php endif; ?>
</form>

<?php if (!empty($donations)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[700px]">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Member</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Method</th>
                        <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Requested</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Paid / Forgiven</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($donations as $don): ?>
                        <?php
                        $statusStyles = match($don['status'] ?? 'pending') {
                            'paid'     => 'bg-teal-50 text-teal-700',
                            'forgiven' => 'bg-purple-50 text-purple-700',
                            default    => 'bg-amber-50 text-amber-700',
                        };
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <a href="<?= url('/admin/members/' . ($don['member_id'] ?? '')) ?>"
                                   class="font-medium text-gray-800 hover:text-teal-700 transition-colors">
                                    <?= e($don['member_name'] ?? '') ?>
                                </a>
                            </td>
                            <td class="px-5 py-3.5 text-right font-medium text-gray-700">
                                <?php if (!empty($don['amount_usd'])): ?>
                                    $<?= number_format((float)$don['amount_usd'], 2) ?>
                                <?php elseif (!empty($don['hours'])): ?>
                                    <?= number_format((float)$don['hours'], 2) ?> <?= e($tenant['currency_name'] ?? 'Hour') ?>s
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 capitalize"><?= e($don['payment_method'] ?? '') ?></td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="text-xs font-medium px-2.5 py-0.5 rounded-full <?= $statusStyles ?>">
                                    <?= e(ucfirst($don['status'] ?? 'pending')) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap"><?= e(date('M j, Y', strtotime($don['requested_at'] ?? ''))) ?></td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">
                                <?= !empty($don['paid_at']) ? e(date('M j, Y', strtotime($don['paid_at']))) : '—' ?>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <?php if (($don['status'] ?? '') === 'pending'): ?>
                                    <form method="POST" action="<?= url('/admin/donations/' . ($don['id'] ?? '') . '/forgive') ?>"
                                          onsubmit="return confirm('Mark this donation as forgiven?')">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                                class="text-xs text-purple-600 hover:text-purple-800 font-medium transition-colors">
                                            Forgive
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No donations recorded yet.</p>
    </div>
<?php endif; ?>
