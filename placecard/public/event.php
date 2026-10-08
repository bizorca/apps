<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';
require_once PC_ROOT . '/includes/api.php';

requireAuth();

$token   = getToken();
$user    = currentUser();
$eventId = $_GET['id'] ?? '';
if (!$eventId) { header('Location: ' . PC_BASE . '/events.php'); exit; }

// Handle RSVP / cancel actions
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'rsvp') {
        $r = api('POST', "events/$eventId/rsvp", [], $token);
        if ($r['success'] ?? false) {
            $message = "You're in. Your spot is confirmed. Show up hungry.";
            $msgType = 'success';
        } else {
            $message = $r['message'] ?? 'Could not RSVP. Please try again.';
            $msgType = 'error';
        }
    } elseif ($action === 'cancel_rsvp') {
        $r = api('DELETE', "events/$eventId/rsvp", [], $token);
        if ($r['success'] ?? false) {
            $message = 'Your RSVP has been cancelled.';
            $msgType = 'info';
        } else {
            $message = $r['message'] ?? 'Could not cancel. Please try again.';
            $msgType = 'error';
        }
    } elseif ($action === 'delete_event') {
        $r = api('DELETE', "events/$eventId", [], $token);
        if ($r['success'] ?? false) {
            header('Location: ' . PC_BASE . '/my-dinners.php?deleted=1');
            exit;
        } else {
            $message = $r['message'] ?? 'Could not cancel dinner.';
            $msgType = 'error';
        }
    }
}

// Fetch event
$resp = api('GET', "events/$eventId", [], $token);
if (!($resp['success'] ?? false)) { header('Location: ' . PC_BASE . '/events.php'); exit; }
$event = $resp['data'];

// User's current events for RSVP state
$myEventsResp = api('GET', 'users/me/events', [], $token);
$myEvents     = $myEventsResp['data'] ?? [];
$rsvpdIds     = array_column(array_filter($myEvents, fn($e) => !(bool)($e['is_host'] ?? false)), 'id');
$hostedIds    = array_column(array_filter($myEvents, fn($e) =>  (bool)($e['is_host'] ?? false)), 'id');

$isRsvpd   = in_array($eventId, $rsvpdIds, true);
$isHosted  = in_array($eventId, $hostedIds, true);
$spotsLeft = ($event['max_attendees'] ?? 0) - ($event['attendee_count'] ?? 0);
$isFull    = $spotsLeft <= 0;
$isPast    = strtotime($event['event_date']) < time();

$dateStr = date('l, F j, Y', strtotime($event['event_date']));
$timeStr = date('g:i A', strtotime($event['event_date']));

$cityId   = $event['city_id'] ?? 'chiang-mai';
$cityName = CITIES[$cityId]['name'] ?? 'the city';

$pageTitle  = htmlspecialchars($event['restaurant_name'] ?? 'Dinner') . ' — placecard';
$activePage = 'events';
?>
<?php include PC_ROOT . '/includes/head.php' ?>
<?php include PC_ROOT . '/includes/app-nav.php' ?>

