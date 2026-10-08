<?php $pageTitle = 'Members'; ?>

<div x-data="{ search: '' }">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Members</h1>
        <input type="text" x-model="search" placeholder="Search members..."
               class="border border-gray-200 rounded-lg px-3 py-2 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-indigo-300">
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($members as $member): ?>
            <div x-show="search === '' || <?= h(json_encode(mb_strtolower($member['first_name'] . ' ' . $member['last_name']))) ?>.includes(search.toLowerCase())"
                 class="bg-white border border-gray-200 rounded-xl p-4 flex items-start gap-3 hover:border-indigo-200 transition-colors">

                <?php if (!empty($member['avatar_url'])): ?>
                    <img src="<?= h(safe_url($member['avatar_url'])) ?>"
                         alt="<?= h($member['first_name']) ?>"
                         class="w-12 h-12 rounded-full object-cover shrink-0 border border-gray-200">
                <?php else: ?>
                    <div class="w-12 h-12 rounded-full shrink-0 bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-lg select-none">
                        <?= h(mb_strtoupper(mb_substr($member['first_name'], 0, 1))) ?>
                    </div>
                <?php endif; ?>

                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-900 text-sm">
                        <?= h($member['first_name'] . ' ' . $member['last_name']) ?>
                    </p>
                    <?php if (!empty($member['bio'])): ?>
                        <p class="text-xs text-gray-400 mt-1 line-clamp-2">
                            <?= h(mb_substr($member['bio'], 0, 100)) ?><?= mb_strlen($member['bio']) > 100 ? '...' : '' ?>
                        </p>
                    <?php endif; ?>
                    <a href="<?= url("profile/{$member['id']}") ?>"
                       class="text-xs text-indigo-600 hover:underline mt-1.5 inline-block">
                        View profile
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($members)): ?>
        <p class="text-sm text-gray-400 text-center py-10">No members yet.</p>
    <?php endif; ?>
</div>
