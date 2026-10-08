<?php
use Dispatch\Core\View;
// $year, $month, $firstDay (DateTime), $prevMonth, $nextMonth
// $campaigns, $actionItems

$monthStr   = sprintf('%04d-%02d', $year, $month);
$monthLabel = $firstDay->format('F Y');

// Grid starts on the Sunday of the week containing the 1st
$calStart   = clone $firstDay;
$dayOfWeek  = (int)$calStart->format('w'); // 0=Sun, 6=Sat
if ($dayOfWeek > 0) $calStart->modify("-{$dayOfWeek} days");

// Always render 6 weeks (42 cells) for consistent height
$daysInGrid = 42;

// Build lookup maps
$campaignsByDate  = [];
foreach ($campaigns as $c) {
    $campaignsByDate[$c['event_date']][] = $c;
}
$actionsByDate = [];
foreach ($actionItems as $ai) {
    $actionsByDate[$ai['due_date']][] = $ai;
}
?>

<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <a href="<?= $_base ?>/campaigns" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1 mb-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            All Campaigns
        </a>
        <h1 class="text-2xl font-bold text-slate-900"><?= View::e($monthLabel) ?></h1>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= $_base ?>/campaigns/calendar?month=<?= $prevMonth ?>" class="px-3 py-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium">← Prev</a>
        <a href="<?= $_base ?>/campaigns/calendar?month=<?= date('Y-m') ?>" class="px-3 py-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium">Today</a>
        <a href="<?= $_base ?>/campaigns/calendar?month=<?= $nextMonth ?>" class="px-3 py-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium">Next →</a>
    </div>
</div>

<!-- Legend -->
<div class="flex items-center gap-5 mb-4 text-xs">
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-indigo-500 rounded-sm inline-block"></span> Event date</span>
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-amber-400 rounded-sm inline-block"></span> Submission due</span>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <!-- Day headers -->
    <div class="grid grid-cols-7 border-b border-slate-100">
        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day): ?>
        <div class="py-2 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider"><?= $day ?></div>
        <?php endforeach; ?>
    </div>

    <!-- Calendar grid -->
    <div class="grid grid-cols-7">
        <?php
        $today   = date('Y-m-d');
        $current = clone $calStart;
        for ($i = 0; $i < $daysInGrid; $i++):
            $dateStr     = $current->format('Y-m-d');
            $isThisMonth = $current->format('Y-m') === $monthStr;
            $isToday     = $dateStr === $today;
            $events      = $campaignsByDate[$dateStr] ?? [];
            $actions     = $actionsByDate[$dateStr] ?? [];
            $borderRight = $i % 7 !== 6 ? 'border-r border-slate-100' : '';
            $borderBottom = $i < $daysInGrid - 7 ? 'border-b border-slate-100' : '';
        ?>
        <div class="min-h-[100px] p-2 <?= $borderRight ?> <?= $borderBottom ?> <?= !$isThisMonth ? 'bg-slate-50/50' : '' ?>">
            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-semibold <?= $isToday ? 'bg-indigo-600 text-white w-6 h-6 flex items-center justify-center rounded-full' : ($isThisMonth ? 'text-slate-700' : 'text-slate-300') ?>">
                    <?= $current->format('j') ?>
                </span>
            </div>

            <?php foreach ($events as $ev): ?>
            <a href="<?= $_base ?>/campaigns/<?= $ev['id'] ?>" class="block mb-0.5">
                <span class="block truncate text-xs bg-indigo-100 text-indigo-800 font-medium px-1.5 py-0.5 rounded" title="Event: <?= htmlspecialchars($ev['name'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= View::e($ev['name']) ?>
                </span>
            </a>
            <?php endforeach; ?>

            <?php foreach ($actions as $ai): ?>
            <a href="<?= $_base ?>/campaigns/<?= $ai['campaign_id'] ?>" class="block mb-0.5">
                <span class="block truncate text-xs bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded" title="Due: <?= htmlspecialchars($ai['venue_name'] . ' — ' . $ai['campaign_name'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= View::e($ai['venue_name']) ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php $current->modify('+1 day'); endfor; ?>
    </div>
</div>
