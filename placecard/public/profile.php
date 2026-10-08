<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';
require_once PC_ROOT . '/includes/api.php';

requireAuth();

$token = getToken();
$resp  = api('GET', 'users/me', [], $token);
if ($resp['success'] ?? false) {
    refreshUser($resp['data']);
}
$user = currentUser();

$firstName   = htmlspecialchars($user['first_name'] ?? '');
$initial     = strtoupper(substr($user['first_name'] ?? 'U', 0, 1));
$bio         = $user['bio'] ?? '';
$interests   = $user['interests'] ?? [];
$memberSince = '';
if (!empty($user['member_since'])) {
    $memberSince = date('F Y', strtotime($user['member_since']));
}

// Age display
$showExact = $user['show_exact_age'] ?? false;
$age       = $user['age'] ?? null;
$ageDisplay = '';
if ($age !== null) {
    if ($showExact) {
        $ageDisplay = (string)$age;
    } else {
        $ageDisplay = match(true) {
            $age < 25 => 'early 20s',
            $age < 30 => 'late 20s',
            $age < 35 => 'early 30s',
            $age < 40 => 'late 30s',
            $age < 50 => '40s',
            $age < 60 => '50s',
            default   => '60+',
        };
    }
}

$flash = getFlash();

$pageTitle  = 'Profile — placecard';
$activePage = 'profile';
?>
<?php include PC_ROOT . '/includes/head.php' ?>
<?php include PC_ROOT . '/includes/app-nav.php' ?>

<main class="app-content min-h-screen">
  <div class="max-w-xl mx-auto px-4 py-8">

    <h1 class="text-2xl font-bold text-pc-charcoal mb-6">Profile</h1>

    <?php if ($flash): ?>
      <?php $bg = $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'; ?>
      <div class="<?= $bg ?> border rounded-xl p-4 mb-5 text-sm"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif ?>

    <!-- Avatar + info -->
    <div class="flex flex-col items-center text-center py-8 bg-white rounded-2xl border border-pc-border shadow-card mb-5">
      <div class="w-20 h-20 rounded-full bg-pc-terracotta flex items-center justify-center text-white text-3xl font-bold mb-4">
        <?= $initial ?>
      </div>
      <h2 class="text-xl font-bold text-pc-charcoal"><?= $firstName ?></h2>
      <?php if ($ageDisplay): ?>
        <p class="text-pc-slate text-sm mt-0.5"><?= htmlspecialchars($ageDisplay) ?></p>
      <?php endif ?>
      <?php if ($memberSince): ?>
        <p class="text-pc-slate text-xs mt-1">Member since <?= $memberSince ?></p>
      <?php endif ?>
      <?php if ($bio): ?>
        <p class="text-pc-charcoal text-sm leading-relaxed mt-4 px-6 max-w-sm"><?= nl2br(htmlspecialchars($bio)) ?></p>
      <?php endif ?>
    </div>

    <!-- Interests -->
    <?php if (!empty($interests)): ?>
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5 mb-5">
        <p class="text-xs text-pc-slate font-semibold uppercase tracking-widest mb-3">Interests</p>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($interests as $int): ?>
            <span class="bg-pc-warm text-pc-terracotta text-xs px-3.5 py-1.5 rounded-full font-medium"><?= htmlspecialchars($int) ?></span>
          <?php endforeach ?>
        </div>
      </div>
    <?php endif ?>

    <!-- Settings menu -->
    <div class="bg-white rounded-2xl border border-pc-border shadow-card overflow-hidden mb-5">
      <a href="<?= PC_BASE ?>/edit-profile.php" class="flex items-center gap-4 px-5 py-4 hover:bg-pc-cream/60 transition-colors">
        <span class="text-xl w-6 text-center">✏️</span>
        <span class="text-pc-charcoal text-sm font-medium flex-1">Edit profile</span>
        <span class="text-pc-slate text-sm">›</span>
      </a>
      <div class="border-t border-pc-border/50"></div>
      <a href="<?= PC_BASE ?>/privacy-settings.php" class="flex items-center gap-4 px-5 py-4 hover:bg-pc-cream/60 transition-colors">
        <span class="text-xl w-6 text-center">🔒</span>
        <span class="text-pc-charcoal text-sm font-medium flex-1">Privacy settings</span>
        <span class="text-pc-slate text-sm">›</span>
      </a>
    </div>

    <!-- Sign out -->
    <div class="bg-white rounded-2xl border border-pc-border shadow-card overflow-hidden">
      <a href="<?= PC_BASE ?>/logout.php"
        onclick="return confirm('Are you sure you want to sign out?')"
        class="flex items-center gap-4 px-5 py-4 hover:bg-red-50/50 transition-colors text-red-500">
        <span class="text-xl w-6 text-center">🚪</span>
        <span class="text-sm font-medium">Sign out</span>
      </a>
    </div>

  </div>
</main>

</body>
</html>
