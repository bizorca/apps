<?php $pageTitle = 'Edit: ' . ($template['name'] ?? 'Email Template'); ?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="<?= url('/admin/email-templates') ?>" class="hover:text-teal-600 transition-colors">Email Templates</a>
                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900"><?= e($template['name'] ?? '') ?></h1>
            <code class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded"><?= e($template['slug'] ?? '') ?></code>
        </div>
    </div>

    <!-- Variables reference -->
    <?php if (!empty($template['variables'])): ?>
        <div class="bg-teal-50 border border-teal-200 rounded-2xl p-4 mb-6">
            <p class="text-sm font-semibold text-teal-800 mb-2">Available Variables</p>
            <p class="text-xs text-teal-600 mb-3">Use these in your subject or body. They'll be replaced with real values when the email is sent.</p>
            <div class="flex flex-wrap gap-1.5">
                <?php foreach (explode(',', $template['variables']) as $var): ?>
                    <button type="button"
                            onclick="navigator.clipboard.writeText('{{<?= e(trim($var)) ?>}}')"
                            title="Click to copy"
                            class="text-xs bg-white border border-teal-200 text-teal-700 px-2 py-0.5 rounded-md hover:bg-teal-100 transition-colors font-mono cursor-pointer">
                        {{<?= e(trim($var)) ?>}}
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <form method="POST" action="<?= url('/admin/email-templates/' . ($template['slug'] ?? '') . '/edit') ?>"
              class="space-y-5">
            <?= csrf_field() ?>

            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1.5">Subject line</label>
                <input type="text" id="subject" name="subject"
                       value="<?= old('subject', $template['subject'] ?? '') ?>"
                       required maxlength="255"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent font-mono">
            </div>

            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 mb-1.5">Email body</label>
                <textarea id="body" name="body" rows="16"
                          required
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-y font-mono"><?= old('body', $template['body'] ?? '') ?></textarea>
                <p class="text-xs text-gray-400 mt-1">Plain text only. Use {{variable_name}} syntax for dynamic values.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <a href="<?= url('/admin/email-templates') ?>"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm">
                    Save Template
                </button>
            </div>
        </form>
    </div>
</div>
