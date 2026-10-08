<?php $pageTitle = 'New Support Ticket'; ?>

<div class="max-w-2xl">
    <div class="mb-6">
        <a href="<?= url('tickets') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Tickets</a>
        <h1 class="text-2xl font-bold mt-1">Open a support ticket</h1>
    </div>

    <form method="POST" action="<?= url('tickets/new') ?>" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <?= csrf_field() ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
            <input type="text" name="subject" required maxlength="255"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                   placeholder="Brief summary of your issue">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
            <textarea name="body" rows="6" required
                      class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none"
                      placeholder="Describe your issue in detail…"></textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                Submit ticket
            </button>
        </div>
    </form>
</div>
