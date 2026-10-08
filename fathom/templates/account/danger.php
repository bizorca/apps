<?php
/** Ported from resources/views (Blade). */
$__title = 'Danger zone';
ob_start();
?>
<div class="max-w-lg mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('account.show')) ?>" class="hover:text-gray-600">Account</a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Danger zone</span>
    </nav>

    <div class="bg-white rounded-xl border border-red-200 p-6">
        <h1 class="text-lg font-semibold text-red-700 mb-2">Cancel account</h1>
        <p class="text-sm text-gray-600 mb-6">
            This will permanently delete your account, all boards, cards, and data. There is no undo.
            Every member of this account will lose access immediately.
        </p>

        <form method="POST" action="<?= e(route('account.cancel')) ?>"
              x-data="{ confirmed: '' }"
              onsubmit="return confirm('Are you absolutely sure? This cannot be undone.')">
            <?= csrf_field() ?>
            <?= method_field('DELETE') ?>

            <div class="mb-4">
                <label for="confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                    Type <strong>DELETE</strong> to confirm
                </label>
                <input type="text" id="confirmation" name="confirmation"
                       x-model="confirmed"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500"
                       placeholder="DELETE"
                       autocomplete="off">
            </div>

            <button type="submit"
                    :disabled="confirmed !== 'DELETE'"
                    :class="confirmed === 'DELETE' ? 'bg-red-600 hover:bg-red-700 cursor-pointer' : 'bg-red-200 cursor-not-allowed'"
                    class="w-full text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                Cancel account permanently
            </button>
        </form>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
