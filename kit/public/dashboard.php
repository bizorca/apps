<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/bootstrap.php';

$user   = requireAuth();
$userId = (int) $user['id'];

$showArchived = ($_GET['archived'] ?? '') === '1';
$clients      = getClients($userId, $showArchived);

// Per-client headline: the worst reading across every instrument run, plus
// the one priority if the advisor has named one.
$rows = [];
foreach ($clients as $c) {
    $readings = [];
    $done     = 0;
    foreach (array_keys(instruments()) as $key) {
        $a = latestAssessment((int) $c['id'], $key);
        if ($a) {
            [$r, ] = instrumentSummary($key, $a);
            if ($r !== READ_UNKNOWN) { $readings[] = $r; $done++; }
        }
    }
    $priorityAssessment = latestAssessment((int) $c['id'], 'priority');
    $rows[] = [
        'client'    => $c,
        'reading'   => $readings ? worstReading($readings) : READ_UNKNOWN,
        'completed' => $done,
        'total'     => count(instruments()),
        'priority'  => trim((string) ($priorityAssessment['data']['priority_statement'] ?? '')),
    ];
}

$pageTitle = 'Clients';
renderHeader(compact('pageTitle'));
?>
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="text-2xl font-semibold">Clients</h1>
    <p class="hint">
      <?= count($rows) ?> <?= count($rows) === 1 ? 'client' : 'clients' ?><?php
      if (!$showArchived): ?> · <a class="underline decoration-hairline underline-offset-4 hover:text-ink" href="<?= h(url('/dashboard.php?archived=1')) ?>">show archived</a><?php
      else: ?> · <a class="underline decoration-hairline underline-offset-4 hover:text-ink" href="<?= h(url('/dashboard.php')) ?>">hide archived</a><?php endif; ?>
    </p>
  </div>
  <a href="<?= h(url('/client-edit.php')) ?>" class="btn-primary">Add a client</a>
</div>

<?php if (!$rows): ?>
  <div class="card mt-6 p-8 text-center">
    <h2 class="text-lg font-semibold">No clients yet</h2>
    <p class="hint mx-auto mt-1 max-w-readable">
      Add the first one and work the instruments in layer order: regulatory exposure
      first, then the numbers, then the offer, then the owner's time.
    </p>
    <a href="<?= h(url('/client-edit.php')) ?>" class="btn-primary mt-5">Add a client</a>
  </div>
<?php else: ?>
  <ul class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($rows as $row): $c = $row['client']; ?>
      <li class="card flex flex-col p-5 transition-shadow hover:shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <h2 class="font-semibold leading-tight">
            <a class="hover:underline" href="<?= h(url('/client.php?id=' . (int) $c['id'])) ?>">
              <?= h($c['business_name']) ?>
            </a>
          </h2>
          <?= renderReading($row['reading']) ?>
        </div>

        <p class="hint">
          <?= h(trim(implode(' · ', array_filter([
                $c['owner_name'], $c['business_type'],
                $c['county'] !== '' ? $c['county'] . ' County' : '',
              ])))) ?: '&nbsp;' ?>
        </p>

        <?php if ($row['priority'] !== ''): ?>
          <p class="mt-3 border-l-2 border-primary pl-3 text-sm text-ink">
            <span class="block text-xs font-semibold uppercase tracking-wide text-primary">Priority</span>
            <?= h($row['priority']) ?>
          </p>
        <?php endif; ?>

        <div class="mt-auto flex items-center justify-between pt-4 text-xs text-muted">
          <span><?= (int) $row['completed'] ?> of <?= (int) $row['total'] ?> instruments run</span>
          <?php if ($c['engagement_status'] === 'archived'): ?>
            <span class="tag bg-sunk text-muted">Archived</span>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php renderFooter();
