<?php
$kindLabel = ['thesis'=>'Thesis','big_idea'=>'Big idea','key_concept'=>'Concept','caveat'=>'Caveat'];
?>
<p class="text-xs text-muted">
  <a href="<?= url('/library') ?>" class="hover:text-navy">Library</a> /
  <?= h($book['scope']) ?>
</p>

<div class="mt-3 flex gap-6">
  <img src="<?= url('/media/cover/' . (int) $book['id']) ?>" alt=""
       class="h-48 w-32 flex-none rounded border border-black/10 object-cover shadow-sm">
  <div class="min-w-0">
    <h1 class="text-2xl font-bold text-navy"><?= h($book['title']) ?></h1>
    <?php if ($book['subtitle']): ?>
      <p class="text-muted italic"><?= h($book['subtitle']) ?></p>
    <?php endif; ?>
    <div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
    <p class="mt-2 text-sm">
      <?= h($book['authors'] ?? 'Unknown author') ?><?= $book['year'] ? ' · ' . (int) $book['year'] : '' ?>
      <?= $book['publisher'] ? ' · ' . h($book['publisher']) : '' ?>
    </p>
    <p class="mt-1 text-xs text-muted">
      <?= (int) ($stats['pages'] ?? 0) ?> pages
      · <?= (int) ($stats['ocr_pages'] ?? 0) ?> OCR
      · avg <?= (int) ($stats['avg_chars'] ?? 0) ?> chars
      <?php if ($book['printed_page_offset'] !== null): ?>
        · printed = pdf <?= (int) $book['printed_page_offset'] >= 0 ? '+' : '' ?><?= (int) $book['printed_page_offset'] ?>
      <?php elseif ($book['pagination_note']): ?>
        · <?= h($book['pagination_note']) ?>
      <?php endif; ?>
    </p>
    <?php if ($book['terms']): ?>
      <p class="mt-2 flex flex-wrap gap-1">
        <?php foreach ($book['terms'] as $t): ?>
          <span class="rounded bg-cream px-2 py-0.5 text-[11px] text-navy"><?= h($t['name']) ?></span>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
    <p class="mt-3">
      <?php $tType='book'; $tId=(int) $book['id']; $tMarks=$book['marks'];
            require __DIR__ . '/_triage.php'; ?>
    </p>
    <p class="mt-2 text-[11px] text-muted">
      Extraction:
      <?php if (!$passes): ?>none yet<?php else: ?>
        <?php foreach ($passes as $p): ?>
          <span class="mr-2"><?= h($p['pass']) ?> (<?= (int) $p['rows_imported'] ?>)</span>
        <?php endforeach; ?>
      <?php endif; ?>
    </p>
  </div>
</div>

<?php require __DIR__ . '/_extract.php'; ?>

<p class="mt-6 flex flex-wrap gap-2">
  <a class="rounded border border-navy px-3 py-2 text-xs font-bold uppercase tracking-wide text-navy hover:bg-navy hover:text-cream"
     href="<?= url('/library/' . rawurlencode($book['slug']) . '/summary') ?>">
    Summary &amp; audiobook script
  </a>
  <a class="rounded border border-navy px-3 py-2 text-xs font-bold uppercase tracking-wide text-navy hover:bg-navy hover:text-cream"
     href="<?= url('/library/' . rawurlencode($book['slug']) . '/images') ?>">
    Chapter images
  </a>
</p>

<?php if ($artifacts): ?>
<details class="mt-8">
<summary class="cursor-pointer text-[11px] font-bold uppercase tracking-[2px] text-gold">
  Artifacts <span class="ml-1 font-normal text-muted"><?= count($artifacts) ?></span>
