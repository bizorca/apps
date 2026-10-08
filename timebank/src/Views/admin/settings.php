<?php $pageTitle = 'Settings'; ?>
<?php
$settings = $tenant['settings'] ?? [];
if (is_string($settings)) $settings = json_decode($settings, true) ?? [];
?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/admin/settings') ?>" class="space-y-6">
        <?= csrf_field() ?>

        <!-- General -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">General</h2>
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">Community name <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name"
                           value="<?= old('name', $tenant['name'] ?? '') ?>"
                           required maxlength="150"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label for="tagline" class="block text-sm font-medium text-gray-700 mb-1.5">Tagline <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" id="tagline" name="tagline"
                           value="<?= old('tagline', $tenant['tagline'] ?? '') ?>"
                           maxlength="255"
                           placeholder="e.g., Connecting neighbors through the gift of time."
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="currency_name" class="block text-sm font-medium text-gray-700 mb-1.5">Currency name (singular)</label>
                        <input type="text" id="currency_name" name="currency_name"
                               value="<?= old('currency_name', $tenant['currency_name'] ?? 'Hour') ?>"
                               maxlength="50"
                               placeholder="Hour"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label for="currency_name_plural" class="block text-sm font-medium text-gray-700 mb-1.5">Currency name (plural)</label>
                        <input type="text" id="currency_name_plural" name="currency_name_plural"
                               value="<?= old('currency_name_plural', $tenant['currency_name_plural'] ?? 'Hours') ?>"
                               maxlength="50"
                               placeholder="Hours"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1.5">Timezone</label>
                    <select id="timezone" name="timezone"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white">
                        <?php
                        $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
                        $currentTz = old('timezone', $tenant['timezone'] ?? 'America/Chicago');
                        foreach ($timezones as $tz):
                        ?>
                            <option value="<?= e($tz) ?>" <?= $tz === $currentTz ? 'selected' : '' ?>><?= e($tz) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Membership -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">Membership</h2>
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 p-4 bg-gray-50 rounded-xl">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Allow self-registration</p>
                        <p class="text-xs text-gray-500 mt-0.5">When enabled, anyone can create an account from the registration page.</p>
                    </div>
                    <label class="relative flex-shrink-0 cursor-pointer" x-data>
                        <input type="checkbox" name="allow_self_registration" value="1"
                               <?= !empty($settings['allow_self_registration']) ? 'checked' : '' ?>
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-teal-500 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
                    </label>
                </div>
                <div class="flex items-start justify-between gap-4 p-4 bg-gray-50 rounded-xl">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Require admin approval</p>
                        <p class="text-xs text-gray-500 mt-0.5">New members must be approved by an admin before participating.</p>
                    </div>
                    <label class="relative flex-shrink-0 cursor-pointer">
                        <input type="checkbox" name="require_approval" value="1"
                               <?= !empty($settings['require_approval']) ? 'checked' : '' ?>
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-teal-500 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
                    </label>
                </div>
                <div>
                    <label for="welcome_credits" class="block text-sm font-medium text-gray-700 mb-1.5">Welcome credits</label>
                    <input type="number" id="welcome_credits" name="welcome_credits"
                           value="<?= old('welcome_credits', $tenant['welcome_credits'] ?? '1.00') ?>"
                           min="0" step="0.25"
                           class="w-full sm:w-48 px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <p class="text-xs text-gray-400 mt-1">Hours credited to new members when they join. Set to 0 to disable.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 justify-end">
            <button type="submit"
                    class="px-8 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                Save Settings
            </button>
        </div>

    </form>
</div>
