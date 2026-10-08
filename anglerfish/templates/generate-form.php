<?php if (!empty($back)): ?>
  <p class="text-[11px]"><a class="text-navy underline hover:text-gold"
     href="<?= url($back) ?>">&larr; back to the draft</a></p>
<?php endif; ?>
<h1 class="text-2xl font-bold text-navy">Generate image</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>

<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
<form method="post" action="<?= url('/generate') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="subject_type" value="<?= h($subject_type) ?>">
  <input type="hidden" name="subject_id" value="<?= (int) $subject_id ?>">

  <div class="rounded border border-black/10 bg-white p-3">
    <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Illustrating</p>
    <p class="mt-1 font-bold text-navy"><?= h($subject['title']) ?></p>
    <?php if (!empty($subject['book_title'])): ?>
      <p class="text-xs text-muted">from <?= h($subject['book_title']) ?></p>
    <?php endif; ?>
  </div>

  <div class="mt-4 rounded border border-gold/40 bg-gold/10 p-3">
    <div class="flex flex-wrap items-baseline gap-3">
      <span class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Art direction</span>
      <a class="text-[11px] font-bold uppercase tracking-wide text-navy hover:text-gold"
         href="<?= url('/generate?subject_type=' . urlencode($subject_type)
                   . '&subject_id=' . (int) $subject_id . '&direct=1') ?>">
        <?= $directed ? 'Direct again' : 'Art-direct with Claude' ?>
      </a>
      <span class="text-[11px] text-muted">~1&cent;, about 20s</span>
    </div>
    <?php if ($directed): ?>
      <p class="mt-2 text-xs"><span class="font-bold text-navy">Metaphor:</span>
        <?= h((string) $directed['metaphor']) ?></p>
      <p class="mt-1 text-xs text-muted"><?= h((string) $directed['composition']) ?></p>
      <p class="mt-1 text-[11px] text-muted">
        <?= count((array) $directed['elements']) ?> elements &middot;
        <?= h(implode(', ', (array) $directed['accents'])) ?> &middot;
        <?= h((string) ($directed['_model'] ?? '')) ?>
      </p>
    <?php else: ?>
      <p class="mt-1 text-xs text-muted">
        The template below gives every concept the same composition. This reads the
        concept and picks a metaphor, a layout and the exact wording for this one.
      </p>
    <?php endif; ?>
  </div>

  <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">Structure<?= $directed ? ' — overridden by art direction' : '' ?></label>
  <p class="mb-1 text-xs text-muted">
    What shape the picture takes. This is the setting that decides whether you get a
    real diagram or a decorated text block — changing it rewrites the prompt below.
  </p>
  <div class="flex flex-wrap gap-1">
    <?php foreach (\Anglerfish\Services\ImagePrompt::STRUCTURES as $k => $v): ?>
      <a href="<?= url('/generate?subject_type=' . urlencode($subject_type)
                . '&subject_id=' . (int) $subject_id . '&structure=' . urlencode($k)) ?>"
         title="<?= h($v['label']) ?>"
         class="rounded px-2 py-1 text-[11px] font-bold uppercase tracking-wide
                <?= $k === $structure ? 'bg-navy text-cream' : 'bg-cream text-navy hover:bg-black/5' ?>">
        <?= h(explode(' — ', $v['label'])[0]) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="mt-1 text-[11px] text-muted">
    <?= h(\Anglerfish\Services\ImagePrompt::STRUCTURES[$structure]['label']) ?>
  </p>

  <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">Footer credit</label>
  <p class="mb-1 text-xs text-muted">
    Whose copyright is printed along the bottom edge. This app serves more than one
    publication now, and the footer is the last place anyone looks &mdash; changing it
    rewrites the prompt below.
  </p>
  <select class="rounded border border-black/15 px-2 py-1.5 text-sm"
          onchange="location.href=this.value">
    <?php foreach (\Anglerfish\Services\ImagePrompt::ATTRIBUTIONS as $k => $a): ?>
      <option value="<?= h(url('/generate?subject_type=' . urlencode($subject_type)
                          . '&subject_id=' . (int) $subject_id
                          . '&structure=' . urlencode($structure)
                          . '&attribution=' . urlencode($k))) ?>"
              <?= $k === $attribution ? 'selected' : '' ?>>
        <?= h($a['label']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <p class="mt-1 text-[11px] text-muted">
    Prints as &ldquo;<?= h(\Anglerfish\Services\ImagePrompt::rightsLine($attribution)) ?>&rdquo;
  </p>

  <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">Prompt</label>
  <p class="mb-1 text-xs text-muted">
    Pre-filled from the source and the brand palette. Edit it before firing &mdash;
    each attempt costs about $0.13, and the text you send is stored on the asset.
  </p>
  <textarea name="prompt" rows="16" required
            class="w-full rounded border border-black/15 p-3 font-mono text-xs"><?= h($prompt) ?></textarea>

  <div class="mt-3 flex items-center gap-3">
    <label class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Aspect</label>
    <select name="aspect_ratio" class="rounded border border-black/15 px-2 py-1.5 text-sm">
      <option value="16:9">16:9 &mdash; Substack header</option>
      <option value="4:5">4:5 &mdash; portrait</option>
      <option value="1:1">1:1 &mdash; square</option>
    </select>
    <button class="ml-auto rounded bg-gold px-5 py-3 font-bold text-navy hover:brightness-95">
      Generate &mdash; $0.13
    </button>
  </div>
  <p class="mt-2 text-[11px] text-muted">
    Renders at 2K on Nano Banana Pro, then downscales to 1456px for Substack.
    PNG only.
  </p>
</form>

<div>
  <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Versions</p>
  <?php if ($existing): ?>
    <div class="mt-2 space-y-2">
      <?php foreach ($existing as $a): ?>
        <div class="rounded border border-black/10 bg-white p-2">
          <p class="text-[11px] text-muted">
            v<?= (int) $a['version'] ?> &middot; <?= h($a['status']) ?>
          </p>
          <?php if ($a['status'] === 'ready'): ?>
            <a href="<?= url('/media/asset/' . (int) $a['id']) ?>" target="_blank">
              <img src="<?= url('/media/asset/' . (int) $a['id']) ?>" alt=""
                   class="mt-1 w-full rounded border border-black/10">
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="mt-2 text-xs text-muted">Nothing generated for this yet.</p>
  <?php endif; ?>
</div>
</div>
