<?php
$pageTitle = ($member['display_name'] ?: trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')));
$currencyName = $tenant['currency_name'] ?? 'Hour';
$currentUserId = \TimeBank\Core\Auth::id();
$isOwnProfile = (int)($member['id'] ?? 0) === (int)$currentUserId;
?>

<div class="max-w-4xl mx-auto">

    <!-- Profile header -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex flex-col sm:flex-row items-start gap-6">

            <!-- Avatar -->
            <?php if (!empty($member['avatar_path'])): ?>
                <img src="<?= e(media_url($member['avatar_path'])) ?>" alt=""
                     class="w-24 h-24 rounded-2xl object-cover flex-shrink-0 ring-4 ring-teal-50">
            <?php else: ?>
                <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center text-white font-bold text-3xl flex-shrink-0">
                    <?= e(mb_strtoupper(mb_substr($member['first_name'] ?? 'M', 0, 1))) ?>
                </div>
            <?php endif; ?>

            <!-- Info -->
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            <?= e($member['display_name'] ?: trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?>
                        </h1>
                        <?php if (!empty($member['city'])): ?>
                            <p class="text-sm text-gray-500 mt-1">
                                <svg class="w-4 h-4 inline mr-1 -mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <?= e($member['city']) ?><?= !empty($member['state']) ? ', ' . e($member['state']) : '' ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($isOwnProfile): ?>
                            <div class="mt-2 inline-flex items-center gap-2 bg-teal-50 px-3 py-1 rounded-full">
                                <span class="text-sm font-semibold text-teal-700">
                                    <?= number_format((float)($member['balance'] ?? 0), 2) ?> <?= e($currencyName) ?>s
                                </span>
                                <span class="text-xs text-teal-500">balance</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex flex-wrap gap-2">
                        <?php if ($isOwnProfile): ?>
                            <a href="<?= url('/profile/edit') ?>"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit Profile
                            </a>
                        <?php else: ?>
                            <a href="<?= url('/messages/compose?to=' . ($member['id'] ?? '')) ?>"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                Send Message
                            </a>
                            <a href="<?= url('/endorsements/create?member=' . ($member['id'] ?? '')) ?>"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-orange-50 text-orange-600 text-sm font-medium rounded-xl hover:bg-orange-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                Endorse <?= e($member['first_name'] ?? 'Member') ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?= url('/transactions/record?provider=' . ($member['id'] ?? '')) ?>"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Record Hours
                        </a>
                    </div>
                </div>

                <?php if (!empty($member['bio'])): ?>
                    <div class="mt-4 text-sm text-gray-600 leading-relaxed">
                        <?= nl2br(e($member['bio'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabs: Offers / Requests / Endorsements -->
    <div x-data="{ tab: 'offers' }">

        <!-- Tab nav -->
        <div class="flex gap-1 border-b border-gray-200 mb-6 bg-white rounded-t-xl">
            <button @click="tab = 'offers'"
                    :class="tab === 'offers' ? 'border-b-2 border-teal-600 text-teal-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition-colors -mb-px">
                Offers
                <?php if (!empty($offers)): ?>
                    <span class="ml-1.5 text-xs bg-teal-100 text-teal-600 rounded-full px-1.5 py-0.5"><?= count($offers) ?></span>
                <?php endif; ?>
            </button>
            <button @click="tab = 'requests'"
                    :class="tab === 'requests' ? 'border-b-2 border-teal-600 text-teal-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition-colors -mb-px">
                Requests
                <?php if (!empty($requests)): ?>
                    <span class="ml-1.5 text-xs bg-orange-100 text-orange-600 rounded-full px-1.5 py-0.5"><?= count($requests) ?></span>
                <?php endif; ?>
            </button>
            <button @click="tab = 'endorsements'"
                    :class="tab === 'endorsements' ? 'border-b-2 border-teal-600 text-teal-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition-colors -mb-px">
                Endorsements
                <?php if (!empty($endorsements)): ?>
                    <span class="ml-1.5 text-xs bg-amber-100 text-amber-600 rounded-full px-1.5 py-0.5"><?= count($endorsements) ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Offers tab -->
        <div x-show="tab === 'offers'" x-transition>
            <?php if (!empty($offers)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($offers as $offer): ?>
                        <a href="<?= url('/offers/' . ($offer['id'] ?? '')) ?>"
                           class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-teal-200 transition-all">
                            <h3 class="text-sm font-semibold text-gray-900 mb-1"><?= e($offer['title'] ?? '') ?></h3>
                            <p class="text-xs text-gray-500 line-clamp-2"><?= e(truncate(strip_tags($offer['description'] ?? ''), 100)) ?></p>
                            <?php if (!empty($offer['category_name'])): ?>
                                <span class="inline-block text-xs text-teal-600 bg-teal-50 px-2 py-0.5 rounded-full mt-2"><?= e($offer['category_name']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center">
                    <p class="text-sm text-gray-400">No offers posted yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Requests tab -->
        <div x-show="tab === 'requests'" x-transition x-cloak>
            <?php if (!empty($requests)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($requests as $req): ?>
                        <a href="<?= url('/offers/' . ($req['id'] ?? '')) ?>"
                           class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-orange-200 transition-all">
                            <h3 class="text-sm font-semibold text-gray-900 mb-1"><?= e($req['title'] ?? '') ?></h3>
                            <p class="text-xs text-gray-500 line-clamp-2"><?= e(truncate(strip_tags($req['description'] ?? ''), 100)) ?></p>
                            <?php if (!empty($req['category_name'])): ?>
                                <span class="inline-block text-xs text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full mt-2"><?= e($req['category_name']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center">
                    <p class="text-sm text-gray-400">No requests posted yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Endorsements tab -->
        <div x-show="tab === 'endorsements'" x-transition x-cloak>
            <?php if (!empty($endorsements)): ?>
                <div class="space-y-4">
                    <?php foreach ($endorsements as $end): ?>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <?php if (!empty($end['from_avatar'])): ?>
                                        <img src="<?= e(media_url($end['from_avatar'])) ?>" alt="" class="w-9 h-9 rounded-full object-cover flex-shrink-0">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-semibold text-sm flex-shrink-0">
                                            <?= e(mb_strtoupper(mb_substr($end['from_name'] ?? 'M', 0, 1))) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800"><?= e($end['from_name'] ?? '') ?></p>
                                        <!-- Stars -->
                                        <div class="flex items-center gap-0.5 mt-0.5">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <svg class="w-3.5 h-3.5 <?= $s <= (int)($end['rating'] ?? 5) ? 'text-amber-400' : 'text-gray-200' ?>"
                                                     fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                                </svg>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-400 flex-shrink-0"><?= e(time_ago($end['created_at'] ?? '')) ?></p>
                            </div>
                            <?php if (!empty($end['comment'])): ?>
                                <p class="text-sm text-gray-600 mt-3 leading-relaxed"><?= e($end['comment']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center">
                    <p class="text-sm text-gray-400">No endorsements yet.</p>
                    <?php if (!$isOwnProfile): ?>
                        <a href="<?= url('/endorsements/create?member=' . ($member['id'] ?? '')) ?>"
                           class="inline-block mt-3 text-sm text-teal-600 font-medium hover:underline">
                            Be the first to endorse <?= e($member['first_name'] ?? 'this member') ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
