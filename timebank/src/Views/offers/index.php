<?php $pageTitle = 'Offers'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Offers</h1>
        <p class="text-sm text-gray-500 mt-1">What your neighbors are offering to share</p>
    </div>
    <a href="<?= url('/offers/create') ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Post an Offer
    </a>
</div>

<!-- Category filter pills -->
<?php if (!empty($categories)): ?>
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="<?= url('/offers') ?>"
           class="px-3 py-1.5 text-sm rounded-full transition-colors <?= empty($_GET['category']) ? 'bg-teal-600 text-white font-semibold' : 'bg-white border border-gray-300 text-gray-600 hover:border-teal-400 hover:text-teal-700' ?>">
            All
        </a>
        <?php foreach ($categories as $cat): ?>
            <a href="<?= url('/offers?category=' . ($cat['id'] ?? '')) ?>"
               class="px-3 py-1.5 text-sm rounded-full transition-colors <?= (string)($_GET['category'] ?? '') === (string)($cat['id'] ?? '') ? 'bg-teal-600 text-white font-semibold' : 'bg-white border border-gray-300 text-gray-600 hover:border-teal-400 hover:text-teal-700' ?>">
                <?= e($cat['name'] ?? '') ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Offers grid -->
<?php if (!empty($offers['data'])): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        <?php foreach ($offers['data'] as $offer): ?>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col hover:shadow-md hover:border-teal-200 transition-all overflow-hidden">

                <!-- Image or placeholder -->
                <?php if (!empty($offer['image_path'])): ?>
                    <div class="h-40 bg-gray-100 overflow-hidden">
                        <img src="<?= e(media_url($offer['image_path'])) ?>" alt=""
                             class="w-full h-full object-cover">
                    </div>
                <?php else: ?>
                    <div class="h-28 bg-gradient-to-br from-teal-50 to-teal-100 flex items-center justify-center">
                        <svg class="w-10 h-10 text-teal-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"/>
                        </svg>
                    </div>
                <?php endif; ?>

                <div class="p-4 flex flex-col flex-1">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="text-sm font-semibold text-gray-900 leading-snug"><?= e($offer['title'] ?? '') ?></h3>
                        <?php if (!empty($offer['category_name'])): ?>
                            <span class="text-xs text-teal-600 bg-teal-50 px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0"><?= e($offer['category_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-500 flex-1 line-clamp-2 mb-3">
                        <?= e(truncate(strip_tags($offer['description'] ?? ''), 100)) ?>
                    </p>
                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                        <div class="flex items-center gap-2">
                            <?php if (!empty($offer['member_avatar'])): ?>
                                <img src="<?= e(media_url($offer['member_avatar'])) ?>" alt="" class="w-6 h-6 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-6 h-6 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 text-xs font-semibold">
                                    <?= e(mb_strtoupper(mb_substr($offer['member_name'] ?? 'M', 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                            <a href="<?= url('/members/' . ($offer['member_id'] ?? '')) ?>"
                               class="text-xs text-gray-600 hover:text-teal-700 transition-colors truncate max-w-[100px]">
                                <?= e($offer['member_name'] ?? '') ?>
                            </a>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="<?= url('/transactions/record?offer=' . ($offer['id'] ?? '')) ?>"
                               title="Record hours for this offer"
                               class="p-1.5 text-gray-400 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </a>
                            <a href="<?= url('/offers/' . ($offer['id'] ?? '')) ?>"
                               class="px-3 py-1.5 bg-teal-50 text-teal-700 text-xs font-medium rounded-lg hover:bg-teal-100 transition-colors">
                                View
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if (($offers['pages'] ?? 1) > 1): ?>
        <div class="flex items-center justify-center gap-2">
            <?php
            $currentPage = (int)($offers['current'] ?? 1);
            $totalPages  = (int)($offers['pages'] ?? 1);
            $baseParams  = array_filter(['category' => $_GET['category'] ?? '']);
            ?>
            <?php if ($currentPage > 1): ?>
                <a href="<?= url('/offers?' . http_build_query(array_merge($baseParams, ['page' => $currentPage - 1]))) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    &lsaquo; Prev
                </a>
            <?php endif; ?>
            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                <a href="<?= url('/offers?' . http_build_query(array_merge($baseParams, ['page' => $p]))) ?>"
                   class="px-3 py-2 text-sm rounded-lg transition-colors <?= $p === $currentPage ? 'bg-teal-600 text-white font-semibold' : 'text-gray-600 bg-white border border-gray-300 hover:bg-gray-50' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= url('/offers?' . http_build_query(array_merge($baseParams, ['page' => $currentPage + 1]))) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Next &rsaquo;
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-16 text-center">
        <div class="w-16 h-16 rounded-full bg-teal-50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-teal-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"/></svg>
        </div>
        <h3 class="text-base font-semibold text-gray-600 mb-2">No offers yet</h3>
        <p class="text-sm text-gray-400 mb-5">Be the first to share something with the community.</p>
        <a href="<?= url('/offers/create') ?>"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Post an Offer
        </a>
    </div>
<?php endif; ?>
