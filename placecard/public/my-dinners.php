<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';
require_once PC_ROOT . '/includes/api.php';

requireAuth();

$token = getToken();
$resp  = api('GET', 'users/me/events', [], $token);
$all   = $resp['data'] ?? [];

// Separate into hosted and attended
$hosted   = array_filter($all, fn($e) => (bool)($e['is_host'] ?? false));
$attended = array_filter($all, fn($e) => !(bool)($e['is_host'] ?? false));

// Sort each by date
usort($hosted,   fn($a, $b) => strtotime($a['event_date']) <=> strtotime($b['event_date']));
usort($attended, fn($a, $b) => strtotime($a['event_date']) <=> strtotime($b['event_date']));

$deleted = isset($_GET['deleted']);

$pageTitle  = 'My Dinners — placecard';
$activePage = 'my-dinners';

// Dummy values for event-card include
$token = getToken();
?>
<?php include PC_ROOT . '/includes/head.php' ?>
<?php include PC_ROOT . '/includes/app-nav.php' ?>

<main class="app-content min-h-screen">
  <div class="max-w-2xl mx-auto px-4 py-8">

    <h1 class="text-2xl font-bold text-pc-charcoal mb-6">My Dinners</h1>

    <?php if ($deleted): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-4 mb-5 text-sm">Dinner cancelled successfully.</div>
    <?php endif ?>

    <?php if (empty($all)): ?>
      <div class="bg-white rounded-2xl border border-pc-border p-10 text-center shadow-card">
        <p class="text-4xl mb-3">📅</p>
        <p class="text-pc-charcoal font-medium mb-1">No dinners yet.</p>
        <p class="text-pc-slate text-sm mb-5">RSVP to a dinner or host your own.</p>
        <div class="flex gap-3 justify-center flex-wrap">
          <a href="<?= PC_BASE ?>/events.php" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-semibold">Browse dinners</a>
          <a href="<?= PC_BASE ?>/create-event.php" class="btn-secondary px-5 py-2.5 rounded-xl text-sm font-semibold">Host one</a>
        </div>
      </div>
    <?php else: ?>

      <!-- Hosting -->
      <?php if (!empty($hosted)): ?>
        <div class="mb-8">
          <h2 class="text-sm font-semibold text-pc-slate uppercase tracking-widest mb-3">Hosting</h2>
          <div class="space-y-4">
            <?php foreach ($hosted as $event):
              $isRsvpd   = false;
              $isHosted  = true;
              $spotsLeft = ($event['max_attendees'] ?? 0) - ($event['attendee_count'] ?? 0);
              $isFull    = $spotsLeft <= 0;
              $dateStr   = date('D, M j', strtotime($event['event_date']));
              $timeStr   = date('g:i A', strtotime($event['event_date']));
            ?>
              <?php include PC_ROOT . '/includes/event-card.php' ?>
            <?php endforeach ?>
          </div>
        </div>
      <?php endif ?>

      <!-- Attending -->
      <?php if (!empty($attended)): ?>
        <div class="mb-8">
          <h2 class="text-sm font-semibold text-pc-slate uppercase tracking-widest mb-3">RSVPd</h2>
          <div class="space-y-4">
            <?php foreach ($attended as $event):
              $isRsvpd   = true;
              $isHosted  = false;
              $spotsLeft = ($event['max_attendees'] ?? 0) - ($event['attendee_count'] ?? 0);
              $isFull    = $spotsLeft <= 0;
              $dateStr   = date('D, M j', strtotime($event['event_date']));
              $timeStr   = date('g:i A', strtotime($event['event_date']));
            ?>
              <?php include PC_ROOT . '/includes/event-card.php' ?>
            <?php endforeach ?>
          </div>
        </div>
      <?php endif ?>

    <?php endif ?>

  </div>
</main>

</body>
</html>
