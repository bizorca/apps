<h1 class="text-2xl font-bold text-navy">Write queue</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
<p class="mt-3 text-sm text-muted">
  Everything marked <strong>Write About This</strong>. Clearing an item means
  publishing it, so this list is meant to go down.
</p>

<?php if (!$rows): ?>
  <p class="mt-10 rounded border-l-4 border-gold bg-cream p-4 text-sm">
    Empty. Mark ideas with <kbd class="rounded border px-1">W</kbd> anywhere in the library.
  </p>
<?php else: ?>
<div class="mt-6 space-y-2">
<?php foreach ($rows as $r): ?>
  <div class="rounded border border-black/10 bg-white p-3">
    <div class="flex items-baseline gap-3">
      <span class="rounded bg-cream px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-navy">
        <?= h(str_replace('_', ' ', $r['concept_kind'] ?? $r['artifact_type'] ?? $r['post_format'] ?? $r['subject_type'])) ?>
      </span>
      <span class="font-bold text-navy"><?= h($r['title'] ?? '(untitled)') ?></span>
      <?php if ($r['book_slug']): ?>
        <a class="text-[11px] text-muted hover:text-navy"
           href="<?= url('/library/' . rawurlencode($r['book_slug'])) ?>"><?= h($r['book_title']) ?></a>
      <?php endif; ?>
      <span class="ml-auto">
        <?php $tType = $r['subject_type']; $tId = (int) $r['subject_id'];
              $tMarks = ['write_about' => true];
              require __DIR__ . '/_triage.php'; ?>
      </span>
    </div>
    <?php if ($r['body']): ?>
      <p class="mt-1 text-sm text-muted"><?= h(mb_substr($r['body'], 0, 240)) ?></p>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
