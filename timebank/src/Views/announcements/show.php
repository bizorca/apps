<?php $pageTitle = $announcement['title'] ?? 'Announcement'; ?>

<div class="max-w-3xl mx-auto">

    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="<?= url('/announcements') ?>" class="hover:text-teal-600 transition-colors">Announcements</a>
        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-800 font-medium truncate"><?= e($announcement['title'] ?? '') ?></span>
    </nav>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">

        <!-- Pin badge -->
        <?php if (!empty($announcement['is_pinned'])): ?>
            <div class="flex items-center gap-2 mb-4">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-orange-600 bg-orange-100 px-3 py-1 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                    Pinned Announcement
                </span>
            </div>
        <?php endif; ?>

        <h1 class="text-2xl font-bold text-gray-900 mb-4"><?= e($announcement['title'] ?? '') ?></h1>

        <!-- Meta -->
        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mb-6 pb-5 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-semibold text-xs">
                    <?= e(mb_strtoupper(mb_substr($announcement['author_name'] ?? 'A', 0, 1))) ?>
                </div>
                <a href="<?= url('/members/' . ($announcement['author_id'] ?? '')) ?>"
                   class="font-medium text-gray-700 hover:text-teal-700 transition-colors">
                    <?= e($announcement['author_name'] ?? '') ?>
                </a>
            </div>
            <span>&bull;</span>
            <span><?= e(date('F j, Y', strtotime($announcement['created_at'] ?? ''))) ?></span>
            <?php if (!empty($announcement['group_name'])): ?>
                <span>&bull;</span>
                <span class="bg-gray-100 px-2 py-0.5 rounded-full"><?= e($announcement['group_name']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="prose text-gray-700 text-base leading-relaxed">
            <?= nl2br(e($announcement['body'] ?? '')) ?>
        </div>

        <!-- Admin actions -->
        <?php if (\TimeBank\Core\Auth::isAdmin()): ?>
            <div class="mt-8 pt-6 border-t border-gray-100 flex gap-3">
                <a href="<?= url('/announcements/' . ($announcement['id'] ?? '') . '/edit') ?>"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </a>
                <form method="POST" action="<?= url('/announcements/' . ($announcement['id'] ?? '') . '/delete') ?>"
                      onsubmit="return confirm('Delete this announcement?')">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm text-red-600 bg-red-50 rounded-xl hover:bg-red-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete
                    </button>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <div class="mt-4 text-center">
        <a href="<?= url('/announcements') ?>" class="text-sm text-gray-500 hover:text-teal-600 transition-colors">&larr; All announcements</a>
    </div>

</div>