</summary>
<div class="mt-3 space-y-2">
<?php foreach ($artifacts as $a): ?>
  <div class="rounded border border-black/10 bg-white p-3">
    <div class="flex items-baseline gap-3">
      <span class="rounded bg-navy px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-cream">
        <?= h(str_replace('_', ' ', $a['type'])) ?>
      </span>
      <span class="font-bold text-navy"><?= h($a['title']) ?></span>
      <span class="text-xs text-muted"><?= (int) $a['items'] ?> items</span>
      <?php if ((int) $a['verbatim']): ?>
        <span class="text-[10px] text-gold">verbatim</span>
      <?php endif; ?>
      <?php if ($a['review_status'] === 'flagged'): ?>
        <span class="text-[10px] text-red-700">flagged</span>
      <?php endif; ?>
      <span class="ml-auto flex items-baseline gap-3">
        <?php if ($a['page_start']): ?>
          <span class="text-[11px] text-muted">p<?= (int) $a['page_start'] ?></span>
        <?php endif; ?>
        <?php $aType='artifact'; $aId=(int) $a['id']; require __DIR__ . '/_actions.php'; ?>
        <?php $tType='artifact'; $tId=(int) $a['id']; $tMarks=$a['marks'];
              require __DIR__ . '/_triage.php'; ?>
      </span>
    </div>
    <?php if ($a['intro']): ?>
      <p class="mt-1 text-sm text-muted"><?= h($a['intro']) ?></p>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
</details>
<?php endif; ?>

<?php if ($concepts): ?>
<?php
// Concepts lead the page: a concept is what gets written about and what gets an
// infographic, so it outranks both artifacts and chapters. Ordered thesis ->
// big idea -> concept -> caveat rather than by insertion.
$rank = ['thesis' => 0, 'big_idea' => 1, 'key_concept' => 2, 'caveat' => 3];
usort($concepts, fn($a, $b) => [$rank[$a['kind']] ?? 9, (int) $a['ord']]
                           <=> [$rank[$b['kind']] ?? 9, (int) $b['ord']]);
?>
<h2 class="mt-8 text-[11px] font-bold uppercase tracking-[2px] text-gold">
  Concepts <span class="ml-1 font-normal text-muted"><?= count($concepts) ?></span>
</h2>
<div class="mt-3 space-y-2">
<?php foreach ($concepts as $c): ?>
  <div class="rounded border border-black/10 bg-white p-3">
    <div class="flex items-baseline gap-3">
      <span class="text-[10px] uppercase tracking-wide text-muted"><?= h($kindLabel[$c['kind']] ?? $c['kind']) ?></span>
      <span class="font-bold text-navy"><?= h($c['title']) ?></span>
      <span class="ml-auto flex items-baseline gap-3">
        <?php if (!empty($c['page_ref'])): ?>
          <?php /* Scanned books give page numbers; Big Ideas give "§3". Only
                   the former wants a "pp" prefix. */ ?>
          <span class="text-[11px] text-muted"><?=
            ctype_digit($c['page_ref'][0]) ? 'pp ' : '' ?><?= h($c['page_ref']) ?></span>
        <?php elseif ($c['chapters_ref']): ?>
          <span class="text-[11px] text-muted">ch <?= h($c['chapters_ref']) ?></span>
        <?php endif; ?>
        <?php $aType='concept'; $aId=(int) $c['id']; require __DIR__ . '/_actions.php'; ?>
        <?php $tType='concept'; $tId=(int) $c['id']; $tMarks=$c['marks'];
              require __DIR__ . '/_triage.php'; ?>
      </span>
    </div>
    <?php if ($c['body']): ?>
      <p class="mt-1 text-sm leading-relaxed"><?= h($c['body']) ?></p>
    <?php endif; ?>
    <?php if (!empty($links[$c['id']])): ?>
      <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px]">
        <?php foreach ($links[$c['id']] as $l): ?>
          <span>
            <span class="uppercase tracking-wide text-gold"><?= h($l['relation']) ?></span>
            <a class="text-navy underline hover:text-gold"
               href="<?= url('/library/' . rawurlencode($l['book_slug'])) ?>#c<?= (int) $l['id'] ?>"
               title="<?= h($l['note'] ?? '') ?>"><?= h($l['title']) ?></a>
            <span class="text-muted">&middot; <?= h($l['book_title']) ?></span>
          </span>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$proposed = array_filter($chapters, fn($c) => $c['status'] === 'proposed');
if ($proposed): ?>
<h2 class="mt-10 text-[11px] font-bold uppercase tracking-[2px] text-gold">
  Chapters &mdash; proposed, needs confirming
</h2>
<p class="mt-1 text-sm text-muted">
  Every extraction pass inherits these ranges, so check them before confirming.
  Edit anything wrong, tick Drop on rows that are not chapters, then confirm.
