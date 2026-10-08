<?php
$pageTitle = $message['subject'] ?? 'Message';
$currentUserId = \TimeBank\Core\Auth::id();
$isSender = (int)($message['sender_id'] ?? 0) === (int)$currentUserId;
$replyRecipientId = $isSender ? ($message['recipient_id'] ?? '') : ($message['sender_id'] ?? '');
$replySubject = str_starts_with(strtolower($message['subject'] ?? ''), 're:')
    ? $message['subject']
    : 'Re: ' . ($message['subject'] ?? '');
?>

<div class="max-w-2xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <nav class="flex items-center gap-2 text-sm text-gray-500">
            <a href="<?= url('/messages') ?>" class="hover:text-teal-600 transition-colors">Messages</a>
            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-800 font-medium truncate max-w-[200px]"><?= e($message['subject'] ?? '') ?></span>
        </nav>
    </div>

    <!-- Message card -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-5">
        <div class="flex items-start justify-between gap-4 mb-5">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-semibold flex-shrink-0">
                    <?= e(mb_strtoupper(mb_substr($message['sender_name'] ?? 'M', 0, 1))) ?>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900"><?= e($message['sender_name'] ?? '') ?></p>
                    <p class="text-xs text-gray-400">
                        To: <?= e($message['recipient_name'] ?? '') ?> &bull; <?= e(date('F j, Y g:i a', strtotime($message['created_at'] ?? ''))) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-5">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= e($message['subject'] ?? '') ?></h2>
            <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap"><?= e($message['body'] ?? '') ?></div>
        </div>
    </div>

    <!-- Reply form -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Reply</h3>

        <?php if (!empty($errors)): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                <ul class="space-y-1">
                    <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('/messages/compose') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="recipient_id" value="<?= (int)$replyRecipientId ?>">
            <input type="hidden" name="subject" value="<?= e($replySubject) ?>">

            <div class="text-sm text-gray-500 bg-gray-50 rounded-xl px-4 py-2.5 border border-gray-200">
                Replying to <strong class="text-gray-700"><?= e($isSender ? ($message['recipient_name'] ?? '') : ($message['sender_name'] ?? '')) ?></strong>
                &mdash; subject: <em><?= e($replySubject) ?></em>
            </div>

            <textarea name="body" rows="5"
                      required
                      placeholder="Your reply..."
                      class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"><?= old('body') ?></textarea>

            <div class="flex gap-3">
                <a href="<?= url('/messages') ?>"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    &larr; Inbox
                </a>
                <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm">
                    Send Reply
                </button>
            </div>
        </form>
    </div>

</div>
