<?php $pageTitle = 'Announcements'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Announcements</h1>
        <p class="text-sm text-gray-500 mt-1">News and updates from <?= e($tenant['name'] ?? 'the community') ?></p>
    </div>
    <?php if (\TimeBank\Core\Auth::isAdmin()): ?>
        <a href="<?= url('/announcements/create') ?>"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Post Announcement
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($announcements['data'])): ?>
    <div class="space-y-4 mb-8">
        <?php foreach ($announcements['data'] as $ann): ?>
            <a href="<?= url('/announcements/' . ($ann['id'] ?? '')) ?>"
               class="block bg-white rounded-2xl border <?= !empty($ann['is_pinned']) ? 'border-orange-200' : 'border-gray-200' ?> shadow-sm p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <?php if (!empty($ann['is_pinned'])): ?>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-orange-600 bg-orange-100 px-2 py-0.5 rounded-full">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                    Pinned
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($ann['group_name'])): ?>
                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full"><?= e($ann['group_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h2 class="text-base font-semibold text-gray-900 mb-2"><?= e($ann['title'] ?? '') ?></h2>
                        <p class="text-sm text-gray-600 line-clamp-2 leading-relaxed">
                            <?= e(truncate(strip_tags($ann['body'] ?? ''), 180)) ?>
                        </p>
                    </div>
                    <svg class="w-5 h-5 text-gray-300 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs text-gray-400">
                    <div class="w-5 h-5 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-semibold text-xs">
                        <?= e(mb_strtoupper(mb_substr($ann['author_name'] ?? 'A', 0, 1))) ?>
                    </div>
                    <span><?= e($ann['author_name'] ?? '') ?></span>
                    <span>&bull;</span>
                    <span><?= e(date('F j, Y', strtotime($ann['created_at'] ?? ''))) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if (($announcements['pages'] ?? 1) > 1): ?>
        <div class="flex items-center justify-center gap-2">
            <?php
            $currentPage = (int)($announcements['current'] ?? 1);
            $totalPages  = (int)($announcements['pages'] ?? 1);
            ?>
            <?php if ($currentPage > 1): ?>
                <a href="<?= url('/announcements?page=' . ($currentPage - 1)) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">&lsaquo; Prev</a>
            <?php endif; ?>
            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                <a href="<?= url('/announcements?page=' . $p) ?>"
                   class="px-3 py-2 text-sm rounded-lg transition-colors <?= $p === $currentPage ? 'bg-teal-600 text-white font-semibold' : 'text-gray-600 bg-white border border-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= url('/announcements?page=' . ($currentPage + 1)) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Next &rsaquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-16 text-center">
        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
        </div>
        <h3 class="text-base font-semibold text-gray-600 mb-1">No announcements yet</h3>
        <p class="text-sm text-gray-400">Check back soon for community news and updates.</p>
    </div>
<?php endif; ?>
