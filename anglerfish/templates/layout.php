<?php use Anglerfish\Core\Auth; use Anglerfish\Core\Session; $u = Auth::user(); ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= h(csrf_token()) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= h($pageTitle ? "$pageTitle · Anglerfish Press" : 'Anglerfish Press') ?></title>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="38" r="18" fill="#1B3A5C"/><circle cx="25" cy="34" r="3.5" fill="#F7F3E7"/><path d="M50 38l10-7v14z" fill="#1B3A5C"/><path d="M30 20c-1-8 6-12 10-8" stroke="#1B3A5C" stroke-width="3" fill="none"/><circle cx="41" cy="12" r="5" fill="#D4A017"/></svg>') ?>">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
<script>
tailwind.config = { theme: { extend: { colors: {
  navy:'#1B3A5C', navy2:'#142C47', gold:'#D4A017',
  ink:'#26303B', muted:'#58616D', cream:'#F7F3E7', paper:'#F5F7FA' },
  fontFamily: { serif:['Georgia','Times New Roman','serif'] } } } }
</script>
<script>
// Triage rows. Keys act on the focused row, so a list is walkable with Tab.
// Page-load job trigger, alongside the server cron. The original host ran mod_php, so there was
// no way to detach work from the response — the browser fires it here instead,
// after the page has already rendered. Nothing runs while nobody is looking,
// which is acceptable: the server-side jobs are all interactive anyway.
(function tickLoop() {
  const csrf = document.querySelector('meta[name=csrf]');
  if (!csrf) return;
  let delay = 4000;
  async function tick(force) {
    try {
      const res = await fetch(<?= json_encode(url('/tick')) ?>, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: force ? 'force=1' : '',
      });
      if (!res.ok) return;
      const d = await res.json();
      const badge = document.getElementById('pending-count');
      if (badge) {
        const n = (d.pending || 0) + (d.pending_local || 0);
        badge.textContent = n || '';
        badge.style.display = n ? '' : 'none';
      }
      // Keep checking while work is waiting; otherwise back off and stop.
      delay = d.pending > 0 ? 4000 : Math.min(delay * 2, 60000);
      if (d.pending > 0 || delay < 60000) setTimeout(() => tick(false), delay);
    } catch (e) { /* a failed tick is never worth bothering the user about */ }
  }
  window.addEventListener('load', () => setTimeout(() => tick(true), 800));
  window.anglerTick = tick;
})();

document.addEventListener('alpine:init', () => {
  // Handoff clipboard. Substack's API is deprecated, so this screen is the
  // publishing path and the copy has to survive the paste intact.
  // Watches a composition while its job runs, forcing ticks so the work
  // actually happens — this host has no cron and no background daemon.
  Alpine.data('composeWatch', (id, initial) => ({
    status: initial, error: '', waited: 0,
    init() {
      if (this.status === 'done' || this.status === 'failed') return;
      const started = Date.now();
      const poll = async () => {
        this.waited = Math.round((Date.now() - started) / 1000);
        if (window.anglerTick) await window.anglerTick(true);
        try {
          const r = await fetch(<?= json_encode(url('/compose/')) ?> + id + '/status');
          const d = await r.json();
          if (d.ok) {
            this.status = d.status;
            this.error = d.error || '';
            if (d.status === 'done') { location.reload(); return; }
          }
        } catch (e) { /* keep waiting */ }
        if (this.waited < 300) setTimeout(poll, 3000);
      };
      setTimeout(poll, 1500);
    },
  }));

  Alpine.data('handoff', () => ({
    bodyLabel: 'Copy body (formatted)',
    flash(el, text) {
      const old = el.textContent; el.textContent = text;
      setTimeout(() => { el.textContent = old; }, 1400);
    },
    async copyText(id, e) {
      await navigator.clipboard.writeText(document.getElementById(id).value);
      this.flash(e.target, 'Copied');
    },
    async copyBody(e) {
      const html = document.getElementById('rich-body').innerHTML;
      // Both flavors in one item: HTML alone can be refused, plain text alone
      // pastes as an undifferentiated wall with every heading and list lost.
      const plain = document.getElementById('rich-body').innerText;
      try {
        await navigator.clipboard.write([new ClipboardItem({
          'text/html':  new Blob([html],  {type: 'text/html'}),
          'text/plain': new Blob([plain], {type: 'text/plain'}),
        })]);
        this.bodyLabel = 'Copied — formatting preserved';
      } catch (err) {
        // Safari and older browsers reject multi-flavor writes; fall back to
        // selecting the rendered node and letting execCommand carry the HTML.
        const node = document.getElementById('rich-body');
        node.classList.remove('sr-only');
        const range = document.createRange(); range.selectNodeContents(node);
        const sel = getSelection(); sel.removeAllRanges(); sel.addRange(range);
        const ok = document.execCommand('copy');
        sel.removeAllRanges(); node.classList.add('sr-only');
        this.bodyLabel = ok ? 'Copied — formatting preserved' : 'Copy failed';
      }
      setTimeout(() => { this.bodyLabel = 'Copy body (formatted)'; }, 1800);
    },
  }));

  Alpine.data('triageRow', (initial) => ({
    on: Object.assign({favorite:false, read:false, write_about:false, meh:false}, initial || {}),
    keymap: {f:'favorite', r:'read', w:'write_about', m:'meh'},
    maybeKey(e) {
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
      const row = this.$el;
      if (document.activeElement !== row && !row.contains(document.activeElement)) return;
      const mark = this.keymap[e.key.toLowerCase()];
      if (!mark) return;
      e.preventDefault();
      this.toggle(mark);
    },
    async toggle(mark) {
      const body = new URLSearchParams({
        subject_type: this.$el.dataset.type,
        subject_id:   this.$el.dataset.id,
        mark,
        _csrf: document.querySelector('meta[name=csrf]').content,
      });
      const res = await fetch(<?= json_encode(url('/triage')) ?>, {method:'POST', body});
      if (!res.ok) return;
      const data = await res.json();
      if (!data.ok) return;
      this.on[mark] = data.on;
      if (mark === 'write_about' && data.on) this.on.meh = false;
      if (mark === 'meh' && data.on) this.on.write_about = false;
      const badge = document.getElementById('queue-count');
      if (badge) badge.textContent = data.queue;
    },
  }));
});
</script>
</head>
<body class="h-full bg-paper text-ink font-serif antialiased">

