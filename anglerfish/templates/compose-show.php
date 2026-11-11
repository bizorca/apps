<div x-data="composeWatch(<?= (int) $c['id'] ?>, '<?= h($c['status']) ?>')">
  <p class="text-xs text-muted">
    <a href="<?= url('/compose') ?>" class="hover:text-navy">Compose</a> /
    <?= h(str_replace('_', ' ', $c['format'])) ?>
  </p>

  <template x-if="status !== 'done' && status !== 'failed'">
    <div class="mt-6 rounded border border-black/10 bg-white p-8 text-center">
      <p class="text-lg font-bold text-navy">Writing…</p>
      <p class="mt-1 text-sm text-muted">
        Running on the server. Usually one to two minutes.
        <span x-text="waited ? '(' + waited + 's)' : ''"></span>
      </p>
      <div class="mx-auto mt-4 h-[3px] w-40 animate-pulse bg-gradient-to-r from-gold to-navy"></div>
      <p class="mt-4 text-[11px] text-muted">
        Nothing runs while no page is open &mdash; keep this tab in front.
      </p>
    </div>
  </template>

  <template x-if="status === 'failed'">
    <div class="mt-6 rounded border-l-4 border-red-600 bg-red-50 p-4 text-sm">
      <strong>Generation failed.</strong>
      <span x-text="error"></span>
    </div>
  </template>

  <?php if ($c['status'] === 'done'): ?>
    <div class="mt-4 flex items-start justify-between gap-6">
      <div>
        <h1 class="text-2xl font-bold text-navy"><?= h($c['title']) ?></h1>
        <?php if ($c['subtitle']): ?>
          <p class="text-muted italic"><?= h($c['subtitle']) ?></p>
        <?php endif; ?>
        <div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
      </div>
      <form method="post" action="<?= url('/compose/' . (int) $c['id'] . '/promote') ?>">
        <?= csrf_field() ?>
        <button class="shrink-0 rounded bg-navy px-4 py-2 text-sm text-cream hover:bg-navy2">
          <?= $c['post_id'] ? 'Open draft' : 'Promote to draft' ?>
        </button>
      </form>
    </div>

    <?php if ($c['angle']): ?>
      <p class="mt-3 rounded border-l-4 border-gold bg-cream p-3 text-xs">
        <strong>Your angle:</strong> <?= h($c['angle']) ?>
      </p>
    <?php endif; ?>

    <pre class="mt-4 whitespace-pre-wrap rounded border border-black/10 bg-white p-4 font-serif text-sm leading-relaxed"><?= h($c['body']) ?></pre>

    <?php if ($c['format'] === 'weekly'):
      // ── The download ───────────────────────────────────────────────────────
      // The composer picks the container; this is where to see what it chose,
      // read the check, and ask for a different one (rewrites only the
      // download, leaving the post alone).
      $dspec = json_decode((string) ($c['deliverable_spec'] ?? ''), true);
      $refs  = [];
      $jp = \Anglerfish\Core\Database::value(
          "SELECT payload FROM af_jobs WHERE type='compose' AND subject_type='composition'
             AND subject_id=? ORDER BY id DESC LIMIT 1", [(int) $c['id']]);
      $origins = (array) (json_decode((string) $jp, true)['origins'] ?? []);
      foreach ((array) (json_decode((string) $jp, true)['source_refs'] ?? []) as $r) {
          $sub = \Anglerfish\Models\Composition::subject((string) $r['subject_type'], (int) $r['subject_id']);
          if ($sub) {
              $refs[] = ($sub['title'] ?? '') . (!empty($sub['book_title']) ? ' (' . $sub['book_title'] . ')' : '');
          }
      }
    ?>
      <div class="mt-4 rounded border border-black/10 bg-white p-4 text-sm">
        <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Download</p>
        <?php if ($dspec): ?>
          <p class="mt-1"><span class="font-bold text-navy"><?= h($dspec['title'] ?? '') ?></span>
            <span class="text-muted">&middot; <?= h(str_replace('_', ' ', (string) $c['deliverable'])) ?>,
            <?= count($dspec['sections'] ?? []) ?> sections</span></p>
          <ul class="mt-1 list-disc pl-5 text-xs text-muted">
            <?php foreach (($dspec['sections'] ?? []) as $sec): ?><li><?= h($sec['title'] ?? '') ?></li><?php endforeach; ?>
          </ul>
          <a href="<?= url('/compose/' . (int) $c['id'] . '/download') ?>" target="_blank"
             class="mt-2 inline-block rounded border border-navy px-3 py-1 text-xs text-navy hover:bg-cream">
            Preview the download &rarr;</a>
          <span class="text-[11px] text-muted">Opens a print-ready page; print or Save as PDF to check the page count.</span>
          <?php $pdfId = \Anglerfish\Core\Database::value(
              "SELECT id FROM af_assets WHERE subject_type='composition' AND subject_id=? AND target='weekly_deliverable' AND status='ready' ORDER BY id DESC LIMIT 1",
              [(int) $c['id']]); ?>
          <?php if ($pdfId): ?>
            <a href="<?= url('/media/asset/' . (int) $pdfId) ?>" target="_blank"
               class="mt-2 ml-2 inline-block rounded bg-navy px-3 py-1 text-xs text-cream hover:bg-navy2">Download the PDF &darr;</a>
            <span class="text-[11px] text-muted">(rendered from this draft, without an issue number; promoting renders the numbered one)</span>
          <?php endif; ?>
        <?php else: ?>
          <p class="mt-1 text-muted">No download content stored yet.</p>
        <?php endif; ?>
        <?php if (!empty($c['error'])): ?>
          <p class="mt-2 rounded bg-cream px-2 py-1 text-xs text-navy"><?= h($c['error']) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= url('/compose/' . (int) $c['id'] . '/deliverable') ?>" class="mt-3 flex flex-wrap items-center gap-2 text-xs">
          <?= csrf_field() ?>
          <span class="text-muted">Change download type:</span>
          <select name="container" class="rounded border border-black/15 px-2 py-1">
            <?php foreach (\Anglerfish\Models\Weekly::CONTAINERS as $k => $cc): ?>
              <option value="<?= h($k) ?>" <?= $k === $c['deliverable'] ? 'selected' : '' ?>><?= h($cc['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="rounded bg-navy px-3 py-1 text-cream hover:bg-navy2">Rewrite download</button>
          <span class="text-muted">(the post stays as it is)</span>
        </form>
        <?php if ($refs): ?>
          <p class="mt-3 text-[11px] font-bold uppercase tracking-[2px] text-gold">Big Ideas it drew on</p>
          <ul class="mt-1 list-disc pl-5 text-xs text-muted">
            <?php foreach ($refs as $r): ?><li><?= h($r) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($origins): ?>
          <p class="mt-3 text-[11px] font-bold uppercase tracking-[2px] text-gold">Original sources behind them</p>
          <ul class="mt-1 list-disc pl-5 text-xs text-muted">
            <?php foreach ($origins as $o): ?>
              <li><span class="text-navy"><?= h($o['author'] ?? '') ?></span> &middot; <?= h(basename((string) ($o['path'] ?? ''))) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php
      // ── Citations ──────────────────────────────────────────────────────────
      //
      // Every row gets three new-tab links, because they answer three different
      // questions and only the first is automatic:
      //
      //   doi.org      does the handle resolve to a real publisher page?
      //   CrossRef     is the stored metadata what the record actually says?
      //   Scholar      **does the paper say what the post claims it says?**
      //
      // That third one is the whole reason this panel exists. Verification
      // proves a DOI exists; it cannot prove the sentence it is attached to is
      // supported by it. Post 011 cited a real 1986 paper and the post still
      // needed a human to read the abstract before the percentages were right.
      $cites = $cites ?? [];
      $unchecked = $unchecked ?? [];
      $gate = $gate ?? ['ok' => true, 'reason' => ''];
      $needs = !empty($pub['citation_required']);
    ?>

    <?php if ($cites || $unchecked || $needs): ?>
      <section class="mt-6 rounded border border-black/10 bg-white">
        <header class="flex items-center justify-between gap-4 border-b border-black/10 px-4 py-3">
          <h2 class="text-sm font-bold uppercase tracking-wide text-navy">
            Citations<?= $needs ? ' — required' : '' ?>
          </h2>
          <?php if ($needs): ?>
            <span class="rounded px-2 py-1 text-[11px] font-bold <?=
              $gate['ok'] ? 'bg-green-100 text-green-900' : 'bg-red-100 text-red-900' ?>">
              <?= $gate['ok'] ? 'PASSES' : 'BLOCKED' ?>
            </span>
          <?php endif; ?>
        </header>

        <?php if (!$gate['ok']): ?>
          <p class="border-b border-black/10 bg-red-50 px-4 py-2 text-xs text-red-900">
            <?= h($gate['reason']) ?>
          </p>
        <?php endif; ?>

        <?php if (!$cites && !$unchecked): ?>
          <p class="px-4 py-4 text-sm text-muted">
            No DOI in the body.
            <?= $needs ? 'This publication requires at least one verified peer-reviewed source.' : '' ?>
          </p>
        <?php endif; ?>

        <?php foreach ($cites as $ct):
          $doi = rawurlencode((string) $ct['doi']);
          $ok = !empty($ct['verified_at']) && !empty($ct['peer_reviewed']);
          // Search on the title when there is one — a DOI in Scholar's box is a
          // worse query than the paper's own name.
          $q = rawurlencode((string) ($ct['title'] ?: $ct['doi']));
        ?>
          <article class="border-b border-black/10 px-4 py-3 last:border-b-0">
            <div class="flex items-start gap-3">
              <span class="mt-[2px] shrink-0 rounded px-2 py-[2px] text-[10px] font-bold <?=
                $ok ? 'bg-green-100 text-green-900' : 'bg-red-100 text-red-900' ?>">
                <?= $ok ? 'VERIFIED' : 'UNVERIFIED' ?>
              </span>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-navy">
                  <?= h((string) ($ct['title'] ?: '(no title in record)')) ?>
                </p>
                <p class="text-xs text-muted">
                  <?= h((string) ($ct['authors'] ?: 'no author in the CrossRef record')) ?>
                  <?= $ct['year'] ? ' (' . h((string) $ct['year']) . ')' : '' ?>
                  <?php if ($ct['container']): ?>
                    · <em><?= h((string) $ct['container']) ?></em>
                    <?= h(trim((string) $ct['volume'] . ($ct['issue'] ? "({$ct['issue']})" : ''))) ?>
                    <?= $ct['pages'] ? ', ' . h((string) $ct['pages']) : '' ?>
                  <?php endif; ?>
                </p>

                <?php if ($ct['supports'] !== 'supports'): ?>
                  <p class="mt-1 inline-block rounded bg-gold/20 px-2 py-[2px] text-[10px] font-bold uppercase text-navy">
                    <?= h((string) $ct['supports']) ?> the claim
                  </p>
                <?php endif; ?>
                <?php if ($ct['claim']): ?>
                  <p class="mt-1 border-l-2 border-gold pl-2 text-xs italic text-muted">
                    <?= h((string) $ct['claim']) ?>
                  </p>
                <?php endif; ?>

                <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px]">
                  <a target="_blank" rel="noopener noreferrer"
                     href="https://doi.org/<?= $doi ?>"
                     class="rounded border border-navy px-2 py-1 font-semibold text-navy hover:bg-navy hover:text-cream">
                    Open the paper ↗
                  </a>
                  <a target="_blank" rel="noopener noreferrer"
                     href="https://api.crossref.org/works/<?= $doi ?>"
                     class="rounded border border-black/20 px-2 py-1 text-muted hover:border-navy hover:text-navy">
                    CrossRef record ↗
                  </a>
                  <a target="_blank" rel="noopener noreferrer"
                     href="https://scholar.google.com/scholar?q=<?= $q ?>"
                     class="rounded border border-black/20 px-2 py-1 text-muted hover:border-navy hover:text-navy">
                    Scholar — read the abstract ↗
                  </a>
                  <code class="text-muted"><?= h((string) $ct['doi']) ?></code>
                  <?php if ($ct['resolves'] === 0 || $ct['resolves'] === '0'): ?>
                    <span class="text-red-700">doi.org did not resolve</span>
                  <?php endif; ?>
                  <?php if (!empty($ct['verified_at']) && empty($ct['peer_reviewed'])): ?>
                    <span class="text-red-700">in CrossRef, but not a peer-reviewed type</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>

        <?php foreach ($unchecked as $doi): ?>
          <article class="border-b border-black/10 bg-cream px-4 py-3 last:border-b-0">
            <div class="flex flex-wrap items-center gap-2 text-[11px]">
              <span class="rounded bg-black/10 px-2 py-[2px] text-[10px] font-bold">NOT CHECKED</span>
              <code class="text-navy"><?= h($doi) ?></code>
              <a target="_blank" rel="noopener noreferrer"
                 href="https://doi.org/<?= rawurlencode($doi) ?>"
                 class="rounded border border-navy px-2 py-1 font-semibold text-navy hover:bg-navy hover:text-cream">
                Open ↗
              </a>
              <a target="_blank" rel="noopener noreferrer"
                 href="https://api.crossref.org/works/<?= rawurlencode($doi) ?>"
                 class="rounded border border-black/20 px-2 py-1 text-muted hover:border-navy hover:text-navy">
                CrossRef ↗
              </a>
              <span class="text-muted">in the body, never verified —
                <code>php cli.php cite --check=<?= (int) $c['id'] ?></code></span>
            </div>
          </article>
        <?php endforeach; ?>

        <p class="px-4 py-2 text-[11px] text-muted">
          Verification proves the DOI exists and the metadata is real. It cannot
          prove the paper says what this post claims — open the abstract and read it.
        </p>
      </section>
    <?php endif; ?>

    <p class="mt-3 text-[11px] text-muted">
      <?= h((string) $c['model']) ?> · promote it to a draft to edit and copy into Substack.
    </p>
  <?php endif; ?>
</div>
