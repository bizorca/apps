<?php
/** @var array $org @var array $errors @var string $action @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$v = static fn (string $k): string => h((string) ($org[$k] ?? ''));
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900';
$label = 'block text-sm font-medium mb-1';
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-6"><?= h($title) ?></h1>

  <?php if ($errors !== []): ?>
    <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= h($action) ?>" class="space-y-5 bg-white rounded-lg border border-slate-200 p-6">
    <?= Csrf::field() ?>

    <div>
      <label class="<?= $label ?>" for="name">Business name</label>
      <input id="name" name="name" required value="<?= $v('name') ?>" class="<?= $field ?>">
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="<?= $label ?>" for="legal_name">Legal name</label>
        <input id="legal_name" name="legal_name" value="<?= $v('legal_name') ?>" class="<?= $field ?>">
      </div>
      <div>
        <label class="<?= $label ?>" for="entity_type">Entity type</label>
        <select id="entity_type" name="entity_type" class="<?= $field ?>">
          <option value="">—</option>
          <?php foreach (ClientOrgRepository::ENTITY_TYPES as $k => $lbl): ?>
            <option value="<?= h($k) ?>" <?= ($org['entity_type'] ?? '') === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
      <div>
        <label class="<?= $label ?>" for="industry_naics">NAICS</label>
        <input id="industry_naics" name="industry_naics" value="<?= $v('industry_naics') ?>" placeholder="332710" class="<?= $field ?>">
      </div>
      <div>
        <label class="<?= $label ?>" for="employee_count">Employees</label>
        <input id="employee_count" name="employee_count" inputmode="numeric" value="<?= $v('employee_count') ?>" class="<?= $field ?>">
      </div>
      <div>
        <label class="<?= $label ?>" for="fiscal_year_end">Fiscal year end</label>
        <input id="fiscal_year_end" name="fiscal_year_end" value="<?= $v('fiscal_year_end') ?>" placeholder="12-31" class="<?= $field ?>">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="<?= $label ?>" for="revenue_band">Revenue</label>
        <select id="revenue_band" name="revenue_band" class="<?= $field ?>">
          <option value="">—</option>
          <?php foreach (ClientOrgRepository::REVENUE_BANDS as $k => $lbl): ?>
            <option value="<?= h($k) ?>" <?= ($org['revenue_band'] ?? '') === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="<?= $label ?>" for="status">Status</label>
        <select id="status" name="status" class="<?= $field ?>">
          <?php foreach (['prospect' => 'Prospect', 'active' => 'Active client', 'archived' => 'Archived'] as $k => $lbl): ?>
            <option value="<?= h($k) ?>" <?= ($org['status'] ?? 'prospect') === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="<?= $label ?>" for="website">Website</label>
        <input id="website" name="website" value="<?= $v('website') ?>" class="<?= $field ?>">
      </div>
      <div>
        <label class="<?= $label ?>" for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= $v('phone') ?>" class="<?= $field ?>">
      </div>
    </div>

    <div class="grid grid-cols-4 gap-4">
      <div class="col-span-2">
        <label class="<?= $label ?>" for="city">City</label>
        <input id="city" name="city" value="<?= $v('city') ?>" class="<?= $field ?>">
      </div>
      <div>
        <label class="<?= $label ?>" for="region">State</label>
        <input id="region" name="region" value="<?= $v('region') ?>" class="<?= $field ?>">
      </div>
      <div>
        <label class="<?= $label ?>" for="country">Country</label>
        <input id="country" name="country" maxlength="2" value="<?= $v('country') ?>" placeholder="US" class="<?= $field ?>">
      </div>
    </div>

    <div>
      <label class="<?= $label ?>" for="situation">Situation</label>
      <textarea id="situation" name="situation" rows="4" class="<?= $field ?>"><?= $v('situation') ?></textarea>
      <p class="text-xs text-slate-500 mt-1">What is actually going on here. Written for you, not for them.</p>
    </div>

    <div class="flex gap-3 pt-2">
      <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save</button>
      <a href="<?= h(url('/clients')) ?>" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</a>
    </div>
  </form>
</div>
