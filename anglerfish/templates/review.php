<?php
/**
 * The review queue. One image per row, resolved without a page reload —
 * reloading a list of twenty 2K images to record one decision is what makes a
 * queue feel slow enough to abandon.
 */
$labels = [
  'unreviewed' => 'To review', 'kept' => 'Kept', 'rejected' => 'Rejected',
  'flagged' => 'Flagged by check', 'waiting' => 'Waiting', 'all' => 'All',
];
?>
<div x-data="reviewQueue()">

<div class="flex flex-wrap items-baseline gap-3">
  <h1 class="text-2xl font-bold text-navy">Image review</h1>
  <span class="text-sm text-muted">
    every infographic in the system, oldest first
  </span>
</div>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>

<div class="mt-4 flex flex-wrap items-center gap-2">
<?php foreach ($filters as $f):
    $n = match ($f) {
      'unreviewed' => $counts['unreviewed'] ?? 0,
      'kept'       => $counts['kept'] ?? 0,
      'rejected'   => $counts['rejected'] ?? 0,
      'flagged'    => $counts['flagged'] ?? 0,
      'waiting'    => $counts['waiting'] ?? 0,
      default      => $counts['all_n'] ?? 0,
    };
?>
  <a href="<?= url('/review?filter=' . $f) ?>"
     class="rounded border px-2.5 py-1 text-xs <?= $f === $filter
        ? 'border-navy bg-navy text-cream'
        : 'border-black/15 text-muted hover:border-navy' ?>">
    <?= h($labels[$f]) ?> <span class="opacity-70"><?= (int) $n ?></span>
  </a>
<?php endforeach; ?>

  <span class="ml-auto text-xs text-muted">
    <kbd class="rounded border border-black/20 px-1">J</kbd>/<kbd class="rounded border border-black/20 px-1">K</kbd> move ·
    <kbd class="rounded border border-black/20 px-1">A</kbd> keep ·
    <kbd class="rounded border border-black/20 px-1">R</kbd> redraw ·
    <kbd class="rounded border border-black/20 px-1">X</kbd> reject
  </span>
</div>

<?php if ($rows && $filter === 'unreviewed'): ?>
  <form method="post" action="<?= url('/review/bulk') ?>" class="mt-3">
    <?= csrf_field() ?>
    <input type="hidden" name="filter" value="<?= h($filter) ?>">
    <?php foreach ($rows as $r): ?>
      <input type="hidden" name="ids[]" value="<?= (int) $r['id'] ?>">
    <?php endforeach; ?>
    <button class="rounded border border-black/20 px-2 py-1 text-xs text-muted hover:border-navy hover:text-navy">
      Keep all <?= count($rows) ?> on this page
    </button>
  </form>
<?php endif; ?>

<div class="mt-5 space-y-5">
<?php foreach ($rows as $i => $r): ?>
  <div class="rounded border border-black/10 bg-white p-4"
       x-ref="row<?= (int) $r['id'] ?>"
       :class="focus === <?= $i ?> ? 'ring-2 ring-gold' : ''"
       x-show="!done[<?= (int) $r['id'] ?>]"
       @click="focus = <?= $i ?>">

    <div class="flex flex-wrap items-baseline gap-2">
      <span class="font-mono text-xs text-gold">#<?= (int) $r['id'] ?></span>
      <span class="font-bold text-navy"><?= h($r['subject_title']) ?></span>
      <?php if ($r['subject_context']): ?>
        <span class="text-xs text-muted"><?= h($r['subject_context']) ?></span>
      <?php endif; ?>
      <span class="rounded bg-cream px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-navy">
        <?= h($r['subject_type']) ?>
      </span>
      <?php if ((int) $r['version'] > 1): ?>
        <span class="text-[10px] text-muted">v<?= (int) $r['version'] ?></span>
      <?php endif; ?>
      <span class="ml-auto text-xs text-muted">
        <?= h((string) $r['created_at']) ?>
        <?php if ((float) $r['cost_usd']): ?>
          · $<?= number_format((float) $r['cost_usd'], 3) ?>
        <?php endif; ?>
      </span>
    </div>

    <?php if ($r['headline']): ?>
      <p class="mt-1 text-sm text-ink">&ldquo;<?= h($r['headline']) ?>&rdquo;</p>
    <?php endif; ?>
    <?php if ($r['subject_note']): ?>
      <p class="mt-1 text-xs text-muted"><?= h($r['subject_note']) ?></p>
    <?php endif; ?>
    <?php if (!empty($r['qa_verdict'])): ?>
      <p class="mt-2 rounded border px-2 py-1 text-xs <?= $r['qa_verdict'] === 'pass'
          ? 'border-black/10 text-muted' : 'border-gold bg-cream text-navy' ?>">
        <span class="font-bold uppercase tracking-wide">Check: <?= h($r['qa_verdict']) ?></span>
        <?php if ($r['qa_notes']): ?> &mdash; <?= h($r['qa_notes']) ?><?php endif; ?>
      </p>
    <?php endif; ?>

    <?php if ($r['web_path'] && in_array($r['status'], ['ready', 'rejected'], true)): ?>
      <a href="<?= url('/media/asset/' . (int) $r['id']) ?>" target="_blank" class="mt-3 block">
        <img src="<?= url('/media/asset/' . (int) $r['id'] . '?w=substack') ?>"
             alt="<?= h($r['subject_title']) ?>" loading="lazy"
             class="w-full rounded border border-black/5 <?= $r['status'] === 'rejected' ? 'opacity-40' : '' ?>">
      </a>
    <?php else: ?>
      <div class="mt-3 flex h-40 items-center justify-center rounded bg-paper text-xs text-muted">
        <?= $r['status'] === 'pending' ? 'waiting on Gemini' : h($r['status']) ?>
      </div>
    <?php endif; ?>

    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-black/5 pt-3">
      <?php if ($r['status'] === 'ready' && !$r['reviewed_at']): ?>
        <button type="button" @click="act(<?= (int) $r['id'] ?>, 'keep')"
                class="rounded bg-navy px-3 py-1.5 text-xs text-cream hover:bg-navy2">
          Keep <span class="opacity-60">A</span>
        </button>
      <?php elseif ($r['reviewed_at']): ?>
        <span class="text-xs text-muted">kept <?= h((string) $r['reviewed_at']) ?></span>
      <?php endif; ?>

      <?php if (in_array($r['status'], ['ready', 'rejected'], true)): ?>
        <button type="button" @click="act(<?= (int) $r['id'] ?>, 'redraw')"
                class="rounded border border-navy px-3 py-1.5 text-xs text-navy hover:bg-navy hover:text-cream">
          Redraw <span class="opacity-60">R</span>
          <span class="opacity-60">$<?= number_format($price, 2) ?></span>
        </button>
      <?php endif; ?>

      <?php if ($r['status'] === 'ready'): ?>
        <button type="button" @click="act(<?= (int) $r['id'] ?>, 'reject')"
                class="rounded border border-black/20 px-3 py-1.5 text-xs text-muted hover:border-navy hover:text-navy">
          Reject <span class="opacity-60">X</span>
        </button>
      <?php endif; ?>

      <?php if ($r['subject_type'] === 'chapter' && $r['subject_slug']): ?>
        <a href="<?= url('/library/' . rawurlencode((string) $r['subject_slug']) . '/images') ?>"
           class="text-xs text-muted underline hover:text-navy">book sheet</a>
      <?php endif; ?>

      <details class="ml-auto text-xs">
        <summary class="cursor-pointer text-muted hover:text-navy">prompt</summary>
        <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap rounded bg-paper p-3 text-[11px] text-ink"><?= h((string) $r['prompt_snapshot']) ?></pre>
      </details>

      <span class="text-xs" x-show="msg[<?= (int) $r['id'] ?>]"
            x-text="msg[<?= (int) $r['id'] ?>]"></span>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <p class="mt-6 rounded border border-black/10 bg-white p-6 text-sm text-muted">
    Nothing here. Order some from a book's <strong>Chapter images</strong> page, or
    from the command line with <code>php cli.php images --book=&lt;slug&gt;</code>.
  </p>
