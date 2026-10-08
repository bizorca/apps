<?php
/** Tabs across an engagement. @var array $engagement @var string $active */
$tabs = [
  'journey'    => ['Journey',    '/engagements/' . $engagement['id'] . '/journey'],
  'sessions'   => ['Sessions',   '/engagements/' . $engagement['id'] . '/sessions'],
  'tasks'      => ['Commitments','/engagements/' . $engagement['id'] . '/tasks'],
  'scoreboard' => ['Scoreboard', '/engagements/' . $engagement['id'] . '/scoreboard'],
  'documents'  => ['Documents',  '/engagements/' . $engagement['id'] . '/documents'],
  'worksheets' => ['Worksheets', '/engagements/' . $engagement['id'] . '/worksheets'],
  'scope'      => ['Scope',      '/engagements/' . $engagement['id'] . '/scope'],
  'messages'   => ['Messages',   '/engagements/' . $engagement['id'] . '/messages'],
];

// Health and the period report are the coach's working documents. A client
// reads their own progress on their dashboard; a health score computed about
// them, with "at risk" printed on it, is not theirs to stumble into.
$viewer = $viewer ?? \Bizorca\Pilotage\Auth\Session::user();

if (($viewer['client_org_id'] ?? null) === null) {
    $tabs['health'] = ['Health', '/engagements/' . $engagement['id'] . '/health'];
    $tabs['report'] = ['Report', '/engagements/' . $engagement['id'] . '/report'];
}
?>
<div class="border-b border-slate-200 bg-white">
  <div class="max-w-5xl mx-auto px-4 flex gap-5 text-sm">
    <?php foreach ($tabs as $key => [$label, $href]): ?>
      <a href="<?= h(url($href)) ?>"
         class="py-2 <?= ($active ?? '') === $key ? 'font-semibold text-slate-900 border-b-2 border-slate-900' : 'text-slate-500 hover:text-slate-900' ?>">
        <?= h($label) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
