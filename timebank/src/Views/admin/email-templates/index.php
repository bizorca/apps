<?php $pageTitle = 'Email Templates'; ?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Email Templates</h1>
    <p class="text-sm text-gray-500 mt-1">Customize the emails sent to your members</p>
</div>

<?php if (!empty($templates)): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php foreach ($templates as $tpl): ?>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <h3 class="font-semibold text-gray-900"><?= e($tpl['name'] ?? '') ?></h3>
                        <code class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded mt-0.5 inline-block"><?= e($tpl['slug'] ?? '') ?></code>
                    </div>
                    <a href="<?= url('/admin/email-templates/' . ($tpl['slug'] ?? '') . '/edit') ?>"
                       class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs text-teal-700 bg-teal-50 rounded-lg hover:bg-teal-100 transition-colors font-medium">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </a>
                </div>
                <p class="text-xs text-gray-500 mb-3 italic"><?= e($tpl['subject'] ?? '') ?></p>
                <?php if (!empty($tpl['variables'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1.5">Available variables:</p>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (explode(',', $tpl['variables']) as $var): ?>
                                <code class="text-xs bg-teal-50 text-teal-700 px-1.5 py-0.5 rounded">{<?= '{'?>  <?= e(trim($var)) ?> <?= '}}'?>}</code>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No email templates found.</p>
    </div>
<?php endif; ?>
