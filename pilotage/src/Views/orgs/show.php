<?php
/** @var array $org @var array $contacts @var array $events @var int $seats @var bool $canEdit @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\ClientContactRepository;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$clientSide = ($user['client_org_id'] ?? null) !== null;
$field = 'w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-5xl mx-auto px-4 py-8">

  <div class="flex items-start justify-between mb-6">
    <div>
      <div class="flex items-center gap-3">
        <h1 class="text-xl font-semibold"><?= h($org['name']) ?></h1>
        <span class="text-xs rounded-full px-2 py-0.5 <?= $org['status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : ($org['status'] === 'prospect' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500') ?>">
          <?= h($org['status']) ?>
        </span>
      </div>
      <?php if (!empty($org['legal_name'])): ?>
        <p class="text-sm text-slate-500 mt-1"><?= h($org['legal_name']) ?></p>
      <?php endif; ?>
    </div>
    <?php if ($canEdit && !$clientSide): ?>
      <div class="flex gap-2">
        <?php if ($org['status'] === 'prospect'): ?>
          <form method="post" action="<?= h(url('/clients/' . $org['id'] . '/convert')) ?>">
            <?= Csrf::field() ?>
            <button class="rounded bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Convert to client</button>
          </form>
        <?php endif; ?>
        <a href="<?= h(url('/clients/' . $org['id'] . '/edit')) ?>" class="rounded border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Edit</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="grid grid-cols-3 gap-6">
    <div class="col-span-2 space-y-6">

      <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold mb-3">Engagements</h2>
        <?php if (empty($engagements)): ?>
          <p class="text-sm text-slate-500 mb-3">None yet.</p>
        <?php else: ?>
          <ul class="divide-y divide-slate-100 -mx-5 mb-3">
            <?php foreach ($engagements as $e): ?>
              <li>
                <a href="<?= h(url('/engagements/' . $e['id'] . '/journey')) ?>" class="flex items-center justify-between px-5 py-2.5 hover:bg-slate-50">
                  <span class="text-sm"><?= h($e['title']) ?></span>
                  <span class="text-xs text-slate-400"><?= h((string) $e['status']) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if ($canEdit && !$clientSide): ?>
          <form method="post" action="<?= h(url('/clients/' . $org['id'] . '/engagements')) ?>" class="border-t border-slate-100 pt-4 space-y-3">
            <?= Csrf::field() ?>
            <input name="title" required placeholder="Engagement title, e.g. Q3 operating rhythm" class="<?= $field ?>">
            <div class="grid grid-cols-2 gap-3">
              <select name="cadence" class="<?= $field ?>">
                <?php foreach ($cadences as $k => $lbl): ?>
                  <option value="<?= h($k) ?>" <?= $k === 'biweekly' ? 'selected' : '' ?>><?= h($lbl) ?></option>
                <?php endforeach; ?>
              </select>
              <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="scope_enabled" value="1"> track scope &amp; change requests
              </label>
            </div>
            <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Start an engagement</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if (!empty($org['situation']) && !$clientSide): ?>
        <div class="rounded-lg border border-slate-200 bg-white p-5">
          <h2 class="text-sm font-semibold mb-2">Situation</h2>
          <p class="text-sm text-slate-700 whitespace-pre-line"><?= h($org['situation']) ?></p>
        </div>
      <?php endif; ?>

      <div class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex items-baseline justify-between mb-3">
          <h2 class="text-sm font-semibold">People</h2>
          <span class="text-xs text-slate-500"><?= (int) $seats ?> of <?= (int) $org['team_invite_cap'] ?> portal seats used</span>
        </div>

        <?php if ($contacts === []): ?>
          <p class="text-sm text-slate-500">Nobody on file yet.</p>
        <?php else: ?>
          <ul class="divide-y divide-slate-100 -mx-5 mb-4">
            <?php foreach ($contacts as $c): ?>
              <li class="px-5 py-2.5 flex items-center justify-between">
                <div>
                  <div class="text-sm font-medium">
                    <?= h($c['name']) ?>
                    <?php if ((int) $c['is_primary'] === 1): ?>
                      <span class="ml-1 text-xs text-slate-400">primary</span>
                    <?php endif; ?>
                  </div>
                  <div class="text-xs text-slate-500">
                    <?= h($c['title'] ?? '') ?><?= !empty($c['title']) && !empty($c['email']) ? ' · ' : '' ?><?= h($c['email'] ?? '') ?>
                  </div>
                </div>
                <span class="text-xs <?= $c['portal_access'] === 'none' ? 'text-slate-400' : 'text-slate-600' ?>">
                  <?= h(ClientContactRepository::ACCESS_LEVELS[$c['portal_access']] ?? '') ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <form method="post" action="<?= h(url('/clients/' . $org['id'] . '/contacts')) ?>" class="border-t border-slate-100 pt-4 space-y-3">
          <?= Csrf::field() ?>
          <div class="grid grid-cols-2 gap-3">
            <input name="name" required placeholder="Name" class="<?= $field ?>">
            <input name="job_title" placeholder="Title" class="<?= $field ?>">
          </div>
          <div class="grid grid-cols-2 gap-3">
            <input name="email" type="email" placeholder="Email" class="<?= $field ?>">
            <select name="portal_access" class="<?= $field ?>">
              <?php foreach (ClientContactRepository::ACCESS_LEVELS as $k => $lbl): ?>
                <option value="<?= h($k) ?>"><?= h($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add person</button>
        </form>
      </div>
    </div>

    <div class="space-y-6">
      <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold mb-3">Details</h2>
        <dl class="text-sm space-y-2">
          <?php
          $rows = [
            'Entity'    => ClientOrgRepository::ENTITY_TYPES[$org['entity_type'] ?? ''] ?? null,
            'Revenue'   => ClientOrgRepository::REVENUE_BANDS[$org['revenue_band'] ?? ''] ?? null,
            'Employees' => $org['employee_count'] ?? null,
            'NAICS'     => $org['industry_naics'] ?? null,
            'FY end'    => $org['fiscal_year_end'] ?? null,
            'Location'  => trim(($org['city'] ?? '') . (!empty($org['city']) && !empty($org['region']) ? ', ' : '') . ($org['region'] ?? '')),
          ];
          foreach ($rows as $k => $val):
            if ($val === null || $val === '') { continue; }
          ?>
            <div class="flex justify-between gap-4">
              <dt class="text-slate-500"><?= h($k) ?></dt>
              <dd class="text-right"><?= h((string) $val) ?></dd>
            </div>
          <?php endforeach; ?>
          <?php if (!empty($org['website'])): ?>
            <div class="flex justify-between gap-4">
              <dt class="text-slate-500">Website</dt>
              <dd class="text-right truncate"><a href="<?= h($org['website']) ?>" rel="noopener noreferrer nofollow" target="_blank" class="underline"><?= h($org['website']) ?></a></dd>
            </div>
          <?php endif; ?>
        </dl>
      </div>

      <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold mb-3">Timeline</h2>
        <?php if ($events === []): ?>
          <p class="text-sm text-slate-500">Nothing yet.</p>
        <?php else: ?>
          <ol class="space-y-3">
            <?php foreach ($events as $e): ?>
              <li class="text-sm">
                <div class="text-slate-800"><?= h($e['summary']) ?></div>
                <div class="text-xs text-slate-400">
                  <?= h(date('j M Y, H:i', strtotime((string) $e['occurred_at']))) ?>
                  <?= !empty($e['actor_label']) ? ' · ' . h($e['actor_label']) : '' ?>
                  <?php if ((int) $e['client_visible'] === 0): ?>
                    <span class="ml-1 rounded bg-slate-100 px-1">internal</span>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
