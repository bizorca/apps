<?php $pageTitle = 'Requests'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Requests</h1>
        <p class="text-sm text-gray-500 mt-1">What your neighbors are looking for help with</p>
    </div>
    <a href="<?= url('/requests/create') ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-orange-500 text-white text-sm font-semibold rounded-xl hover:bg-orange-600 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Post a Request
    </a>
</div>

<!-- Category filter pills -->
<?php if (!empty($categories)): ?>
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="<?= url('/requests') ?>"
           class="px-3 py-1.5 text-sm rounded-full transition-colors <?= empty($_GET['category']) ? 'bg-orange-500 text-white font-semibold' : 'bg-white border border-gray-300 text-gray-600 hover:border-orange-300 hover:text-orange-600' ?>">
            All
        </a>
        <?php foreach ($categories as $cat): ?>
            <a href="<?= url('/requests?category=' . ($cat['id'] ?? '')) ?>"
               class="px-3 py-1.5 text-sm rounded-full transition-colors <?= (string)($_GET['category'] ?? '') === (string)($cat['id'] ?? '') ? 'bg-orange-500 text-white font-semibold' : 'bg-white border border-gray-300 text-gray-600 hover:border-orange-300 hover:text-orange-600' ?>">
                <?= e($cat['name'] ?? '') ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Requests grid -->
<?php if (!empty($offers['data'])): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        <?php foreach ($offers['data'] as $req): ?>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col hover:shadow-md hover:border-orange-200 transition-all overflow-hidden">

                <!-- Image or placeholder -->
                <?php if (!empty($req['image_path'])): ?>
                    <div class="h-40 bg-gray-100 overflow-hidden">
                        <img src="<?= e(media_url($req['image_path'])) ?>" alt="" class="w-full h-full object-cover">
                    </div>
                <?php else: ?>
                    <div class="h-28 bg-gradient-to-br from-orange-50 to-orange-100 flex items-center justify-center">
                        <svg class="w-10 h-10 text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                <?php endif; ?>

                <div class="p-4 flex flex-col flex-1">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="text-sm font-semibold text-gray-900 leading-snug"><?= e($req['title'] ?? '') ?></h3>
                        <?php if (!empty($req['category_name'])): ?>
                            <span class="text-xs text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0"><?= e($req['category_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-500 flex-1 line-clamp-2 mb-3">
                        <?= e(truncate(strip_tags($req['description'] ?? ''), 100)) ?>
                    </p>
                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                        <div class="flex items-center gap-2">
                            <?php if (!empty($req['member_avatar'])): ?>
                                <img src="<?= e(media_url($req['member_avatar'])) ?>" alt="" class="w-6 h-6 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-6 h-6 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 text-xs font-semibold">
                                    <?= e(mb_strtoupper(mb_substr($req['member_name'] ?? 'M', 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                            <a href="<?= url('/members/' . ($req['member_id'] ?? '')) ?>"
                               class="text-xs text-gray-600 hover:text-orange-600 transition-colors truncate max-w-[100px]">
                                <?= e($req['member_name'] ?? '') ?>
                            </a>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="<?= url('/transactions/record?offer=' . ($req['id'] ?? '') . '&provider=' . ($req['member_id'] ?? '')) ?>"
                               title="Record hours for this request"
                               class="p-1.5 text-gray-400 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </a>
                            <a href="<?= url('/offers/' . ($req['id'] ?? '')) ?>"
                               class="px-3 py-1.5 bg-orange-50 text-orange-600 text-xs font-medium rounded-lg hover:bg-orange-100 transition-colors">
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
                <a href="<?= url('/requests?' . http_build_query(array_merge($baseParams, ['page' => $currentPage - 1]))) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">&lsaquo; Prev</a>
            <?php endif; ?>
            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                <a href="<?= url('/requests?' . http_build_query(array_merge($baseParams, ['page' => $p]))) ?>"
                   class="px-3 py-2 text-sm rounded-lg transition-colors <?= $p === $currentPage ? 'bg-orange-500 text-white font-semibold' : 'text-gray-600 bg-white border border-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= url('/requests?' . http_build_query(array_merge($baseParams, ['page' => $currentPage + 1]))) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Next &rsaquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-16 text-center">
        <div class="w-16 h-16 rounded-full bg-orange-50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="text-base font-semibold text-gray-600 mb-2">No requests yet</h3>
        <p class="text-sm text-gray-400 mb-5">Tell the community what you need help with.</p>
        <a href="<?= url('/requests/create') ?>"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-orange-500 text-white text-sm font-semibold rounded-xl hover:bg-orange-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Post a Request
        </a>
    </div>
<?php endif; ?>
