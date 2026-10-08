<?php
/** Ported from resources/views (Blade). */
$__title = 'Client Portal — Sign In';
ob_start();
?>
<div class="max-w-md mx-auto px-4 py-16">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Client Portal</h1>
        <p class="text-sm text-gray-500 mt-2">Enter your email and we'll send you a sign-in link.</p>
    </div>

    <?php if (session('success')): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-800 mb-6">
            <?= e(session('success')) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors->any()): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-800 mb-6">
            <?php foreach ($errors->all() as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="<?= e(route('portal.login.store')) ?>">
            <?= csrf_field() ?>
            <div class="mb-4">
                <label for="email_address" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                <input
                    type="email"
                    id="email_address"
                    name="email_address"
                    value="<?= e(old('email_address')) ?>"
                    required
                    autofocus
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="you@example.com"
                >
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:bg-indigo-700 transition">
                Send sign-in link
            </button>
        </form>
    </div>
</div>
<?php fm_layout('public', (string) $__title, (string) ob_get_clean()); ?>
