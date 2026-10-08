<?php
/**
 * The exact commands to extract this book, so they don't have to be
 * reconstructed from the README every session.
 *
 * Not a button that runs anything — extraction is manual in Claude Code by
 * design. This is a copy target, and it says so, because a button that looks
 * like it starts a job and doesn't is worse than no button.
 *
 * Expects $book and $passes.
 */
$slug = (string) $book['slug'];
$src  = (string) $book['source_path'];
// The .txt beside the PDF is what the passes read; a born-text source is
// already the .txt.
$txt  = preg_match('/\.pdf$/i', $src) ? preg_replace('/\.pdf$/i', '.txt', $src) : $src;
$done = [];
foreach ($passes as $p) {
    $done[$p['pass']] = $p;
}
$order = ['profile' => '01-book-profile', 'chapters' => '02-chapters',
          'ideas' => '03-big-ideas', 'artifacts' => '04-artifacts', 'links' => '05-links'];
?>
<details class="mt-8" <?= $done ? '' : 'open' ?>>
<summary class="cursor-pointer text-[11px] font-bold uppercase tracking-[2px] text-gold">
  Extract this book
  <span class="ml-1 font-normal text-muted"><?= count($done) ?>/5 passes</span>
</summary>

<div class="mt-3 rounded border border-black/10 bg-white p-4">
  <p class="text-xs text-muted">
    Extraction runs by hand in Claude Code against the subscription — nothing here
    queues a job. Open Claude Code in <code class="text-navy">writer/</code>, then
    work down this list. Pass 3 reads pass 2's output; pass 5 reads both.
  </p>

  <div class="mt-3 space-y-1">
    <?php foreach ($order as $pass => $prompt): ?>
      <?php $has = isset($done[$pass]); ?>
      <div class="flex items-baseline gap-2 text-xs">
        <span class="w-4 flex-none <?= $has ? 'text-gold' : 'text-muted' ?>"><?= $has ? '✓' : '○' ?></span>
        <span class="w-16 flex-none font-bold <?= $has ? 'text-navy' : 'text-muted' ?>"><?= h($pass) ?></span>
        <code class="flex-1 text-[11px] text-muted">extractions/prompts/<?= h($prompt) ?>.md</code>
        <?php if ($has): ?>
          <span class="flex-none text-[11px] text-muted">
            <?= (int) $done[$pass]['rows_imported'] ?> rows &middot;
            <?= h(substr((string) $done[$pass]['imported_at'], 0, 10)) ?>
          </span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php
  $cmd = "Run extraction on this book, following extractions/README.md.\n\n"
       . "book_slug:  {$slug}\n"
       . "source_txt: {$txt}\n\n"
       . "Passes still to do: "
       . implode(', ', array_values(array_diff(array_keys($order), array_keys($done)))) . "\n\n"
       . "Write each to extractions/outbox/{$slug}.<pass>.md, then:\n"
       . "  press/worker/.venv/bin/python press/worker/validate_extraction.py extractions/outbox/\n"
       . "  press/worker/.venv/bin/python press/worker/import_extraction.py";
  ?>
  <label class="mt-4 block text-[11px] font-bold uppercase tracking-[2px] text-gold">
    Paste this into Claude Code
  </label>
  <pre id="xcmd" class="mt-1 overflow-x-auto whitespace-pre-wrap rounded border border-black/10
       bg-cream p-3 font-mono text-[11px] leading-relaxed"><?= h($cmd) ?></pre>
  <button type="button"
          class="mt-2 rounded border border-navy px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-navy hover:bg-navy hover:text-cream"
          onclick="navigator.clipboard.writeText(document.getElementById('xcmd').textContent)
                   .then(() => this.textContent = 'Copied')">Copy</button>

  <?php if (!is_file($txt)): ?>
    <p class="mt-3 text-[11px] text-gold">
      Heads up: <code><?= h(basename($txt)) ?></code> is not on this machine —
      that path is the Mac's. Extraction runs there, not here.
    </p>
  <?php endif; ?>
</div>
</details>
