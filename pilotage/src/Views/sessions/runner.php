<?php
/**
 * The session runner (SPEC.md §9).
 *
 * One screen the coach drives during a live meeting: agenda with timings and
 * auto-filled blocks, tick-off as you go, both note panes side by side, and a
 * one-click close that drafts the recap.
 *
 * @var array $session @var bool $clientSide @var bool $canRun @var array $user @var array $tenant
 */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);

$act = url('/sessions/' . $session['id']);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
$totalMinutes = 0;
foreach ($session['agenda'] as $item) { $totalMinutes += (int) ($item['minutes'] ?? 0); }
?>
<div class="max-w-5xl mx-auto px-4 py-8">

  <div class="flex items-start justify-between mb-6">
    <div>
      <h1 class="text-xl font-semibold"><?= h($session['title']) ?></h1>
      <p class="text-sm text-slate-500 mt-1">
        <?= h($session['engagement_title']) ?>
        <?php if (!empty($session['scheduled_at'])): ?>
          · <?= h(date('j M Y, H:i', strtotime((string) $session['scheduled_at']))) ?> UTC
        <?php endif; ?>
        <?php if ($totalMinutes > 0): ?> · <?= $totalMinutes ?> min planned<?php endif; ?>
      </p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= h(url('/sessions/' . $session['id'] . '.ics')) ?>"
         class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50">Add to calendar</a>
      <?php if ($canRun && $session['status'] === 'scheduled'): ?>
        <form method="post" action="<?= h($act) ?>">
          <?= Csrf::field() ?><input type="hidden" name="action" value="start">
          <button class="rounded bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Start session</button>
        </form>
      <?php elseif ($canRun && $session['status'] === 'in_progress'): ?>
        <form method="post" action="<?= h($act) ?>">
          <?= Csrf::field() ?><input type="hidden" name="action" value="close">
          <button class="rounded bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Close &amp; draft recap</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-<?= $clientSide ? '1' : '2' ?> gap-6">

    <div class="space-y-3">
      <h2 class="text-sm font-semibold">Agenda</h2>
      <?php if ($session['agenda'] === []): ?>
        <p class="text-sm text-slate-500">No agenda — this session was scheduled without a template.</p>
      <?php endif; ?>

      <?php foreach ($session['agenda'] as $item):
        $covered = $item['covered_at'] !== null; ?>
        <div class="rounded-lg border border-slate-200 bg-white p-4 <?= $covered ? 'opacity-60' : '' ?>">
          <div class="flex items-start justify-between gap-3">
            <div class="text-sm font-medium <?= $covered ? 'line-through' : '' ?>"><?= h($item['title']) ?></div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <?php if (!empty($item['minutes'])): ?>
                <span class="text-xs text-slate-400"><?= (int) $item['minutes'] ?>m</span>
              <?php endif; ?>
              <?php if ($canRun): ?>
                <form method="post" action="<?= h($act) ?>">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="cover">
                  <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                  <button class="text-xs rounded border border-slate-300 px-2 py-0.5 hover:bg-slate-50"><?= $covered ? 'undo' : 'done' ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!$clientSide && !empty($item['prompt'])): ?>
            <p class="text-xs text-slate-500 mt-2 italic"><?= h((string) $item['prompt']) ?></p>
          <?php endif; ?>

          <?php if (!empty($item['block'])): ?>
            <div class="mt-2 pt-2 border-t border-slate-100">
              <?php if ($item['block']['items'] !== []): ?>
                <ul class="text-xs text-slate-700 space-y-1">
                  <?php foreach ($item['block']['items'] as $line): ?>
                    <li>· <?= h((string) $line) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p class="text-xs text-slate-400"><?= h((string) ($item['block']['note'] ?? '')) ?></p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="space-y-4">
      <?php if ($canRun): ?>
        <form method="post" action="<?= h($act) ?>" class="space-y-3">
          <?= Csrf::field() ?><input type="hidden" name="action" value="save_notes">

          <div>
            <label class="block text-sm font-semibold mb-1">Shared notes</label>
            <p class="text-xs text-slate-500 mb-1">The client sees these.</p>
            <textarea name="shared" rows="8" class="<?= $field ?>"><?= h((string) ($session['shared_notes']['body'] ?? '')) ?></textarea>
          </div>

          <div>
            <label class="block text-sm font-semibold mb-1">Private notes</label>
            <p class="text-xs text-slate-500 mb-1">Yours alone. Not the client, not your colleagues.</p>
            <textarea name="private" rows="6" class="<?= $field ?> bg-amber-50/40"><?= h((string) ($session['private_notes']['body'] ?? '')) ?></textarea>
          </div>

          <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Save notes</button>
        </form>
      <?php else: ?>
        <div>
          <h2 class="text-sm font-semibold mb-1">Notes</h2>
          <?php if (!empty($session['shared_notes']['body'])): ?>
            <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm whitespace-pre-line"><?= h((string) $session['shared_notes']['body']) ?></div>
          <?php else: ?>
            <p class="text-sm text-slate-500">Nothing shared yet.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($session['recap'])): ?>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold">Recap</h2>
            <span class="text-xs <?= $session['recap']['status'] === 'sent' ? 'text-emerald-600' : 'text-amber-600' ?>">
              <?= h((string) $session['recap']['status']) ?>
            </span>
          </div>
          <?php if ($canRun && $session['recap']['status'] === 'draft'): ?>
            <form method="post" action="<?= h($act) ?>" class="space-y-2">
              <?= Csrf::field() ?><input type="hidden" name="action" value="save_recap">
              <textarea name="recap" rows="8" class="<?= $field ?>"><?= h((string) $session['recap']['body']) ?></textarea>
              <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Save draft</button>
            </form>
            <form method="post" action="<?= h($act) ?>" class="mt-2">
              <?= Csrf::field() ?><input type="hidden" name="action" value="send_recap">
              <button class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Send to the client</button>
            </form>
            <p class="text-xs text-slate-500 mt-2">Read it before you send it. Nothing goes out on its own.</p>
          <?php else: ?>
            <div class="text-sm whitespace-pre-line"><?= h((string) $session['recap']['body']) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($session['attendees'] !== []): ?>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <h2 class="text-sm font-semibold mb-2">Attendees</h2>
          <ul class="text-sm space-y-1">
            <?php foreach ($session['attendees'] as $a): ?>
              <li class="flex justify-between">
                <span><?= h($a['display_name']) ?></span>
                <?php if ($a['attended'] !== null): ?>
                  <span class="text-xs <?= (int) $a['attended'] === 1 ? 'text-emerald-600' : 'text-slate-400' ?>">
                    <?= (int) $a['attended'] === 1 ? 'attended' : 'no show' ?>
                  </span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
