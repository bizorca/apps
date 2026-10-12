<?php use Anglerfish\Models\DailyIdea; ?>
<h1 class="text-2xl font-bold text-navy">Daily Idea</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
<p class="mt-2 max-w-3xl text-sm text-muted">
  One random Hypnosis Brain item, emailed to <?= h(DailyIdea::TO) ?> at <?= DailyIdea::SEND_AT ?>am Pacific
  with a short explainer. Every item goes out once before anything repeats, and a week's mornings
  stay on different topics. <?= number_format((int) $counts['sent']) ?> of
  <?= number_format((int) $counts['pool']) ?> sent. Refresh the pool from the Mac with
  <code>python3 "Hypnosis Brain/export_daily.py" --push</code>.
</p>

<?php if (!$sends): ?>
  <p class="mt-10 text-sm text-muted">Nothing sent yet.</p>
<?php else: ?>
<div class="mt-6 divide-y divide-black/5 rounded border border-black/10 bg-white">
  <?php foreach ($sends as $s): ?>
    <a href="<?= url('/daily/' . (int) $s['idea_id']) ?>" class="flex items-center gap-4 px-4 py-2.5 hover:bg-cream/40">
      <span class="w-24 shrink-0 text-xs text-muted"><?= h($s['send_date']) ?></span>
      <span class="flex-1 text-sm text-navy"><?= h(DailyIdea::niceTitle($s['title'])) ?></span>
      <span class="text-[11px] text-muted"><?= h($s['topic']) ?></span>
      <?php if (!$s['sent']): ?><span class="rounded bg-red-100 px-1.5 text-[10px] text-red-700">not sent</span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
