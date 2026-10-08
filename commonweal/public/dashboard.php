<?php
require __DIR__ . '/_bootstrap.php';
requireLogin();

$user = getCurrentUser();

// Redirect coordinators and admins to their own dashboards
if ($user['role'] === 'admin') {
    header('Location: ' . url('/admin.php'));
    exit;
}
if ($user['role'] === 'coordinator') {
    header('Location: ' . url('/coordinator.php'));
    exit;
}

// Get current case
$case = getUserCase((int)$user['id']);

// No business/case yet — send to intake
if (!$case) {
    header('Location: ' . url('/intake.php'));
    exit;
}

$flash = getFlash();

// Pipeline steps
$pipeline = [
    'intake'             => 'Intake',
    'structure_selection'=> 'Structure',
    'modeling'           => 'Deal Model',
    'documents'          => 'Documents',
    'review'             => 'Review',
    'complete'           => 'Complete',
];

$statusOrder = array_keys($pipeline);
$currentIndex = array_search($case['status'], $statusOrder);
if ($currentIndex === false) $currentIndex = 0;

// Next action mapping
$nextActions = [
    'intake'              => ['label' => 'Complete your intake',           'href' => '/intake.php'],
    'structure_selection' => ['label' => 'Select a cooperative structure', 'href' => '/structure-selector.php'],
    'modeling'            => ['label' => 'Model your deal',                'href' => '/deal-modeler.php'],
    'documents'           => ['label' => 'Generate documents',             'href' => '/documents.php'],
    'review'              => ['label' => 'View your case',                 'href' => '/case.php'],
    'complete'            => ['label' => 'View your equity ledger',        'href' => '/equity-ledger.php'],
];
$nextAction = $nextActions[$case['status']] ?? $nextActions['intake'];

// Coordinator info
$coordinator = null;
if (!empty($case['coordinator_id'])) {
    $db = getDB();
    $stmt = $db->prepare('SELECT name, email FROM cw_users WHERE id = ? LIMIT 1');
    $stmt->execute([$case['coordinator_id']]);
    $coordinator = $stmt->fetch();
}

// Recent non-internal notes
$db = getDB();
$stmt = $db->prepare(
    'SELECT cn.*, u.name AS author_name
     FROM cw_case_notes cn
     JOIN cw_users u ON u.id = cn.user_id
     WHERE cn.case_id = ? AND cn.is_internal = 0
     ORDER BY cn.created_at DESC
     LIMIT 3'
);
$stmt->execute([$case['id']]);
$recentNotes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CoopConvert</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { emerald: { 600: '#059669', 700: '#047857' }, teal: { 500: '#14b8a6' } } } } }</script>
</head>
<body class="bg-gray-50 min-h-screen">

<nav class="bg-emerald-700 text-white px-6 py-4 flex items-center justify-between">
    <a href="<?= url('/dashboard.php') ?>" class="font-bold text-lg tracking-tight">CoopConvert</a>
    <div class="flex items-center gap-6 text-sm">
        <a href="<?= url('/case.php') ?>" class="hover:text-teal-300">My Case</a>
        <span class="text-emerald-300"><?= h($user['name']) ?></span>
        <a href="<?= url('/logout.php') ?>" class="hover:text-teal-300">Sign out</a>
    </div>
</nav>

<div class="max-w-4xl mx-auto px-4 py-10">

    <?php if ($flash): ?>
    <div class="mb-6 px-4 py-3 rounded-md text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= h($flash['message']) ?>
    </div>
    <?php endif; ?>

    <h1 class="text-2xl font-bold text-gray-900 mb-1">Welcome back, <?= h($user['name']) ?></h1>
    <p class="text-gray-500 mb-8"><?= h($case['business_name']) ?></p>

    <!-- Pipeline progress -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Conversion Progress</h2>
        <div class="flex items-center gap-0">
            <?php foreach ($pipeline as $key => $label):
                $idx = array_search($key, $statusOrder);
                $done = $idx < $currentIndex;
                $active = $idx === $currentIndex;
            ?>
            <div class="flex-1 flex flex-col items-center relative">
                <?php if ($idx > 0): ?>
                <div class="absolute left-0 top-4 w-1/2 h-0.5 <?= $done || $active ? 'bg-emerald-600' : 'bg-gray-200' ?>"></div>
                <?php endif; ?>
                <?php if ($idx < count($pipeline) - 1): ?>
                <div class="absolute right-0 top-4 w-1/2 h-0.5 <?= $done ? 'bg-emerald-600' : 'bg-gray-200' ?>"></div>
                <?php endif; ?>
                <div class="relative z-10 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                    <?php if ($done) echo 'bg-emerald-600 text-white';
                          elseif ($active) echo 'bg-emerald-700 text-white ring-4 ring-emerald-100';
                          else echo 'bg-gray-200 text-gray-500'; ?>">
                    <?php if ($done): ?>
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    <?php else: ?>
                    <?= $idx + 1 ?>
                    <?php endif; ?>
                </div>
                <span class="mt-2 text-xs font-medium <?= $active ? 'text-emerald-700' : ($done ? 'text-gray-600' : 'text-gray-400') ?> text-center leading-tight">
                    <?= h($label) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Next action CTA -->
    <div class="bg-emerald-700 rounded-xl p-6 mb-6 flex items-center justify-between">
        <div>
            <p class="text-emerald-200 text-sm font-medium mb-1">Next step</p>
            <p class="text-white font-semibold text-lg"><?= h($nextAction['label']) ?></p>
        </div>
        <a href="<?= url($nextAction['href']) ?>"
           class="bg-white text-emerald-700 font-semibold px-5 py-2.5 rounded-lg hover:bg-emerald-50 transition-colors whitespace-nowrap">
            Get started &rarr;
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Quick links -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Tools</h2>
            <ul class="space-y-2">
                <?php $tools = [
                    ['Structure Selector', '/structure-selector.php'],
                    ['Deal Modeler',       '/deal-modeler.php'],
                    ['Documents',          '/documents.php'],
                    ['Equity Ledger',      '/equity-ledger.php'],
                    ['Case Notes',         '/case.php'],
                ]; foreach ($tools as [$label, $href]): ?>
                <li>
                    <a href="<?= url($href) ?>"
                       class="flex items-center gap-2 text-sm text-gray-700 hover:text-emerald-700 py-1 transition-colors">
                        <span class="w-1.5 h-1.5 rounded-full bg-teal-500 inline-block"></span>
                        <?= h($label) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Coordinator + notes -->
        <div class="space-y-4">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Your Coordinator</h2>
                <?php if ($coordinator): ?>
                <p class="text-gray-800 font-medium"><?= h($coordinator['name']) ?></p>
                <p class="text-gray-500 text-sm"><?= h($coordinator['email']) ?></p>
                <?php else: ?>
                <p class="text-gray-500 text-sm italic">Not yet assigned. A coordinator will be matched to your case shortly.</p>
                <?php endif; ?>
            </div>

            <?php if ($recentNotes): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Recent Notes</h2>
                <ul class="space-y-3">
                    <?php foreach ($recentNotes as $note): ?>
                    <li class="text-sm border-l-2 border-teal-400 pl-3">
                        <p class="text-gray-700"><?= h(mb_strimwidth($note['body'], 0, 120, '…')) ?></p>
                        <p class="text-gray-400 text-xs mt-0.5"><?= h($note['author_name']) ?> &middot; <?= h(date('M j, Y', strtotime($note['created_at']))) ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= url('/case.php') ?>" class="text-emerald-600 hover:underline text-xs mt-3 inline-block">View all notes &rarr;</a>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>
</body>
</html>
