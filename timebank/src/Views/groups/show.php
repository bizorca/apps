<?php
$pageTitle = $group['name'] ?? 'Group';
$currentUserId = \TimeBank\Core\Auth::id();
$isMember = false;
foreach ($members ?? [] as $m) {
    if ((int)($m['id'] ?? 0) === (int)$currentUserId) { $isMember = true; break; }
}
?>

<div class="max-w-4xl mx-auto">

    <!-- Header -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2"><?= e($group['name'] ?? '') ?></h1>
                <?php if (!empty($group['description'])): ?>
                    <p class="text-sm text-gray-600 leading-relaxed max-w-xl"><?= e($group['description']) ?></p>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-3">
                    <?= count($members ?? []) ?> member<?= count($members ?? []) != 1 ? 's' : '' ?>
                    &bull; Created <?= e(date('F j, Y', strtotime($group['created_at'] ?? ''))) ?>
                </p>
            </div>
            <div class="flex gap-2">
                <?php if ($isMember): ?>
                    <a href="<?= url('/messages/compose?group=' . ($group['id'] ?? '')) ?>"
                       class="inline-flex items-center gap-2 px-4 py-2 text-sm text-teal-700 bg-teal-50 rounded-xl hover:bg-teal-100 transition-colors font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Message Group
                    </a>
                    <form method="POST" action="<?= url('/groups/' . ($group['id'] ?? '') . '/leave') ?>">
                        <?= csrf_field() ?>
                        <button type="submit"
                                class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                            Leave Group
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= url('/groups/' . ($group['id'] ?? '') . '/join') ?>">
                        <?= csrf_field() ?>
                        <button type="submit"
                                class="px-5 py-2.5 text-sm bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition-colors font-semibold shadow-sm">
                            Join Group
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div x-data="{ tab: 'members' }">
        <div class="flex gap-1 border-b border-gray-200 mb-6 bg-white rounded-t-xl px-2">
            <button @click="tab = 'members'"
                    :class="tab === 'members' ? 'border-b-2 border-teal-600 text-teal-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition-colors -mb-px">
                Members
                <span class="ml-1.5 text-xs bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5"><?= count($members ?? []) ?></span>
            </button>
            <button @click="tab = 'messages'"
                    :class="tab === 'messages' ? 'border-b-2 border-teal-600 text-teal-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition-colors -mb-px">
                Messages
            </button>
            <button @click="tab = 'announcements'"
                    :class="tab === 'announcements' ? 'border-b-2 border-teal-600 text-teal-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition-colors -mb-px">
                Announcements
            </button>
        </div>

        <!-- Members tab -->
        <div x-show="tab === 'members'" x-transition>
            <?php if (!empty($members)): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($members as $m): ?>
                        <a href="<?= url('/members/' . ($m['id'] ?? '')) ?>"
                           class="flex flex-col items-center gap-2 bg-white rounded-xl border border-gray-200 p-4 text-center hover:shadow-md hover:border-teal-200 transition-all">
                            <?php if (!empty($m['avatar_path'])): ?>
                                <img src="<?= e(media_url($m['avatar_path'])) ?>" alt="" class="w-12 h-12 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-semibold">
                                    <?= e(mb_strtoupper(mb_substr($m['first_name'] ?? 'M', 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <p class="text-xs font-semibold text-gray-800 truncate max-w-[100px]">
                                    <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                                </p>
                                <?php if (!empty($m['role']) && $m['role'] === 'moderator'): ?>
                                    <span class="text-xs text-teal-600 bg-teal-50 px-1.5 py-0.5 rounded-full mt-0.5">Mod</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-8">No members yet.</p>
            <?php endif; ?>
        </div>

        <!-- Messages tab -->
        <div x-show="tab === 'messages'" x-cloak x-transition>
            <?php if ($isMember): ?>
                <?php if (!empty($recentMessages)): ?>
                    <div class="space-y-3">
                        <?php foreach ($recentMessages as $msg): ?>
                            <a href="<?= url('/messages/' . ($msg['id'] ?? '')) ?>"
                               class="flex items-start gap-3 bg-white rounded-xl border border-gray-200 px-5 py-4 hover:shadow-md transition-shadow">
                                <div class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-semibold text-sm flex-shrink-0">
                                    <?= e(mb_strtoupper(mb_substr($msg['sender_name'] ?? 'M', 0, 1))) ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-gray-800"><?= e($msg['sender_name'] ?? '') ?></p>
                                        <p class="text-xs text-gray-400"><?= e(time_ago($msg['created_at'] ?? '')) ?></p>
                                    </div>
                                    <p class="text-sm text-gray-600 truncate mt-0.5"><?= e($msg['subject'] ?? '') ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                        <p class="text-sm text-gray-400">No group messages yet.</p>
                        <a href="<?= url('/messages/compose?group=' . ($group['id'] ?? '')) ?>"
                           class="inline-block mt-3 text-sm text-teal-600 font-medium hover:underline">Send the first message</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                    <p class="text-sm text-gray-500">Join this group to view and send messages.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Announcements tab -->
        <div x-show="tab === 'announcements'" x-cloak x-transition>
            <?php if (!empty($recentAnnouncements)): ?>
                <div class="space-y-3">
                    <?php foreach ($recentAnnouncements as $ann): ?>
                        <a href="<?= url('/announcements/' . ($ann['id'] ?? '')) ?>"
                           class="block bg-white rounded-xl border border-gray-200 px-5 py-4 hover:shadow-md transition-shadow">
                            <h3 class="text-sm font-semibold text-gray-900 mb-1"><?= e($ann['title'] ?? '') ?></h3>
                            <p class="text-xs text-gray-500 line-clamp-2"><?= e(truncate(strip_tags($ann['body'] ?? ''), 120)) ?></p>
                            <p class="text-xs text-gray-400 mt-2"><?= e(time_ago($ann['created_at'] ?? '')) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                    <p class="text-sm text-gray-400">No announcements for this group yet.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
