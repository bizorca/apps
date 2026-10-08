<?php $pageTitle = 'Messages'; ?>
<?php $currentUserId = \TimeBank\Core\Auth::id(); ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Messages</h1>
    <a href="<?= url('/messages/compose') ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Compose
    </a>
</div>

<div x-data="{ tab: '<?= isset($_GET['tab']) && $_GET['tab'] === 'sent' ? 'sent' : 'inbox' ?>' }">

    <!-- Tab nav -->
    <div class="flex gap-1 border-b border-gray-200 mb-5 bg-white rounded-t-xl px-2">
        <button @click="tab = 'inbox'"
                :class="tab === 'inbox' ? 'border-b-2 border-teal-600 text-teal-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-3 text-sm transition-colors -mb-px">
            Inbox
            <?php
            $unreadCount = 0;
            foreach (($messages['inbox'] ?? []) as $msg) {
                if (empty($msg['is_read'])) $unreadCount++;
            }
            ?>
            <?php if ($unreadCount > 0): ?>
                <span class="ml-1.5 text-xs bg-orange-500 text-white rounded-full px-1.5 py-0.5"><?= $unreadCount ?></span>
            <?php endif; ?>
        </button>
        <button @click="tab = 'sent'"
                :class="tab === 'sent' ? 'border-b-2 border-teal-600 text-teal-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-3 text-sm transition-colors -mb-px">
            Sent
        </button>
    </div>

    <!-- Inbox -->
    <div x-show="tab === 'inbox'" x-transition>
        <?php if (!empty($messages['inbox'])): ?>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <ul class="divide-y divide-gray-100">
                    <?php foreach ($messages['inbox'] as $msg): ?>
                        <?php $unread = empty($msg['is_read']); ?>
                        <li>
                            <a href="<?= url('/messages/' . ($msg['id'] ?? '')) ?>"
                               class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 transition-colors <?= $unread ? 'bg-teal-50/40' : '' ?>">
                                <div class="w-9 h-9 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-semibold text-sm flex-shrink-0 mt-0.5">
                                    <?= e(mb_strtoupper(mb_substr($msg['sender_name'] ?? 'M', 0, 1))) ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm <?= $unread ? 'font-bold text-gray-900' : 'font-medium text-gray-700' ?>">
                                            <?= e($msg['sender_name'] ?? '') ?>
                                        </p>
                                        <p class="text-xs text-gray-400 flex-shrink-0"><?= e(time_ago($msg['created_at'] ?? '')) ?></p>
                                    </div>
                                    <p class="text-sm <?= $unread ? 'font-semibold text-gray-800' : 'text-gray-600' ?> truncate mt-0.5">
                                        <?php if ($unread): ?>
                                            <span class="inline-block w-2 h-2 rounded-full bg-teal-500 mr-1.5 -mt-0.5"></span>
                                        <?php endif; ?>
                                        <?= e($msg['subject'] ?? '(no subject)') ?>
                                    </p>
                                    <p class="text-xs text-gray-400 truncate mt-0.5"><?= e(truncate(strip_tags($msg['body'] ?? ''), 80)) ?></p>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
                <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <p class="text-sm text-gray-400">Your inbox is empty.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sent -->
    <div x-show="tab === 'sent'" x-cloak x-transition>
        <?php if (!empty($messages['sent'])): ?>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <ul class="divide-y divide-gray-100">
                    <?php foreach ($messages['sent'] as $msg): ?>
                        <li>
                            <a href="<?= url('/messages/' . ($msg['id'] ?? '')) ?>"
                               class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 transition-colors">
                                <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-semibold text-sm flex-shrink-0 mt-0.5">
                                    <?= e(mb_strtoupper(mb_substr($msg['recipient_name'] ?? 'T', 0, 1))) ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-medium text-gray-700">To: <?= e($msg['recipient_name'] ?? '') ?></p>
                                        <p class="text-xs text-gray-400 flex-shrink-0"><?= e(time_ago($msg['created_at'] ?? '')) ?></p>
                                    </div>
                                    <p class="text-sm text-gray-600 truncate mt-0.5"><?= e($msg['subject'] ?? '(no subject)') ?></p>
                                    <p class="text-xs text-gray-400 truncate mt-0.5"><?= e(truncate(strip_tags($msg['body'] ?? ''), 80)) ?></p>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
                <p class="text-sm text-gray-400">No sent messages yet.</p>
            </div>
        <?php endif; ?>
    </div>

</div>
