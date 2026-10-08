<?php $pageTitle = 'Add Member'; ?>
<?php $currencyName = $tenant['currency_name'] ?? 'Hour'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Add Member</h1>
        <a href="<?= url('/admin/members') ?>" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Back to members</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/admin/members/create') ?>" class="space-y-6">
        <?= csrf_field() ?>

        <!-- Basic info -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">Personal Information</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1.5">First name <span class="text-red-500">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?= old('first_name') ?>" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1.5">Last name <span class="text-red-500">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="<?= old('last_name') ?>" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="sm:col-span-2">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" value="<?= old('email') ?>" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs text-gray-500">Members sign in with a Bizorca Tools account. If this email has none yet, one is created without a password; ask them to set it with "Forgot your password?".</p>
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700 mb-1.5">City</label>
                    <input type="text" id="city" name="city" value="<?= old('city') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700 mb-1.5">State</label>
                    <input type="text" id="state" name="state" value="<?= old('state') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="sm:col-span-2">
                    <label for="bio" class="block text-sm font-medium text-gray-700 mb-1.5">Bio <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea id="bio" name="bio" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 resize-none"><?= old('bio') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Account settings -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">Account Settings</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1.5">Role</label>
                    <select id="role" name="role"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white">
                        <option value="member"      <?= old('role', 'member') === 'member'      ? 'selected' : '' ?>>Member</option>
                        <option value="admin"       <?= old('role', 'member') === 'admin'       ? 'selected' : '' ?>>Admin</option>
                        <option value="super_admin" <?= old('role', 'member') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_approved" value="1"
                           <?= old('is_approved', '1') ? 'checked' : '' ?>
                           class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Approved</p>
                        <p class="text-xs text-gray-500">Approved members can participate fully. Uncheck to create as pending.</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           <?= old('is_active', '1') ? 'checked' : '' ?>
                           class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Active</p>
                        <p class="text-xs text-gray-500">Inactive members cannot log in.</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="send_welcome" value="1"
                           <?= old('send_welcome', '1') ? 'checked' : '' ?>
                           class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Send welcome email</p>
                        <p class="text-xs text-gray-500">Send the welcome email template to the new member.</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="<?= url('/admin/members') ?>"
               class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                Cancel
            </a>
            <button type="submit"
                    class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm">
                Add Member
            </button>
        </div>

    </form>
</div>
