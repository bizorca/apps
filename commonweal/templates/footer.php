<?php if (!$fullWidth): ?>
</div><!-- /.max-w-7xl content wrapper -->
<?php endif; ?>
</main>

<footer class="bg-brand-800 text-brand-200 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-8">

            <div class="max-w-sm">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-6 h-6 text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="font-bold text-white text-lg">CoopConvert</span>
                </div>
                <p class="text-sm leading-relaxed text-brand-300">Washington State Cooperative Conversion Platform</p>
                <p class="text-xs leading-relaxed text-brand-400 mt-3">
                    CoopConvert provides educational tools and document templates. Nothing on this platform constitutes legal or financial advice. Always consult a licensed Washington state attorney before filing cooperative documents.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-8 text-sm">
                <div>
                    <h3 class="font-semibold text-white mb-3 uppercase tracking-wide text-xs">Platform</h3>
                    <ul class="space-y-2">
                        <li><a href="<?= url('/index.php') ?>" class="hover:text-white transition-colors">Home</a></li>
                        <li><a href="<?= url('/about.php') ?>" class="hover:text-white transition-colors">About</a></li>
                        <li><a href="<?= url('/register.php') ?>" class="hover:text-white transition-colors">Start Your Conversion</a></li>
                        <li><a href="<?= url('/register.php?role=coordinator') ?>" class="hover:text-white transition-colors">Volunteer as Coordinator</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-white mb-3 uppercase tracking-wide text-xs">Account</h3>
                    <ul class="space-y-2">
                        <li><a href="<?= url('/login.php') ?>" class="hover:text-white transition-colors">Login</a></li>
                        <li><a href="<?= url('/forgot-password.php') ?>" class="hover:text-white transition-colors">Forgot Password</a></li>
                    </ul>
                </div>
            </div>

        </div>

        <div class="border-t border-brand-700 mt-8 pt-6 text-xs text-brand-400 flex flex-col sm:flex-row sm:justify-between gap-2">
            <p>&copy; <?= date('Y') ?> CoopConvert. All rights reserved.</p>
            <p>Built for Washington State &mdash; <a href="https://app.leg.wa.gov/rcw/default.aspx?cite=23.86" target="_blank" rel="noopener" class="hover:text-white transition-colors underline">RCW 23.86</a></p>
        </div>
    </div>
</footer>

</body>
</html>
