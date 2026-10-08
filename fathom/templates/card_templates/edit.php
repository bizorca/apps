<?php
/** Ported from resources/views (Blade). */
$__title = 'Edit template';
ob_start();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('card-templates.index')) ?>" class="hover:text-gray-600">Templates</a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Edit template</span>
    </nav>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h1 class="text-lg font-semibold text-gray-900 mb-6">Edit template</h1>

        <form method="POST" action="<?= e(route('card-templates.update', $cardTemplate)) ?>"
              x-data="{
                  boardId: '<?= e($cardTemplate->defaultColumn?->board_id ?? '') ?>',
                  boards: <?= e($boards->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'columns' => $b->columns->map(fn($c) => ['id' => $c->id, 'name' => $c->name])])->toJson()) ?>,
                  steps: <?= e($cardTemplate->steps->pluck('title')->toJson() ?: "['']") ?>,
                  defaultColumnId: '<?= e($cardTemplate->default_column_id ?? '') ?>',
                  get columns() { const b = this.boards.find(b => b.id === this.boardId); return b ? b.columns : []; },
                  addStep() { this.steps.push(''); },
                  removeStep(i) { this.steps.splice(i, 1); }
              }"
              class="space-y-4">
            <?= csrf_field() ?>
            <?= method_field('PUT') ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Template name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="<?= e(old('name', $cardTemplate->name)) ?>" required autofocus
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title pattern</label>
                <input type="text" name="title_pattern" value="<?= e(old('title_pattern', $cardTemplate->title_pattern)) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="4"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= e(old('description', $cardTemplate->description)) ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default board</label>
                <select x-model="boardId"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">No default</option>
                    <?php foreach ($boards as $b): ?>
                        <option value="<?= e($b->id) ?>" <?= e(($cardTemplate->defaultColumn?->board_id === $b->id) ? 'selected' : '') ?>><?= e($b->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div x-show="columns.length > 0" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default column</label>
                <select name="default_column_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">No default</option>
                    <template x-for="col in columns" :key="col.id">
                        <option :value="col.id"
                                :selected="col.id === defaultColumnId"
                                x-text="col.name"></option>
                    </template>
                </select>
            </div>

            <?php if ($tags->isNotEmpty()): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Default tags</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($tags as $tag): ?>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="tags[]" value="<?= e($tag->id) ?>"
                                       <?= e(in_array($tag->id, old('tags', $cardTemplate->tags->pluck('id')->toArray())) ? 'checked' : '') ?>
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

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Checklist items</label>
                <div class="space-y-2">
                    <template x-for="(step, i) in steps" :key="i">
                        <div class="flex gap-2">
                            <input type="text" :name="`steps[${i}]`" x-model="steps[i]"
                                   class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <button type="button" @click="removeStep(i)"
                                    class="text-gray-300 hover:text-gray-500 text-lg leading-none px-1">×</button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addStep()"
                        class="mt-2 text-xs text-indigo-600 hover:underline">+ Add item</button>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                    Save changes
                </button>
                <a href="<?= e(route('card-templates.index')) ?>" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
