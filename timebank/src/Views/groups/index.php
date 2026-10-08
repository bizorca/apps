<?php $pageTitle = 'Groups'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Groups</h1>
        <p class="text-sm text-gray-500 mt-1">Neighborhoods, interests, and circles within <?= e($tenant['name'] ?? 'the community') ?></p>
    </div>
    <a href="<?= url('/groups/create') ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Create Group
    </a>
</div>

<?php if (!empty($groups)): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($groups as $group): ?>
            <a href="<?= url('/groups/' . ($group['id'] ?? '')) ?>"
               class="block bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md hover:border-teal-200 transition-all">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-teal-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900 mb-1 truncate"><?= e($group['name'] ?? '') ?></h3>
                        <?php if (!empty($group['description'])): ?>
                            <p class="text-xs text-gray-500 line-clamp-2 mb-2"><?= e(truncate($group['description'], 80)) ?></p>
                        <?php endif; ?>
                        <div class="flex items-center gap-2 text-xs text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span><?= (int)($group['member_count'] ?? 0) ?> member<?= (int)($group['member_count'] ?? 0) != 1 ? 's' : '' ?></span>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-16 text-center">
        <div class="w-16 h-16 rounded-full bg-teal-50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-teal-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <h3 class="text-base font-semibold text-gray-600 mb-2">No groups yet</h3>
        <p class="text-sm text-gray-400 mb-5">Start a group around a neighborhood, interest, or project.</p>
        <a href="<?= url('/groups/create') ?>"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors">
            Create the First Group
        </a>
    </div>
<?php endif; ?>
