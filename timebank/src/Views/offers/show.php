<?php
$isOffer = ($offer['type'] ?? 'offer') === 'offer';
$pageTitle = $offer['title'] ?? ($isOffer ? 'Offer' : 'Request');
$currencyName = $tenant['currency_name'] ?? 'Hour';
$currentUserId = \TimeBank\Core\Auth::id();
$isOwner = (int)($offer['member_id'] ?? 0) === (int)$currentUserId;
$isAdmin = \TimeBank\Core\Auth::isAdmin();
?>

<div class="max-w-3xl mx-auto">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="<?= url($isOffer ? '/offers' : '/requests') ?>" class="hover:text-teal-600 transition-colors">
            <?= $isOffer ? 'Offers' : 'Requests' ?>
        </a>
        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-800 font-medium truncate"><?= e($offer['title'] ?? '') ?></span>
    </nav>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

        <!-- Image -->
        <?php if (!empty($offer['image_path'])): ?>
            <div class="h-56 sm:h-72 bg-gray-100">
                <img src="<?= e(media_url($offer['image_path'])) ?>" alt="<?= e($offer['title'] ?? '') ?>" class="w-full h-full object-cover">
            </div>
        <?php endif; ?>

        <div class="p-6 sm:p-8">

            <!-- Header row -->
            <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full <?= $isOffer ? 'bg-teal-100 text-teal-700' : 'bg-orange-100 text-orange-700' ?>">
                            <?= $isOffer ? 'Offer' : 'Request' ?>
                        </span>
                        <?php if (!empty($offer['category_name'])): ?>
                            <a href="<?= url(($isOffer ? '/offers' : '/requests') . '?category=' . ($offer['category_id'] ?? '')) ?>"
                               class="text-xs text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full hover:bg-gray-200 transition-colors">
                                <?= e($offer['category_name']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900"><?= e($offer['title'] ?? '') ?></h1>
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap gap-2">
                    <?php if ($isOwner || $isAdmin): ?>
                        <a href="<?= url('/offers/' . ($offer['id'] ?? '') . '/edit') ?>"
                           class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Edit
                        </a>
                        <form method="POST" action="<?= url('/offers/' . ($offer['id'] ?? '') . '/delete') ?>"
                              onsubmit="return confirm('Delete this <?= $isOffer ? 'offer' : 'request' ?>? This cannot be undone.')">
                            <?= csrf_field() ?>
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-red-600 bg-red-50 rounded-xl hover:bg-red-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Delete
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= url('/transactions/record?offer=' . ($offer['id'] ?? '') . ($isOffer ? '&provider=' . ($offer['member_id'] ?? '') : '&receiver=' . ($offer['member_id'] ?? ''))) ?>"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Record Hours
                    </a>
                </div>
            </div>

            <!-- Description -->
            <div class="prose text-sm text-gray-700 leading-relaxed mb-6">
                <?= nl2br(e($offer['description'] ?? '')) ?>
            </div>

            <!-- Member info footer -->
            <div class="flex items-center justify-between pt-5 border-t border-gray-100">
                <a href="<?= url('/members/' . ($offer['member_id'] ?? '')) ?>"
                   class="flex items-center gap-3 group">
                    <?php if (!empty($offer['member_avatar'])): ?>
                        <img src="<?= e(media_url($offer['member_avatar'])) ?>" alt="" class="w-10 h-10 rounded-full object-cover">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center text-teal-700 font-semibold">
                            <?= e(mb_strtoupper(mb_substr($offer['member_name'] ?? 'M', 0, 1))) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <p class="text-sm font-semibold text-gray-800 group-hover:text-teal-700 transition-colors">
                            <?= e($offer['member_name'] ?? '') ?>
                        </p>
                        <p class="text-xs text-gray-400">Posted <?= e(time_ago($offer['created_at'] ?? '')) ?></p>
                    </div>
                </a>

                <?php if (!$isOwner): ?>
                    <a href="<?= url('/messages/compose?to=' . ($offer['member_id'] ?? '')) ?>"
                       class="inline-flex items-center gap-2 px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Message
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
