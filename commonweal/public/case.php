<?php
require __DIR__ . '/_bootstrap.php';
requireLogin();

$user       = getCurrentUser();
$db         = getDB();
$isAdmin    = isAdmin();
$isCoord    = isCoordinator();
$isClient   = $user['role'] === 'client';

// Resolve which case to load
$case = null;

if ($isClient) {
    // Client: load their own case
    $case = getUserCase((int)$user['id']);
    if (!$case) {
        header('Location: ' . url('/intake.php'));
        exit;
    }
} elseif ($isAdmin) {
    // Admin: can view any case
    if (!isset($_GET['id'])) {
        header('Location: ' . url('/admin.php'));
        exit;
    }
    $stmt = $db->prepare(
        'SELECT cw_cases.*, cw_businesses.name AS business_name, cw_businesses.id AS business_id,
                cw_businesses.industry, cw_businesses.business_type
         FROM cw_cases
         JOIN cw_businesses ON cw_businesses.id = cw_cases.business_id
         WHERE cw_cases.id = ? LIMIT 1'
    );
    $stmt->execute([(int)$_GET['id']]);
    $case = $stmt->fetch() ?: null;
    if (!$case) {
        setFlash('error', 'Case not found.');
        header('Location: ' . url('/admin.php'));
        exit;
    }
} elseif ($isCoord) {
    // Coordinator: must be assigned or admin
    if (!isset($_GET['id'])) {
        header('Location: ' . url('/coordinator.php'));
        exit;
    }
    $stmt = $db->prepare(
        'SELECT cw_cases.*, cw_businesses.name AS business_name, cw_businesses.id AS business_id,
                cw_businesses.industry, cw_businesses.business_type
         FROM cw_cases
         JOIN cw_businesses ON cw_businesses.id = cw_cases.business_id
         WHERE cw_cases.id = ? LIMIT 1'
    );
    $stmt->execute([(int)$_GET['id']]);
    $case = $stmt->fetch() ?: null;
    if (!$case || ($case['coordinator_id'] != getCurrentUserId())) {
        setFlash('error', 'You are not assigned to that case.');
        header('Location: ' . url('/coordinator.php'));
        exit;
    }
}

if (!$case) {
    setFlash('error', 'Case not found.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

// Load coordinator info
$coordinator = null;
if (!empty($case['coordinator_id'])) {
    $stmt = $db->prepare('SELECT id, name, email FROM cw_users WHERE id = ? LIMIT 1');
    $stmt->execute([$case['coordinator_id']]);
    $coordinator = $stmt->fetch() ?: null;
}

// Load client info
$stmt = $db->prepare(
    'SELECT u.name, u.email FROM cw_users u
     JOIN cw_businesses b ON b.user_id = u.id
     WHERE b.id = ? LIMIT 1'
);
$stmt->execute([$case['business_id']]);
$clientUser = $stmt->fetch() ?: null;

// Pipeline
$pipeline = [
    'intake'              => 'Intake',
    'structure_selection' => 'Structure',
    'modeling'            => 'Deal Model',
    'documents'           => 'Documents',
    'review'              => 'Review',
    'complete'            => 'Complete',
];
$statusOrder = array_keys($pipeline);
$currentIndex = array_search($case['status'], $statusOrder);
if ($currentIndex === false) $currentIndex = 0;

$nextStatusMap = [
    'intake'              => 'structure_selection',
    'structure_selection' => 'modeling',
    'modeling'            => 'documents',
    'documents'           => 'review',
    'review'              => 'complete',
];
$nextStatus = $nextStatusMap[$case['status']] ?? null;

$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        $now    = date('Y-m-d H:i:s');

        if ($action === 'add_note') {
            $body       = trim($_POST['body'] ?? '');
            $isInternal = 0;

            if ($isCoord || $isAdmin) {
                $isInternal = !empty($_POST['is_internal']) ? 1 : 0;
            }

            if (empty($body)) {
                $errors[] = 'Note body cannot be empty.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO cw_case_notes (case_id, user_id, body, is_internal, created_at)
                     VALUES (?,?,?,?,?)'
                );
                $stmt->execute([$case['id'], getCurrentUserId(), $body, $isInternal, $now]);
                setFlash('success', 'Note added.');
                $redirect = $isClient ? '/case.php' : '/case.php?id=' . (int)$case['id'];
                header('Location: ' . url($redirect));
                exit;
            }

        } elseif ($action === 'advance_status' && ($isCoord || $isAdmin)) {
            if ($nextStatus) {
                $stmt = $db->prepare('UPDATE cw_cases SET status=?, updated_at=? WHERE id=?');
                $stmt->execute([$nextStatus, $now, $case['id']]);
                setFlash('success', 'Case advanced to ' . ($pipeline[$nextStatus] ?? $nextStatus) . '.');
            }
            $redirect = $isClient ? '/case.php' : '/case.php?id=' . (int)$case['id'];
            header('Location: ' . url($redirect));
            exit;

        } elseif ($action === 'assign_self' && ($isCoord || $isAdmin)) {
            if (empty($case['coordinator_id'])) {
                $stmt = $db->prepare('UPDATE cw_cases SET coordinator_id=?, updated_at=? WHERE id=?');
                $stmt->execute([getCurrentUserId(), $now, $case['id']]);
                setFlash('success', 'You have been assigned to this case.');
            }
            header('Location: ' . url('/case.php?id=' . (int)$case['id']));
            exit;
        }
    }
}

