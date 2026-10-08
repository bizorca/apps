<?php $pageTitle = 'Admin — Edit User'; ?>

<div class="mb-6">
    <a href="<?= url('admin/users') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Users</a>
    <h1 class="text-2xl font-bold mt-1">Edit User</h1>
</div>

<?php if ($error ?? null): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-5">
        <?= h($error) ?>
    </div>
<?php endif; ?>
<?php if ($success ?? null): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3 mb-5">
        <?= h($success) ?>
    </div>
<?php endif; ?>

<?php if (empty($canEditAccount)): ?>
<div class="bg-white border border-gray-200 rounded-xl p-6 max-w-lg mb-8">
    <p class="text-sm font-medium text-gray-900"><?= h(trim(($target['first_name'] ?? '') . ' ' . ($target['last_name'] ?? ''))) ?></p>
    <p class="text-sm text-gray-500"><?= h($target['email'] ?? '') ?></p>
    <p class="text-xs text-gray-400 mt-2">Name and email are this person's Bizorca Tools account, used to sign in to every tool. Only a site admin can change them.</p>
</div>
<?php else: ?>
<div class="bg-white border border-gray-200 rounded-xl p-6 max-w-lg mb-8">
    <form method="POST" action="<?= url("admin/users/{$target['id']}/update") ?>">
        <?= csrf_field() ?>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">First name</label>
                <input type="text" name="first_name" required
                       value="<?= h($target['first_name'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Last name</label>
                <input type="text" name="last_name"
                       value="<?= h($target['last_name'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
            <input type="email" name="email" required
                   value="<?= h($target['email'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <p class="text-xs text-gray-400 mt-1">Changing the email here updates it across the entire Bizorca system.</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors">
                Save changes
            </button>
            <a href="<?= url('admin/users') ?>" class="text-sm text-gray-400 hover:text-gray-600">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Admin Notes -->
<div class="max-w-lg">
    <h2 class="text-lg font-semibold mb-3">Admin Notes</h2>

    <?php if (empty($notes)): ?>
        <p class="text-sm text-gray-400 mb-4">No notes yet.</p>
    <?php else: ?>
        <div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200 mb-4">
            <?php foreach ($notes as $note): ?>
                <div class="px-4 py-3 flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($note['body']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">
                            <?= h($note['admin_first'] . ' ' . $note['admin_last']) ?>
                            &middot; <?= timeAgo($note['created_at']) ?>
                        </p>
                    </div>
                    <form method="POST" action="<?= url("admin/notes/{$note['id']}/delete") ?>"
                          onsubmit="return confirm('Delete this note?')">
                        <?= csrf_field() ?>
                        <button class="text-xs text-red-400 hover:text-red-600 transition-colors shrink-0">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-gray-200 rounded-xl p-4">
        <form method="POST" action="<?= url("admin/users/{$target['id']}/notes") ?>">
            <?= csrf_field() ?>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Add a note</label>
            <textarea name="body" rows="3" required
                      placeholder="Internal note about this user..."
                      class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"></textarea>
            <div class="mt-2 text-right">
                <button type="submit"
                        class="bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                    Save note
                </button>
            </div>
        </form>
    </div>
</div>
