<?php $pageTitle = 'Dashboard'; ?>
<?php
$currencyName = $tenant['currency_name'] ?? 'Hour';
$currencyPlural = $tenant['currency_name_plural'] ?? 'Hours';
$balance = (float)($member['balance'] ?? 0);
?>

<div class="flex flex-col lg:flex-row gap-8">

    <!-- Sidebar -->
    <aside class="lg:w-72 flex-shrink-0 space-y-5">

        <!-- Balance card -->
        <div class="bg-gradient-to-br from-teal-600 to-teal-700 rounded-2xl p-6 text-white shadow-lg">
            <p class="text-teal-100 text-sm font-medium mb-1">Your Balance</p>
            <p class="text-4xl font-bold tracking-tight mb-0.5">
                <?= number_format($balance, 2) ?>
            </p>
            <p class="text-teal-200 text-sm">
                <?= e($balance == 1 ? $currencyName : $currencyPlural) ?>
            </p>
            <div class="mt-4 pt-4 border-t border-teal-500">
                <p class="text-xs text-teal-200">
                    Welcome back, <?= e($member['first_name'] ?? 'member') ?>!
                </p>
            </div>
        </div>

        <!-- Quick links -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Quick Links</h3>
            </div>
            <nav class="p-2 space-y-0.5">
                <a href="<?= url('/transactions/record') ?>"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-teal-50 hover:text-teal-700 transition-colors group">
                    <span class="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center flex-shrink-0 group-hover:bg-teal-200 transition-colors">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    Record Hours
                </a>
                <a href="<?= url('/offers/create') ?>"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-teal-50 hover:text-teal-700 transition-colors group">
                    <span class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center flex-shrink-0 group-hover:bg-orange-200 transition-colors">
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </span>
                    Post an Offer
                </a>
                <a href="<?= url('/requests/create') ?>"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-teal-50 hover:text-teal-700 transition-colors group">
                    <span class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0 group-hover:bg-blue-200 transition-colors">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    Post a Request
                </a>
                <a href="<?= url('/messages/compose') ?>"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-teal-50 hover:text-teal-700 transition-colors group">
                    <span class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0 group-hover:bg-purple-200 transition-colors">
                        <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </span>
                    Send Message
                </a>
            </nav>
        </div>

        <!-- Unread messages reminder -->
        <?php if (($unreadMessages ?? 0) > 0): ?>
            <a href="<?= url('/messages') ?>"
               class="flex items-center gap-3 p-4 bg-orange-50 border border-orange-200 rounded-2xl hover:bg-orange-100 transition-colors">
                <span class="w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                    <?= $unreadMessages ?>
                </span>
                <div>
                    <p class="text-sm font-semibold text-orange-800">
                        <?= $unreadMessages === 1 ? '1 unread message' : "$unreadMessages unread messages" ?>
                    </p>
                    <p class="text-xs text-orange-600">View your inbox</p>
                </div>
            </a>
        <?php endif; ?>

    </aside>

    <!-- Main content -->
    <div class="flex-1 min-w-0 space-y-10">

        <!-- Announcements -->
        <?php if (!empty($recentAnnouncements)): ?>
            <section>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Announcements</h2>
                    <a href="<?= url('/announcements') ?>" class="text-sm text-teal-600 hover:text-teal-800 font-medium transition-colors">View all</a>
                </div>
                <div class="space-y-3">
                    <?php foreach ($recentAnnouncements as $ann): ?>
                        <a href="<?= url('/announcements/' . ($ann['id'] ?? '')) ?>"
                           class="block bg-white rounded-xl border <?= !empty($ann['is_pinned']) ? 'border-orange-200 bg-orange-50' : 'border-gray-200' ?> p-5 hover:shadow-md transition-shadow">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <?php if (!empty($ann['is_pinned'])): ?>
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-orange-600 bg-orange-100 px-2 py-0.5 rounded-full">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                                Pinned
                                            </span>
                                        <?php endif; ?>
                                        <h3 class="font-semibold text-gray-900 truncate"><?= e($ann['title'] ?? '') ?></h3>
                                    </div>
                                    <p class="text-sm text-gray-500 line-clamp-2"><?= e(truncate(strip_tags($ann['body'] ?? ''), 140)) ?></p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs text-gray-400"><?= e(time_ago($ann['created_at'] ?? '')) ?></p>
                                    <?php if (!empty($ann['author_name'])): ?>
                                        <p class="text-xs text-gray-400 mt-0.5"><?= e($ann['author_name']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Recent Offers -->
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Recent Offers</h2>
                <a href="<?= url('/offers') ?>" class="text-sm text-teal-600 hover:text-teal-800 font-medium transition-colors">Browse all</a>
            </div>
            <?php if (!empty($recentOffers)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <?php foreach ($recentOffers as $offer): ?>
                        <a href="<?= url('/offers/' . ($offer['id'] ?? '')) ?>"
                           class="block bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-teal-200 transition-all group">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center flex-shrink-0 group-hover:bg-teal-100 transition-colors">
                                    <svg class="w-5 h-5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-semibold text-gray-900 truncate group-hover:text-teal-700 transition-colors"><?= e($offer['title'] ?? '') ?></h3>
                                    <p class="text-xs text-gray-500 mt-0.5 truncate"><?= e($offer['member_name'] ?? '') ?></p>
                                    <?php if (!empty($offer['category_name'])): ?>
                                        <span class="inline-block text-xs text-teal-600 bg-teal-50 px-2 py-0.5 rounded-full mt-1"><?= e($offer['category_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center">
                    <p class="text-sm text-gray-400">No offers yet. <a href="<?= url('/offers/create') ?>" class="text-teal-600 hover:underline">Be the first to post one.</a></p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Recent Requests -->
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Recent Requests</h2>
                <a href="<?= url('/requests') ?>" class="text-sm text-teal-600 hover:text-teal-800 font-medium transition-colors">Browse all</a>
            </div>
            <?php if (!empty($recentRequests)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <?php foreach ($recentRequests as $req): ?>
                        <a href="<?= url('/offers/' . ($req['id'] ?? '')) ?>"
                           class="block bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-orange-200 transition-all group">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center flex-shrink-0 group-hover:bg-orange-100 transition-colors">
                                    <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-semibold text-gray-900 truncate group-hover:text-orange-600 transition-colors"><?= e($req['title'] ?? '') ?></h3>
                                    <p class="text-xs text-gray-500 mt-0.5 truncate"><?= e($req['member_name'] ?? '') ?></p>
                                    <?php if (!empty($req['category_name'])): ?>
                                        <span class="inline-block text-xs text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full mt-1"><?= e($req['category_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center">
                    <p class="text-sm text-gray-400">No requests yet. <a href="<?= url('/requests/create') ?>" class="text-orange-500 hover:underline">Post what you need.</a></p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Recent Transactions -->
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Recent Transactions</h2>
                <a href="<?= url('/transactions') ?>" class="text-sm text-teal-600 hover:text-teal-800 font-medium transition-colors">View all</a>
            </div>
            <?php if (!empty($recentTransactions)): ?>
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">With</th>
                                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= e($currencyName) ?>s</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($recentTransactions as $tx): ?>
                                <?php
                                $memberId = $member['id'] ?? 0;
                                $isProvider = (int)($tx['provider_id'] ?? 0) === (int)$memberId;
                                $hoursColor = $isProvider ? 'text-teal-600' : 'text-orange-500';
                                $hoursSign  = $isProvider ? '+' : '-';
                                $otherName  = $isProvider ? ($tx['receiver_name'] ?? '') : ($tx['provider_name'] ?? '');
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap">
                                        <?= e(date('M j', strtotime($tx['service_date'] ?? $tx['created_at'] ?? ''))) ?>
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-800 max-w-xs">
                                        <span class="truncate block"><?= e(truncate($tx['description'] ?? 'Service exchange', 60)) ?></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-500 hidden sm:table-cell whitespace-nowrap"><?= e($otherName) ?></td>
                                    <td class="px-5 py-3.5 text-right font-semibold <?= $hoursColor ?> whitespace-nowrap">
                                        <?= $hoursSign ?><?= number_format((float)($tx['hours'] ?? 0), 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center">
                    <p class="text-sm text-gray-400">No transactions recorded yet. <a href="<?= url('/transactions/record') ?>" class="text-teal-600 hover:underline">Record your first exchange.</a></p>
                </div>
            <?php endif; ?>
        </section>

    </div>
</div>
