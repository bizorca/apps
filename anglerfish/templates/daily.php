<?php use Anglerfish\Models\DailyIdea; ?>
<h1 class="text-2xl font-bold text-navy">Daily Idea</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
<p class="mt-2 max-w-3xl text-sm text-muted">
  One Hypnosis Brain item each morning at <?= DailyIdea::SEND_AT ?>am Pacific to <?= h(DailyIdea::TO) ?>,
  with an explainer and a plate. The writing happens ahead of time in Claude Code ("queue a week of
  Daily Ideas"); the server only draws the plate and sends. <strong><?= (int) $counts['ahead'] ?></strong>
  written and waiting. A morning with nothing queued still sends the raw item.
  <?= number_format((int) $counts['sent']) ?> of <?= number_format((int) $counts['pool']) ?> ideas used.
</p>

<nav class="mt-5 flex flex-wrap gap-1 text-sm">
  <a href="<?= url('/daily') ?>" class="rounded px-3 py-1.5 <?= $status === '' ? 'bg-navy text-cream' : 'text-muted hover:bg-black/5' ?>">All</a>
  <?php foreach (DailyIdea::STATUSES as $key => $label): ?>
    <a href="<?= url('/daily?status=' . $key) ?>"
       class="rounded px-3 py-1.5 <?= $status === $key ? 'bg-gold text-navy' : 'text-muted hover:bg-black/5' ?>">
      <?= h($label) ?> <span class="opacity-60"><?= (int) $counts[$key] ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<?php if (!$rows): ?>
  <p class="mt-10 text-sm text-muted">Nothing on the roster.</p>
<?php else: ?>
<div class="mt-4 divide-y divide-black/5 rounded border border-black/10 bg-white">
  <?php foreach ($rows as $r):
      $badge = ['reserved' => 'bg-black/5 text-muted', 'queued' => 'bg-blue-100 text-blue-800',
                'sent' => 'bg-green-100 text-green-800', 'failed' => 'bg-red-100 text-red-700'][$r['status']]; ?>
    <a href="<?= url('/daily/' . (int) $r['idea_id']) ?>"
       class="flex items-center gap-4 px-4 py-2.5 hover:bg-cream/40 <?= $r['send_date'] === $today ? 'bg-cream/60' : '' ?>">
      <span class="w-28 shrink-0 text-xs text-muted">
        <?= h((new DateTimeImmutable($r['send_date']))->format('D M j')) ?>
      </span>
      <?php if ($r['image_path']): ?>
        <img src="<?= url('/daily/plate/' . (int) $r['id'] . '?w=1') ?>" alt="" loading="lazy"
             class="h-10 w-[72px] shrink-0 rounded border border-black/10 object-cover">
      <?php else: ?>
        <span class="flex h-10 w-[72px] shrink-0 items-center justify-center rounded border border-dashed border-black/15 text-[10px] text-muted">
          <?= $r['image_error'] ? 'failed' : ($r['status'] === 'queued' ? 'to draw' : 'no plate') ?>
        </span>
      <?php endif; ?>
      <span class="flex-1">
        <span class="block text-sm text-navy"><?= h(DailyIdea::niceTitle($r['title'])) ?></span>
        <span class="block text-[11px] text-muted"><?= h($r['topic']) ?> &middot; <?= h(DailyIdea::niceTitle($r['section'])) ?></span>
      </span>
      <span class="rounded px-1.5 py-0.5 text-[10px] <?= $badge ?>"><?= h(DailyIdea::STATUSES[$r['status']]) ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
