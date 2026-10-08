<?php
/** Ported from resources/views (Blade). */
$__title = 'Export data';
ob_start();
?>
<div class="max-w-lg mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('account.show')) ?>" class="hover:text-gray-600">Account</a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Export data</span>
    </nav>

    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h1 class="text-lg font-semibold text-gray-900 mb-2">Export your data</h1>
        <p class="text-sm text-gray-500 mb-5">
            Your export will include all boards, cards, comments, and tags. It's built right away, and we'll email you the download link too.
        </p>

        <form method="POST" action="<?= e(route('account.export.store')) ?>">
            <?= csrf_field() ?>
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded-lg hover:bg-indigo-700 transition">
                Request new export
            </button>
        </form>
    </div>

    <?php if ($exports->isNotEmpty()): ?>
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Recent exports</h2>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <?php foreach ($exports as $export): ?>
                <div class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-700"><?= e($export->created_at->format('M j, Y g:ia')) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">Requested by <?= e($export->user->name ?? 'you') ?></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if ($export->status === 'completed' && $export->file_path): ?>
                            <a href="<?= e(route('account.export.download', $export)) ?>"
                               class="text-sm text-indigo-600 hover:underline">Download</a>
                        <?php elseif ($export->status === 'pending' || $export->status === 'processing'): ?>
                            <span class="text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full"><?= e(ucfirst($export->status)) ?></span>
                        <?php elseif ($export->status === 'failed'): ?>
                            <span class="text-xs text-red-600 bg-red-50 px-2 py-0.5 rounded-full">Failed</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
