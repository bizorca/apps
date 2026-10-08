<nav class="fixed top-0 left-0 right-0 z-50 bg-pc-cream/95 backdrop-blur border-b border-pc-border">
  <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
    <a href="<?= PC_BASE ?>/" class="text-pc-charcoal font-semibold text-lg tracking-tight" style="font-family: -apple-system, 'SF Pro Rounded', sans-serif;">placecard</a>
    <div class="hidden md:flex items-center gap-8">
      <a href="<?= PC_BASE ?>/about.php" class="text-pc-slate hover:text-pc-charcoal text-sm transition-colors <?= ($activePage ?? '') === 'about' ? 'text-pc-charcoal font-medium' : '' ?>">About</a>
      <a href="<?= PC_BASE ?>/features.php" class="text-pc-slate hover:text-pc-charcoal text-sm transition-colors <?= ($activePage ?? '') === 'features' ? 'text-pc-charcoal font-medium' : '' ?>">How it works</a>
    </div>
    <div class="flex items-center gap-3">
      <a href="<?= PC_BASE ?>/login.php" class="text-sm text-pc-charcoal hover:text-pc-terracotta transition-colors font-medium">Sign in</a>
      <a href="<?= PC_BASE ?>/register.php" class="btn-primary text-sm px-4 py-2 rounded-full font-medium transition-colors inline-block">Join</a>
    </div>
  </div>
</nav>
