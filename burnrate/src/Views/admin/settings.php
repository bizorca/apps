<?php $title = 'Site Settings - Admin'; $h = \App\Core\Helpers::class; ?>
<?php ob_start(); ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Site Settings</h1>
        <a href="<?= url('/admin') ?>" class="text-red-600 hover:text-red-700 font-medium text-sm">Back to Admin</a>
    </div>

    <form method="POST" action="<?= url('/admin/settings') ?>" class="space-y-6">
        <?= $h::csrfField($csrf_token) ?>

        <!-- General Settings -->
        <div class="bg-white rounded-xl shadow-sm border p-6 space-y-5">
            <h2 class="text-lg font-semibold text-gray-900 border-b pb-3">General</h2>

            <label class="flex items-center space-x-3 cursor-pointer">
                <input type="checkbox" name="registration_open" value="1"
                    <?= ($settings['registration_open'] ?? '1') === '1' ? 'checked' : '' ?>
                    class="rounded border-gray-300 text-red-600 focus:ring-red-500 h-5 w-5">
                <div>
                    <span class="text-sm font-medium text-gray-700">Allow New Registrations</span>
                    <p class="text-xs text-gray-400">When disabled, the registration page shows a closed message.</p>
                </div>
            </label>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Site Tagline</label>
                <input type="text" name="site_tagline"
                    value="<?= htmlspecialchars($settings['site_tagline'] ?? '') ?>"
                    class="block w-full rounded-lg border-gray-300 border px-4 py-2 text-sm focus:border-red-500 focus:ring-red-500">
            </div>
        </div>

        <!-- Stripe Settings -->
        <div class="bg-white rounded-xl shadow-sm border p-6 space-y-5">
            <h2 class="text-lg font-semibold text-gray-900 border-b pb-3">Stripe Payments</h2>

            <label class="flex items-center space-x-3 cursor-pointer">
                <input type="checkbox" name="stripe_enabled" value="1"
                    <?= ($settings['stripe_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                    class="rounded border-gray-300 text-red-600 focus:ring-red-500 h-5 w-5">
                <div>
                    <span class="text-sm font-medium text-gray-700">Enable Stripe Payments</span>
                    <p class="text-xs text-gray-400">When enabled, pricing pages show purchase buttons and Stripe checkout is active.</p>
                </div>
            </label>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <p class="text-xs text-amber-700">Stripe keys are not stored in the database. Set BR_STRIPE_PUBLIC_KEY, BR_STRIPE_SECRET_KEY and BR_STRIPE_WEBHOOK_SECRET in the server's private_html/.env.php. (No checkout is built yet; this switch only changes the pricing page buttons.)</p>
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-red-600 px-8 py-3 text-sm font-bold text-white hover:bg-red-700 transition-colors">
            Save Settings
        </button>
    </form>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
