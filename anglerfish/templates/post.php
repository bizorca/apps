<?php $marks = array_column($post['marks'] ?? [], 'mark'); ?>
<p class="text-xs text-muted">
  <a href="<?= url('/posts') ?>" class="hover:text-navy">Posts</a> /
  <?= h(str_replace('_', ' ', $post['format'])) ?>
  <?= $post['number'] ? ' · #' . (int) $post['number'] : '' ?>
</p>

<?php if ($post['warnings']): ?>
  <div class="mt-3 rounded border-l-4 border-red-600 bg-red-50 p-3 text-sm">
    <strong>Do not paste yet.</strong>
    <?= h(implode('; ', $post['warnings'])) ?>. Fix it in the body below first.
  </div>
<?php endif; ?>

<div x-data="handoff()" class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">

  <form method="post" action="<?= url('/posts/' . rawurlencode($post['slug'])) ?>">
    <?= csrf_field() ?>

    <label class="block text-[11px] font-bold uppercase tracking-[2px] text-gold">Title</label>
    <div class="mt-1 flex gap-2">
      <input name="title" id="f-title" value="<?= h($post['title']) ?>"
             class="w-full rounded border border-black/15 px-3 py-2 font-bold text-navy">
      <button type="button" @click="copyText('f-title', $event)"
              class="shrink-0 rounded bg-navy px-3 text-sm text-cream hover:bg-navy2">Copy</button>
    </div>

    <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">Subtitle</label>
    <div class="mt-1 flex gap-2">
      <input name="subtitle" id="f-subtitle" value="<?= h($post['subtitle'] ?? '') ?>"
             placeholder="<?= h($post['suggested']) ?>"
             class="w-full rounded border border-black/15 px-3 py-2">
      <button type="button" @click="copyText('f-subtitle', $event)"
              class="shrink-0 rounded bg-navy px-3 text-sm text-cream hover:bg-navy2">Copy</button>
    </div>

    <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">
      Publication <span class="font-normal normal-case tracking-normal text-muted">— sets the footer credit on this post's images</span>
    </label>
    <?php $att = $post['attribution'] ?? \Anglerfish\Services\ImagePrompt::DEFAULT_ATTRIBUTION; ?>
    <select name="attribution" class="mt-1 rounded border border-black/15 px-2 py-2 text-sm">
      <?php foreach (\Anglerfish\Services\ImagePrompt::ATTRIBUTIONS as $k => $a): ?>
        <option value="<?= h($k) ?>" <?= $k === $att ? 'selected' : '' ?>><?= h($a['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="mt-1 text-[11px] text-muted">
      Images generated for this post will read
      &ldquo;<?= h(\Anglerfish\Services\ImagePrompt::rightsLine($att)) ?>&rdquo;
    </p>

    <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">
      Body <span class="font-normal normal-case tracking-normal text-muted">— plain text; edits change the preview</span>
    </label>
    <textarea name="body" rows="24"
              class="mt-1 w-full rounded border border-black/15 p-3 font-mono text-xs leading-relaxed"><?= h($post['body']) ?></textarea>

    <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">
      Your angle <span class="font-normal normal-case tracking-normal text-muted">— what happened to you, why now</span>
    </label>
    <textarea name="angle" rows="3" placeholder="Only you can write this part."
              class="mt-1 w-full rounded border border-black/15 p-3 text-sm"><?= h($post['angle'] ?? '') ?></textarea>

    <button class="mt-4 rounded bg-navy px-4 py-2 text-sm text-cream hover:bg-navy2">Save</button>
  </form>

  <div>
    <div class="rounded border border-black/10 bg-white p-4">
      <div class="flex items-baseline justify-between">
        <span class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Paste into Substack</span>
        <span class="text-[11px] text-muted"><?= h($post['status']) ?></span>
      </div>

      <button type="button" @click="copyBody($event)"
              class="mt-3 w-full rounded bg-gold px-4 py-3 font-bold text-navy hover:brightness-95">
        <span x-text="bodyLabel">Copy body (formatted)</span>
      </button>
      <p class="mt-2 text-[11px] text-muted">
        Copies rich HTML and plain text together, so Substack keeps headings,
        lists and emphasis instead of pasting a wall of text.
      </p>

      <div id="rich-body" class="sr-only" aria-hidden="true"><?= $post['html'] ?></div>

      <details class="mt-4">
        <summary class="cursor-pointer text-xs text-muted hover:text-navy">Preview</summary>
        <div class="prose-preview mt-2 max-h-80 overflow-y-auto rounded bg-paper p-3 text-sm">
          <?= $post['html'] ?>
        </div>
      </details>
    </div>

    <div class="mt-4 rounded border border-black/10 bg-white p-4">
      <span class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Mark published</span>
      <p class="mt-1 text-[11px] text-muted">
        No API means no confirmation from Substack, so this is the only thing
        keeping the queue honest.
      </p>
      <form method="post" action="<?= url('/posts/' . rawurlencode($post['slug'])) ?>" class="mt-3 space-y-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="publish">
        <input name="substack_url" value="<?= h($post['substack_url'] ?? '') ?>"
               placeholder="Paste the Substack URL (optional)"
               class="w-full rounded border border-black/15 px-2 py-1.5 text-xs">
        <input name="published_at" type="date"
               value="<?= h($post['published_at'] ? substr($post['published_at'], 0, 10) : date('Y-m-d')) ?>"
               class="w-full rounded border border-black/15 px-2 py-1.5 text-xs">
        <button class="w-full rounded border border-navy px-3 py-2 text-sm text-navy hover:bg-navy hover:text-cream">
          <?= $post['status'] === 'published' ? 'Update record' : 'Mark published' ?>
        </button>
      </form>
    </div>

    <?php /* Header image. The post page is where Substack actually gets fed,
             so the image belongs in this workflow, not on a separate screen. */ ?>
    <div class="mt-6 border-t border-black/10 pt-4">
      <div class="flex items-baseline gap-3">
        <h2 class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Header image</h2>
        <a class="text-[11px] font-bold uppercase tracking-wide text-navy hover:text-gold"
           href="<?= url('/generate?subject_type=post&subject_id=' . (int) $post['id']) ?>">
          <?= $images ? 'New version' : 'Generate' ?>
        </a>
      </div>

      <?php if (!$images): ?>
        <p class="mt-2 text-xs text-muted">
          None yet. Generating builds a prompt from this post's title and body,
          shows it for editing, and costs about $0.13 a shot.
        </p>
      <?php else: ?>
        <?php $live = $images[0]; ?>
        <?php if ($live['status'] === 'ready'): ?>
          <a href="<?= url('/media/asset/' . (int) $live['id']) ?>" target="_blank">
            <img src="<?= url('/media/asset/' . (int) $live['id'] . '?w=substack') ?>"
                 alt="" class="mt-2 w-full rounded border border-black/10">
          </a>
          <p class="mt-2 flex flex-wrap items-baseline gap-3 text-[11px]">
            <a class="font-bold uppercase tracking-wide text-navy hover:text-gold"
               href="<?= url('/media/asset/' . (int) $live['id'] . '?w=substack&download=1') ?>">
              Download for Substack
            </a>
            <span class="text-muted">1456px wide &middot; upload this one</span>
            <a class="text-muted underline hover:text-gold"
               href="<?= url('/media/asset/' . (int) $live['id'] . '?download=1') ?>">full size</a>
            <span class="text-muted">v<?= (int) $live['version'] ?> &middot; <?= h((string) $live['model']) ?></span>
          </p>
        <?php else: ?>
          <p class="mt-2 text-xs text-muted"><?= h($live['status']) ?> — reload shortly.</p>
        <?php endif; ?>

        <?php if (count($images) > 1): ?>
          <div class="mt-2 flex flex-wrap gap-2">
            <?php foreach (array_slice($images, 1) as $old): ?>
              <?php if ($old['status'] !== 'ready') { continue; } ?>
              <a href="<?= url('/media/asset/' . (int) $old['id']) ?>" target="_blank"
                 title="v<?= (int) $old['version'] ?>">
                <img src="<?= url('/media/asset/' . (int) $old['id'] . '?w=substack') ?>"
                     alt="" class="h-14 rounded border border-black/10 opacity-70 hover:opacity-100">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="mt-4 flex justify-end">
      <?php $tType='post'; $tId=(int) $post['id'];
            $tMarks = array_fill_keys($marks, true);
            require __DIR__ . '/_triage.php'; ?>
    </div>
  </div>
</div>

<style>
.sr-only { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }
.prose-preview h2 { font-size:1.05rem; font-weight:700; color:#1B3A5C; margin:.9rem 0 .3rem; }
.prose-preview h3 { font-size:.95rem; font-weight:700; margin:.7rem 0 .25rem; }
.prose-preview p  { margin:.5rem 0; }
.prose-preview ul { list-style:disc;   margin:.5rem 0 .5rem 1.2rem; }
.prose-preview ol { list-style:decimal; margin:.5rem 0 .5rem 1.2rem; }
.prose-preview li { margin:.2rem 0; }
</style>
