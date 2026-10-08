<?php $slug = rawurlencode((string) $book['slug']); ?>
<p class="text-[11px]">
  <a class="text-navy underline hover:text-gold"
     href="<?= url('/library/' . $slug) ?>">&larr; <?= h($book['title']) ?></a>
</p>

<h1 class="mt-1 text-2xl font-bold text-navy">Summary</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>

<div class="mt-4 flex flex-wrap items-baseline gap-x-4 gap-y-1 text-xs text-muted">
  <span><strong class="text-navy"><?= number_format($o['words']) ?></strong> words</span>
  <span><strong class="text-navy"><?= $o['minutes'] ?></strong> min read</span>
  <?php foreach (['thesis' => 'thesis', 'big_idea' => 'big ideas',
                  'key_concept' => 'concepts', 'caveat' => 'caveats'] as $k => $label): ?>
    <?php if (!empty($o['counts'][$k])): ?>
      <span><strong class="text-navy"><?= (int) $o['counts'][$k] ?></strong> <?= h($label) ?></span>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($o['chapters']): ?>
    <span><strong class="text-navy"><?= (int) $o['chapters'] ?></strong> chapters</span>
  <?php endif; ?>
  <?php if ($o['artifacts']): ?>
    <span><strong class="text-navy"><?= (int) $o['artifacts'] ?></strong> tools</span>
  <?php endif; ?>
</div>

<?php if ($o['verbatim']): ?>
  <p class="mt-3 rounded border border-gold/40 bg-gold/10 p-3 text-xs">
    <strong class="text-navy"><?= (int) $o['verbatim'] ?> verbatim
    <?= $o['verbatim'] === 1 ? 'tool is' : 'tools are' ?> included.</strong>
    Those are transcribed word-for-word from a copyrighted book. Fine as your own
    reference; not for redistribution. The audiobook script is told to describe
    them rather than read them out.
  </p>
<?php endif; ?>

<div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">

<div>
  <div class="flex items-baseline gap-3">
    <h2 class="text-[11px] font-bold uppercase tracking-[2px] text-gold">The document</h2>
    <a class="text-[11px] font-bold uppercase tracking-wide text-navy hover:text-gold"
       href="<?= url('/library/' . $slug . '/summary.md') ?>">Download .md</a>
    <button type="button" class="text-[11px] font-bold uppercase tracking-wide text-navy hover:text-gold"
            onclick="navigator.clipboard.writeText(document.getElementById('outline').textContent)
                     .then(()=>this.textContent='Copied')">Copy</button>
  </div>
  <pre id="outline" class="mt-2 max-h-[32rem] overflow-y-auto whitespace-pre-wrap rounded
       border border-black/10 bg-white p-4 font-mono text-[11px] leading-relaxed"><?= h($o['markdown']) ?></pre>
</div>

<div>
  <h2 class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Audiobook script</h2>

  <?php if ($script && $script['status'] === 'done'): ?>
    <div class="mt-2 rounded border border-black/10 bg-white p-3">
      <p class="font-bold text-navy"><?= h($script['title']) ?></p>
      <p class="mt-1 text-xs text-muted">
        <?= number_format((int) $script['word_count']) ?> words &middot;
        <?= gmdate('i:s', (int) $script['est_seconds']) ?> narrated &middot;
        <?= h($script['model']) ?>
      </p>
      <?php if ($stale): ?>
        <p class="mt-2 text-[11px] text-gold">
          The concepts changed since this was generated. Re-run it to match.
        </p>
      <?php endif; ?>
      <p class="mt-2 flex gap-3 text-[11px]">
        <a class="font-bold uppercase tracking-wide text-navy hover:text-gold"
           href="<?= url('/library/' . $slug . '/script.txt') ?>">Download .txt</a>
        <button type="button" class="font-bold uppercase tracking-wide text-navy hover:text-gold"
                onclick="navigator.clipboard.writeText(document.getElementById('script').textContent)
                         .then(()=>this.textContent='Copied')">Copy</button>
      </p>
    </div>
    <pre id="script" class="mt-2 max-h-96 overflow-y-auto whitespace-pre-wrap rounded
         border border-black/10 bg-white p-3 text-xs leading-relaxed"><?= h($script['body']) ?></pre>

  <?php elseif ($script && in_array($script['status'], ['queued', 'running'], true)): ?>
    <p class="mt-2 rounded border border-black/10 bg-white p-3 text-sm text-muted"
       x-data x-init="setTimeout(() => location.reload(), 8000)">
      <?= h($script['status']) ?> — this page reloads on its own.
    </p>

  <?php elseif ($script && $script['status'] === 'failed'): ?>
    <p class="mt-2 rounded border border-red-700/30 bg-red-50 p-3 text-xs text-red-800">
      <?= h(mb_substr((string) $script['error'], 0, 300)) ?>
    </p>
  <?php endif; ?>

  <?php if (!$script || !in_array($script['status'], ['queued', 'running'], true)): ?>
    <form method="post" action="<?= url('/library/' . $slug . '/script') ?>" class="mt-3">
      <?= csrf_field() ?>
      <label class="block text-[11px] font-bold uppercase tracking-[2px] text-gold">Runtime</label>
      <select name="minutes" class="mt-1 w-full rounded border border-black/15 px-2 py-2 text-sm">
        <?php foreach ([5, 8, 12, 20, 30] as $m): ?>
          <option value="<?= $m ?>" <?= $m === 12 ? 'selected' : '' ?>>
            about <?= $m ?> minutes (~<?= number_format($m * 150) ?> words)
          </option>
        <?php endforeach; ?>
      </select>
      <button class="mt-2 w-full rounded bg-gold px-4 py-3 font-bold text-navy hover:brightness-95">
        <?= $script ? 'Regenerate script' : 'Make audiobook script' ?>
      </button>
      <p class="mt-2 text-[11px] text-muted">
        Rewrites the document above into spoken English — no headings, no bullets,
        figures read out. Runs on the server, about a minute.
      </p>
    </form>
  <?php endif; ?>
</div>
</div>
