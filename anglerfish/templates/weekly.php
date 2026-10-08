<?php use Anglerfish\Models\Composition; use Anglerfish\Models\Weekly; ?>
<h1 class="text-2xl font-bold text-navy">Weekly</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
<p class="mt-2 max-w-3xl text-sm text-muted">
  One shape every week — trap, diagnostic, protocol, Go! — slotted against a
  curriculum module and shipping one free PDF. The six rotating formats are
  still live under <a class="text-navy underline hover:text-gold" href="<?= url('/compose') ?>">Compose</a>;
  this is the format that replaced them.
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

  <?php /* Source material, once the library has been mapped to the modules.
           Empty until that pass runs — an honest blank beats falling back to
           unmapped material, which would look like the mapping was done. */ ?>
  <div class="mt-3 rounded border border-black/10 bg-white p-3">
    <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Source</p>
    <?php if ($sources): ?>
      <p class="mt-1 text-xs text-muted">
        Library material mapped to this module. Pick one, or leave it unselected to
        write freeform from your angle alone.
      </p>
      <div class="mt-2 max-h-64 divide-y divide-black/5 overflow-y-auto rounded border border-black/10">
        <label class="flex cursor-pointer items-start gap-2 px-2 py-1.5 text-xs hover:bg-cream">
          <input type="radio" name="src" value="" checked class="mt-0.5"
                 onchange="document.getElementById('st').value='freeform';document.getElementById('si').value='0'">
          <span class="font-bold text-navy">Freeform — no source</span>
        </label>
        <?php foreach ($sources as $s): ?>
          <label class="flex cursor-pointer items-start gap-2 px-2 py-1.5 text-xs hover:bg-cream">
            <input type="radio" name="src" class="mt-0.5"
                   value="<?= h($s['subject_type']) ?>:<?= (int) $s['subject_id'] ?>"
                   onchange="document.getElementById('st').value='<?= h($s['subject_type']) ?>';document.getElementById('si').value='<?= (int) $s['subject_id'] ?>'">
            <span>
              <span class="font-bold text-navy"><?= h($s['title']) ?></span>
              <span class="block text-[11px] text-muted">
                <?= h($s['meta']) ?>
                <?php if ($s['rationale']): ?> &middot; <?= h($s['rationale']) ?><?php endif; ?>
              </span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="mt-1 text-xs text-muted">
        <?php if ($mapped === 0): ?>
          The library has not been mapped to the twelve modules yet, so there is nothing
          to offer here. Run the mapping pass, or write freeform from your angle below.
        <?php else: ?>
          Nothing in the library is mapped to this module yet. Write freeform from your
          angle below.
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <input type="hidden" name="subject_type" id="st" value="freeform">
    <input type="hidden" name="subject_id"   id="si" value="0">
  </div>

  <label class="mt-3 flex items-start gap-2 rounded border border-black/10 bg-white p-3 text-sm">
    <input type="checkbox" name="expand" value="1" checked class="mt-0.5">
    <span>
      <span class="font-bold text-navy">Pull supporting detail from the library</span>
      <span class="block text-xs text-muted">
        Searches all press-scope books and attaches the closest passages. The composer
        uses what fits and ignores the rest.
      </span>
    </span>
  </label>

  <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">Deliverable</label>
  <p class="mb-1 text-xs text-muted">
    Four containers, reused relentlessly. If the post seems to need a new page design,
    reshape the content to fit one of these instead.
  </p>
  <div class="grid gap-2 sm:grid-cols-2">
    <?php foreach (Weekly::CONTAINERS as $k => $c): ?>
      <label class="flex cursor-pointer items-start gap-2 rounded border border-black/15 bg-white p-2.5 text-xs hover:bg-cream">
        <input type="radio" name="deliverable" value="<?= h($k) ?>"
               <?= $k === 'test' ? 'checked' : '' ?> class="mt-0.5">
        <span>
          <span class="font-bold text-navy"><?= h($c['label']) ?></span>
          <span class="block text-muted"><?= h($c['hint']) ?></span>
        </span>
      </label>
    <?php endforeach; ?>
  </div>

  <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">
    Your angle <span class="font-normal normal-case tracking-normal text-muted">— optional</span>
  </label>
  <p class="mb-1 text-xs text-muted">
    What happened to you, what you noticed, why you're writing this now. The
    failure-first opening lives here — leave it empty and nothing personal gets
    invented.
  </p>
  <textarea name="angle" rows="4" class="w-full rounded border border-black/15 p-3 text-sm"></textarea>

  <div class="mt-4 grid gap-3 sm:grid-cols-2">
    <div>
      <label class="block text-[11px] font-bold uppercase tracking-[2px] text-gold">Audience</label>
      <select name="audience" class="mt-1 w-full rounded border border-black/15 px-2 py-2 text-sm">
        <?php foreach (Composition::AUDIENCES as $k => $v): ?>
          <option value="<?= h($k) ?>"><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-[11px] font-bold uppercase tracking-[2px] text-gold">Length</label>
      <p class="mt-1 rounded border border-black/10 bg-cream px-2 py-2 text-sm text-muted">
        900&ndash;1,400 words — fixed for this format
      </p>
    </div>
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
    Runs on the server, about 30&ndash;50 seconds. Promote it to a draft and it takes
    deliverable number <strong class="text-navy"><?= (int) $nextSeq ?></strong>.
  </p>
</form>

<div>
  <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">The four movements</p>
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
