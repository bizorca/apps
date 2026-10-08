<?php $pageTitle = 'Admin — Bulk Email'; ?>

<div class="mb-6">
    <a href="<?= url('admin') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Dashboard</a>
    <h1 class="text-2xl font-bold mt-1">Bulk Email</h1>
    <p class="text-sm text-gray-400 mt-1">Send an email to all <strong><?= (int)$memberCount ?></strong> active pod members.</p>
</div>

<div class="bg-white border border-gray-200 rounded-xl p-6 max-w-2xl">
    <form method="POST" action="<?= url('admin/email') ?>">
        <?= csrf_field() ?>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Subject</label>
            <input type="text" name="subject" required
                   class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                   placeholder="Email subject line">
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Message body</label>
            <textarea name="body" rows="12" required
                      class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-y font-mono"
                      placeholder="Write your message here..."></textarea>
            <p class="text-xs text-gray-400 mt-1">Plain text. Line breaks are preserved.</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    onclick="return confirm('Send this email to all <?= (int)$memberCount ?> active members?')"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors">
                Send to <?= (int)$memberCount ?> members
            </button>
            <a href="<?= url('admin') ?>" class="text-sm text-gray-400 hover:text-gray-600">Cancel</a>
        </div>
    </form>
</div>