<main class="app-content min-h-screen">
  <div class="max-w-xl mx-auto px-4 py-8">

    <!-- Back -->
    <a href="<?= PC_BASE ?>/events.php?city=<?= $cityId ?>" class="inline-flex items-center gap-1.5 text-pc-slate text-sm hover:text-pc-charcoal mb-6 transition-colors">
      ← Back to dinners
    </a>

    <!-- Message banner -->
    <?php if ($message): ?>
      <?php $bg = match($msgType) { 'success' => 'bg-green-50 border-green-200 text-green-700', 'error' => 'bg-red-50 border-red-200 text-red-700', default => 'bg-blue-50 border-blue-200 text-blue-700' }; ?>
      <div class="<?= $bg ?> border rounded-xl p-4 mb-5 text-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif ?>

    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-pc-charcoal"><?= htmlspecialchars($event['restaurant_name'] ?? '') ?></h1>
      <p class="text-pc-slate mt-1">📍 <?= htmlspecialchars($event['restaurant_neighborhood'] ?? '') ?></p>
    </div>

    <!-- Details card -->
    <div class="bg-white rounded-2xl border border-pc-border shadow-card overflow-hidden mb-5">
      <div class="flex items-center px-5 py-4 gap-4">
        <span class="text-xl w-6 text-center flex-shrink-0">📅</span>
        <div>
          <p class="text-xs text-pc-slate">Date</p>
          <p class="font-medium text-pc-charcoal text-sm"><?= $dateStr ?></p>
        </div>
      </div>
      <div class="border-t border-pc-border/50 flex items-center px-5 py-4 gap-4">
        <span class="text-xl w-6 text-center flex-shrink-0">🕐</span>
        <div>
          <p class="text-xs text-pc-slate">Time</p>
          <p class="font-medium text-pc-charcoal text-sm"><?= $timeStr ?></p>
        </div>
      </div>
      <div class="border-t border-pc-border/50 flex items-center px-5 py-4 gap-4">
        <span class="text-xl w-6 text-center flex-shrink-0">👤</span>
        <div>
          <p class="text-xs text-pc-slate">Host</p>
          <p class="font-medium text-pc-charcoal text-sm"><?= htmlspecialchars($event['host_first_name'] ?? '') ?></p>
        </div>
      </div>
      <div class="border-t border-pc-border/50 flex items-center px-5 py-4 gap-4">
        <span class="text-xl w-6 text-center flex-shrink-0">🪑</span>
        <div>
          <p class="text-xs text-pc-slate">Seats</p>
          <?php if ($isFull): ?>
            <p class="font-medium text-pc-slate text-sm">Full (<?= $event['max_attendees'] ?> people)</p>
          <?php else: ?>
            <p class="font-medium text-pc-charcoal text-sm"><?= $spotsLeft ?> of <?= $event['max_attendees'] ?> spots remaining</p>
          <?php endif ?>
        </div>
      </div>
    </div>

    <!-- Host note -->
    <?php if (!empty($event['notes'])): ?>
      <div class="bg-white rounded-2xl border border-pc-border shadow-card p-5 mb-5">
        <p class="text-xs text-pc-slate font-medium uppercase tracking-widest mb-2">From the host</p>
        <p class="text-pc-charcoal text-sm leading-relaxed"><?= nl2br(htmlspecialchars($event['notes'])) ?></p>
      </div>
    <?php endif ?>

    <!-- Privacy banner -->
    <?php if (!$isRsvpd && !$isHosted): ?>
      <div class="rounded-xl p-4 mb-6 flex gap-3" style="background: rgba(74,122,90,0.08); border: 1px solid rgba(74,122,90,0.2);">
        <span class="text-pc-sage text-lg flex-shrink-0 mt-0.5">🔒</span>
        <p class="text-pc-sage text-sm leading-relaxed">Attendee identities are kept private until the dinner. You'll only see first names after you RSVP.</p>
      </div>
    <?php endif ?>

    <!-- RSVP actions -->
    <?php if (!$isPast): ?>
      <?php if ($isHosted): ?>
        <div class="space-y-3">
          <div class="bg-pc-sage/15 text-pc-sage rounded-xl p-4 text-sm font-medium text-center">✓ You're hosting this dinner</div>
          <form method="POST">
      <?= pcCsrfField() ?>
            <input type="hidden" name="action" value="delete_event">
            <button type="submit"
              onclick="return confirm('Cancel this dinner? All RSVPs will be removed.')"
              class="w-full btn-ghost py-3.5 rounded-xl font-semibold text-sm transition-colors text-red-500 border-red-200">
              Cancel dinner
            </button>
          </form>
        </div>
      <?php elseif ($isRsvpd): ?>
        <div class="space-y-3">
          <div class="bg-pc-sage/15 text-pc-sage rounded-xl p-4 text-sm font-medium text-center">✓ You're going to this dinner</div>
          <form method="POST">
      <?= pcCsrfField() ?>
            <input type="hidden" name="action" value="cancel_rsvp">
            <button type="submit" class="w-full btn-secondary py-3.5 rounded-xl font-semibold text-sm transition-colors">
              Cancel RSVP
            </button>
          </form>
        </div>
      <?php elseif ($isFull): ?>
        <button disabled class="w-full btn-ghost py-3.5 rounded-xl font-semibold text-sm cursor-not-allowed opacity-60">
          This dinner is full
        </button>
      <?php else: ?>
        <form method="POST">
      <?= pcCsrfField() ?>
          <input type="hidden" name="action" value="rsvp">
          <button type="submit" class="w-full btn-primary py-4 rounded-xl font-semibold transition-colors">
            RSVP — I'm in
          </button>
        </form>
      <?php endif ?>
    <?php else: ?>
      <div class="bg-pc-border/40 text-pc-slate rounded-xl p-4 text-sm text-center">This dinner has already passed.</div>
    <?php endif ?>

  </div>
</main>

</body>
</html>
