<?php $pageTitle = 'Reports'; ?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Reports</h1>
    <p class="text-sm text-gray-500 mt-1">Data exports and community analytics</p>
</div>

<?php
$reports = [
    [
        'href'    => '/admin/reports/member-balances',
        'title'   => 'Member Balances',
        'desc'    => 'All active members and their current time credit balances, sorted highest to lowest.',
        'icon'    => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
        'color'   => 'teal',
    ],
    [
        'href'    => '/admin/transactions',
        'title'   => 'Transaction History',
        'desc'    => 'Full transaction log with date range and member filters. Includes all types of exchanges.',
        'icon'    => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
        'color'   => 'blue',
    ],
    [
        'href'    => '/admin/reports/category-breakdown',
        'title'   => 'Category Breakdown',
        'desc'    => 'Total offers, requests, and hours exchanged broken down by service category.',
        'icon'    => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        'color'   => 'orange',
    ],
    [
        'href'    => '/admin/reports/member-hours-summary',
        'title'   => 'Member Hours Summary',
        'desc'    => 'Hours given versus hours received per member, with net balance for each.',
        'icon'    => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'color'   => 'purple',
    ],
    [
        'href'    => '/admin/reports/inactive-members',
        'title'   => 'Inactive Members',
        'desc'    => 'Members with zero transactions in the last 90 days. Good for outreach campaigns.',
        'icon'    => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
        'color'   => 'amber',
    ],
    [
        'href'    => '/admin/reports/new-members',
        'title'   => 'New Members',
        'desc'    => 'Members who joined in the last 30 days, including approval status.',
        'icon'    => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
        'color'   => 'green',
    ],
];

$colorMap = [
    'teal'   => ['border' => 'border-teal-200',   'bg' => 'bg-teal-50',   'icon' => 'bg-teal-100 text-teal-600'],
    'blue'   => ['border' => 'border-blue-200',   'bg' => 'bg-blue-50',   'icon' => 'bg-blue-100 text-blue-600'],
    'orange' => ['border' => 'border-orange-200', 'bg' => 'bg-orange-50', 'icon' => 'bg-orange-100 text-orange-500'],
    'purple' => ['border' => 'border-purple-200', 'bg' => 'bg-purple-50', 'icon' => 'bg-purple-100 text-purple-600'],
    'amber'  => ['border' => 'border-amber-200',  'bg' => 'bg-amber-50',  'icon' => 'bg-amber-100 text-amber-600'],
    'green'  => ['border' => 'border-green-200',  'bg' => 'bg-green-50',  'icon' => 'bg-green-100 text-green-600'],
];
?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($reports as $report): ?>
        <?php $c = $colorMap[$report['color']]; ?>
        <a href="<?= url($report['href']) ?>"
           class="block bg-white rounded-2xl border <?= $c['border'] ?> p-5 hover:shadow-md hover:<?= $c['bg'] ?> transition-all">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 <?= $c['icon'] ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $report['icon'] ?>"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900 mb-1"><?= e($report['title']) ?></h3>
                    <p class="text-xs text-gray-500 leading-relaxed"><?= e($report['desc']) ?></p>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-end">
                <span class="text-xs text-gray-400 hover:text-teal-600 transition-colors">View report &rarr;</span>
            </div>
        </a>
    <?php endforeach; ?>
</div>
