<?php $pageTitle = 'Search Results'; ?>

<div class="mb-8">
    <form method="GET" action="<?= url('/search') ?>" class="flex gap-3 max-w-2xl">
            <?= route_field('/search') ?>
        <input type="text" name="q" value="<?= e($query ?? '') ?>"
               class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent"
               placeholder="Search members, offers, requests, announcements...">
        <button type="submit"
                class="px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors">
            Search
        </button>
    </form>
    <?php if (!empty($query)): ?>
        <p class="text-sm text-gray-500 mt-2">Results for <strong class="text-gray-800">"<?= e($query) ?>"</strong></p>
    <?php endif; ?>
</div>

<?php
$hasResults = !empty($results['members']) || !empty($results['offers']) || !empty($results['requests']) || !empty($results['announcements']);
?>

<?php if (!$hasResults): ?>
    <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-700 mb-2">Nothing found</h3>
        <p class="text-sm text-gray-500">Try a different search term or <a href="<?= url('/offers') ?>" class="text-teal-600 hover:underline">browse all offers</a>.</p>
    </div>
<?php endif; ?>

<div class="space-y-10">

    <!-- Members -->
    <?php if (!empty($results['members'])): ?>
        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Members
                <span class="text-sm font-normal text-gray-400">(<?= count($results['members']) ?>)</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($results['members'] as $m): ?>
                    <a href="<?= url('/members/' . ($m['id'] ?? '')) ?>"
                       class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-teal-200 transition-all">
                        <?php if (!empty($m['avatar_path'])): ?>
                            <img src="<?= e(media_url($m['avatar_path'])) ?>" alt="" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center text-teal-700 font-semibold text-sm flex-shrink-0">
                                <?= e(mb_strtoupper(mb_substr($m['first_name'] ?? 'M', 0, 1))) ?>
                            </div>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">
                                <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                            </p>
                            <?php if (!empty($m['city'])): ?>
                                <p class="text-xs text-gray-500 truncate"><?= e($m['city']) ?><?= !empty($m['state']) ? ', ' . e($m['state']) : '' ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Offers -->
    <?php if (!empty($results['offers'])): ?>
        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"/></svg>
                Offers
                <span class="text-sm font-normal text-gray-400">(<?= count($results['offers']) ?>)</span>
            </h2>
            <div class="space-y-3">
                <?php foreach ($results['offers'] as $offer): ?>
                    <a href="<?= url('/offers/' . ($offer['id'] ?? '')) ?>"
                       class="flex items-center justify-between gap-4 bg-white rounded-xl border border-gray-200 px-5 py-4 hover:shadow-md hover:border-teal-200 transition-all">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate"><?= e($offer['title'] ?? '') ?></h3>
                            <p class="text-xs text-gray-500 mt-0.5"><?= e($offer['member_name'] ?? '') ?> &bull; <?= e(time_ago($offer['created_at'] ?? '')) ?></p>
                        </div>
                        <?php if (!empty($offer['category_name'])): ?>
                            <span class="text-xs text-teal-600 bg-teal-50 px-2.5 py-1 rounded-full whitespace-nowrap"><?= e($offer['category_name']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Requests -->
    <?php if (!empty($results['requests'])): ?>
        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Requests
                <span class="text-sm font-normal text-gray-400">(<?= count($results['requests']) ?>)</span>
            </h2>
            <div class="space-y-3">
                <?php foreach ($results['requests'] as $req): ?>
                    <a href="<?= url('/offers/' . ($req['id'] ?? '')) ?>"
                       class="flex items-center justify-between gap-4 bg-white rounded-xl border border-gray-200 px-5 py-4 hover:shadow-md hover:border-orange-200 transition-all">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate"><?= e($req['title'] ?? '') ?></h3>
                            <p class="text-xs text-gray-500 mt-0.5"><?= e($req['member_name'] ?? '') ?> &bull; <?= e(time_ago($req['created_at'] ?? '')) ?></p>
                        </div>
                        <?php if (!empty($req['category_name'])): ?>
                            <span class="text-xs text-orange-600 bg-orange-50 px-2.5 py-1 rounded-full whitespace-nowrap"><?= e($req['category_name']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Announcements -->
    <?php if (!empty($results['announcements'])): ?>
        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                Announcements
                <span class="text-sm font-normal text-gray-400">(<?= count($results['announcements']) ?>)</span>
            </h2>
            <div class="space-y-3">
                <?php foreach ($results['announcements'] as $ann): ?>
                    <a href="<?= url('/announcements/' . ($ann['id'] ?? '')) ?>"
                       class="flex items-center justify-between gap-4 bg-white rounded-xl border border-gray-200 px-5 py-4 hover:shadow-md hover:border-blue-200 transition-all">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate"><?= e($ann['title'] ?? '') ?></h3>
                            <p class="text-xs text-gray-500 mt-0.5"><?= e($ann['author_name'] ?? '') ?> &bull; <?= e(time_ago($ann['created_at'] ?? '')) ?></p>
                        </div>
                        <?php if (!empty($ann['is_pinned'])): ?>
                            <span class="text-xs text-orange-600 bg-orange-50 px-2.5 py-1 rounded-full whitespace-nowrap">Pinned</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</div>
