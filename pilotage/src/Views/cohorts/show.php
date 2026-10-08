<?php
/** @var array $cohort @var bool $firmSide @var array $members @var array $roster
 *  @var array $sessions @var array $materials @var array $announcements
 *  @var array $available @var array $library @var ?string $error
 *  @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
$post = url('/cohorts/' . $cohort['id']);
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1"><?= h((string) $cohort['name']) ?></h1>
  <p class="text-sm text-slate-600 mb-6">
    <?php if (!empty($cohort['description'])): ?><?= h((string) $cohort['description']) ?><?php endif; ?>
    <?php if (!empty($cohort['playbook_name'])): ?>
      <span class="block text-xs text-slate-500 mt-1">Running <?= h((string) $cohort['playbook_name']) ?></span>
    <?php endif; ?>
  </p>

  <?php if ($error !== null): ?>
    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 mb-5"><?= h((string) $error) ?></div>
  <?php endif; ?>

  <!-- Sessions: the shared thing. Both sides see these. -->
  <h2 class="text-sm font-semibold mb-2">Sessions</h2>
  <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-4">
    <?php if ($sessions === []): ?>
      <div class="px-4 py-3 text-sm text-slate-500">Nothing scheduled yet.</div>
    <?php endif; ?>
    <?php foreach ($sessions as $s): ?>
      <div class="flex items-center justify-between px-4 py-3">
        <div>
          <div class="text-sm"><?= h((string) $s['title']) ?></div>
          <div class="text-xs text-slate-500">
            <?php if ($s['scheduled_at'] !== null): ?>
              <?= h(date('l j F, H:i', strtotime((string) $s['scheduled_at']))) ?> UTC
            <?php endif; ?>
            <?php if (!empty($s['location'])): ?> · <?= h((string) $s['location']) ?><?php endif; ?>
          </div>
        </div>
        <div class="text-right">
          <div class="text-xs text-slate-500"><?= h(str_replace('_', ' ', (string) $s['status'])) ?></div>
          <?php if ((int) $s['attended_count'] > 0): ?>
            <div class="text-xs text-slate-400"><?= (int) $s['attended_count'] ?> attended</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($firmSide): ?>
    <form method="post" action="<?= h($post) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-8 space-y-2">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="schedule">
      <div class="text-xs font-semibold">Schedule a session for the whole group</div>
      <input type="text" name="title" required placeholder="What is this one about?" class="<?= $field ?>">
      <div class="flex gap-2">
        <input type="datetime-local" name="scheduled_at" class="<?= $field ?>">
        <input type="text" name="location" placeholder="Where" class="<?= $field ?>">
        <input type="number" name="duration_minutes" value="90" min="15" step="15" class="<?= $field ?> max-w-24">
      </div>
      <p class="text-xs text-slate-500">Times are UTC. Everyone in the group is told.</p>
      <button class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Schedule</button>
    </form>
  <?php endif; ?>

  <!-- Material. Both sides. -->
  <h2 class="text-sm font-semibold mb-2">Material</h2>
  <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-4">
    <?php if ($materials === []): ?>
      <div class="px-4 py-3 text-sm text-slate-500">Nothing shared yet.</div>
    <?php endif; ?>
    <?php foreach ($materials as $m): ?>
      <div class="flex items-center justify-between px-4 py-3">
        <div>
          <a href="<?= h(url('/documents/' . $m['document_id'])) ?>" class="text-sm hover:underline">
            <?= h((string) $m['title']) ?>
          </a>
          <?php if (!empty($m['note'])): ?>
            <div class="text-xs text-slate-500"><?= h((string) $m['note']) ?></div>
          <?php endif; ?>
        </div>
        <?php if ($firmSide): ?>
          <form method="post" action="<?= h($post) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="withdraw">
            <input type="hidden" name="document_id" value="<?= (int) $m['document_id'] ?>">
            <button class="text-xs text-slate-500 underline hover:text-slate-900">Withdraw</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($firmSide): ?>
    <form method="post" action="<?= h($post) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-8 space-y-2">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="publish">
      <div class="text-xs font-semibold">Share something with the group</div>
      <select name="document_id" required class="<?= $field ?>">
        <option value="">Choose from your library…</option>
        <?php foreach ($library as $doc): ?>
          <option value="<?= (int) $doc['id'] ?>"><?= h((string) $doc['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="note" placeholder="A line about it (optional)" class="<?= $field ?>">
      <p class="text-xs text-slate-500">
        Library documents only. A deliverable or a client file belongs to one client, and sharing it
        here would hand it to their peers.
      </p>
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Share</button>
    </form>
  <?php endif; ?>

  <!-- Who is in it. -->
  <?php if ($firmSide): ?>
    <h2 class="text-sm font-semibold mb-2">Members and progress</h2>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-4">
      <?php if ($members === []): ?>
        <div class="px-4 py-3 text-sm text-slate-500">Nobody yet.</div>
      <?php endif; ?>
      <?php foreach ($members as $m): ?>
        <div class="flex items-center justify-between px-4 py-3">
          <div>
            <div class="text-sm font-medium"><?= h((string) $m['org_name']) ?></div>
            <div class="text-xs text-slate-500"><?= h((string) $m['engagement_title']) ?></div>
          </div>
          <div class="flex items-center gap-4">
            <?php if ($m['progress'] !== null): ?>
              <div class="text-right">
                <div class="text-sm tabular-nums"><?= (int) ($m['progress']['percent'] ?? 0) ?>%</div>
                <div class="text-xs text-slate-500">through the playbook</div>
              </div>
            <?php else: ?>
              <span class="text-xs text-slate-400">no playbook applied</span>
            <?php endif; ?>
            <form method="post" action="<?= h($post) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="remove_member">
              <input type="hidden" name="engagement_id" value="<?= (int) $m['engagement_id'] ?>">
              <button class="text-xs text-slate-500 underline hover:text-slate-900">Remove</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="post" action="<?= h($post) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-8 space-y-2">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add_member">
      <div class="text-xs font-semibold">Add a client</div>
      <select name="engagement_id" required class="<?= $field ?>">
        <option value="">Choose an engagement…</option>
        <?php foreach ($available as $e): ?>
          <option value="<?= (int) $e['id'] ?>"><?= h((string) $e['org_name']) ?> — <?= h((string) $e['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add</button>
    </form>

  <?php elseif ($roster !== []): ?>
    <h2 class="text-sm font-semibold mb-2">Who else is here</h2>
    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 mb-8">
      <p class="text-sm text-slate-700"><?= h(implode(' · ', $roster)) ?></p>
    </div>
  <?php endif; ?>

  <!-- Announcements. -->
  <?php if ($announcements !== []): ?>
    <h2 class="text-sm font-semibold mb-2">Notices</h2>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-4">
      <?php foreach ($announcements as $a): ?>
        <div class="px-4 py-3">
          <div class="text-sm font-medium"><?= h((string) $a['subject']) ?></div>
          <div class="text-sm text-slate-600 mt-0.5"><?= nl2br(h((string) $a['body'])) ?></div>
          <div class="text-xs text-slate-400 mt-1">
            <?= h(date('j M Y', strtotime((string) $a['sent_at']))) ?>
            <?php if (!empty($a['created_by_name'])): ?> · <?= h((string) $a['created_by_name']) ?><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($firmSide): ?>
    <form method="post" action="<?= h($post) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-8 space-y-2">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="announce">
      <div class="text-xs font-semibold">Tell the group something</div>
      <input type="text" name="subject" required placeholder="Subject" class="<?= $field ?>">
      <textarea name="body" rows="3" placeholder="What do they need to know?" class="<?= $field ?>"></textarea>
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Send</button>
    </form>

    <form method="post" action="<?= h($post) ?>" class="rounded-lg border border-slate-200 bg-white p-4 space-y-3">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="settings">
      <div class="text-xs font-semibold">Settings</div>
      <input type="text" name="name" value="<?= h((string) $cohort['name']) ?>" class="<?= $field ?>">
      <textarea name="description" rows="2" class="<?= $field ?>"><?= h((string) ($cohort['description'] ?? '')) ?></textarea>
      <div class="flex gap-2">
        <select name="cadence" class="<?= $field ?>">
          <?php foreach (['weekly' => 'Weekly', 'biweekly' => 'Fortnightly', 'monthly' => 'Monthly',
                          'quarterly' => 'Quarterly', 'adhoc' => 'As needed'] as $v => $label): ?>
            <option value="<?= h($v) ?>" <?= (string) $cohort['cadence'] === $v ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="<?= $field ?>">
          <?php foreach (['draft' => 'Draft', 'active' => 'Active', 'complete' => 'Finished', 'archived' => 'Archived'] as $v => $label): ?>
            <option value="<?= h($v) ?>" <?= (string) $cohort['status'] === $v ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <label class="flex items-start gap-2 text-sm">
        <input type="checkbox" name="roster_visible" value="1" class="mt-0.5 rounded border-slate-300"
               <?= (int) $cohort['roster_visible'] === 1 ? 'checked' : '' ?>>
        <span>
          Let members see who else is in the group
          <span class="block text-xs text-slate-500">
            Currently <?= (int) $cohort['roster_visible'] === 1 ? 'visible to members' : 'hidden' ?>.
            Turning it on publishes the list of client names to everyone in the group.
          </span>
        </span>
      </label>
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Save</button>
    </form>

    <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
      <strong>Worth remembering.</strong> Notes you take on a group session are read by everyone in
      the group — they were all there. If something is about one client, put it in that engagement,
      not here.
    </div>
  <?php endif; ?>
</div>
