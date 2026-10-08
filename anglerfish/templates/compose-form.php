<?php use Anglerfish\Models\Composition; ?>
<h1 class="text-2xl font-bold text-navy">Compose</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>

<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
<form method="post" action="<?= url('/compose') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="subject_type" value="<?= h($subject_type) ?>">
  <input type="hidden" name="subject_id" value="<?= (int) $subject_id ?>">

  <?php if ($subject): ?>
    <div class="rounded border border-black/10 bg-white p-3">
      <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Source</p>
      <p class="mt-1 font-bold text-navy"><?= h($subject['title']) ?></p>
      <?php if (!empty($subject['book_title'])): ?>
        <p class="text-xs text-muted">from <?= h($subject['book_title']) ?></p>
      <?php endif; ?>
      <?php if (!empty($subject['verbatim'])): ?>
        <p class="mt-1 text-[11px] text-gold">
          Verbatim from a copyrighted source — the prompt tells the model to
          transform it, not reproduce it.
        </p>
      <?php endif; ?>
      <p class="mt-2 text-[11px]">
        <a class="text-navy underline hover:text-gold" href="<?= url('/compose') ?>">Change source</a>
      </p>
    </div>

    <label class="mt-3 flex items-start gap-2 rounded border border-black/10 bg-white p-3 text-sm">
      <input type="checkbox" name="expand" value="1" checked class="mt-0.5">
      <span>
        <span class="font-bold text-navy">Pull supporting detail from the library</span>
        <span class="block text-xs text-muted">
          Searches all press-scope books — the other brain's take on the same topic,
          plus any scanned book that covers it — and attaches the closest passages.
          The composer uses what fits and ignores the rest.
        </span>
      </span>
    </label>
  <?php else: ?>
    <?php /* No subject yet. The picker is the doorway that used to be missing. */ ?>
    <div class="rounded border border-black/10 bg-white p-3">
      <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Source</p>
      <p class="mt-1 text-sm text-muted">
        Writing freeform from your angle alone. Or pick something to write from:
      </p>

      <div class="mt-2 flex flex-wrap gap-1">
        <?php foreach (Composition::PICK_TYPES as $k => $v): ?>
          <a href="<?= url('/compose?pick_type=' . urlencode($k)
                    . ($pick !== '' ? '&pick=' . urlencode($pick) : '')) ?>"
             class="rounded px-2 py-1 text-[11px] font-bold uppercase tracking-wide
                    <?= $k === $pickType ? 'bg-navy text-cream' : 'bg-cream text-navy hover:bg-black/5' ?>">
            <?= h($v['label']) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="mt-2 flex gap-2">
        <input form="pickform" type="search" name="pick" value="<?= h($pick) ?>"
               placeholder="Filter by title&hellip;"
               class="w-full rounded border border-black/15 px-2 py-1.5 text-sm">
        <button form="pickform"
                class="rounded border border-navy px-3 py-1.5 text-xs font-bold text-navy hover:bg-navy hover:text-cream">
          Filter
        </button>
      </div>

      <?php $rows = $candidates['rows']; $total = (int) $candidates['total']; ?>
      <?php if ($rows): ?>
        <p class="mt-2 text-[11px] text-muted">
          <?php if (count($rows) < $total): ?>
            Showing <strong class="text-navy"><?= count($rows) ?></strong> of
            <strong class="text-navy"><?= number_format($total) ?></strong> —
            type above to narrow it down.
          <?php else: ?>
            <strong class="text-navy"><?= number_format($total) ?></strong>
            <?= $pick !== '' ? 'match' . ($total === 1 ? '' : 'es') : 'available' ?>.
          <?php endif; ?>
        </p>
        <div class="mt-1 max-h-72 divide-y divide-black/5 overflow-y-auto rounded border border-black/10">
          <?php foreach ($rows as $c): ?>
            <a href="<?= url('/compose?subject_type=' . urlencode($c['subject'])
                      . '&subject_id=' . $c['id']) ?>"
               class="block px-2 py-1.5 text-xs hover:bg-cream">
              <span class="font-bold text-navy"><?= h($c['title']) ?></span>
              <span class="block text-[11px] text-muted"><?= h($c['meta']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="mt-2 text-xs text-muted">Nothing matches that filter.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">
    Your angle <span class="font-normal normal-case tracking-normal text-muted">— optional</span>
  </label>
  <p class="mb-1 text-xs text-muted">
    What happened to you, what you noticed, why you're writing this now.
    Leave it empty to write from the source alone — nothing personal gets invented,
    so there are no fabricated clients or anecdotes to strip out later.
  </p>
  <textarea name="angle" rows="4"
            class="w-full rounded border border-black/15 p-3 text-sm"></textarea>

  <div class="mt-4 grid gap-3 sm:grid-cols-3">
    <div>
      <label class="block text-[11px] font-bold uppercase tracking-[2px] text-gold">Format</label>
      <select name="format" class="mt-1 w-full rounded border border-black/15 px-2 py-2 text-sm">
        <?php foreach (Composition::FORMATS as $k => $v): ?>
          <option value="<?= h($k) ?>"><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-[11px] font-bold uppercase tracking-[2px] text-gold">Length</label>
      <select name="length" class="mt-1 w-full rounded border border-black/15 px-2 py-2 text-sm">
        <?php foreach (Composition::LENGTHS as $k => $v): ?>
          <option value="<?= h($k) ?>" <?= $k === 'medium' ? 'selected' : '' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-[11px] font-bold uppercase tracking-[2px] text-gold">Audience</label>
      <select name="audience" class="mt-1 w-full rounded border border-black/15 px-2 py-2 text-sm">
        <?php foreach (Composition::AUDIENCES as $k => $v): ?>
          <option value="<?= h($k) ?>"><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">
    One-off steering <span class="font-normal normal-case tracking-normal text-muted">— optional</span>
  </label>
  <textarea name="extra" rows="2" placeholder="Anything specific for this post only."
            class="mt-1 w-full rounded border border-black/15 p-3 text-sm"></textarea>

  <button class="mt-4 rounded bg-gold px-5 py-3 font-bold text-navy hover:brightness-95">
    Generate
  </button>
  <p class="mt-2 text-[11px] text-muted">
    Runs on the server and takes about 30&ndash;50 seconds. The next page waits for it.
  </p>
</form>

<?php if (!$subject): ?>
  <?php /* Forms cannot nest, so the picker's filter lives out here and its
           fields point back at it with form="pickform". */ ?>
  <form id="pickform" method="get" action="<?= AF_BASE ?>/" class="hidden">
    <input type="hidden" name="r" value="/compose">
    <input type="hidden" name="pick_type" value="<?= h($pickType) ?>">
  </form>
<?php endif; ?>

<div>
  <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Recent</p>
  <div class="mt-2 space-y-1">
    <?php foreach ($recent as $r): ?>
      <a href="<?= url('/compose/' . (int) $r['id']) ?>"
         class="block rounded border border-black/10 bg-white px-2 py-1.5 text-xs hover:bg-black/[.02]">
        <span class="text-[10px] uppercase text-muted"><?= h(str_replace('_', ' ', $r['format'])) ?></span>
        <span class="ml-1 <?= $r['status'] === 'done' ? 'text-navy' : 'text-gold' ?>">
          <?= h($r['title'] ?: $r['status']) ?>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
</div>
