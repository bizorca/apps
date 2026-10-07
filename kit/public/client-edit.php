<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/bootstrap.php';

$user   = requireAuth();
$userId = (int) $user['id'];

$clientId = (int) ($_GET['id'] ?? 0);
$client   = $clientId > 0 ? requireClient($clientId, $userId) : null;
$isNew    = $client === null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();

    if (post('action') === 'delete' && !$isNew) {
        deleteClient($clientId, $userId);
        flashSuccess('Client deleted, along with every assessment on it.');
        redirect(url('/dashboard.php'));
    }

    $data = [];
    foreach (clientFields() as $f) $data[$f] = post($f);

    if ($data['business_name'] === '') {
        flashError('A business name is required.');
    } else {
        if ($isNew) {
            $newId = createClient($userId, $data);
            flashSuccess('Client added. Start with the Regulatory Intake Screen.');
            redirect(url('/client.php?id=' . $newId));
        }
        updateClient($clientId, $userId, $data);
        flashSuccess('Client details saved.');
        redirect(url('/client.php?id=' . $clientId));
    }
}

$v = function (string $field) use ($client): string {
    return (string) ($_POST[$field] ?? $client[$field] ?? ($field === 'state_code' ? 'WA' : ''));
};

$pageTitle = $isNew ? 'Add a client' : 'Edit details';
renderHeader(compact('pageTitle', 'client'));

$entityTypes = ['' => 'Not known yet', 'sole_prop' => 'Sole proprietor', 'llc' => 'LLC',
                's_corp' => 'S corporation', 'partnership' => 'Partnership',
                'corporation' => 'Corporation', 'other' => 'Other'];
?>
<div class="mx-auto max-w-readable">
  <h1 class="text-2xl font-semibold"><?= $isNew ? 'Add a client' : 'Edit client details' ?></h1>
  <p class="hint">
    Enough to tell one client from another and to aim the regulatory screen. The
    assessment instruments collect the rest.
  </p>

  <form method="post" class="mt-6 space-y-5">
    <?= csrf() ?>

    <div class="card space-y-4 p-5">
      <div>
        <label class="label" for="business_name">Business name <span class="text-bad">*</span></label>
        <input class="field mt-1" id="business_name" name="business_name" required
               value="<?= h($v('business_name')) ?>" autofocus>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label" for="owner_name">Owner name</label>
          <input class="field mt-1" id="owner_name" name="owner_name" value="<?= h($v('owner_name')) ?>">
        </div>
        <div>
          <label class="label" for="business_type">Type of business</label>
          <input class="field mt-1" id="business_type" name="business_type"
                 placeholder="septic service, massage therapy, boatyard…"
                 value="<?= h($v('business_type')) ?>">
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-3">
        <div>
          <label class="label" for="county">County</label>
          <input class="field mt-1" id="county" name="county" placeholder="Clallam"
                 value="<?= h($v('county')) ?>">
        </div>
        <div>
          <label class="label" for="state_code">State</label>
          <input class="field mt-1" id="state_code" name="state_code" maxlength="2"
                 value="<?= h($v('state_code')) ?>">
        </div>
        <div>
          <label class="label" for="year_started">Year started</label>
          <input class="field mt-1" id="year_started" name="year_started" inputmode="numeric"
                 placeholder="2022" value="<?= h($v('year_started')) ?>">
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label" for="entity_type">Legal structure</label>
          <select class="field mt-1" id="entity_type" name="entity_type">
            <?php foreach ($entityTypes as $k => $lbl): ?>
              <option value="<?= h($k) ?>"<?= $v('entity_type') === $k ? ' selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint">Confirmed on the Regulatory Intake Screen, not taken on faith here.</p>
        </div>
        <div>
          <label class="label" for="employee_count">People working in the business</label>
          <input class="field mt-1" id="employee_count" name="employee_count"
                 placeholder="owner only · 1 part-time W-2 · 2 contractors"
                 value="<?= h($v('employee_count')) ?>">
        </div>
      </div>

      <div>
        <label class="label" for="seasonality">Seasonality</label>
        <input class="field mt-1" id="seasonality" name="seasonality"
               placeholder="peak May–September, slow January–March"
               value="<?= h($v('seasonality')) ?>">
        <p class="hint">
          A negative cash reading in the strongest season is louder than it looks.
          One in the slow season may be normal. The health check needs this context.
        </p>
      </div>

      <div>
        <label class="label" for="notes">Notes</label>
        <textarea class="field mt-1" id="notes" name="notes" rows="4"><?= h($v('notes')) ?></textarea>
      </div>

      <?php if (!$isNew): ?>
        <div>
          <label class="label" for="engagement_status">Engagement status</label>
          <select class="field mt-1" id="engagement_status" name="engagement_status">
            <?php foreach (['active' => 'Active', 'paused' => 'Paused', 'archived' => 'Archived'] as $k => $lbl): ?>
              <option value="<?= h($k) ?>"<?= $v('engagement_status') === $k ? ' selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <button type="submit" class="btn-primary"><?= $isNew ? 'Add client' : 'Save changes' ?></button>
      <a href="<?= h($isNew ? url('/dashboard.php') : url('/client.php?id=' . $clientId)) ?>" class="btn-quiet">Cancel</a>
    </div>
  </form>

  <?php if (!$isNew): ?>
    <form method="post" class="mt-10 border-t border-hairline pt-6"
          onsubmit="return confirm('Delete <?= h(addslashes($client['business_name'])) ?> and every assessment on it? This cannot be undone.');">
      <?= csrf() ?>
      <input type="hidden" name="action" value="delete">
      <h2 class="font-semibold">Delete this client</h2>
      <p class="hint">
        Removes the client and every assessment, progress entry, and worksheet
        attached to it. There is no undo and no backup inside the app.
      </p>
      <button type="submit" class="btn-danger mt-3">Delete permanently</button>
    </form>
  <?php endif; ?>
</div>
<?php renderFooter();
