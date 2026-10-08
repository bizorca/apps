<?php
/** Ported from resources/views (Blade). */
$__title = $board->name;
ob_start();
?>
<div class="flex flex-col h-[calc(100vh-56px)]"
     x-data="{
         boardId: '<?= e($board->id) ?>',
         dragging: null,
         clearIndicators(zone) {
             zone.querySelectorAll('[id^=card-]').forEach(c =>
                 c.classList.remove('x-drag-top', 'x-drag-bot', 'border-t-2', 'border-b-2', 'border-indigo-400')
             );
         },
         async drop(event, columnId) {
             if (!this.dragging) return;
             const cardId = this.dragging;
             this.dragging = null;
             event.currentTarget.classList.remove('ring-2', 'ring-indigo-300', 'bg-indigo-50');

             const zone = document.getElementById('col-' + columnId);
             const btn  = zone ? zone.querySelector('[data-add-card]') : null;
             const el   = document.getElementById('card-' + cardId);
             if (!zone || !el) return;

             const overTop = zone.querySelector('.x-drag-top');
             this.clearIndicators(zone);
             el.classList.remove('opacity-50');

             // Insert at the correct position in the DOM
             zone.insertBefore(el, overTop ?? btn);

             // Read the new order from DOM and persist all positions at once
             const cardIds = Array.from(zone.querySelectorAll('[id^=card-]'))
                 .map(c => c.id.replace('card-', ''));

             await fetch(window.fmUrl('/boards/' + this.boardId + '/cards/reorder'), {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json',
                     'Accept': 'application/json',
                     'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                 },
                 body: JSON.stringify({ column_id: columnId, card_ids: cardIds })
             });
         },
         dragOverCol(event, columnId) {
             const zone       = document.getElementById('col-' + columnId);
             if (!zone) return;
             const draggingEl = document.getElementById('card-' + this.dragging);
             const cards      = Array.from(zone.querySelectorAll('[id^=card-]')).filter(c => c !== draggingEl);
             this.clearIndicators(zone);
             let placed = false;
             for (const card of cards) {
                 const rect = card.getBoundingClientRect();
                 if (event.clientY < rect.top + rect.height / 2) {
                     card.classList.add('x-drag-top', 'border-t-2', 'border-indigo-400');
                     placed = true;
                     break;
                 }
             }
             if (!placed && cards.length > 0) {
                 cards[cards.length - 1].classList.add('x-drag-bot', 'border-b-2', 'border-indigo-400');
             }
         }
     }">
    
    <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
            <?php if ($board->color): ?>
                <div class="w-3 h-3 rounded-full" style="background-color: <?= e($board->color) ?>"></div>
            <?php endif; ?>
            <h1 class="font-semibold text-gray-900"><?= e($board->name) ?></h1>
        </div>

        <div class="flex items-center gap-2 text-sm">
            
            <?php foreach ($filters as $filter): ?>
                <a href="<?= e(route('boards.show', array_merge([$board], (array) $filter->params))) ?>"
                   class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-600">
                    <?= e($filter->name) ?>
                </a>
            <?php endforeach; ?>

            
            <a href="<?= e(route('cards.create', ['board_id' => $board->id])) ?>"
               class="bg-indigo-600 text-white text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition">
                + Card
            </a>

            
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="text-gray-400 hover:text-gray-600 p-1">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                    </svg>
                </button>
                <div x-show="open" @click.away="open = false" x-cloak
                     class="absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-md shadow-lg z-50 py-1">
                    <a href="<?= e(route('boards.edit', $board)) ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Edit board</a>
                    <a href="<?= e(route('boards.columns.create', $board)) ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Add column</a>
                    <?php if ($board->is_public): ?>
                        <a href="<?= e($board->publicUrl()) ?>" target="_blank" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">View public page</a>
                    <?php endif; ?>
                    <hr class="my-1">
                    <form method="POST" action="<?= e(route('boards.archive', $board)) ?>">
                        <?= csrf_field() ?>
                        <?= method_field('PATCH') ?>
                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Archive board</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    
    <div class="flex-1 overflow-x-auto">
        <div class="flex gap-4 p-6 h-full" style="min-width: max-content">
            <?php $__empty1 = true; foreach ($board->columns as $column): $__empty1 = false; ?>
                <div class="w-72 flex-shrink-0 flex flex-col">
                    
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <?php if ($column->color): ?>
                                <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?= e($column->color) ?>"></div>
                            <?php endif; ?>
                            <h3 class="text-sm font-semibold text-gray-700"><?= e($column->name) ?></h3>
                            <span class="text-xs text-gray-400 bg-gray-100 rounded-full px-1.5 py-0.5">
                                <?= e($column->cards->count()) ?>
                            </span>
                        </div>
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="text-gray-300 hover:text-gray-500 p-0.5">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                </svg>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 class="absolute right-0 mt-1 w-40 bg-white border border-gray-200 rounded-md shadow-lg z-50 py-1">
                                <a href="<?= e(route('boards.columns.edit', [$board, $column])) ?>"
                                   class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">Edit column</a>
                                <form method="POST" action="<?= e(route('boards.columns.move_left', [$board, $column])) ?>">
                                    <?= csrf_field() ?> <?= method_field('PATCH') ?>
                                    <button class="block w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">← Move left</button>
                                </form>
                                <form method="POST" action="<?= e(route('boards.columns.move_right', [$board, $column])) ?>">
                                    <?= csrf_field() ?> <?= method_field('PATCH') ?>
                                    <button class="block w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">Move right →</button>
                                </form>
                                <hr class="my-1">
                                <form method="POST" action="<?= e(route('boards.columns.destroy', [$board, $column])) ?>">
                                    <?= csrf_field() ?> <?= method_field('DELETE') ?>
                                    <button class="block w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-gray-50"
                                            onclick="return confirm('Delete this column? Cards will be unassigned.')">
                                        Delete column
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    
                    <div id="col-<?= e($column->id) ?>"
                         class="flex-1 space-y-2 overflow-y-auto rounded-lg transition"
                         @dragover.prevent="$event.currentTarget.classList.add('ring-2','ring-indigo-300','bg-indigo-50'); dragOverCol($event, '<?= e($column->id) ?>')"
                         @dragleave="if(!$event.currentTarget.contains($event.relatedTarget)){$event.currentTarget.classList.remove('ring-2','ring-indigo-300','bg-indigo-50'); clearIndicators($event.currentTarget)}"
                         @drop.prevent="drop($event, '<?= e($column->id) ?>')">
                        <?php foreach ($column->cards as $card): ?>
                            <a id="card-<?= e($card->id) ?>"
                               href="<?= e(route('cards.show', $card)) ?>"
                               draggable="true"
                               @dragstart="dragging = '<?= e($card->id) ?>'; $el.classList.add('opacity-50')"
                               @dragend="dragging = null; $el.classList.remove('opacity-50'); document.querySelectorAll('.x-drag-top,.x-drag-bot').forEach(c => c.classList.remove('x-drag-top','x-drag-bot','border-t-2','border-b-2','border-indigo-400'))"
                               class="block bg-white rounded-lg border border-gray-200 p-3 hover:border-indigo-300 hover:shadow-sm transition group cursor-grab active:cursor-grabbing">
                                <?php if ($card->color): ?>
                                    <div class="h-1 rounded-full mb-2" style="background-color: <?= e($card->color) ?>"></div>
                                <?php endif; ?>
                                <p class="text-sm text-gray-900 font-medium leading-snug"><?= e($card->title) ?></p>

                                
                                <?php if ($card->tags->isNotEmpty()): ?>
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        <?php foreach ($card->tags as $tag): ?>
                                            <span class="text-xs px-1.5 py-0.5 rounded-full"
                                                  style="background-color: <?= e($tag->color ?? '#e5e7eb') ?>; color: #374151">
                                                <?= e($tag->name) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                
                                <div class="flex items-center justify-between mt-2">
                                    <div class="flex -space-x-1">
                                        <?php foreach ($card->assignees->take(3) as $assignee): ?>
                                            <div class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs border border-white"
                                                 title="<?= e($assignee->name) ?>">
                                                <?= e(substr($assignee->name, 0, 1)) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-gray-400">
                                        <?php if ($card->due_at): ?>
                                            <?php
                                                // Red when overdue, amber within 3 days. The original compared a
                                                // signed Carbon 3 diff, so every future date came out amber.
                                                $dueCls = $card->due_at->isPast()
                                                    ? 'text-red-500 font-medium'
                                                    : ($card->due_at->daysFromNow() <= 3 ? 'text-amber-500 font-medium' : 'text-gray-400');
                                            ?>
                                            <span class="<?= e($dueCls) ?>" title="Due <?= e($card->due_at->format('M j, Y')) ?>">
                                                <?= e($card->due_at->format('M j')) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($card->stalled_at): ?>
                                            <span class="flex items-center gap-1 text-amber-500 font-medium" title="No activity in 14+ days">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
                                                </svg>
                                                Stalled
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($card->totalStepsCount() > 0): ?>
                                            <span><?= e($card->completedStepsCount()) ?>/<?= e($card->totalStepsCount()) ?></span>
                                        <?php endif; ?>
                                        <?php if ($card->is_golden): ?>
                                            <span title="Golden card">⭐</span>
                                        <?php endif; ?>
                                        <?php if ($card->is_draft): ?>
                                            <span class="text-yellow-500">Draft</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>

                        
                        <a href="<?= e(route('cards.create', ['board_id' => $board->id, 'column_id' => $column->id])) ?>"
                           data-add-card
                           class="block text-center text-xs text-gray-400 hover:text-gray-600 py-2 border border-dashed border-gray-200 hover:border-gray-300 rounded-lg transition">
                            + Add card
                        </a>
                    </div>
                </div>
            <?php endforeach; if ($__empty1): ?>
                <div class="flex items-center justify-center w-full text-gray-400 text-sm">
                    No columns yet.
                    <a href="<?= e(route('boards.columns.create', $board)) ?>" class="ml-2 text-indigo-600 hover:underline">Add one</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
