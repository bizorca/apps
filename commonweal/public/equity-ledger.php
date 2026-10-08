<?php
require __DIR__ . '/_bootstrap.php';
requireLogin();

$user = getCurrentUser();
if (!in_array($user['role'], ['client'], true)) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$case = getUserCase((int)$user['id']);
if (!$case) {
    header('Location: ' . url('/intake.php'));
    exit;
}

$db = getDB();
$errors = [];
$editMember = null;

// Load all members
function loadMembers(PDO $db, int $caseId): array {
    $stmt = $db->prepare('SELECT * FROM cw_equity_ledger WHERE case_id = ? ORDER BY created_at ASC');
    $stmt->execute([$caseId]);
    return $stmt->fetchAll();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $memberName       = trim($_POST['member_name'] ?? '');
            $memberEmail      = trim($_POST['member_email'] ?? '');
            $equityClassRaw   = $_POST['equity_class'] ?? '';
            $equityClassOther = trim($_POST['equity_class_other'] ?? '');
            $equityClass      = $equityClassRaw === 'Other' ? $equityClassOther : $equityClassRaw;
            $capitalAccount   = (float)str_replace([',','$'], '', $_POST['capital_account'] ?? '0');
            $acquisitionMethod= $_POST['acquisition_method'] ?? '';
            $acquisitionDate  = $_POST['acquisition_date'] ?? null;
            $patronageBasis   = $_POST['patronage_basis'] !== '' ? (float)$_POST['patronage_basis'] : null;
            $notes            = trim($_POST['notes'] ?? '');
            $now              = date('Y-m-d H:i:s');

            if (empty($memberName)) $errors[] = 'Member name is required.';

            if (empty($errors)) {
                if ($action === 'add') {
                    $stmt = $db->prepare(
                        'INSERT INTO cw_equity_ledger
                         (case_id, member_name, member_email, equity_class, capital_account,
                          acquisition_method, acquisition_date, patronage_basis, notes, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $stmt->execute([
                        $case['id'], $memberName, $memberEmail, $equityClass, $capitalAccount,
                        $acquisitionMethod, $acquisitionDate ?: null, $patronageBasis,
                        $notes, $now, $now,
                    ]);
                    setFlash('success', 'Member added.');
                } else {
                    $memberId = (int)($_POST['member_id'] ?? 0);
                    $stmt = $db->prepare(
                        'UPDATE cw_equity_ledger SET member_name=?, member_email=?, equity_class=?,
                         capital_account=?, acquisition_method=?, acquisition_date=?,
                         patronage_basis=?, notes=?, updated_at=?
                         WHERE id=? AND case_id=?'
                    );
                    $stmt->execute([
                        $memberName, $memberEmail, $equityClass, $capitalAccount,
                        $acquisitionMethod, $acquisitionDate ?: null, $patronageBasis,
                        $notes, $now, $memberId, $case['id'],
                    ]);
                    setFlash('success', 'Member updated.');
                }
                header('Location: ' . url('/equity-ledger.php'));
                exit;
            }

        } elseif ($action === 'delete') {
            $memberId = (int)($_POST['member_id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM cw_equity_ledger WHERE id=? AND case_id=?');
            $stmt->execute([$memberId, $case['id']]);
            setFlash('success', 'Member removed.');
            header('Location: ' . url('/equity-ledger.php'));
            exit;
        }
    }
}

// Edit mode
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM cw_equity_ledger WHERE id=? AND case_id=? LIMIT 1');
    $stmt->execute([$editId, $case['id']]);
    $editMember = $stmt->fetch() ?: null;
}

$members = loadMembers($db, $case['id']);
$flash = getFlash();

$equityClasses = ['Class A Worker', 'Class B Investor', 'Class C Community', 'Other'];
$acquisitionMethods = ['cash', 'seller_financing', 'sweat_equity', 'patronage', 'gift'];

