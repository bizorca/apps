<?php $pageTitle = 'Compose Message'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Compose Message</h1>
        <a href="<?= url('/messages') ?>" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Inbox</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <form method="POST" action="<?= url('/messages/compose') ?>" class="space-y-5"
              x-data="{ recipientSearch: '', filteredMembers: [] }">
            <?= csrf_field() ?>

            <!-- Recipient -->
            <div>
                <label for="recipient_id" class="block text-sm font-medium text-gray-700 mb-1.5">To <span class="text-red-500">*</span></label>
                <?php $preRecipient = $_GET['to'] ?? old('recipient_id', ''); ?>
                <select id="recipient_id" name="recipient_id" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white">
                    <option value="">-- Select a member --</option>
                    <?php foreach ($members ?? [] as $m): ?>
                        <?php $sel = (string)($m['id'] ?? '') === (string)$preRecipient; ?>
                        <option value="<?= (int)($m['id'] ?? 0) ?>" <?= $sel ? 'selected' : '' ?>>
                            <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                            <?php if (!empty($m['city'])): ?> &mdash; <?= e($m['city']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Subject -->
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1.5">Subject <span class="text-red-500">*</span></label>
                <input type="text" id="subject" name="subject"
                       value="<?= old('subject') ?>"
                       required maxlength="255"
                       placeholder="What's this about?"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
            </div>

            <!-- Body -->
            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 mb-1.5">Message <span class="text-red-500">*</span></label>
                <textarea id="body" name="body" rows="8"
                          required
                          placeholder="Write your message here..."
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"><?= old('body') ?></textarea>
            </div>

            <div class="flex items-center gap-3">
                <a href="<?= url('/messages') ?>"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm">
                    Send Message
                </button>
            </div>
        </form>
    </div>
</div>
