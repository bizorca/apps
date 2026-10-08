<div class="flex items-end justify-between gap-6">
  <div>
    <h1 class="text-2xl font-bold text-navy">Corpus</h1>
    <div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
    <p class="mt-2 text-xs text-muted">
      <?= number_format((int) ($counts['total'] ?? 0)) ?> indexed files ·
      <?= number_format((int) ($counts['collections'] ?? 0)) ?> collections
    </p>
  </div>
</div>

<form method="get" action="<?= AF_BASE ?>/" class="mt-5 flex flex-wrap gap-2">
    <input type="hidden" name="r" value="/corpus">
  <input name="q" value="<?= h($q) ?>" placeholder="Search titles, authors, collections, summaries…"
         class="min-w-64 flex-1 rounded border border-black/15 px-3 py-2 text-sm">
  <select name="type" class="rounded border border-black/15 px-2 py-2 text-sm">
    <option value="">Any type</option>
    <?php foreach ($types as $t): ?>
      <option value="<?= h($t['content_type']) ?>" <?= $type === $t['content_type'] ? 'selected' : '' ?>>
        <?= h($t['content_type']) ?> (<?= (int) $t['n'] ?>)
      </option>
    <?php endforeach; ?>
  </select>
  <button class="rounded bg-navy px-4 py-2 text-sm text-cream hover:bg-navy2">Search index</button>
</form>

<?php if ($q !== ''): ?>
<form method="post" action="<?= url('/corpus/deep') ?>" class="mt-2">
  <?= csrf_field() ?>
  <input type="hidden" name="q" value="<?= h($q) ?>">
  <input type="hidden" name="brain" value="<?= h($brain) ?>">
  <button class="rounded border border-navy px-3 py-1.5 text-xs text-navy hover:bg-navy hover:text-cream">
    Search inside the files for &ldquo;<?= h($q) ?>&rdquo;
  </button>
  <span class="ml-2 text-[11px] text-muted">
    Runs on the Mac, where the 1.2GB of text lives. Takes a few seconds.
  </span>
</form>
<?php endif; ?>

<?php if ($deep): ?>
<div class="mt-5 rounded border border-black/10 bg-white p-4">
  <div class="flex items-baseline gap-3">
    <span class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Inside the files</span>
    <span class="text-sm">&ldquo;<?= h($deep['query']) ?>&rdquo;</span>
    <span class="ml-auto text-xs text-muted">
      <?php if ($deep['status'] === 'done'): ?>
        <?= number_format((int) $deep['hits']) ?> hits in <?= number_format((int) $deep['files']) ?> files
      <?php else: ?>
        <?= h($deep['status']) ?> — reload in a moment
      <?php endif; ?>
    </span>
  </div>

  <?php if ($deep['status'] === 'done' && $deep['results']): ?>
    <div class="mt-3 space-y-2">
      <?php foreach ($deep['results'] as $r): ?>
        <div class="border-t border-black/5 pt-2">
          <p class="text-xs">
            <span class="mr-1 rounded bg-cream px-1 text-[10px] uppercase text-navy"><?= h($r['brain']) ?></span>
            <span class="font-bold text-navy"><?= h($r['path']) ?></span>
          </p>
          <?php foreach ($r['snippets'] as $sn): ?>
            <form method="post" action="<?= url('/corpus/clip') ?>"
                  class="ml-2 mt-1 flex items-start gap-2">
              <?= csrf_field() ?>
              <input type="hidden" name="brain" value="<?= h($r['brain']) ?>">
              <input type="hidden" name="path" value="<?= h($r['path']) ?>">
              <input type="hidden" name="line_ref" value="L<?= (int) $sn['line'] ?>">
              <input type="hidden" name="query" value="<?= h($deep['query'] ?? '') ?>">
              <input type="hidden" name="q" value="<?= h($q) ?>">
              <input type="hidden" name="deep" value="<?= (int) ($deep['id'] ?? 0) ?>">
              <input type="hidden" name="body" value="<?= h($sn['text']) ?>">
              <p class="flex-1 text-xs text-muted">
                <span class="text-gold">L<?= (int) $sn['line'] ?></span>
                <?= h($sn['text']) ?>
              </p>
              <button class="flex-none text-[10px] font-bold uppercase tracking-wide text-navy hover:text-gold"
                      title="Save this excerpt so you can compose from it">Clip</button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php elseif ($deep['status'] === 'done'): ?>
    <p class="mt-2 text-sm text-muted">No matches in the file bodies.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($results): ?>
<div class="mt-6 divide-y divide-black/5 rounded border border-black/10 bg-white">
  <?php foreach ($results as $r): ?>
    <div class="px-4 py-2.5">
      <div class="flex items-baseline gap-2">
        <span class="rounded bg-cream px-1.5 text-[10px] uppercase text-navy"><?= h($r['brain']) ?></span>
        <span class="text-sm font-bold text-navy"><?= h($r['collection'] ?: $r['path']) ?></span>
        <?php if ($r['author']): ?>
          <span class="text-xs text-muted"><?= h($r['author']) ?></span>
        <?php endif; ?>
        <?php if ($r['content_type']): ?>
          <span class="ml-auto text-[10px] uppercase tracking-wide text-muted"><?= h($r['content_type']) ?></span>
        <?php endif; ?>
      </div>
      <p class="mt-0.5 font-mono text-[11px] text-muted"><?= h($r['path']) ?></p>
      <?php if ($r['summary']): ?>
        <p class="mt-1 text-xs"><?= h(mb_substr($r['summary'], 0, 220)) ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<p class="mt-3 text-xs text-muted"><?= count($results) ?> shown</p>
<?php elseif ($q !== ''): ?>
  <p class="mt-8 text-sm text-muted">
    Nothing in the index. The Dan Kennedy half has no authors or summaries, so
    try the body search above.
  </p>
<?php endif; ?>
