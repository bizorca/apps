<?php
$currentPage = $activePage ?? '';
$user = currentUser();
$firstName = htmlspecialchars($user['first_name'] ?? '');

function navLink(string $href, string $label, string $icon, string $page, string $currentPage): string {
    $isActive = $currentPage === $page;
    $color    = $isActive ? 'text-pc-terracotta' : 'text-pc-slate hover:text-pc-charcoal';
    $weight   = $isActive ? 'font-medium' : '';
    return "<a href=\"$href\" class=\"flex flex-col items-center gap-1 px-3 py-2 transition-colors $color $weight\">
      <span class=\"text-xl\">$icon</span>
      <span class=\"text-xs\">$label</span>
    </a>";
}
?>

<!-- Desktop sidebar -->
<aside class="sidebar fixed left-0 top-0 h-full w-56 bg-white border-r border-pc-border flex flex-col z-40 md:flex hidden">
  <div class="px-6 py-6 border-b border-pc-border">
    <a href="<?= PC_BASE ?>/dashboard.php" class="text-pc-charcoal font-semibold text-lg" style="font-family: -apple-system, 'SF Pro Rounded', sans-serif;">placecard</a>
  </div>
  <nav class="flex-1 py-4 px-3">
    <a href="<?= PC_BASE ?>/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors mb-1 <?= $currentPage === 'home' ? 'bg-pc-warm text-pc-terracotta font-medium' : 'text-pc-slate hover:bg-pc-cream hover:text-pc-charcoal' ?>">
      <span class="text-base">🏠</span> Home
    </a>
    <a href="<?= PC_BASE ?>/events.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors mb-1 <?= $currentPage === 'events' ? 'bg-pc-warm text-pc-terracotta font-medium' : 'text-pc-slate hover:bg-pc-cream hover:text-pc-charcoal' ?>">
      <span class="text-base">🍽️</span> Dinners
    </a>
    <a href="<?= PC_BASE ?>/my-dinners.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors mb-1 <?= $currentPage === 'my-dinners' ? 'bg-pc-warm text-pc-terracotta font-medium' : 'text-pc-slate hover:bg-pc-cream hover:text-pc-charcoal' ?>">
      <span class="text-base">📅</span> My Dinners
    </a>
    <a href="<?= PC_BASE ?>/profile.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors mb-1 <?= $currentPage === 'profile' ? 'bg-pc-warm text-pc-terracotta font-medium' : 'text-pc-slate hover:bg-pc-cream hover:text-pc-charcoal' ?>">
      <span class="text-base">👤</span> Profile
    </a>
  </nav>
  <div class="p-4 border-t border-pc-border">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-8 h-8 rounded-full bg-pc-terracotta flex items-center justify-center text-white text-sm font-semibold">
        <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
      </div>
      <span class="text-sm text-pc-charcoal font-medium"><?= $firstName ?></span>
    </div>
    <a href="<?= PC_BASE ?>/logout.php" class="text-xs text-pc-slate hover:text-pc-charcoal transition-colors">Sign out</a>
  </div>
</aside>

<!-- Mobile top bar -->
<header class="md:hidden fixed top-0 left-0 right-0 z-40 bg-white border-b border-pc-border px-4 h-14 flex items-center justify-between">
  <a href="<?= PC_BASE ?>/dashboard.php" class="text-pc-charcoal font-semibold" style="font-family: -apple-system, 'SF Pro Rounded', sans-serif;">placecard</a>
  <div class="w-8 h-8 rounded-full bg-pc-terracotta flex items-center justify-center text-white text-sm font-semibold">
    <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
  </div>
</header>

<!-- Mobile bottom tab bar -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-pc-border flex justify-around py-1 safe-area-inset-bottom">
  <?= navLink(PC_BASE . '/dashboard.php',   'Home',       '🏠', 'home',       $currentPage) ?>
  <?= navLink(PC_BASE . '/events.php',      'Dinners',    '🍽️', 'events',     $currentPage) ?>
  <?= navLink(PC_BASE . '/my-dinners.php',  'My Dinners', '📅', 'my-dinners', $currentPage) ?>
  <?= navLink(PC_BASE . '/profile.php',     'Profile',    '👤', 'profile',    $currentPage) ?>
</nav>

<style>
  @supports (padding-bottom: env(safe-area-inset-bottom)) {
    nav.safe-area-inset-bottom { padding-bottom: calc(0.25rem + env(safe-area-inset-bottom)); }
  }
  .app-content { padding-left: 14rem; }
  @media (max-width: 768px) { .app-content { padding-left: 0; padding-top: 3.5rem; padding-bottom: 5rem; } }
</style>
