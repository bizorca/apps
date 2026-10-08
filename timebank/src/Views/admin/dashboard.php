<?php $pageTitle = 'Admin Dashboard'; ?>
<?php $currencyPlural = $tenant['currency_name_plural'] ?? 'Hours'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
        <p class="text-sm text-gray-500 mt-1"><?= e($tenant['name'] ?? 'TimeBank') ?> &mdash; administration overview</p>
    </div>
    <a href="<?= url('/dashboard') ?>"
       class="text-sm text-gray-500 hover:text-teal-600 transition-colors">&larr; Back to timebank</a>
</div>

<!-- Stats grid -->
<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
    <?php
    $statCards = [
        ['label' => 'Total Members',          'key' => 'total_members',          'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'color' => 'teal', 'suffix' => ''],
        ['label' => $currencyPlural . ' Exchanged', 'key' => 'total_hours',      'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'teal', 'suffix' => ''],
        ['label' => 'Active Offers',           'key' => 'active_offers',          'icon' => 'M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11', 'color' => 'orange', 'suffix' => ''],
        ['label' => 'Total Transactions',      'key' => 'total_transactions',     'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', 'color' => 'blue', 'suffix' => ''],
        ['label' => 'This Month (' . $currencyPlural . ')', 'key' => 'month_hours', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'color' => 'purple', 'suffix' => ''],
        ['label' => 'Community Fund',          'key' => 'community_fund',         'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3', 'color' => 'amber', 'suffix' => ' ' . $currencyPlural],
    ];
    $colorMap = [
        'teal'   => ['bg' => 'bg-teal-50',   'icon' => 'text-teal-600',   'iconBg' => 'bg-teal-100'],
        'orange' => ['bg' => 'bg-orange-50',  'icon' => 'text-orange-500', 'iconBg' => 'bg-orange-100'],
        'blue'   => ['bg' => 'bg-blue-50',    'icon' => 'text-blue-600',   'iconBg' => 'bg-blue-100'],
        'purple' => ['bg' => 'bg-purple-50',  'icon' => 'text-purple-600', 'iconBg' => 'bg-purple-100'],
        'amber'  => ['bg' => 'bg-amber-50',   'icon' => 'text-amber-600',  'iconBg' => 'bg-amber-100'],
    ];
    ?>
    <?php foreach ($statCards as $card): ?>
        <?php $c = $colorMap[$card['color']]; ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-2"><?= e($card['label']) ?></p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?= number_format((float)($stats[$card['key']] ?? 0), is_float((float)($stats[$card['key']] ?? 0)) ? 2 : 0) ?><?= e($card['suffix']) ?>
                    </p>
                </div>
                <div class="w-10 h-10 rounded-xl <?= $c['iconBg'] ?> flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 <?= $c['icon'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $card['icon'] ?>"/>
                    </svg>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Recent Activity -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">Recent Transactions</h2>
                <a href="<?= url('/admin/transactions') ?>" class="text-xs text-teal-600 hover:text-teal-800 font-medium">View all</a>
            </div>
            <?php if (!empty($recentTransactions)): ?>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($recentTransactions as $tx): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 text-gray-500 whitespace-nowrap text-xs"><?= e(date('M j', strtotime($tx['service_date'] ?? $tx['created_at'] ?? ''))) ?></td>
                                <td class="px-5 py-3 text-gray-700 max-w-xs"><span class="truncate block text-xs"><?= e(truncate($tx['description'] ?? '', 50)) ?></span></td>
                                <td class="px-5 py-3 text-gray-500 text-xs whitespace-nowrap"><?= e($tx['provider_name'] ?? '') ?></td>
                                <td class="px-5 py-3 text-right text-xs font-semibold text-teal-600 whitespace-nowrap"><?= number_format((float)($tx['hours'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="p-8 text-center text-sm text-gray-400">No recent transactions.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick links -->
    <div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-800">Admin Tools</h2>
            </div>
            <nav class="p-2 space-y-0.5">
                <?php
                $adminLinks = [
                    ['href' => '/admin/members',    'label' => 'Manage Members',    'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                    ['href' => '/admin/transactions','label' => 'All Transactions',  'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
                    ['href' => '/admin/reports',    'label' => 'Reports',            'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ['href' => '/admin/categories', 'label' => 'Categories',         'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
                    ['href' => '/admin/email-templates','label' => 'Email Templates','icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    ['href' => '/admin/donations',  'label' => 'Donations',          'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
                    ['href' => '/admin/settings',   'label' => 'Settings',           'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                ];
                ?>
                <?php foreach ($adminLinks as $link): ?>
                    <a href="<?= url($link['href']) ?>"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-700 hover:bg-teal-50 hover:text-teal-700 transition-colors group">
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-teal-500 transition-colors flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $link['icon'] ?>"/>
                        </svg>
                        <?= e($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>

</div>