<?php endif; ?>

<?php if ($pages > 1): ?>
  <div class="mt-6 flex items-center gap-3 text-sm">
    <?php if ($page > 1): ?>
      <a class="rounded border border-black/20 px-3 py-1 hover:border-navy"
         href="<?= url('/review?filter=' . $filter . '&page=' . ($page - 1)) ?>">Previous</a>
    <?php endif; ?>
    <span class="text-muted">Page <?= $page ?> of <?= $pages ?> · <?= (int) $total ?> images</span>
    <?php if ($page < $pages): ?>
      <a class="rounded border border-black/20 px-3 py-1 hover:border-navy"
         href="<?= url('/review?filter=' . $filter . '&page=' . ($page + 1)) ?>">Next</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('reviewQueue', () => ({
    focus: 0,
    done: {},          // rows resolved in this session, hidden without a reload
    msg: {},
    ids: <?= json_encode(array_map(static fn($r) => (int) $r['id'], $rows)) ?>,

    init() {
      // Keys act on the focused row. Ignore them while typing, or "a" in a
      // search box silently keeps an image.
      window.addEventListener('keydown', (e) => {
        if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.metaKey || e.ctrlKey) return;
        const id = this.ids[this.focus];
        const k = e.key.toLowerCase();
        if (k === 'j') { this.move(1); e.preventDefault(); }
        else if (k === 'k') { this.move(-1); e.preventDefault(); }
        else if (id && k === 'a') { this.act(id, 'keep'); e.preventDefault(); }
        else if (id && k === 'r') { this.act(id, 'redraw'); e.preventDefault(); }
        else if (id && k === 'x') { this.act(id, 'reject'); e.preventDefault(); }
      });
    },

    move(d) {
      // Step over rows already resolved in this session. They are hidden, so
      // landing on one looks like the keys have stopped working.
      let next = this.focus;
      for (let i = 0; i < this.ids.length; i++) {
        const candidate = next + d;
        if (candidate < 0 || candidate >= this.ids.length) break;
        next = candidate;
        if (!this.done[this.ids[next]]) break;
      }
      this.focus = next;
      const el = this.$refs['row' + this.ids[next]];
      if (el) el.scrollIntoView({block: 'center', behavior: 'smooth'});
    },

    async act(id, action) {
      const body = new URLSearchParams({
        asset_id: id, action,
        // The router reads `_csrf`, not `csrf_token` — csrf_field() emits that
        // name and a fetch has to match it or every action 403s.
        _csrf: document.querySelector('meta[name=csrf]').content,
      });
      try {
        const res = await fetch(<?= json_encode(url('/review/act')) ?>, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'fetch', 'Accept': 'application/json'},
          body,
        });
        const d = await res.json();
        this.msg[id] = d.message || '';
        if (d.ok) {
          // Hide the row and step on, so the queue always has the next
          // undecided image under the cursor.
          this.done[id] = true;
          if (this.ids[this.focus] === id) this.move(1);
          // A redraw creates work; nudge the queue so it starts now.
          if (action === 'redraw' && window.anglerTick) window.anglerTick(true);
        }
      } catch (err) {
        this.msg[id] = 'failed — reload and try again';
      }
    },
  }));
});
</script>
