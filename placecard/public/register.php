<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';
require_once PC_ROOT . '/includes/api.php';

// Joining Placecard: a signed-in tools account plus the original's
// registration fields (first name, last name, age 18+). Email and password are
// the shared account's, so they are no longer asked for here.
if (!tl_logged_in()) {
    header('Location: /account/register.php?next=' . rawurlencode(PC_BASE . '/register.php'));
    exit;
}
$account = requireAccount();
if (isLoggedIn()) {
    $user = currentUser();
    pcRedirect(empty($user['bio']) ? '/onboarding' : '/dashboard');
}

$errors = [];
$parts  = preg_split('/\s+/', trim((string) $account['name']), 2);
$vals   = ['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '', 'age' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vals = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name'] ?? ''),
        'age'        => (int)($_POST['age'] ?? 0),
    ];

    if (!$vals['first_name']) $errors['first_name'] = 'First name is required.';
    if ($vals['age'] < 18) $errors['age'] = 'You must be 18 or older to join.';

    if (empty($errors)) {
        Profile::create((int) $account['id'], $vals['first_name'], $vals['last_name'], $vals['age']);
        header('Location: ' . PC_BASE . '/onboarding.php');
        exit;
    }
}

$pageTitle = 'Join placecard';
?>
<?php include PC_ROOT . '/includes/head.php' ?>

<div class="min-h-screen flex">
  <!-- Left panel — decoration -->
  <div class="hidden lg:flex lg:w-1/2 bg-pc-charcoal flex-col justify-between p-12">
    <a href="<?= PC_BASE ?>/" class="text-white font-semibold text-lg">placecard</a>
    <div>
      <p class="text-white text-3xl font-bold leading-snug mb-4">"Good food. Interesting people. No agenda."</p>
      <p class="text-gray-400 leading-relaxed">Group dinners at real restaurants with strangers who become, occasionally, friends.</p>
      <div class="mt-10 flex gap-4">
        <div class="bg-white/10 rounded-xl p-4 flex-1">
          <p class="text-white text-2xl font-bold mb-1">🇹🇭</p>
          <p class="text-white font-medium text-sm">Chiang Mai</p>
          <p class="text-gray-400 text-xs">Northern Thailand</p>
        </div>
        <div class="bg-white/10 rounded-xl p-4 flex-1">
          <p class="text-white text-2xl font-bold mb-1">🇺🇸</p>
          <p class="text-white font-medium text-sm">Port Townsend</p>
          <p class="text-gray-400 text-xs">Washington, USA</p>
        </div>
      </div>
    </div>
    <p class="text-gray-500 text-sm">© <?= date('Y') ?> placecard</p>
  </div>

  <!-- Right panel — form -->
  <div class="flex-1 flex items-center justify-center p-6">
    <div class="w-full max-w-md">
      <div class="lg:hidden mb-8">
        <a href="<?= PC_BASE ?>/" class="text-pc-charcoal font-semibold text-lg">placecard</a>
      </div>

      <h1 class="text-2xl font-bold text-pc-charcoal mb-1">Join placecard</h1>
      <p class="text-pc-slate text-sm mb-6">Your last name is never shown publicly. Other diners only see your first name.</p>

      <?php if (isset($errors['general'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-5 text-sm"><?= htmlspecialchars($errors['general']) ?></div>
      <?php endif ?>

      <form method="POST" class="space-y-4">
      <?= pcCsrfField() ?>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-pc-charcoal mb-1.5">First name <span class="text-pc-terracotta">*</span></label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($vals['first_name'] ?? '') ?>"
              class="w-full border border-pc-border rounded-xl px-4 py-3 text-sm bg-white transition-shadow"
              placeholder="Maya" required autofocus>
            <?php if (isset($errors['first_name'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['first_name'] ?></p><?php endif ?>
          </div>
          <div>
            <label class="block text-sm font-medium text-pc-charcoal mb-1.5">Last name</label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($vals['last_name'] ?? '') ?>"
              class="w-full border border-pc-border rounded-xl px-4 py-3 text-sm bg-white transition-shadow"
              placeholder="Chen">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-pc-charcoal mb-1.5">Age <span class="text-pc-terracotta">*</span></label>
          <input type="number" name="age" value="<?= htmlspecialchars((string)($vals['age'] ?? '')) ?>"
            class="w-full border border-pc-border rounded-xl px-4 py-3 text-sm bg-white transition-shadow"
            placeholder="28" min="18" max="120" required>
          <?php if (isset($errors['age'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['age'] ?></p><?php endif ?>
        </div>

        <button type="submit" class="w-full btn-primary py-3.5 rounded-xl font-semibold text-sm transition-colors mt-2">
          Join placecard
        </button>
      </form>

      <p class="text-center text-sm text-pc-slate mt-5">
        Signed in as <?= htmlspecialchars($account['email']) ?>. <a href="/account/logout.php" class="text-pc-terracotta font-medium hover:underline">Not you?</a>
      </p>
    </div>
  </div>
</div>

</body>
</html>
