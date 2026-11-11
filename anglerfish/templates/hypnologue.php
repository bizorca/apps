<div>
  <h1 class="text-2xl font-bold text-navy">Hypnologue</h1>
  <div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
  <p class="mt-2 max-w-3xl text-sm text-muted">
    <?= h($pub['tagline'] ?? '') ?> Posts are written in Claude Code and filed with
    <code>worker/hypno_push.py</code>; the queue and drafts live in
    <code>~/Dropbox/Hypnotherapy/Hypnologue/</code>.
    <a class="underline" href="<?= h($pub['substack_url'] ?? '') ?>">Substack</a>
  </p>
</div>

<?php if (!$rows): ?>
  <p class="mt-10 text-sm text-muted">No Hypnologue posts filed yet.</p>
<?php else: ?>
<div class="mt-6 divide-y divide-black/5 rounded border border-black/10 bg-white">
<?php foreach ($rows as $r): ?>
  <a href="<?= url('/compose/' . (int) $r['id']) ?>"
     class="flex items-baseline gap-3 px-4 py-2.5 hover:bg-black/[.02]">
    <span class="w-24 shrink-0 font-mono text-[11px] text-muted"><?= h(substr((string) $r['created_at'], 0, 10)) ?></span>
    <span class="flex-1">
      <span class="text-sm font-bold text-navy"><?= h($r['title'] ?: '(untitled)') ?></span>
      <?php if ($r['subtitle']): ?><span class="block text-xs text-muted"><?= h($r['subtitle']) ?></span><?php endif; ?>
    </span>
    <span class="text-[10px] text-muted"><?= number_format((int) $r['words']) ?> words</span>
    <span class="text-[10px] uppercase <?= $r['status'] === 'done' ? 'text-navy' : 'text-gold' ?>"><?= h($r['status']) ?></span>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>
