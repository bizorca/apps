<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';
require_once PC_ROOT . '/includes/api.php';

requireAuth();

$token  = getToken();
$cityId = $_GET['city'] ?? 'chiang-mai';
if (!array_key_exists($cityId, CITIES)) $cityId = 'chiang-mai';
$city = CITIES[$cityId];

$eventsResp = api('GET', "events?city_id=$cityId", [], $token);
$allEvents  = $eventsResp['data'] ?? [];

// User's RSVPs
$myEventsResp = api('GET', 'users/me/events', [], $token);
$myEvents     = $myEventsResp['data'] ?? [];
$rsvpdIds     = array_column(
    array_filter($myEvents, fn($e) => !(bool)($e['is_host'] ?? false)),
    'id'
);
$hostedIds    = array_column(
    array_filter($myEvents, fn($e) => (bool)($e['is_host'] ?? false)),
    'id'
);

// Sort by date
usort($allEvents, fn($a, $b) => strtotime($a['event_date']) <=> strtotime($b['event_date']));

$pageTitle  = htmlspecialchars($city['emoji'] . ' ' . $city['name']) . ' — placecard';
$activePage = 'events';
?>
<?php include PC_ROOT . '/includes/head.php' ?>
<?php include PC_ROOT . '/includes/app-nav.php' ?>

<main class="app-content min-h-screen">
  <div class="max-w-2xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-pc-charcoal">Dinners</h1>
    </div>

    <!-- City picker -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-1">
      <?php foreach (CITIES as $id => $c): ?>
        <a href="<?= PC_BASE ?>/events.php?city=<?= $id ?>"
          class="flex-shrink-0 px-5 py-2.5 rounded-full text-sm font-medium transition-colors <?= $id === $cityId ? 'bg-pc-terracotta text-white' : 'bg-white border border-pc-border text-pc-charcoal hover:bg-pc-warm' ?>">
          <?= $c['emoji'] ?> <?= htmlspecialchars($c['name']) ?>
        </a>
      <?php endforeach ?>
    </div>

    <!-- Event count -->
    <p class="text-pc-slate text-sm mb-4"><?= count($allEvents) ?> dinner<?= count($allEvents) !== 1 ? 's' : '' ?> in <?= htmlspecialchars($city['name']) ?></p>

    <!-- Events list -->
    <?php if (empty($allEvents)): ?>
      <div class="bg-white rounded-2xl border border-pc-border p-10 text-center shadow-card">
        <p class="text-4xl mb-3">🍽️</p>
        <p class="text-pc-charcoal font-medium mb-1">No dinners scheduled yet.</p>
        <p class="text-pc-slate text-sm mb-5">Be the first to host one in <?= htmlspecialchars($city['name']) ?>.</p>
        <a href="<?= PC_BASE ?>/create-event.php?city=<?= $cityId ?>" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-semibold inline-block">
          Host a dinner
        </a>
      </div>
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($allEvents as $event):
          $isRsvpd   = in_array($event['id'], $rsvpdIds, true);
          $isHosted  = in_array($event['id'], $hostedIds, true);
          $spotsLeft = ($event['max_attendees'] ?? 0) - ($event['attendee_count'] ?? 0);
          $isFull    = $spotsLeft <= 0;
          $dateStr   = date('D, M j', strtotime($event['event_date']));
          $timeStr   = date('g:i A', strtotime($event['event_date']));
        ?>
          <?php include PC_ROOT . '/includes/event-card.php' ?>
        <?php endforeach ?>
      </div>
    <?php endif ?>

    <!-- Host CTA -->
    <div class="mt-8 bg-pc-warm rounded-2xl p-5 border border-pc-border flex items-center gap-4">
      <span class="text-2xl">👋</span>
      <div class="flex-1">
        <p class="text-pc-charcoal font-medium text-sm">Don't see one you like?</p>
        <p class="text-pc-slate text-xs">Host your own — it takes about two minutes.</p>
      </div>
      <a href="<?= PC_BASE ?>/create-event.php?city=<?= $cityId ?>" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold flex-shrink-0">
        Host
      </a>
    </div>

  </div>
</main>

</body>
</html>
