<?php
/** Ported from resources/views (Blade). */
$__title = $card->title;
ob_start();
?>
<div class="max-w-4xl mx-auto px-4 py-8">
    
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('boards.index')) ?>" class="hover:text-gray-600">Boards</a>
        <span class="mx-2">/</span>
        <a href="<?= e(route('boards.show', $card->board)) ?>" class="hover:text-gray-600"><?= e($card->board->name) ?></a>
        <span class="mx-2">/</span>
        <span class="text-gray-600"><?= e(Str::limit($card->title, 50)) ?></span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-6">
            
            <div>
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <?php if ($card->is_draft): ?>
                            <span class="inline-block text-xs font-medium px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded mb-2">Draft</span>
                        <?php endif; ?>
                        <?php if ($card->isClosed()): ?>
                            <span class="inline-block text-xs font-medium px-2 py-0.5 bg-gray-100 text-gray-600 rounded mb-2">Closed</span>
                        <?php endif; ?>
                        <?php if ($card->is_golden): ?>
                            <span class="inline-block text-xs font-medium px-2 py-0.5 bg-amber-100 text-amber-700 rounded mb-2">⭐ Golden</span>
                        <?php endif; ?>
                        <h1 class="text-2xl font-bold text-gray-900 leading-snug"><?= e($card->title) ?></h1>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="<?= e(route('cards.edit', $card)) ?>" class="text-sm text-gray-400 hover:text-gray-600">Edit</a>
                    </div>
                </div>

                <div class="flex items-center gap-4 mt-3 text-sm text-gray-400">
                    <span><?= e($card->column?->name ?? 'No column') ?></span>
                    <span>Created <?= e($card->created_at->diffForHumans()) ?></span>
                    <?php if ($card->creator): ?>
                        <span>by <?= e($card->creator->name) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            
            <?php if ($card->description): ?>
                <div class="prose prose-sm max-w-none text-gray-700 bg-white rounded-xl border border-gray-200 p-5">
                    <?= markdown($card->description) ?>
                </div>
            <?php endif; ?>

            
            <?php if ($card->steps->isNotEmpty()): ?>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">
                        Checklist
                        <span class="font-normal text-gray-400 ml-1"><?= e($card->completedStepsCount()) ?>/<?= e($card->totalStepsCount()) ?></span>
                    </h3>
                    <div class="space-y-2">
                        <?php foreach ($card->steps as $step): ?>
                            <div class="flex items-center gap-3">
                                <form method="POST" action="<?= e($step->completed ? route('cards.steps.incomplete', [$card, $step]) : route('cards.steps.complete', [$card, $step])) ?>">
                                    <?= csrf_field() ?> <?= method_field('PATCH') ?>
                                    <button type="submit" class="w-4 h-4 rounded border <?= e($step->completed ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300') ?> flex items-center justify-center flex-shrink-0">
                                        <?php if ($step->completed): ?>
                                            <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        <?php endif; ?>
                                    </button>
                                </form>
                                <span class="text-sm <?= e($step->completed ? 'line-through text-gray-400' : 'text-gray-700') ?>"><?= e($step->title) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">
                    Comments <span class="font-normal text-gray-400">(<?= e($card->comments->count()) ?>)</span>
                </h3>

                <?php $__empty1 = true; foreach ($card->comments as $comment): $__empty1 = false; ?>
                    <div id="comment-<?= e($comment->id) ?>" class="flex gap-3 mb-5">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-medium flex-shrink-0">
                            <?= e($comment->creatorInitials()) ?>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-baseline gap-2 mb-1">
                                <span class="text-sm font-medium text-gray-900"><?= e($comment->creatorName()) ?></span>
                                <span class="text-xs text-gray-400"><?= e($comment->created_at->diffForHumans()) ?></span>
                                <?php if ($comment->isEditableBy(auth()->user())): ?>
                                    <a href="<?= e(route('cards.comments.edit', [$card, $comment])) ?>" class="text-xs text-gray-400 hover:text-gray-600">Edit</a>
                                <?php endif; ?>
                            </div>
                            <div class="prose prose-sm max-w-none text-gray-700">
                                <?= $comment->html() ?>
                            </div>

                            
                            <?php if ($comment->reactions->isNotEmpty()): ?>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    <?php foreach ($comment->reactions->groupBy('emoji') as $emoji => $reactions): ?>
                                        <form method="POST" action="<?= e(route('comments.reactions.store', $comment)) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="emoji" value="<?= e($emoji) ?>">
                                            <button type="submit" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 hover:bg-gray-200 transition">
                                                <?= e($emoji) ?> <?= e($reactions->count()) ?>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; if ($__empty1): ?>
                    <p class="text-sm text-gray-400 mb-4">No comments yet.</p>
                <?php endif; ?>

                
                <form method="POST" action="<?= e(route('cards.comments.store', $card)) ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <textarea name="body" rows="3" placeholder="Add a comment… (Markdown supported)"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    <div class="mt-2">
                        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-1.5 rounded-lg hover:bg-indigo-700 transition">
                            Comment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        
        <div class="space-y-4">
            
            <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-2">
                <?php if ($card->isOpen()): ?>
                    <form method="POST" action="<?= e(route('cards.close', $card)) ?>">
                        <?= csrf_field() ?> <?= method_field('PATCH') ?>
                        <button type="submit" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-gray-50 text-gray-700">
                            ✓ Close card
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= e(route('cards.reopen', $card)) ?>">
                        <?= csrf_field() ?> <?= method_field('PATCH') ?>
                        <button type="submit" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-gray-50 text-gray-700">
                            ↩ Reopen card
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($card->isDraft()): ?>
                    <form method="POST" action="<?= e(route('cards.publish', $card)) ?>">
                        <?= csrf_field() ?> <?= method_field('PATCH') ?>
                        <button type="submit" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-gray-50 text-gray-700">
                            Publish card
                        </button>
                    </form>
                <?php endif; ?>

                <form method="POST" action="<?= e($isPinned ? route('cards.unpin', $card) : route('cards.pin', $card)) ?>">
                    <?= csrf_field() ?>
                    <?php if ($isPinned): ?> <?= method_field('DELETE') ?> <?php endif; ?>
                    <button type="submit" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-gray-50 text-gray-700">
                        <?= e($isPinned ? '📌 Unpin' : '📌 Pin card') ?>
                    </button>
                </form>

                <form method="POST" action="<?= e($isWatching ? route('cards.unwatch', $card) : route('cards.watch', $card)) ?>">
                    <?= csrf_field() ?>
                    <?php if ($isWatching): ?> <?= method_field('DELETE') ?> <?php endif; ?>
                    <button type="submit" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-gray-50 text-gray-700">
                        <?= e($isWatching ? '🔔 Watching' : '🔕 Watch card') ?>
                    </button>
                </form>

                <form method="POST" action="<?= e(route('cards.duplicate', $card)) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-gray-50 text-gray-700">
                        ⧉ Duplicate card
                    </button>
                </form>
            </div>

            
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Assignees</h4>
                <?php foreach ($card->assignees as $assignee): ?>
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">
                                <?= e($assignee->initials()) ?>
                            </div>
                            <span class="text-sm text-gray-700"><?= e($assignee->name) ?></span>
                        </div>
                        <form method="POST" action="<?= e(route('cards.assignments.destroy', [$card, $card->assignments->where('user_id', $assignee->id)->first()])) ?>">
                            <?= csrf_field() ?> <?= method_field('DELETE') ?>
                            <button type="submit" class="text-xs text-gray-300 hover:text-gray-500">×</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            
            <?php if ($card->tags->isNotEmpty()): ?>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Tags</h4>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($card->tags as $tag): ?>
                            <span class="text-xs px-2 py-0.5 rounded-full"
                                  style="background-color: <?= e($tag->color ?? '#e5e7eb') ?>; color: #374151">
                                <?= e($tag->name) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            
            <?php if ($card->due_at): ?>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Due</h4>
                    <p class="text-sm <?= e($card->due_at->isPast() ? 'text-red-600 font-medium' : 'text-gray-700') ?>">
                        <?= e($card->due_at->format('M j, Y')) ?>
                        <?php if ($card->due_at->isPast()): ?> (overdue) <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Client Access</h4>

                <?php foreach ($card->clients as $linkedClient): ?>
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs">
                                <?= e($linkedClient->initials()) ?>
                            </div>
                            <span class="text-sm text-gray-700"><?= e($linkedClient->name) ?></span>
                        </div>
                        <form method="POST" action="<?= e(route('cards.clients.destroy', [$card, $linkedClient])) ?>">
                            <?= csrf_field() ?> <?= method_field('DELETE') ?>
                            <button type="submit" class="text-xs text-gray-300 hover:text-gray-500">×</button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <?php if ($availableClients->isNotEmpty()): ?>
                    <?php $unlinkedClients = $availableClients->whereNotIn('id', $card->clients->pluck('id')); ?>
                    <?php if ($unlinkedClients->isNotEmpty()): ?>
                        <form method="POST" action="<?= e(route('cards.clients.store', $card)) ?>" class="mt-2">
                            <?= csrf_field() ?>
                            <select name="client_id" class="w-full text-xs border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-500 mb-1">
                                <option value="">Add client…</option>
                                <?php foreach ($unlinkedClients as $availableClient): ?>
                                    <option value="<?= e($availableClient->id) ?>"><?= e($availableClient->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full text-xs text-indigo-600 hover:text-indigo-800 py-1">
                                + Grant access
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-xs text-gray-400">
                        <a href="<?= e(route('clients.index')) ?>" class="hover:underline">Manage clients</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
