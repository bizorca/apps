<?php
use Dispatch\Core\View;
use Dispatch\Core\Auth;
$isOwn = (int) $doc['user_id'] === Auth::userId();
?>
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="javascript:history.back()" class="text-sm text-slate-400 hover:text-slate-600 flex items-center gap-1 mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back
            </a>
            <h1 class="text-2xl font-bold text-slate-900"><?= View::e($doc['name']) ?></h1>
            <?php if ($doc['description']): ?>
            <p class="text-sm text-slate-500 mt-1"><?= View::e($doc['description']) ?></p>
            <?php endif; ?>
            <p class="text-xs text-slate-400 mt-1">
                <?= $isOwn ? 'Your document' : 'By ' . View::e($doc['owner_name']) ?>
                <?php if ($doc['campaign_name']): ?> · <?= View::e($doc['campaign_name']) ?><?php endif; ?>
                · <?= View::relativeDate($doc['created_at']) ?>
                <?php if ($doc['is_template']): ?> · <span class="text-violet-600 font-medium">Template</span><?php endif; ?>
            </p>
        </div>
        <?php if ($isOwn): ?>
        <div class="flex gap-2">
            <form method="POST" action="<?= $_base ?>/documents/<?= $doc['id'] ?>/template">
                <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                <button type="submit" class="px-3 py-2 text-sm font-medium text-violet-700 bg-violet-50 hover:bg-violet-100 rounded-xl transition-colors">
                    <?= $doc['is_template'] ? 'Unshare' : 'Share as Template' ?>
                </button>
            </form>
            <form method="POST" action="<?= $_base ?>/documents/<?= $doc['id'] ?>/delete" onsubmit="return confirm('Delete this document?')">
                <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                <button type="submit" class="px-3 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-xl transition-colors">Delete</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <pre class="whitespace-pre-wrap text-sm text-slate-800 font-sans leading-relaxed"><?= View::e($doc['content']) ?></pre>
    </div>
</div>
