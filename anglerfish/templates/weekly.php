<?php use Anglerfish\Models\Composition; use Anglerfish\Models\Weekly; ?>
<h1 class="text-2xl font-bold text-navy">Weekly</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
<p class="mt-2 max-w-3xl text-sm text-muted">
  Pick a module and generate. Anglerfish chooses the sources from the library mapped to
  that module, pulls supporting detail from the rest of the library, picks the topic and
  the download type, and writes the post the way the brief says: trap, diagnostic,
  psychology, protocol, close. Change the download type on the draft if you want another.
</p>

<?php /* Coverage strip. Four posts per module per year is the target, so the
         gap between modules is the thing worth seeing on arrival. */ ?>
<div class="mt-5 flex flex-wrap gap-1">
  <?php foreach ($modules as $m): $n = $coverage[(int) $m['id']] ?? 0; $on = (int) $m['id'] === $moduleId; ?>
    <a href="<?= url('/weekly?module=' . (int) $m['id']) ?>"
       title="<?= h($m['premise']) ?>"
       class="rounded border px-2 py-1 text-[11px] leading-tight
              <?= $on ? 'border-navy bg-navy text-cream' : 'border-black/10 bg-white text-navy hover:bg-cream' ?>">
      <span class="font-bold"><?= (int) $m['id'] ?>.</span> <?= h($m['title']) ?>
      <span class="ml-1 rounded px-1 <?= $n ? ($on ? 'bg-gold text-navy' : 'bg-cream text-navy') : 'text-muted' ?>"><?= $n ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">

<form method="post" action="<?= url('/weekly') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="module_id" value="<?= $moduleId ?>">

  <?php if ($module): ?>
    <div class="rounded border border-black/10 bg-white p-4">
      <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">
        Module <?= (int) $module['id'] ?> &middot; <?= h(ucfirst($module['cohort'])) ?> cohort
      </p>
      <p class="mt-1 text-lg font-bold text-navy"><?= h($module['title']) ?></p>
      <p class="mt-1 text-sm"><?= h($module['premise']) ?></p>
      <p class="mt-2 text-xs text-muted">
        <span class="font-bold text-navy">Leaves the reader holding:</span>
        <?= h($module['artifact']) ?>
      </p>
      <?php if ((int) $module['stalls'] === 1): ?>
        <p class="mt-2 rounded bg-cream px-2 py-1.5 text-[11px] text-navy">
          <span class="font-bold">Stall point.</span> The reader already knows what to do
          and has not done it. The trap is avoidance; the protocol removes the decision
          rather than explaining the concept again.
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="mt-3 rounded border border-black/10 bg-white p-3 text-xs text-muted">
    <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Sources</p>
    <?php if ($sources): ?>
      Chosen automatically from <?= count($sources) ?> library items mapped to this module
      (up to four, one per book, skipping anything used in the last 30 days), plus
      supporting passages from the whole library. The draft page lists what was used.
    <?php elseif ($mapped === 0): ?>
      The library has not been mapped to the modules yet, so this post will be written
      from the curriculum, the story bank and supporting passages alone.
    <?php else: ?>
      Nothing in the library is mapped to this module yet, so this post will be written
      from the curriculum, the story bank and supporting passages alone.
    <?php endif; ?>
  </div>

  <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">
    One-off steering <span class="font-normal normal-case tracking-normal text-muted">— optional</span>
  </label>
  <textarea name="extra" rows="2" placeholder="Anything specific for this post only."
            class="mt-1 w-full rounded border border-black/15 p-3 text-sm"></textarea>

  <button class="mt-4 rounded bg-gold px-5 py-3 font-bold text-navy hover:brightness-95">
    Generate weekly post
  </button>
  <p class="mt-2 text-[11px] text-muted">
    Runs on the server, about two minutes. Promote it to a draft and it takes
    deliverable number <strong class="text-navy"><?= (int) $nextSeq ?></strong>.
  </p>
</form>

<div>
  <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">The five movements</p>
  <div class="mt-2 space-y-1.5">
    <?php foreach (Weekly::MOVEMENTS as $name => $what): ?>
      <div class="rounded border border-black/10 bg-white px-2.5 py-2">
        <p class="text-xs font-bold text-navy"><?= h($name) ?></p>
        <p class="text-[11px] text-muted"><?= h($what) ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($recent): ?>
    <p class="mt-5 text-[11px] font-bold uppercase tracking-[2px] text-gold">Recent</p>
    <div class="mt-2 space-y-1">
      <?php foreach ($recent as $r): ?>
        <a href="<?= url('/compose/' . (int) $r['id']) ?>"
           class="block rounded border border-black/10 bg-white px-2 py-1.5 text-xs hover:bg-black/[.02]">
          <span class="<?= $r['status'] === 'done' ? 'text-navy' : 'text-gold' ?>">
            <?= h($r['title'] ?: $r['status']) ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($posts): ?>
    <p class="mt-5 text-[11px] font-bold uppercase tracking-[2px] text-gold">
      Published order <span class="font-normal normal-case tracking-normal text-muted">— <?= count($posts) ?></span>
    </p>
    <div class="mt-2 space-y-1">
      <?php foreach ($posts as $p): ?>
        <a href="<?= url('/posts/' . rawurlencode($p['slug'])) ?>"
           class="block rounded border border-black/10 bg-white px-2 py-1.5 text-xs hover:bg-black/[.02]">
          <span class="text-[10px] text-muted">
            M<?= (int) $p['module_id'] ?>
            <?php if ($p['sequence_number']): ?>&middot; #<?= (int) $p['sequence_number'] ?><?php endif; ?>
            <?php if ($p['deliverable']): ?>
              &middot; <?= h(str_replace('_', ' ', $p['deliverable'])) ?>
            <?php endif; ?>
          </span>
          <span class="block font-bold <?= $p['status'] === 'published' ? 'text-navy' : 'text-gold' ?>">
            <?= h($p['title']) ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</div>
