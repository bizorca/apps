<?php $title = 'Create User - Admin'; $h = \App\Core\Helpers::class; ?>
<?php ob_start(); ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Create User Account</h1>
        <a href="<?= url('/admin') ?>" class="text-red-600 hover:text-red-700 font-medium text-sm">Back to Admin</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6" x-data="{
        password: '',
        generate() {
            const colors = ['Red','Blue','Green','Gold','Silver','Purple','Orange','Teal','Jade','Ruby','Amber','Coral'];
            const animals = ['Tiger','Eagle','Falcon','Lion','Bear','Wolf','Hawk','Fox','Shark','Cobra','Otter','Raven'];
            this.password = colors[Math.floor(Math.random()*colors.length)] +
                           animals[Math.floor(Math.random()*animals.length)] +
                           (Math.floor(Math.random()*900)+100);
        }
    }">
        <form method="POST" action="<?= url('/admin/users/create') ?>" class="space-y-5">
            <?= $h::csrfField($csrf_token) ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                    <input type="email" name="email" required
                        class="block w-full rounded-lg border-gray-300 border px-4 py-2 text-sm focus:border-red-500 focus:ring-red-500"
                        placeholder="user@example.com">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Membership Level</label>
                    <select name="member_level"
                        class="block w-full rounded-lg border-gray-300 border px-4 py-2 text-sm focus:border-red-500 focus:ring-red-500">
                        <?php foreach ($membershipTiers as $level => $tier): ?>
                        <option value="<?= $level ?>"><?= htmlspecialchars($tier['name']) ?> (Level <?= $level ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                    <input type="text" name="first_name" required
                        class="block w-full rounded-lg border-gray-300 border px-4 py-2 text-sm focus:border-red-500 focus:ring-red-500"
                        placeholder="First name">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" name="last_name"
                        class="block w-full rounded-lg border-gray-300 border px-4 py-2 text-sm focus:border-red-500 focus:ring-red-500"
                        placeholder="Last name">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                <div class="flex items-center space-x-3">
                    <input type="text" name="password" x-model="password" required minlength="8"
                        class="flex-1 rounded-lg border-gray-300 border px-4 py-2 text-sm font-mono focus:border-red-500 focus:ring-red-500"
                        placeholder="Minimum 8 characters">
                    <button type="button" @click="generate()"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 transition-colors whitespace-nowrap">
                        Generate Password
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-400">The password will be visible. Share it securely with the user.</p>
            </div>

            <div class="pt-3 border-t">
                <button type="submit"
                    class="rounded-lg bg-red-600 px-8 py-3 text-sm font-bold text-white hover:bg-red-700 transition-colors">
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
