<?php
use Dispatch\Core\View;
use Dispatch\Core\Auth;
$isOwn = isset($isOwn) ? $isOwn : ((int) $doc['user_id'] === Auth::userId());
$hasFile = !empty($doc['file_path']);
$hasText = !empty($doc['content']);
?>
<div class="flex items-start gap-4 px-6 py-4">
    <!-- Icon -->
    <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center mt-0.5">
        <?php if ($hasFile && str_starts_with($doc['mime_type'] ?? '', 'image/')): ?>
        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <?php elseif ($hasFile): ?>
        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <?php else: ?>
        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
        </svg>
        <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-slate-900"><?= View::e($doc['name']) ?></span>
            <?php if ($doc['is_template']): ?>
            <span class="text-xs bg-violet-50 text-violet-700 px-2 py-0.5 rounded-full font-medium">Template</span>
            <?php endif; ?>
            <?php if (!empty($doc['campaign_name'])): ?>
            <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= View::e($doc['campaign_name']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($doc['description']): ?>
        <p class="text-xs text-slate-500 mt-0.5"><?= View::e($doc['description']) ?></p>
        <?php endif; ?>
        <p class="text-xs text-slate-400 mt-0.5">
            <?php if (!$isOwn): ?>By <?= View::e($doc['owner_name']) ?> · <?php endif; ?>
            <?= $hasFile ? View::e($doc['file_name']) . ' · ' . round($doc['file_size'] / 1024) . ' KB' : 'Text document' ?>
            · <?= View::relativeDate($doc['created_at']) ?>
        </p>
    </div>

    <!-- Actions -->
    <div class="flex-shrink-0 flex items-center gap-1.5">
        <?php if ($hasFile): ?>
        <a href="<?= $_base ?>/documents/<?= $doc['id'] ?>/download"
            class="px-2.5 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">
            Download
        </a>
        <?php elseif ($hasText): ?>
        <a href="<?= $_base ?>/documents/<?= $doc['id'] ?>/view"
            class="px-2.5 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">
            View
        </a>
        <?php endif; ?>

        <?php if ($isOwn): ?>
        <form method="POST" action="<?= $_base ?>/documents/<?= $doc['id'] ?>/template">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
            <button type="submit" class="px-2.5 py-1.5 text-xs font-medium <?= $doc['is_template'] ? 'text-violet-700 bg-violet-50 hover:bg-violet-100' : 'text-slate-600 bg-slate-100 hover:bg-slate-200' ?> rounded-lg transition-colors">
                <?= $doc['is_template'] ? 'Unshare' : 'Share' ?>
            </button>
        </form>
        <form method="POST" action="<?= $_base ?>/documents/<?= $doc['id'] ?>/delete" onsubmit="return confirm('Delete this document?')">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
            <button type="submit" class="px-2.5 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">Delete</button>
        </form>
        <?php endif; ?>
    </div>
</div>