// Patronage dividend calculation
$dividendResults = null;
if (isset($_GET['calc_surplus']) && is_numeric($_GET['calc_surplus'])) {
    $surplus = (float)$_GET['calc_surplus'];
    $totalPatronage = array_sum(array_column($members, 'patronage_basis'));
    $dividendResults = ['surplus' => $surplus, 'total_patronage' => $totalPatronage, 'rows' => []];
    foreach ($members as $m) {
        $basis = (float)$m['patronage_basis'];
        $share = $totalPatronage > 0 ? ($basis / $totalPatronage) * $surplus : 0;
        $dividendResults['rows'][] = [
            'name'    => $m['member_name'],
            'basis'   => $basis,
            'pct'     => $totalPatronage > 0 ? round(($basis / $totalPatronage) * 100, 2) : 0,
            'amount'  => $share,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equity Ledger — CoopConvert</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<nav class="bg-emerald-700 text-white px-6 py-4 flex items-center justify-between">
    <a href="<?= url('/dashboard.php') ?>" class="font-bold text-lg tracking-tight">CoopConvert</a>
    <div class="flex items-center gap-6 text-sm">
        <a href="<?= url('/dashboard.php') ?>" class="hover:text-teal-300">Dashboard</a>
        <span class="text-emerald-300"><?= h($user['name']) ?></span>
        <a href="<?= url('/logout.php') ?>" class="hover:text-teal-300">Sign out</a>
    </div>
</nav>

<div class="max-w-5xl mx-auto px-4 py-10">

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

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Equity Ledger</h1>
        <p class="text-gray-500 text-sm"><?= h($case['business_name']) ?> — post-conversion member equity tracker</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Member form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 text-sm mb-4 border-b border-gray-100 pb-2">
                    <?= $editMember ? 'Edit Member' : 'Add Member' ?>
                </h2>
                <form method="POST" action="<?= url('/equity-ledger.php' . ($editMember ? '?edit=' . (int)$editMember['id'] : '')) ?>" class="space-y-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $editMember ? 'edit' : 'add' ?>">
                    <?php if ($editMember): ?>
                    <input type="hidden" name="member_id" value="<?= (int)$editMember['id'] ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Member name *</label>
                        <input type="text" name="member_name" required
                               value="<?= h($editMember['member_name'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
                        <input type="email" name="member_email"
                               value="<?= h($editMember['member_email'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Equity class</label>
                        <select name="equity_class" id="equity_class_select"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                onchange="document.getElementById('equity_class_other_wrap').classList.toggle('hidden', this.value !== 'Other')">
                            <?php
                            $currentClass = $editMember['equity_class'] ?? '';
                            $inStandard = in_array($currentClass, array_slice($equityClasses, 0, 3));
                            foreach ($equityClasses as $ec):
                            ?>
                            <option value="<?= h($ec) ?>"
                                <?= ($currentClass === $ec || (!$inStandard && $ec === 'Other' && $editMember)) ? 'selected' : '' ?>>
                                <?= h($ec) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="equity_class_other_wrap" class="mt-2 <?= (!$inStandard && $editMember) ? '' : 'hidden' ?>">
                            <input type="text" name="equity_class_other"
                                   value="<?= h((!$inStandard && $editMember) ? $currentClass : '') ?>"
                                   placeholder="Specify class…"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Capital account ($)</label>
                        <input type="number" name="capital_account" min="0" step="0.01"
                               value="<?= h($editMember['capital_account'] ?? '0') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Acquisition method</label>
                        <select name="acquisition_method"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <?php foreach ($acquisitionMethods as $m): ?>
                            <option value="<?= h($m) ?>" <?= ($editMember['acquisition_method'] ?? '') === $m ? 'selected' : '' ?>>
                                <?= h(ucwords(str_replace('_', ' ', $m))) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Acquisition date</label>
                        <input type="date" name="acquisition_date"
                               value="<?= h($editMember['acquisition_date'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Patronage basis</label>
                        <input type="number" name="patronage_basis" min="0" step="0.01"
                               value="<?= h($editMember['patronage_basis'] ?? '') ?>"
                               placeholder="Labor hours or purchase volume"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                        <textarea name="notes" rows="2"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><?= h($editMember['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button type="submit"
                                class="flex-1 bg-emerald-700 hover:bg-emerald-600 text-white font-semibold py-2 rounded-lg text-sm transition-colors">
                            <?= $editMember ? 'Save changes' : 'Add member' ?>
                        </button>
                        <?php if ($editMember): ?>
                        <a href="<?= url('/equity-ledger.php') ?>"
                           class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors">
                            Cancel
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Member table + patronage calculator -->
        <div class="lg:col-span-2 space-y-5">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-5 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Members (<?= count($members) ?>)</h2>
                </div>
                <?php if (empty($members)): ?>
                <div class="p-8 text-center text-gray-400 text-sm">No members yet. Add one using the form.</div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold">Name</th>
                                <th class="text-left px-4 py-3 font-semibold">Equity Class</th>
                                <th class="text-right px-4 py-3 font-semibold">Capital Acct.</th>
                                <th class="text-left px-4 py-3 font-semibold">Method</th>
                                <th class="text-right px-4 py-3 font-semibold">Patronage</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($members as $m): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800"><?= h($m['member_name']) ?></p>
                                    <?php if ($m['member_email']): ?>
                                    <p class="text-gray-400 text-xs"><?= h($m['member_email']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><?= h($m['equity_class'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-right text-gray-700 font-medium">$<?= number_format((float)$m['capital_account'], 2) ?></td>
                                <td class="px-4 py-3 text-gray-600 capitalize"><?= h(str_replace('_', ' ', $m['acquisition_method'] ?? '—')) ?></td>
                                <td class="px-4 py-3 text-right text-gray-600"><?= $m['patronage_basis'] !== null ? number_format((float)$m['patronage_basis'], 2) : '—' ?></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="<?= url('/equity-ledger.php?edit=' . (int)$m['id']) ?>"
                                       class="text-emerald-600 hover:underline text-xs mr-3">Edit</a>
                                    <form method="POST" action="<?= url('/equity-ledger.php') ?>" class="inline"
                                          onsubmit="return confirm('Remove <?= h(addslashes($m['member_name'])) ?>?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:underline text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Patronage Dividend Calculator -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 mb-1">Patronage Dividend Calculator</h2>
                <p class="text-gray-500 text-xs mb-4">Calculate how a distributable surplus would be allocated among members based on patronage basis. Display only — not saved.</p>

                <form method="GET" action="<?= url('/equity-ledger.php') ?>" class="flex gap-3 items-end mb-5">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Total distributable surplus ($)</label>
                        <input type="number" name="calc_surplus" min="0" step="0.01"
                               value="<?= h($_GET['calc_surplus'] ?? '') ?>"
                               placeholder="e.g. 25000"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <button type="submit"
                            class="bg-teal-500 hover:bg-teal-600 text-white font-semibold px-4 py-2 rounded-lg text-sm transition-colors">
                        Calculate
                    </button>
                </form>

                <?php if ($dividendResults && !empty($dividendResults['rows'])): ?>
                <?php if ($dividendResults['total_patronage'] == 0): ?>
                <p class="text-amber-600 text-sm">No patronage basis values have been entered. Add patronage basis to each member first.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                            <tr>
                                <th class="text-left px-3 py-2 font-semibold">Member</th>
                                <th class="text-right px-3 py-2 font-semibold">Patronage Basis</th>
                                <th class="text-right px-3 py-2 font-semibold">% Share</th>
                                <th class="text-right px-3 py-2 font-semibold">Dividend</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($dividendResults['rows'] as $row): ?>
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-800"><?= h($row['name']) ?></td>
                                <td class="px-3 py-2 text-right text-gray-600"><?= number_format($row['basis'], 2) ?></td>
                                <td class="px-3 py-2 text-right text-gray-600"><?= number_format($row['pct'], 2) ?>%</td>
                                <td class="px-3 py-2 text-right font-semibold text-emerald-700">$<?= number_format($row['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="text-xs text-gray-500 border-t border-gray-100">
                            <tr>
                                <td class="px-3 py-2 font-semibold text-gray-700" colspan="3">Total</td>
                                <td class="px-3 py-2 text-right font-bold text-gray-800">$<?= number_format($dividendResults['surplus'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>
