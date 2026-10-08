<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';
require_once PC_ROOT . '/includes/api.php';

requireAuth();

$user  = currentUser();
$token = getToken();
$step  = (int)($_GET['step'] ?? 1);
if ($step < 1 || $step > 3) $step = 1;

$firstName = htmlspecialchars($user['first_name'] ?? 'there');
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'bio') {
        $bio = trim($_POST['bio'] ?? '');
        if (strlen($bio) < 10) {
            $error = 'Bio must be at least 10 characters.';
        } else {
            // Save to session temporarily
            $_SESSION['pc_setup_bio'] = $bio;
            header('Location: ' . PC_BASE . '/setup.php?step=2');
            exit;
        }
    } elseif ($action === 'interests') {
        $interests = $_POST['interests'] ?? [];
        if (count($interests) < 2) {
            $error = 'Pick at least two interests.';
        } else {
            $_SESSION['pc_setup_interests'] = $interests;
            header('Location: ' . PC_BASE . '/setup.php?step=3');
            exit;
        }
    } elseif ($action === 'prefs') {
        $prefs = $_POST['prefs'] ?? [];
        $bio   = $_SESSION['pc_setup_bio'] ?? '';
        $ints  = $_SESSION['pc_setup_interests'] ?? [];

        $result = api('PUT', 'users/me', [
            'bio'                => $bio,
            'interests'          => $ints,
            'dining_preferences' => $prefs,
        ], $token);

        if ($result['success'] ?? false) {
            refreshUser($result['data']);
            unset($_SESSION['pc_setup_bio'], $_SESSION['pc_setup_interests']);
            header('Location: ' . PC_BASE . '/dashboard.php');
            exit;
        } else {
            $error = $result['message'] ?? 'Something went wrong. Please try again.';
        }
    }
}

$savedBio       = $_SESSION['pc_setup_bio'] ?? ($user['bio'] ?? '');
$savedInterests = $_SESSION['pc_setup_interests'] ?? ($user['interests'] ?? []);
$savedPrefs     = $user['dining_preferences'] ?? [];

$pageTitle = 'Set up your profile — placecard';
?>
<?php include PC_ROOT . '/includes/head.php' ?>

<style>
  .interest-chip input { display: none; }
  .interest-chip label { cursor: pointer; display: block; transition: all 0.15s; }
  .interest-chip input:checked + label { background-color: #C4704A; color: #fff; border-color: #C4704A; }
  .pref-row input { display: none; }
  .pref-row label { cursor: pointer; transition: all 0.15s; }
  .pref-row input:checked + label { border-color: #C4704A; color: #C4704A; background: #FAF7F2; }
</style>

<div class="min-h-screen flex flex-col" style="background: #FAF7F2;">

  <!-- Header -->
  <div class="sticky top-0 bg-pc-cream/95 backdrop-blur border-b border-pc-border z-10">
    <div class="max-w-lg mx-auto px-6 py-4">
      <div class="flex justify-between items-center mb-3">
        <span class="text-pc-charcoal font-semibold">placecard</span>
        <span class="text-pc-slate text-sm">Step <?= $step ?> of 3</span>
      </div>
      <!-- Progress bar -->
      <div class="h-1.5 bg-pc-border rounded-full">
        <div class="h-1.5 bg-pc-terracotta rounded-full transition-all duration-500" style="width: <?= ($step / 3 * 100) ?>%"></div>
      </div>
    </div>
  </div>

  <div class="flex-1 max-w-lg mx-auto w-full px-6 py-8">

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-6 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>

    <!-- Step 1: Bio -->
    <?php if ($step === 1): ?>
    <form method="POST" class="flex flex-col gap-6">
      <?= pcCsrfField() ?>
      <input type="hidden" name="action" value="bio">
      <div>
        <h1 class="text-2xl font-bold text-pc-charcoal mb-1">Hey <?= $firstName ?>.</h1>
        <p class="text-pc-slate">Tell the table a little about yourself.</p>
      </div>
      <div>
        <textarea name="bio" id="bio" maxlength="300" rows="5"
          class="w-full border border-pc-border rounded-xl px-4 py-3.5 text-sm bg-white resize-none transition-shadow leading-relaxed"
          placeholder="A sentence or two about who you are and why you'd make good dinner company."
          oninput="updateCount()"><?= htmlspecialchars($savedBio) ?></textarea>
        <div class="flex justify-between mt-1.5">
          <p class="text-pc-slate text-xs">This is the first thing other diners see. Keep it short and genuine.</p>
          <span id="charCount" class="text-pc-slate text-xs"><?= strlen($savedBio) ?>/300</span>
        </div>
      </div>
      <button type="submit" class="w-full btn-primary py-4 rounded-xl font-semibold transition-colors">
        Continue
      </button>
    </form>
    <script>
      function updateCount() {
        const bio = document.getElementById('bio');
        document.getElementById('charCount').textContent = bio.value.length + '/300';
      }
    </script>

    <!-- Step 2: Interests -->
    <?php elseif ($step === 2): ?>
    <form method="POST" class="flex flex-col gap-6">
      <?= pcCsrfField() ?>
      <input type="hidden" name="action" value="interests">
      <div>
        <h1 class="text-2xl font-bold text-pc-charcoal mb-1">What are you into?</h1>
        <p class="text-pc-slate">Pick at least two. Shared interests make for better dinners.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <?php foreach (INTERESTS as $interest):
          $checked = in_array($interest, $savedInterests, true) ? 'checked' : '';
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
      <button type="submit" class="w-full btn-primary py-4 rounded-xl font-semibold transition-colors">
        Continue
      </button>
    </form>

    <!-- Step 3: Dining preferences -->
    <?php elseif ($step === 3): ?>
    <form method="POST" class="flex flex-col gap-6">
      <?= pcCsrfField() ?>
      <input type="hidden" name="action" value="prefs">
      <div>
        <h1 class="text-2xl font-bold text-pc-charcoal mb-1">Any dining preferences?</h1>
        <p class="text-pc-slate">Helps hosts pick restaurants that work for the whole table.</p>
      </div>
      <div class="space-y-2">
        <?php foreach (DINING_PREFS as $pref):
          $checked = in_array($pref, $savedPrefs, true) ? 'checked' : '';
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
      <button type="submit" class="w-full btn-primary py-4 rounded-xl font-semibold transition-colors">
        Finish setup
      </button>
    </form>
    <script>
      document.querySelectorAll('.pref-row input').forEach(inp => {
        function sync() {
          const label = inp.nextElementSibling;
          const icon  = label.querySelector('.check-icon');
          if (inp.checked) {
            icon.style.opacity = '1';
          } else {
            icon.style.opacity = '0';
          }
        }
        sync();
        inp.addEventListener('change', sync);
      });
    </script>
    <?php endif ?>

  </div>
</div>

</body>
</html>
