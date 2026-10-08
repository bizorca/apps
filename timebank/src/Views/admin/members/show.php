<?php
$pageTitle = ($member['display_name'] ?: trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) . ' — Admin';
$currencyName   = $tenant['currency_name'] ?? 'Hour';
$currencyPlural = $tenant['currency_name_plural'] ?? 'Hours';
$isActive = !empty($member['is_active']);
?>

<div class="max-w-4xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <nav class="flex items-center gap-2 text-sm text-gray-500">
            <a href="<?= url('/admin/members') ?>" class="hover:text-teal-600 transition-colors">Members</a>
            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-800 font-medium"><?= e($member['first_name'] ?? '') ?> <?= e($member['last_name'] ?? '') ?></span>
        </nav>
    </div>

    <!-- Member header -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <?php if (!empty($member['avatar_path'])): ?>
                    <img src="<?= e(media_url($member['avatar_path'])) ?>" alt="" class="w-16 h-16 rounded-2xl object-cover">
                <?php else: ?>
                    <div class="w-16 h-16 rounded-2xl bg-teal-100 flex items-center justify-center text-teal-600 font-bold text-2xl">
                        <?= e(mb_strtoupper(mb_substr($member['first_name'] ?? 'M', 0, 1))) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">
                        <?= e($member['display_name'] ?: trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?>
                    </h1>
                    <p class="text-sm text-gray-500"><?= e($member['email'] ?? '') ?></p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <?php
                        $roleStyles = match($member['role'] ?? 'member') {
                            'super_admin' => 'bg-purple-100 text-purple-700',
                            'admin'       => 'bg-blue-100 text-blue-700',
                            default       => 'bg-gray-100 text-gray-600',
                        };
                        ?>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full <?= $roleStyles ?>">
                            <?= e(str_replace('_', ' ', ucfirst($member['role'] ?? 'member'))) ?>
                        </span>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full <?= $isActive ? 'bg-teal-50 text-teal-700' : 'bg-red-50 text-red-600' ?>">
                            <?= $isActive ? 'Active' : 'Inactive' ?>
                        </span>
                        <?php if (empty($member['is_approved'])): ?>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">Pending Approval</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="<?= url('/members/' . ($member['id'] ?? '') . '/edit') ?>"
                   class="inline-flex items-center gap-2 px-3 py-2 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                    Edit Profile
                </a>
                <form method="POST" action="<?= url('/admin/members/' . ($member['id'] ?? '') . '/toggle') ?>">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm rounded-xl transition-colors <?= $isActive ? 'text-red-600 bg-red-50 hover:bg-red-100' : 'text-teal-600 bg-teal-50 hover:bg-teal-100' ?>">
                        <?= $isActive ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
                <?php if (empty($member['is_approved'])): ?>
                    <form method="POST" action="<?= url('/admin/members/' . ($member['id'] ?? '') . '/approve') ?>">
                        <?= csrf_field() ?>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 text-sm text-amber-700 bg-amber-50 rounded-xl hover:bg-amber-100 transition-colors">
                            Approve
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5 pt-5 border-t border-gray-100">
            <div class="text-center">
                <p class="text-2xl font-bold text-teal-600"><?= number_format((float)($member['balance'] ?? 0), 2) ?></p>
                <p class="text-xs text-gray-500 mt-0.5"><?= e($currencyPlural) ?> Balance</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-700"><?= number_format((int)($memberStats['total_transactions'] ?? 0)) ?></p>
                <p class="text-xs text-gray-500 mt-0.5">Transactions</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-700"><?= number_format((float)($memberStats['hours_given'] ?? 0), 2) ?></p>
                <p class="text-xs text-gray-500 mt-0.5"><?= e($currencyPlural) ?> Given</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-700"><?= number_format((float)($memberStats['hours_received'] ?? 0), 2) ?></p>
                <p class="text-xs text-gray-500 mt-0.5"><?= e($currencyPlural) ?> Received</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left: details -->
        <div class="space-y-5">

            <!-- Info -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Account Details</h3>
                <dl class="space-y-2 text-sm">
                    <?php
                    $details = [
                        'Member ID'    => '#' . ($member['id'] ?? ''),
                        'Joined'       => !empty($member['created_at']) ? date('F j, Y', strtotime($member['created_at'])) : '—',
                        'Last Login'   => !empty($member['last_login_at']) ? date('M j, Y g:i a', strtotime($member['last_login_at'])) : 'Never',
                        'City'         => ($member['city'] ?? '') . (!empty($member['state']) ? ', ' . $member['state'] : ''),
                        'Phone'        => $member['phone'] ?? '—',
                    ];
                    ?>
                    <?php foreach ($details as $label => $value): ?>
                        <div class="flex items-start justify-between gap-2">
                            <dt class="text-gray-500 flex-shrink-0"><?= e($label) ?></dt>
                            <dd class="text-gray-800 font-medium text-right"><?= e($value) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>

            <!-- Record hours on behalf -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Record Hours on Behalf</h3>
                <a href="<?= url('/transactions/record?provider=' . ($member['id'] ?? '')) ?>"
                   class="block w-full text-center px-4 py-2.5 bg-teal-50 text-teal-700 text-sm font-medium rounded-xl hover:bg-teal-100 transition-colors">
                    Record Exchange
                </a>
            </div>

        </div>

        <!-- Right: transactions + endorsements -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Transactions -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Transaction History</h3>
                    <a href="<?= url('/admin/transactions?member=' . ($member['id'] ?? '')) ?>"
                       class="text-xs text-teal-600 hover:text-teal-800 font-medium">View all</a>
                </div>
                <?php if (!empty($transactions)): ?>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach (array_slice($transactions, 0, 10) as $tx): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3 text-xs text-gray-400 whitespace-nowrap"><?= e(date('M j, Y', strtotime($tx['service_date'] ?? $tx['created_at'] ?? ''))) ?></td>
                                    <td class="px-5 py-3 text-gray-700 text-xs"><span class="truncate block max-w-xs"><?= e(truncate($tx['description'] ?? '', 50)) ?></span></td>
                                    <td class="px-5 py-3 text-right text-xs font-medium <?= (int)($tx['provider_id'] ?? 0) === (int)($member['id'] ?? 0) ? 'text-teal-600' : 'text-orange-500' ?>">
                                        <?= (int)($tx['provider_id'] ?? 0) === (int)($member['id'] ?? 0) ? '+' : '-' ?><?= number_format((float)($tx['hours'] ?? 0), 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="px-5 py-8 text-center text-sm text-gray-400">No transactions on record.</div>
                <?php endif; ?>
            </div>

            <!-- Endorsements received -->
            <?php if (!empty($endorsements)): ?>
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Endorsements Received</h3>
                    <div class="space-y-3">
                        <?php foreach ($endorsements as $end): ?>
                            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-xl">
                                <div class="w-7 h-7 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-semibold text-xs flex-shrink-0">
                                    <?= e(mb_strtoupper(mb_substr($end['from_name'] ?? 'M', 0, 1))) ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-xs font-semibold text-gray-700"><?= e($end['from_name'] ?? '') ?></p>
                                        <div class="flex">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <svg class="w-3 h-3 <?= $s <= (int)($end['rating'] ?? 5) ? 'text-amber-400' : 'text-gray-200' ?>" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($end['comment'])): ?>
                                        <p class="text-xs text-gray-500 mt-0.5"><?= e(truncate($end['comment'], 100)) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
