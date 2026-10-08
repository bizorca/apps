<?php use Anglerfish\Models\Video; ?>
<p class="text-xs">
  <a class="text-navy underline hover:text-gold" href="<?= url('/videos?part=' . (int) $v['part_no']) ?>">&larr; Videos</a>
  <span class="text-muted">&middot; Part <?= (int) $v['part_no'] ?>: <?= h($v['part_title']) ?></span>
</p>

<div class="mt-2 flex items-start justify-between gap-6">
  <div class="min-w-0">
    <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Video <?= (int) $v['number'] ?> of 100</p>
    <h1 class="text-2xl font-bold text-navy"><?= h($v['title'] ?: $v['topic']) ?></h1>
    <div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
    <p class="mt-2 text-sm text-muted">Topic: <?= h($v['topic']) ?></p>
    <?php if ($v['words']): ?>
      <p class="mt-1 text-xs text-muted">
        <?= (int) $v['words'] ?> spoken words &middot; about <?= (int) $v['runtime_min'] ?> minutes
        &middot; scripted <?= h(substr((string) $v['scripted_at'], 0, 10)) ?> (<?= h($v['model']) ?>)
      </p>
    <?php endif; ?>
  </div>
  <div class="flex shrink-0 gap-2 text-xs">
    <?php if ($neighbours['prev']): ?>
      <a class="rounded border border-black/10 bg-white px-2 py-1 text-navy hover:bg-cream"
         href="<?= url('/videos/' . (int) $neighbours['prev']['number']) ?>">&larr; #<?= (int) $neighbours['prev']['number'] ?></a>
    <?php endif; ?>
    <?php if ($neighbours['next']): ?>
      <a class="rounded border border-black/10 bg-white px-2 py-1 text-navy hover:bg-cream"
         href="<?= url('/videos/' . (int) $neighbours['next']['number']) ?>">#<?= (int) $neighbours['next']['number'] ?> &rarr;</a>
    <?php endif; ?>
  </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]" x-data="handoff">

  <div class="min-w-0">
    <?php if (!$v['script']): ?>
      <div class="rounded border border-dashed border-black/20 bg-white p-6 text-sm text-muted">
        No script yet. Scripts are written in a Claude Code session from the Personal
        Finance Brain and filed with <code>press/worker/video_push.py video-scripts/NNN-*.txt</code>.
      </div>
    <?php else: ?>
      <?php if ($markers): ?>
        <div class="rounded border border-gold/60 bg-cream p-3 text-sm text-navy">
          <p class="font-bold">Before recording</p>
          <ul class="mt-1 list-disc pl-5 text-xs">
            <?php foreach ($markers as $m): ?><li><?= h($m) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="mt-3 flex items-center justify-between">
        <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Script</p>
        <div class="flex gap-2 text-xs">
          <button type="button" class="rounded bg-navy px-2 py-1 text-cream hover:bg-navy2"
                  @click="copyText('spoken', $event)">Copy spoken script</button>
          <a class="rounded border border-black/10 bg-white px-2 py-1 text-navy hover:bg-cream"
             href="<?= url('/videos/' . (int) $v['number'] . '/script.txt') ?>">Teleprompter .txt</a>
        </div>
      </div>
      <textarea id="spoken" class="sr-only" readonly><?= h(($v['title'] ?: $v['topic']) . "\n\n" . $spoken) ?></textarea>
      <div class="mt-2 rounded border border-black/10 bg-white p-6 text-[17px] leading-relaxed">
        <?php foreach (preg_split('/\n{2,}/', trim((string) $v['script'])) as $para): ?>
          <?php if (preg_match('/^\[[^\]]+\]$/', trim($para))): ?>
            <p class="my-3 text-sm italic text-muted"><?= h(trim($para)) ?></p>
          <?php else: ?>
            <p class="my-3"><?= nl2br(h($para)) ?></p>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <?php foreach (['on_screen' => 'On screen', 'sources' => 'Sources (Personal Finance Brain)', 'notes' => 'Production notes'] as $field => $label): ?>
        <?php if ($v[$field]): ?>
          <p class="mt-5 text-[11px] font-bold uppercase tracking-[2px] text-gold"><?= h($label) ?></p>
          <div class="mt-1 rounded border border-black/10 bg-white p-4 text-sm whitespace-pre-line"><?= h($v[$field]) ?></div>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= url('/videos/' . (int) $v['number']) ?>"
        class="h-fit rounded border border-black/10 bg-white p-4 text-sm">
    <?= csrf_field() ?>
    <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Production</p>
    <label class="mt-3 block text-xs font-bold text-navy">Status</label>
    <select name="status" class="mt-1 w-full rounded border border-black/15 px-2 py-1.5">
      <?php foreach (Video::STATUSES as $key => $label): ?>
        <option value="<?= h($key) ?>" <?= $v['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="mt-3 block text-xs font-bold text-navy">Video link</label>
    <input name="video_url" value="<?= h($v['video_url']) ?>" placeholder="https://"
           class="mt-1 w-full rounded border border-black/15 px-2 py-1.5">
    <label class="mt-3 block text-xs font-bold text-navy">Published on</label>
    <input type="date" name="published_at" value="<?= h($v['published_at']) ?>"
           class="mt-1 w-full rounded border border-black/15 px-2 py-1.5">
    <label class="mt-3 block text-xs font-bold text-navy">My notes</label>
    <textarea name="my_notes" rows="5"
              class="mt-1 w-full rounded border border-black/15 px-2 py-1.5"><?= h($v['my_notes']) ?></textarea>
    <button class="mt-3 w-full rounded bg-navy px-3 py-1.5 text-cream hover:bg-navy2">Save</button>
  </form>
</div>
