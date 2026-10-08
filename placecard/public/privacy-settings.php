<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';
require_once PC_ROOT . '/includes/api.php';

requireAuth();

$token  = getToken();
$user   = currentUser();
$error  = '';
$saved  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $showAge      = isset($_POST['show_exact_age']);
    $showLastName = isset($_POST['show_last_name']);

    $result = api('PUT', 'users/me', [
        'show_exact_age'  => $showAge,
        'show_last_name'  => $showLastName,
    ], $token);

    if ($result['success'] ?? false) {
        refreshUser($result['data']);
        $user  = currentUser();
        $saved = true;
    } else {
        $error = $result['message'] ?? 'Could not save settings.';
    }
}

$showExactAge  = (bool)($user['show_exact_age'] ?? false);
$showLastName  = (bool)($user['show_last_name'] ?? false);

$pageTitle  = 'Privacy Settings — placecard';
$activePage = 'profile';
?>
<?php include PC_ROOT . '/includes/head.php' ?>
<?php include PC_ROOT . '/includes/app-nav.php' ?>

<main class="app-content min-h-screen">
  <div class="max-w-xl mx-auto px-4 py-8">

    <div class="flex items-center gap-3 mb-6">
      <a href="<?= PC_BASE ?>/profile.php" class="text-pc-slate hover:text-pc-charcoal transition-colors text-sm">← Back</a>
      <h1 class="text-2xl font-bold text-pc-charcoal">Privacy settings</h1>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-5 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>
    <?php if ($saved): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-4 mb-5 text-sm">Privacy settings saved.</div>
    <?php endif ?>

    <form method="POST" class="space-y-4">
      <?= pcCsrfField() ?>

      <!-- Age toggle -->
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5">
        <label class="flex items-start justify-between gap-4 cursor-pointer">
          <div>
            <p class="font-medium text-pc-charcoal text-sm">Show exact age</p>
            <p class="text-pc-slate text-xs mt-0.5 leading-relaxed">If off, other diners see an age range instead (e.g., "late 20s"). Your exact age is never shown to strangers by default.</p>
          </div>
          <div class="relative flex-shrink-0 mt-0.5">
            <input type="checkbox" name="show_exact_age" id="show_age" class="sr-only peer" <?= $showExactAge ? 'checked' : '' ?>>
            <div class="w-11 h-6 bg-pc-border rounded-full peer-checked:bg-pc-terracotta transition-colors"></div>
            <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
          </div>
        </label>
      </div>

      <!-- Last name toggle -->
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5">
        <label class="flex items-start justify-between gap-4 cursor-pointer">
          <div>
            <p class="font-medium text-pc-charcoal text-sm">Show last name</p>
            <p class="text-pc-slate text-xs mt-0.5 leading-relaxed">If off, only your first name is ever visible to other diners. Last names are hidden by default.</p>
          </div>
          <div class="relative flex-shrink-0 mt-0.5">
            <input type="checkbox" name="show_last_name" id="show_last" class="sr-only peer" <?= $showLastName ? 'checked' : '' ?>>
            <div class="w-11 h-6 bg-pc-border rounded-full peer-checked:bg-pc-terracotta transition-colors"></div>
            <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
          </div>
        </label>
      </div>

      <button type="submit" class="w-full btn-primary py-4 rounded-xl font-semibold transition-colors">
        Save settings
      </button>
    </form>

  </div>
</main>

</body>
</html>
