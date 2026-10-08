<?php
/** Ported from resources/views (Blade). */
$__title = 'New card';
ob_start();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('boards.index')) ?>" class="hover:text-gray-600">Boards</a>
        <?php if ($board): ?>
            <span class="mx-2">/</span>
            <a href="<?= e(route('boards.show', $board)) ?>" class="hover:text-gray-600"><?= e($board->name) ?></a>
        <?php endif; ?>
        <span class="mx-2">/</span>
        <span class="text-gray-600">New card</span>
    </nav>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h1 class="text-lg font-semibold text-gray-900 mb-6">New card</h1>

        <?php if (isset($templates) && $templates->isNotEmpty()): ?>
        <div class="mb-5 pb-5 border-b border-gray-100"
             x-data="{
                 templates: <?= e($templates->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'title_pattern' => $t->title_pattern, 'description' => $t->description])->toJson()) ?>,
                 apply(id) {
                     const t = this.templates.find(t => t.id === id);
                     if (!t) return;
                     if (t.title_pattern) document.getElementById('title').value = t.title_pattern;
                     if (t.description)   document.getElementById('description').value = t.description;
                 }
             }">
            <label class="block text-sm font-medium text-gray-700 mb-1">Start from template</label>
            <select @change="apply($event.target.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Blank card</option>
                <?php foreach ($templates as $template): ?>
                    <option value="<?= e($template->id) ?>"><?= e($template->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= e(route('cards.store')) ?>" x-data="{
            boardId: '<?= e($board?->id ?? '') ?>',
            columns: <?= e($board ? $board->columns->toJson() : '[]') ?>,
            boards: <?= e($boards->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'columns' => $b->columns->map(fn($c) => ['id' => $c->id, 'name' => $c->name])])->toJson()) ?>,
            get boardColumns() {
                const b = this.boards.find(b => b.id === this.boardId);
                return b ? b.columns : [];
            }
        }">
            <?= csrf_field() ?>

            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Board <span class="text-red-500">*</span></label>
                <?php if ($board): ?>
                    <input type="hidden" name="board_id" value="<?= e($board->id) ?>">
                    <div class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2"><?= e($board->name) ?></div>
                <?php else: ?>
                    <select name="board_id" x-model="boardId" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">Select a board…</option>
                        <?php foreach ($boards as $b): ?>
                            <option value="<?= e($b->id) ?>" <?= e(old('board_id') === $b->id ? 'selected' : '') ?>><?= e($b->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Column <span class="text-red-500">*</span></label>
                <select name="column_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                    <?php if ($board): ?>
                        <?php foreach ($board->columns as $col): ?>
                            <option value="<?= e($col->id) ?>" <?= e((old('column_id', $column?->id) === $col->id) ? 'selected' : '') ?>><?= e($col->name) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <template x-for="col in boardColumns" :key="col.id">
                            <option :value="col.id" x-text="col.name"></option>
                        </template>
                    <?php endif; ?>
                </select>
            </div>

            
            <div class="mb-4">
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                <input type="text" id="title" name="title" value="<?= e(old('title')) ?>" required autofocus
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       placeholder="What needs to happen?">
            </div>

            
            <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="4"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                          placeholder="Add context, links, or notes…"><?= e(old('description')) ?></textarea>
            </div>

            
            <div class="mb-4">
                <label for="due_at" class="block text-sm font-medium text-gray-700 mb-1">Due date</label>
                <input type="date" id="due_at" name="due_at" value="<?= e(old('due_at')) ?>"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            
            <?php if ($tags->isNotEmpty()): ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($tags as $tag): ?>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="tags[]" value="<?= e($tag->id) ?>"
                                       <?= e(in_array($tag->id, old('tags', [])) ? 'checked' : '') ?>
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                                      style="background-color: <?= e($tag->color ?? '#e5e7eb') ?>; color: #374151">
                                    <?= e($tag->name) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            
            <?php if ($members->isNotEmpty()): ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assign to</label>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ($members as $member): ?>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="assignees[]" value="<?= e($member->id) ?>"
                                       <?= e(in_array($member->id, old('assignees', [])) ? 'checked' : '') ?>
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-medium">
                                        <?= e(substr($member->name, 0, 1)) ?>
                                    </div>
                                    <span class="text-sm text-gray-700"><?= e($member->name) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            
            <div class="mb-6 flex items-center gap-2">
                <input type="checkbox" id="is_draft" name="is_draft" value="1"
                       <?= e(old('is_draft') ? 'checked' : '') ?>
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_draft" class="text-sm text-gray-700">Save as draft</label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                    Create card
                </button>
                <?php if ($board): ?>
                    <a href="<?= e(route('boards.show', $board)) ?>" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <?php else: ?>
                    <a href="<?= e(route('boards.index')) ?>" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
