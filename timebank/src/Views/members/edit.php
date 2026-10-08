<?php $pageTitle = 'Edit Profile'; ?>
<?php $m = $member ?? []; ?>

<div class="max-w-3xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Edit Profile</h1>
        <a href="<?= url('/members/' . ($m['id'] ?? '')) ?>"
           class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
            &larr; Back to profile
        </a>
    </div>

    <!-- Validation errors -->
    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Avatar section — separate form -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4">Profile Photo</h2>
        <div class="flex items-center gap-5">
            <?php if (!empty($m['avatar_path'])): ?>
                <img src="<?= e(media_url($m['avatar_path'])) ?>" alt="" class="w-20 h-20 rounded-2xl object-cover ring-4 ring-teal-50">
            <?php else: ?>
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center text-white font-bold text-2xl">
                    <?= e(mb_strtoupper(mb_substr($m['first_name'] ?? 'M', 0, 1))) ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="<?= url('/profile/avatar') ?>" enctype="multipart/form-data" class="flex-1">
                <?= csrf_field() ?>
                <label class="block text-sm text-gray-600 mb-2">Upload a new photo (JPG, PNG, max 2MB)</label>
                <div class="flex items-center gap-3">
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp"
                           class="block text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 file:cursor-pointer">
                    <button type="submit"
                            class="px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors whitespace-nowrap">
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main profile form -->
    <form method="POST" action="<?= url('/profile/edit') ?>">
        <?= csrf_field() ?>

        <!-- Basic info -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">Basic Information</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1.5">First name</label>
                    <input type="text" id="first_name" name="first_name"
                           value="<?= old('first_name', $m['first_name'] ?? '') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1.5">Last name</label>
                    <input type="text" id="last_name" name="last_name"
                           value="<?= old('last_name', $m['last_name'] ?? '') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                </div>
                <div class="sm:col-span-2">
                    <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Display name <span class="text-gray-400 font-normal">(shown instead of full name if set)</span>
                    </label>
                    <input type="text" id="display_name" name="display_name"
                           value="<?= old('display_name', $m['display_name'] ?? '') ?>"
                           placeholder="Leave blank to use your full name"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                </div>
                <div class="sm:col-span-2">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                    <input type="email" id="email" value="<?= e($m['email'] ?? '') ?>" readonly disabled
                           class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50 rounded-xl text-sm text-gray-500">
                    <p class="mt-1 text-xs text-gray-500">This is your Bizorca Tools account email, shared by every tool. Name and password live in <a href="/account/settings.php" class="text-teal-700 hover:underline">account settings</a>.</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="bio" class="block text-sm font-medium text-gray-700 mb-1.5">Bio</label>
                    <textarea id="bio" name="bio" rows="4"
                              placeholder="Tell the community about yourself — skills, interests, what you can offer..."
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"><?= old('bio', $m['bio'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1.5">Phone <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="tel" id="phone" name="phone"
                           value="<?= old('phone', $m['phone'] ?? '') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                </div>
            </div>
        </div>

        <!-- Address -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">Location</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1.5">Street address <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" id="address" name="address"
                           value="<?= old('address', $m['address'] ?? '') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700 mb-1.5">City</label>
                    <input type="text" id="city" name="city"
                           value="<?= old('city', $m['city'] ?? '') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                </div>
                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700 mb-1.5">State</label>
                    <input type="text" id="state" name="state"
                           value="<?= old('state', $m['state'] ?? '') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                </div>
                <div>
                    <label for="zip" class="block text-sm font-medium text-gray-700 mb-1.5">ZIP / Postal code</label>
                    <input type="text" id="zip" name="zip"
                           value="<?= old('zip', $m['zip'] ?? '') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                </div>
                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700 mb-1.5">Country</label>
                    <input type="text" id="country" name="country"
                           value="<?= old('country', $m['country'] ?? 'US') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                </div>
            </div>
        </div>

        <!-- Privacy settings -->
        <?php
        $privacy = [];
        if (!empty($m['privacy_settings'])) {
            $privacy = is_array($m['privacy_settings']) ? $m['privacy_settings'] : json_decode($m['privacy_settings'], true) ?? [];
        }
        ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-800 mb-1">Privacy</h2>
            <p class="text-sm text-gray-500 mb-4">Control what other members can see on your profile.</p>
            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="show_email" value="1"
                           <?= !empty($privacy['show_email']) ? 'checked' : '' ?>
                           class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                    <span class="text-sm text-gray-700">Show my email address to other members</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="show_phone" value="1"
                           <?= !empty($privacy['show_phone']) ? 'checked' : '' ?>
                           class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                    <span class="text-sm text-gray-700">Show my phone number to other members</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="show_address" value="1"
                           <?= !empty($privacy['show_address']) ? 'checked' : '' ?>
                           class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                    <span class="text-sm text-gray-700">Show my address to other members</span>
                </label>
            </div>
        </div>

        <!-- Email preferences -->
        <?php
        $emailPrefs = [];
        if (!empty($m['email_preferences'])) {
            $emailPrefs = is_array($m['email_preferences']) ? $m['email_preferences'] : json_decode($m['email_preferences'], true) ?? [];
        }
        ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-800 mb-1">Email Notifications</h2>
            <p class="text-sm text-gray-500 mb-4">Choose what emails you'd like to receive.</p>
            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="weekly_digest" value="1"
                           <?= !empty($emailPrefs['weekly_digest']) ? 'checked' : '' ?>
                           class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Weekly digest</p>
                        <p class="text-xs text-gray-500">A summary of activity in the timebank each week</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="new_message" value="1"
                           <?= !empty($emailPrefs['new_message']) ? 'checked' : '' ?>
                           class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                    <div>
                        <p class="text-sm font-medium text-gray-700">New message notifications</p>
                        <p class="text-xs text-gray-500">Email when someone sends you a message</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="transaction_recorded" value="1"
                           <?= !empty($emailPrefs['transaction_recorded']) ? 'checked' : '' ?>
                           class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Transaction recorded</p>
                        <p class="text-xs text-gray-500">Email when a transaction is recorded on your account</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex items-center gap-3 justify-end">
            <a href="<?= url('/members/' . ($m['id'] ?? '')) ?>"
               class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                Cancel
            </a>
            <button type="submit"
                    class="px-6 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                Save Changes
            </button>
        </div>

    </form>
</div>