</p>
<form method="post" action="<?= url('/library/' . rawurlencode($book['slug']) . '/chapters') ?>"
      class="mt-3">
  <?= csrf_field() ?>
  <div class="overflow-x-auto rounded border border-black/10 bg-white">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-black/10 text-left text-[10px] uppercase tracking-[1.5px] text-muted">
          <th class="p-2 w-16">Label</th><th class="p-2">Title</th>
          <th class="p-2 w-20">From</th><th class="p-2 w-20">To</th><th class="p-2 w-14">Drop</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($proposed as $ch): ?>
        <tr class="border-b border-black/5">
          <td class="p-1"><input name="ch[<?= (int) $ch['id'] ?>][number_label]"
              value="<?= h($ch['number_label'] ?? '') ?>"
              class="w-full rounded border border-black/15 px-1.5 py-1 text-xs"></td>
          <td class="p-1"><input name="ch[<?= (int) $ch['id'] ?>][title]"
              value="<?= h($ch['title']) ?>"
              class="w-full rounded border border-black/15 px-1.5 py-1 text-xs"></td>
          <td class="p-1"><input name="ch[<?= (int) $ch['id'] ?>][pdf_page_start]" type="number"
              value="<?= (int) $ch['pdf_page_start'] ?>"
              class="w-full rounded border border-black/15 px-1.5 py-1 text-xs"></td>
          <td class="p-1"><input name="ch[<?= (int) $ch['id'] ?>][pdf_page_end]" type="number"
              value="<?= (int) $ch['pdf_page_end'] ?>"
              class="w-full rounded border border-black/15 px-1.5 py-1 text-xs"></td>
          <td class="p-1 text-center">
            <input type="checkbox" name="ch[<?= (int) $ch['id'] ?>][delete]" value="1"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-3 flex gap-2">
    <button class="rounded bg-navy px-4 py-2 text-sm text-cream hover:bg-navy2">
      Confirm <?= count($proposed) ?> chapters
    </button>
    <button name="action" value="redetect"
            class="rounded border border-black/20 px-4 py-2 text-sm hover:bg-black/5">
      Discard and re-detect
    </button>
  </div>
</form>
<?php endif; ?>

<?php $chapters = array_filter($chapters, fn($c) => $c['status'] !== 'proposed'); ?>
<?php if ($chapters): ?>
<?php /* Chapters are navigation and page anchors now, not the primary structure —
         concepts are what get written about. Collapsed by default. */ ?>
<details class="mt-8">
<summary class="cursor-pointer text-[11px] font-bold uppercase tracking-[2px] text-gold">
  Chapters <span class="ml-1 font-normal text-muted"><?= count($chapters) ?></span>
</summary>
<div class="mt-3 space-y-1">
<?php foreach ($chapters as $ch): ?>
  <div class="flex gap-3 border-b border-black/5 py-2 text-sm">
    <span class="w-8 flex-none text-muted"><?= h($ch['number_label'] ?? $ch['ord']) ?></span>
    <span class="flex-1">
      <span class="font-bold text-navy"><?= h($ch['title']) ?></span>
      <?php if ($ch['core_idea']): ?>
        <span class="block text-xs text-muted"><?= h($ch['core_idea']) ?></span>
      <?php endif; ?>
    </span>
    <?php if ($ch['pdf_page_start']): ?>
      <span class="flex-none text-xs text-muted">
        pp <?= (int) $ch['pdf_page_start'] ?>&ndash;<?= (int) $ch['pdf_page_end'] ?>
      </span>
    <?php endif; ?>
    <span class="flex flex-none items-baseline gap-3">
      <?php $aType='chapter'; $aId=(int) $ch['id']; require __DIR__ . '/_actions.php'; ?>
    </span>
  </div>
<?php endforeach; ?>
</div>
</details>
<?php endif; ?>

<?php if (!$artifacts && !$concepts && !$chapters && !$proposed): ?>
  <p class="mt-10 rounded border-l-4 border-gold bg-cream p-4 text-sm">
    <?php if ((int) ($stats['pages'] ?? 0) === 0): ?>
      No text yet. Queue an ingest for this book and run the worker.
    <?php else: ?>
      No chapter map yet &mdash; run <code>angler.py --types detect_chapters</code>,
      confirm the proposal, then the extraction passes can run.
    <?php endif; ?>
  </p>
<?php endif; ?>
