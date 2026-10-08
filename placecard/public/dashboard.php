<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';
require_once PC_ROOT . '/includes/api.php';

requireAuth();

$user      = currentUser();
$token     = getToken();
$firstName = htmlspecialchars($user['first_name'] ?? 'there');
$cityId    = $_GET['city'] ?? 'chiang-mai';
if (!array_key_exists($cityId, CITIES)) $cityId = 'chiang-mai';
$city = CITIES[$cityId];

// Fetch events
$eventsResp = api('GET', "events?city_id=$cityId", [], $token);
$allEvents  = $eventsResp['data'] ?? [];

// Time of day greeting
$hour = (int)date('G');
$greeting = match(true) {
    $hour < 12 => 'Good morning',
    $hour < 17 => 'Good afternoon',
    default    => 'Good evening',
};

// RSVPd event IDs (from user's events)
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

// Show only upcoming, limit to 3 for home
$upcoming = array_filter($allEvents, fn($e) => strtotime($e['event_date'] ?? '') > time());
$upcoming = array_slice(array_values($upcoming), 0, 3);

$pageTitle  = 'Home — placecard';
$activePage = 'home';
?>
<?php include PC_ROOT . '/includes/head.php' ?>
<?php include PC_ROOT . '/includes/app-nav.php' ?>

<main class="app-content min-h-screen">
  <div class="max-w-2xl mx-auto px-4 py-8">

    <!-- Greeting -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-pc-charcoal"><?= $greeting ?>, <?= $firstName ?>.</h1>
      <p class="text-pc-slate mt-0.5">Find your table.</p>
    </div>

    <!-- City picker -->
    <div class="flex gap-2 mb-8 overflow-x-auto pb-1">
      <?php foreach (CITIES as $id => $c): ?>
        <a href="<?= PC_BASE ?>/dashboard.php?city=<?= $id ?>"
          class="flex-shrink-0 px-5 py-2.5 rounded-full text-sm font-medium transition-colors <?= $id === $cityId ? 'bg-pc-terracotta text-white' : 'bg-white border border-pc-border text-pc-charcoal hover:bg-pc-warm' ?>">
          <?= $c['emoji'] ?> <?= htmlspecialchars($c['name']) ?>
        </a>
      <?php endforeach ?>
    </div>

    <!-- Upcoming dinners -->
    <div class="mb-8">
      <div class="flex justify-between items-center mb-4">
        <h2 class="font-semibold text-pc-charcoal">Upcoming dinners</h2>
        <a href="<?= PC_BASE ?>/events.php?city=<?= $cityId ?>" class="text-pc-terracotta text-sm font-medium hover:underline">See all</a>
      </div>

      <?php if (empty($upcoming)): ?>
        <div class="bg-white rounded-2xl border border-pc-border p-8 text-center shadow-card">
          <p class="text-4xl mb-3">🍽️</p>
          <p class="text-pc-charcoal font-medium mb-1">No dinners scheduled yet.</p>
          <p class="text-pc-slate text-sm">Be the first to host one.</p>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($upcoming as $event):
            $isRsvpd  = in_array($event['id'], $rsvpdIds, true);
            $isHosted = in_array($event['id'], $hostedIds, true);
            $spotsLeft = ($event['max_attendees'] ?? 0) - ($event['attendee_count'] ?? 0);
            $isFull    = $spotsLeft <= 0;
            $dateStr   = date('D, M j', strtotime($event['event_date']));
            $timeStr   = date('g:i A', strtotime($event['event_date']));
          ?>
            <?php include PC_ROOT . '/includes/event-card.php' ?>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>

    <!-- Host CTA -->
    <div class="bg-pc-warm rounded-2xl p-6 border border-pc-border">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-2xl flex-shrink-0 shadow-sm">👋</div>
        <div class="flex-1">
          <h3 class="font-semibold text-pc-charcoal mb-1">Host a dinner</h3>
          <p class="text-pc-slate text-sm leading-relaxed mb-4">Pick a restaurant, set a time, open it up. Somebody will show up. They usually do.</p>
          <a href="<?= PC_BASE ?>/create-event.php?city=<?= $cityId ?>"
            class="btn-primary px-5 py-2.5 rounded-xl text-sm font-semibold transition-colors inline-block">
            Create a dinner
          </a>
        </div>
      </div>
    </div>

  </div>
</main>

</body>
</html>
