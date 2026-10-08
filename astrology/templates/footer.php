    </main>
    <footer class="bg-gray-900 mt-12 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div>
                    <h4 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">Explore</h4>
                    <ul class="space-y-2">
                        <li><a href="<?= url('/') ?>" class="text-sm text-gray-400 hover:text-white transition">Home</a></li>
                        <li><a href="<?= url('/work-with-jillian.php') ?>" class="text-sm text-gray-400 hover:text-white transition">Work with Jillian</a></li>
                        <li><a href="<?= url('/pricing.php') ?>" class="text-sm text-gray-400 hover:text-white transition">Pricing</a></li>
                        <?php if (isLoggedIn()): ?>
                        <li><a href="<?= url('/dashboard.php') ?>" class="text-sm text-gray-400 hover:text-white transition">Dashboard</a></li>
                        <?php else: ?>
                        <li><a href="<?= authUrl('register') ?>" class="text-sm text-gray-400 hover:text-white transition">Sign Up</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">Features</h4>
                    <ul class="space-y-2">
                        <li><a href="<?= url('/reading.php') ?>" class="text-sm text-gray-400 hover:text-white transition">Zodiac Reading</a></li>
                        <li><a href="<?= url('/weekly-forecasts.php') ?>" class="text-sm text-gray-400 hover:text-white transition">Weekly Forecasts</a></li>
                        <li><a href="<?= url('/meditations.php') ?>" class="text-sm text-gray-400 hover:text-white transition">Guided Meditations</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">Account</h4>
                    <ul class="space-y-2">
                        <li><a href="<?= authUrl('login') ?>" class="text-sm text-gray-400 hover:text-white transition">Login</a></li>
                        <li><a href="<?= url('/settings.php') ?>" class="text-sm text-gray-400 hover:text-white transition">Settings</a></li>
                        <li><a href="<?= url('/billing.php') ?>" class="text-sm text-gray-400 hover:text-white transition">Billing</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">Legal</h4>
                    <ul class="space-y-2">
                        <li><span class="text-sm text-gray-600">Privacy Policy</span></li>
                        <li><span class="text-sm text-gray-600">Terms of Service</span></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-10 pt-8 flex flex-col sm:flex-row justify-between items-center">
                <p class="text-sm text-gray-500">&copy; <?= date('Y') ?> <?= h(APP_NAME) ?>. All rights reserved.</p>
                <p class="text-xs text-gray-600 mt-2 sm:mt-0">Traditional Chinese astrology meets modern insight</p>
            </div>
        </div>
    </footer>
</body>
</html>
