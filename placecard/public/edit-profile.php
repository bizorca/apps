<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';
require_once PC_ROOT . '/includes/api.php';

requireAuth();

$token = getToken();
$user  = currentUser();

// Refresh user
$resp = api('GET', 'users/me', [], $token);
if ($resp['success'] ?? false) {
    refreshUser($resp['data']);
    $user = currentUser();
}

$bio      = $user['bio'] ?? '';
$ints     = $user['interests'] ?? [];
$prefs    = $user['dining_preferences'] ?? [];
$error    = '';
$success  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newBio   = trim($_POST['bio'] ?? '');
    $newInts  = $_POST['interests'] ?? [];
    $newPrefs = $_POST['prefs'] ?? [];

    if (strlen($newBio) < 10) {
        $error = 'Bio must be at least 10 characters.';
    } else {
        $result = api('PUT', 'users/me', [
            'bio'                => $newBio,
            'interests'          => $newInts,
            'dining_preferences' => $newPrefs,
        ], $token);

        if ($result['success'] ?? false) {
            refreshUser($result['data']);
            $user    = currentUser();
            $bio     = $newBio;
            $ints    = $newInts;
            $prefs   = $newPrefs;
            $success = 'Profile updated.';
        } else {
            $error = $result['message'] ?? 'Update failed. Please try again.';
        }
    }
}

$pageTitle  = 'Edit Profile — placecard';
$activePage = 'profile';
?>
<?php include PC_ROOT . '/includes/head.php' ?>
<?php include PC_ROOT . '/includes/app-nav.php' ?>

<style>
  .interest-chip input { display: none; }
  .interest-chip label { cursor: pointer; display: block; transition: all 0.15s; }
  .interest-chip input:checked + label { background-color: #C4704A; color: #fff; border-color: #C4704A; }
  .pref-row input { display: none; }
  .pref-row label { cursor: pointer; transition: all 0.15s; }
  .pref-row input:checked + label { border-color: #C4704A; color: #C4704A; background: #FAF7F2; }
</style>

<main class="app-content min-h-screen">
  <div class="max-w-xl mx-auto px-4 py-8">

    <div class="flex items-center gap-3 mb-6">
      <a href="<?= PC_BASE ?>/profile.php" class="text-pc-slate hover:text-pc-charcoal transition-colors text-sm">← Back</a>
      <h1 class="text-2xl font-bold text-pc-charcoal">Edit profile</h1>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-5 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>
    <?php if ($success): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-4 mb-5 text-sm"><?= htmlspecialchars($success) ?></div>
    <?php endif ?>

    <form method="POST" class="space-y-8">
      <?= pcCsrfField() ?>

      <!-- Bio -->
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5">
        <label class="block text-sm font-semibold text-pc-charcoal mb-3">Bio</label>
        <textarea name="bio" id="bio" maxlength="300" rows="5"
          class="w-full border border-pc-border rounded-xl px-4 py-3.5 text-sm bg-pc-cream/50 resize-none transition-shadow leading-relaxed"
          oninput="document.getElementById('charCount').textContent = this.value.length + '/300'"><?= htmlspecialchars($bio) ?></textarea>
        <div class="flex justify-between mt-1.5">
          <p class="text-pc-slate text-xs">Min 10 characters.</p>
          <span id="charCount" class="text-pc-slate text-xs"><?= strlen($bio) ?>/300</span>
        </div>
      </div>

      <!-- Interests -->
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5">
        <label class="block text-sm font-semibold text-pc-charcoal mb-1">Interests</label>
        <p class="text-pc-slate text-xs mb-4">Pick at least two.</p>
        <div class="flex flex-wrap gap-2">
          <?php foreach (INTERESTS as $interest):
            $checked = in_array($interest, $ints, true) ? 'checked' : '';
            $id = 'int-' . md5($interest);
          ?>
          <div class="interest-chip">
            <input type="checkbox" name="interests[]" value="<?= htmlspecialchars($interest) ?>" id="<?= $id ?>" <?= $checked ?>>
            <label for="<?= $id ?>" class="px-4 py-2 rounded-full border border-pc-border text-sm font-medium bg-white text-pc-charcoal">
              <?= htmlspecialchars($interest) ?>
            </label>
          </div>
          <?php endforeach ?>
        </div>
      </div>

      <!-- Dining preferences -->
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5">
        <label class="block text-sm font-semibold text-pc-charcoal mb-1">Dining preferences</label>
        <p class="text-pc-slate text-xs mb-4">Helps hosts pick restaurants that work for everyone.</p>
        <div class="space-y-2">
          <?php foreach (DINING_PREFS as $pref):
            $checked = in_array($pref, $prefs, true) ? 'checked' : '';
            $id = 'pref-' . md5($pref);
          ?>
          <div class="pref-row">
            <input type="checkbox" name="prefs[]" value="<?= htmlspecialchars($pref) ?>" id="<?= $id ?>" <?= $checked ?>>
            <label for="<?= $id ?>" class="flex items-center justify-between w-full px-4 py-3.5 rounded-xl border border-pc-border bg-white text-sm font-medium text-pc-charcoal">
              <?= htmlspecialchars($pref) ?>
              <span class="check-icon text-pc-terracotta opacity-0 transition-opacity">✓</span>
            </label>
          </div>
          <?php endforeach ?>
        </div>
      </div>

      <button type="submit" class="w-full btn-primary py-4 rounded-xl font-semibold transition-colors">
        Save changes
      </button>

    </form>
  </div>
</main>

<script>
document.querySelectorAll('.pref-row input').forEach(inp => {
  function sync() {
    inp.nextElementSibling.querySelector('.check-icon').style.opacity = inp.checked ? '1' : '0';
  }
  sync();
  inp.addEventListener('change', sync);
});
</script>

</body>
</html>
