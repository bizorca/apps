<?php
/** @var array $entries @var string $action @var array $actions @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-4xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Audit log</h1>
  <p class="text-sm text-slate-600 mb-6">
    Append-only. Nothing here can be edited or removed, including by us — that is what makes it
    worth anything when someone asks what happened.
  </p>

  <form method="get" class="mb-5 flex items-end gap-2">
        <?= pl_route_field() ?>
    <label class="text-xs text-slate-600">Filter
      <select name="action" class="block rounded border border-slate-300 px-2 py-1 text-sm">
        <option value="">Everything</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= h($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= h($a) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Show</button>
  </form>

  <div class="rounded-lg border border-slate-200 bg-white overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-xs text-slate-500 border-b border-slate-100">
        <tr>
          <th class="text-left font-medium px-4 py-2 whitespace-nowrap">When</th>
          <th class="text-left font-medium px-4 py-2">Who</th>
          <th class="text-left font-medium px-4 py-2">What</th>
          <th class="text-left font-medium px-4 py-2">On</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if ($entries === []): ?>
          <tr><td colspan="4" class="px-4 py-3 text-sm text-slate-500">Nothing recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($entries as $e): ?>
          <tr>
            <td class="px-4 py-2 whitespace-nowrap text-xs text-slate-500">
              <?= h(date('j M Y H:i', strtotime((string) $e['created_at']))) ?>
            </td>
            <td class="px-4 py-2">
              <?= h((string) ($e['actor_label'] ?? 'system')) ?>
              <?php if (!empty($e['impersonator_id'])): ?>
                <span class="text-xs text-amber-700">(impersonating)</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-2 font-mono text-xs"><?= h((string) $e['action']) ?></td>
            <td class="px-4 py-2 text-xs text-slate-500">
              <?php if (!empty($e['object_type'])): ?>
                <?= h((string) $e['object_type']) ?> #<?= (int) $e['object_id'] ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p class="text-xs text-slate-500 mt-4">
    The actor's name is stored as text alongside the id, so an entry still says who did something
    after that person is removed from the firm.
  </p>
</div>