$flash = getFlash();

// Load notes
$notesQuery = ($isCoord || $isAdmin)
    ? 'SELECT cn.*, u.name AS author_name, u.role AS author_role FROM cw_case_notes cn JOIN cw_users u ON u.id = cn.user_id WHERE cn.case_id = ? ORDER BY cn.created_at ASC'
    : 'SELECT cn.*, u.name AS author_name, u.role AS author_role FROM cw_case_notes cn JOIN cw_users u ON u.id = cn.user_id WHERE cn.case_id = ? AND cn.is_internal = 0 ORDER BY cn.created_at ASC';
$stmt = $db->prepare($notesQuery);
$stmt->execute([$case['id']]);
$notes = $stmt->fetchAll();

// Next action for status
$nextActionMap = [
    'intake'              => ['Complete intake',               '/intake.php'],
    'structure_selection' => ['Select a structure',            '/structure-selector.php'],
    'modeling'            => ['Model the deal',                '/deal-modeler.php'],
    'documents'           => ['Generate documents',            '/documents.php'],
    'review'              => ['Review complete — finalize', null],
    'complete'            => ['Case complete',                 null],
];
$nextAction = $nextActionMap[$case['status']] ?? null;

$statusColors = [
    'intake'              => 'bg-gray-100 text-gray-600',
    'structure_selection' => 'bg-blue-100 text-blue-700',
    'modeling'            => 'bg-purple-100 text-purple-700',
    'documents'           => 'bg-yellow-100 text-yellow-700',
    'review'              => 'bg-orange-100 text-orange-700',
    'complete'            => 'bg-emerald-100 text-emerald-700',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case — <?= h($case['business_name']) ?> — CoopConvert</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<nav class="bg-emerald-700 text-white px-6 py-4 flex items-center justify-between">
    <a href="<?= url($isClient ? '/dashboard.php' : ($isAdmin ? '/admin.php' : '/coordinator.php')) ?>" class="font-bold text-lg tracking-tight">CoopConvert</a>
    <div class="flex items-center gap-6 text-sm">
        <?php if ($isClient): ?>
        <a href="<?= url('/dashboard.php') ?>" class="hover:text-teal-300">Dashboard</a>
        <?php elseif ($isCoord): ?>
        <a href="<?= url('/coordinator.php') ?>" class="hover:text-teal-300">My Cases</a>
        <?php elseif ($isAdmin): ?>
        <a href="<?= url('/admin.php') ?>" class="hover:text-teal-300">Admin</a>
        <?php endif; ?>
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

    <?php if ($errors): ?>
    <div class="mb-6 bg-red-50 border border-red-200 rounded-md px-4 py-3">
        <ul class="list-disc list-inside text-red-600 text-sm space-y-0.5">
            <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Case header -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-start justify-between gap-4 mb-5">
            <div>
                <h1 class="text-xl font-bold text-gray-900"><?= h($case['business_name']) ?></h1>
                <?php if ($clientUser): ?>
                <p class="text-gray-500 text-sm mt-0.5"><?= h($clientUser['name']) ?> &middot; <?= h($clientUser['email']) ?></p>
                <?php endif; ?>
            </div>
            <span class="px-3 py-1 rounded-full text-sm font-semibold <?= $statusColors[$case['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                <?= h(ucfirst(str_replace('_', ' ', $case['status']))) ?>
            </span>
        </div>

        <!-- Pipeline -->
        <div class="flex items-center gap-0 mb-5">
            <?php foreach ($pipeline as $key => $label):
                $idx   = array_search($key, $statusOrder);
                $done  = $idx < $currentIndex;
                $active= $idx === $currentIndex;
            ?>
            <div class="flex-1 flex flex-col items-center relative">
                <?php if ($idx > 0): ?>
                <div class="absolute left-0 top-3.5 w-1/2 h-0.5 <?= $done || $active ? 'bg-emerald-600' : 'bg-gray-200' ?>"></div>
                <?php endif; ?>
                <?php if ($idx < count($pipeline) - 1): ?>
                <div class="absolute right-0 top-3.5 w-1/2 h-0.5 <?= $done ? 'bg-emerald-600' : 'bg-gray-200' ?>"></div>
                <?php endif; ?>
                <div class="relative z-10 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                    <?php if ($done) echo 'bg-emerald-600 text-white';
                          elseif ($active) echo 'bg-emerald-700 text-white ring-4 ring-emerald-100';
                          else echo 'bg-gray-200 text-gray-500'; ?>">
                    <?php if ($done): ?>
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    <?php else: ?>
                    <?= $idx + 1 ?>
                    <?php endif; ?>
                </div>
                <span class="mt-1 text-xs font-medium <?= $active ? 'text-emerald-700' : ($done ? 'text-gray-600' : 'text-gray-400') ?> text-center leading-tight"><?= h($label) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Coordinator row -->
        <div class="flex items-center justify-between text-sm border-t border-gray-50 pt-4">
            <div>
                <span class="text-gray-500 mr-2">Coordinator:</span>
                <?php if ($coordinator): ?>
                <span class="font-medium text-gray-800"><?= h($coordinator['name']) ?></span>
                <?php else: ?>
                <span class="text-gray-400 italic">Unassigned</span>
                <?php endif; ?>
            </div>
            <?php if (($isCoord || $isAdmin) && empty($case['coordinator_id'])): ?>
            <form method="POST" action="<?= url('/case.php?id=' . (int)$case['id']) ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="assign_self">
                <button type="submit" class="text-emerald-600 hover:underline text-sm font-medium">Assign to me</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Notes thread + form -->
        <div class="md:col-span-2 space-y-5">

            <!-- Add note form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 text-sm mb-3">Add a note</h2>
                <form method="POST" action="<?= url('/case.php' . (!$isClient ? '?id=' . (int)$case['id'] : '')) ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_note">
                    <textarea name="body" rows="3" required
                              placeholder="Write your note here…"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 mb-3"></textarea>
                    <?php if ($isCoord || $isAdmin): ?>
                    <label class="flex items-center gap-2 text-sm text-gray-600 mb-3 cursor-pointer">
                        <input type="checkbox" name="is_internal" value="1" class="accent-emerald-600">
                        Internal note (not visible to client)
                    </label>
                    <?php endif; ?>
                    <button type="submit"
                            class="bg-emerald-700 hover:bg-emerald-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                        Post note
                    </button>
                </form>
            </div>

            <!-- Notes list -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 text-sm mb-4">Notes (<?= count($notes) ?>)</h2>
                <?php if (empty($notes)): ?>
                <p class="text-gray-400 text-sm italic">No notes yet.</p>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($notes as $note): ?>
                    <div class="<?= $note['is_internal'] ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50 border-gray-100' ?> border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2 gap-2">
                            <span class="font-semibold text-gray-800 text-sm"><?= h($note['author_name']) ?></span>
                            <div class="flex items-center gap-2">
                                <?php if ($note['is_internal'] && ($isCoord || $isAdmin)): ?>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">Internal</span>
                                <?php endif; ?>
                                <span class="text-gray-400 text-xs"><?= h(date('M j, Y g:ia', strtotime($note['created_at']))) ?></span>
                            </div>
                        </div>
                        <p class="text-gray-700 text-sm whitespace-pre-line"><?= h($note['body']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Sidebar: quick links + coordinator actions -->
        <div class="space-y-4">

            <!-- Next step -->
            <?php if ($nextAction && $nextAction[1]): ?>
            <div class="bg-emerald-700 rounded-xl p-4 text-white">
                <p class="text-emerald-200 text-xs mb-1">Next step</p>
                <p class="font-semibold text-sm mb-3"><?= h($nextAction[0]) ?></p>
                <a href="<?= url($nextAction[1]) ?>"
                   class="block text-center bg-white text-emerald-700 font-semibold text-sm px-3 py-2 rounded-lg hover:bg-emerald-50 transition-colors">
                    Go &rarr;
                </a>
            </div>
            <?php endif; ?>

            <!-- Quick links -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Tools</h2>
                <ul class="space-y-1.5">
                    <?php $tools = [
                        ['Structure Selector', '/structure-selector.php'],
                        ['Deal Modeler',       '/deal-modeler.php'],
                        ['Documents',          '/documents.php'],
                        ['Equity Ledger',      '/equity-ledger.php'],
                    ]; foreach ($tools as [$label, $href]): ?>
                    <li>
                        <a href="<?= url($href) ?>"
                           class="flex items-center gap-2 text-sm text-gray-700 hover:text-emerald-700 py-0.5 transition-colors">
                            <span class="w-1.5 h-1.5 rounded-full bg-teal-500 inline-block"></span>
                            <?= h($label) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Coordinator actions -->
            <?php if (($isCoord || $isAdmin) && $nextStatus): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Coordinator Actions</h2>
                <form method="POST" action="<?= url('/case.php?id=' . (int)$case['id']) ?>"
                      onsubmit="return confirm('Advance case to <?= h(ucfirst(str_replace('_', ' ', $nextStatus))) ?>?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="advance_status">
                    <button type="submit"
                            class="w-full bg-gray-800 hover:bg-gray-700 text-white text-sm font-semibold py-2 rounded-lg transition-colors">
                        Mark as <?= h(ucfirst(str_replace('_', ' ', $nextStatus))) ?> &rarr;
                    </button>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>
</body>
</html>
