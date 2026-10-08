<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';
require_once PC_ROOT . '/includes/api.php';

requireAuth();

$token  = getToken();
$user   = currentUser();

if (empty($user['bio'])) {
    header('Location: ' . PC_BASE . '/setup.php');
    exit;
}

$cityId = $_GET['city'] ?? 'chiang-mai';
if (!array_key_exists($cityId, CITIES)) $cityId = 'chiang-mai';

// Fetch restaurants for this city
$restResp    = api('GET', 'restaurants?city_id=' . $cityId, [], $token);
$restaurants = $restResp['data'] ?? [];

$error   = '';
$success = '';
$vals    = [
    'restaurant_id'  => '',
    'event_date'     => '',
    'event_time'     => '19:00',
    'max_attendees'  => 5,
    'notes'          => '',
    'city_id'        => $cityId,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vals['restaurant_id'] = $_POST['restaurant_id'] ?? '';
    $vals['event_date']    = $_POST['event_date'] ?? '';
    $vals['event_time']    = $_POST['event_time'] ?? '19:00';
    $vals['max_attendees'] = (int)($_POST['max_attendees'] ?? 5);
    $vals['notes']         = trim($_POST['notes'] ?? '');
    $vals['city_id']       = $_POST['city_id'] ?? $cityId;

    if (!$vals['restaurant_id']) {
        $error = 'Please select a restaurant.';
    } elseif (!$vals['event_date']) {
        $error = 'Please select a date.';
    } else {
        $datetime = $vals['event_date'] . 'T' . $vals['event_time'] . ':00';
        if (strtotime($datetime) <= time()) {
            $error = 'The dinner must be scheduled in the future.';
        } else {
            $result = api('POST', 'events', [
                'restaurant_id' => $vals['restaurant_id'],
                'city_id'       => $vals['city_id'],
                'event_date'    => $datetime,
                'max_attendees' => $vals['max_attendees'],
                'notes'         => $vals['notes'] ?: null,
            ], $token);

            if ($result['success'] ?? false) {
                $newId = $result['data']['id'] ?? '';
                header('Location: ' . PC_BASE . '/event.php?id=' . urlencode($newId) . '&created=1');
                exit;
            } else {
                $error = $result['message'] ?? 'Could not create dinner. Please try again.';
            }
        }
    }
}

$city        = CITIES[$cityId];
$pageTitle   = 'Host a dinner — placecard';
$activePage  = 'events';
$minDate     = date('Y-m-d', strtotime('+1 day'));
?>
<?php include PC_ROOT . '/includes/head.php' ?>
<?php include PC_ROOT . '/includes/app-nav.php' ?>

<main class="app-content min-h-screen">
  <div class="max-w-xl mx-auto px-4 py-8">

    <div class="flex items-center gap-3 mb-2">
      <a href="<?= PC_BASE ?>/events.php?city=<?= $cityId ?>" class="text-pc-slate hover:text-pc-charcoal transition-colors text-sm">← Back</a>
    </div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-pc-charcoal">Host a dinner</h1>
      <p class="text-pc-slate text-sm mt-0.5"><?= $city['emoji'] ?> <?= htmlspecialchars($city['name']) ?></p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-5 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>

    <form method="POST" class="space-y-5">
      <?= pcCsrfField() ?>
      <input type="hidden" name="city_id" value="<?= htmlspecialchars($cityId) ?>">

      <!-- Restaurant -->
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5">
        <label class="block text-sm font-semibold text-pc-charcoal mb-3">Restaurant <span class="text-pc-terracotta">*</span></label>
        <?php if (empty($restaurants)): ?>
          <p class="text-pc-slate text-sm">No restaurants available for this city.</p>
        <?php else: ?>
          <div class="space-y-2">
            <?php foreach ($restaurants as $r):
              $isSelected = ($vals['restaurant_id'] === $r['id']);
              $price = $r['price_range'] ?? '';
            ?>
            <label class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition-all <?= $isSelected ? 'border-pc-terracotta bg-pc-warm' : 'border-pc-border hover:bg-pc-cream/60' ?>">
              <input type="radio" name="restaurant_id" value="<?= htmlspecialchars($r['id']) ?>" class="text-pc-terracotta" <?= $isSelected ? 'checked' : '' ?>>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-pc-charcoal text-sm"><?= htmlspecialchars($r['name']) ?></p>
                <p class="text-pc-slate text-xs"><?= htmlspecialchars($r['neighborhood'] ?? '') ?> · <?= htmlspecialchars($price) ?></p>
              </div>
            </label>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </div>

      <!-- Date & time -->
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5">
        <label class="block text-sm font-semibold text-pc-charcoal mb-3">Date &amp; time <span class="text-pc-terracotta">*</span></label>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <p class="text-xs text-pc-slate mb-1.5">Date</p>
            <input type="date" name="event_date" value="<?= htmlspecialchars($vals['event_date']) ?>"
              min="<?= $minDate ?>"
              class="w-full border border-pc-border rounded-xl px-3.5 py-3 text-sm bg-white" required>
          </div>
          <div>
            <p class="text-xs text-pc-slate mb-1.5">Time</p>
            <input type="time" name="event_time" value="<?= htmlspecialchars($vals['event_time']) ?>"
              class="w-full border border-pc-border rounded-xl px-3.5 py-3 text-sm bg-white" required>
          </div>
        </div>
      </div>

      <!-- Party size -->
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5">
        <label class="block text-sm font-semibold text-pc-charcoal mb-1">Party size <span class="text-pc-terracotta">*</span></label>
        <p class="text-xs text-pc-slate mb-3">Including you.</p>
        <div class="flex gap-3">
          <?php foreach ([4, 5, 6, 8] as $n): ?>
            <label class="flex-1 cursor-pointer">
              <input type="radio" name="max_attendees" value="<?= $n ?>" class="sr-only peer" <?= $vals['max_attendees'] === $n ? 'checked' : '' ?>>
              <div class="text-center py-3 rounded-xl border border-pc-border text-sm font-semibold text-pc-slate transition-all peer-checked:border-pc-terracotta peer-checked:bg-pc-terracotta peer-checked:text-white">
                <?= $n ?>
              </div>
            </label>
          <?php endforeach ?>
        </div>
      </div>

      <!-- Notes -->
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5">
        <label class="block text-sm font-semibold text-pc-charcoal mb-1">Notes <span class="text-pc-slate font-normal">(optional)</span></label>
        <p class="text-xs text-pc-slate mb-3">A heads-up about the vibe, a theme, anything worth knowing.</p>
        <textarea name="notes" rows="3"
          class="w-full border border-pc-border rounded-xl px-4 py-3 text-sm bg-pc-cream/50 resize-none transition-shadow leading-relaxed"
          placeholder="Bring an appetite and a story or two."><?= htmlspecialchars($vals['notes']) ?></textarea>
      </div>

      <button type="submit" class="w-full btn-primary py-4 rounded-xl font-semibold transition-colors">
        Create dinner
      </button>
    </form>

  </div>
</main>

</body>
</html>