<?php if ($u): ?>
<header class="bg-navy text-cream">
  <div class="mx-auto max-w-7xl px-6 h-14 flex items-center gap-6">
    <a href="<?= url('/dashboard') ?>" class="flex items-center gap-2 font-bold tracking-tight">
      <span class="text-gold">&#9679;</span> Anglerfish Press
    </a>
    <nav class="flex-1 flex items-center gap-5 text-sm">
      <a class="hover:text-gold" href="<?= url('/dashboard') ?>">Dashboard</a>
      <a class="hover:text-gold" href="<?= url('/library') ?>">Library</a>
      <a class="hover:text-gold" href="<?= url('/queue') ?>">Queue
        <span id="queue-count" class="ml-0.5 rounded bg-gold px-1 text-[10px] text-navy"><?= (int) \Anglerfish\Core\Database::value("SELECT COUNT(*) FROM af_triage WHERE mark='write_about'") ?></span>
      </a>
      <a class="hover:text-gold" href="<?= url('/weekly') ?>">Weekly</a>
      <a class="hover:text-gold" href="<?= url('/compose') ?>">Compose</a>
      <a class="hover:text-gold" href="<?= url('/posts') ?>">Posts</a>
      <a class="hover:text-gold" href="<?= url('/videos') ?>">Videos</a>
      <a class="hover:text-gold" href="<?= url('/corpus') ?>">Corpus</a>
      <span id="pending-count" title="jobs waiting"
            class="rounded bg-gold px-1 text-[10px] text-navy" style="display:none"></span>
      <a class="hover:text-gold" href="<?= url('/review') ?>">Images
        <?php $unreviewed = (int) \Anglerfish\Core\Database::value(
          "SELECT COUNT(*) FROM af_assets WHERE kind='infographic' AND status='ready'
             AND reviewed_at IS NULL"); ?>
        <?php if ($unreviewed): ?>
          <span class="ml-0.5 rounded bg-gold px-1 text-[10px] text-navy"><?= $unreviewed ?></span>
        <?php endif; ?>
      </a>
      <a class="hover:text-gold" href="<?= url('/kits') ?>">Vault</a>
      <span class="text-white/30">Admin</span>
    </nav>
    <span class="text-xs text-white/60"><?= h($u['email']) ?></span>
    <a class="text-xs hover:text-gold" href="/account/settings.php">Account</a>
    <form method="post" action="/account/logout.php"><?= tl_csrf_field() ?>
      <button class="text-xs hover:text-gold underline underline-offset-2">Sign out</button>
    </form>
  </div>
</header>
<?php endif; ?>

<?php foreach (Session::takeFlash() as $f): ?>
  <div class="mx-auto max-w-7xl px-6 pt-4">
    <div class="rounded border-l-4 <?= $f['type']==='error' ? 'border-red-600 bg-red-50' : 'border-gold bg-cream' ?> px-4 py-2 text-sm">
      <?= h($f['message']) ?>
    </div>
  </div>
<?php endforeach; ?>

<main class="mx-auto max-w-7xl px-6 py-8"><?= $content ?></main>

<footer class="mx-auto max-w-7xl px-6 py-8 text-xs text-muted">
  bizorca.com &middot; &copy; <?= date('Y') ?> Bizorca LLC
</footer>
</body>
</html>
