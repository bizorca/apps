<?php use Anglerfish\Models\Video; ?>
<div class="flex items-end justify-between gap-6">
  <div>
    <h1 class="text-2xl font-bold text-navy">Videos</h1>
    <div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
    <p class="mt-2 max-w-3xl text-sm text-muted">
      The bite-size video production queue: 100 short personal-finance videos, one core
      idea each, in teaching order. Scripts are written in Claude Code from the Personal
      Finance Brain's trusted sources and filed with <code>worker/video_push.py</code>.
      Move each video along as you record, edit and publish it.
    </p>
  </div>
  <form method="get" action="<?= AF_BASE ?>/" class="flex gap-2">
    <input type="hidden" name="r" value="/videos">
    <input type="hidden" name="status" value="<?= h($status) ?>">
    <input name="q" value="<?= h($q) ?>" placeholder="Search topics and scripts…"
           class="rounded border border-black/15 px-3 py-1.5 text-sm w-64">
    <button class="rounded bg-navy px-3 py-1.5 text-sm text-cream hover:bg-navy2">Search</button>
  </form>
</div>

<?php /* Pipeline strip: where the 100 sit right now. */ ?>
<nav class="mt-5 flex flex-wrap gap-1 text-sm">
  <a href="<?= url('/videos') ?>"
     class="rounded px-3 py-1.5 <?= $status === '' ? 'bg-navy text-cream' : 'text-muted hover:bg-black/5' ?>">
    All <span class="opacity-60"><?= array_sum($counts) ?></span>
  </a>
  <?php foreach (Video::STATUSES as $key => $label): ?>
    <a href="<?= url('/videos?status=' . $key) ?>"
       class="rounded px-3 py-1.5 <?= $status === $key ? 'bg-gold text-navy' : 'text-muted hover:bg-black/5' ?>">
      <?= h($label) ?> <span class="opacity-60"><?= (int) $counts[$key] ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<div class="mt-3 flex flex-wrap gap-1">
  <a href="<?= url('/videos' . ($status ? '?status=' . $status : '')) ?>"
     class="rounded border px-2 py-1 text-[11px] <?= $part === 0 ? 'border-navy bg-navy text-cream' : 'border-black/10 bg-white text-navy hover:bg-cream' ?>">
    All parts
  </a>
  <?php foreach ($parts as $p): $on = (int) $p['part_no'] === $part; ?>
    <a href="<?= url('/videos?part=' . (int) $p['part_no'] . ($status ? '&status=' . $status : '')) ?>"
       class="rounded border px-2 py-1 text-[11px] <?= $on ? 'border-navy bg-navy text-cream' : 'border-black/10 bg-white text-navy hover:bg-cream' ?>">
      <span class="font-bold"><?= (int) $p['part_no'] ?>.</span> <?= h($p['part_title']) ?>
      <span class="ml-1 opacity-60"><?= (int) $p['scripted'] ?>/<?= (int) $p['n'] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$videos): ?>
  <p class="mt-10 text-sm text-muted">Nothing matches.</p>
<?php else: ?>
<div class="mt-6 divide-y divide-black/5 rounded border border-black/10 bg-white">
  <?php $lastPart = null; foreach ($videos as $v): ?>
    <?php if ($lastPart !== (int) $v['part_no']): $lastPart = (int) $v['part_no']; ?>
      <div class="bg-cream/60 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[2px] text-gold">
        Part <?= $lastPart ?> &middot; <?= h($v['part_title']) ?>
      </div>
    <?php endif; ?>
    <a href="<?= url('/videos/' . (int) $v['number']) ?>" class="flex items-center gap-4 px-4 py-2.5 hover:bg-cream/40">
      <span class="w-10 shrink-0 text-right font-mono text-sm text-muted"><?= (int) $v['number'] ?></span>
      <span class="min-w-0 flex-1">
        <span class="block truncate font-bold text-navy"><?= h($v['title'] ?: $v['topic']) ?></span>
        <?php if ($v['title']): ?>
          <span class="block truncate text-xs text-muted"><?= h($v['topic']) ?></span>
        <?php endif; ?>
      </span>
      <?php if ($v['runtime_min']): ?>
        <span class="shrink-0 text-xs text-muted"><?= (int) $v['runtime_min'] ?> min</span>
      <?php endif; ?>
      <span class="shrink-0 rounded px-2 py-0.5 text-[11px]
        <?= match ($v['status']) {
              'topic'     => 'bg-black/5 text-muted',
              'scripted'  => 'bg-cream text-navy',
              'approved'  => 'bg-gold/30 text-navy',
              'recorded', 'edited' => 'bg-navy/10 text-navy',
              'published' => 'bg-navy text-cream',
              default     => 'bg-black/5 text-muted line-through',
            } ?>"><?= h(Video::STATUSES[$v['status']] ?? $v['status']) ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
