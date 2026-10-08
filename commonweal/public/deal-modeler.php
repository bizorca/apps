<?php
require __DIR__ . '/_bootstrap.php';
require_once CW_ROOT . '/includes/modeling.php';
require_once CW_ROOT . '/includes/structures.php';
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

// Load latest structure assessment
$stmt = $db->prepare('SELECT * FROM cw_structure_assessments WHERE case_id = ? ORDER BY completed_at DESC LIMIT 1');
$stmt->execute([$case['id']]);
$assessment = $stmt->fetch() ?: null;

$errors = [];
$savedModel = null;

// Load model if requested
$loadModelId = isset($_GET['model_id']) ? (int)$_GET['model_id'] : 0;
if ($loadModelId) {
    $stmt = $db->prepare('SELECT * FROM cw_deal_models WHERE id = ? AND case_id = ? LIMIT 1');
    $stmt->execute([$loadModelId, $case['id']]);
    $savedModel = $stmt->fetch() ?: null;
}

// All scenarios for this case
$stmt = $db->prepare('SELECT * FROM cw_deal_models WHERE case_id = ? ORDER BY created_at DESC');
$stmt->execute([$case['id']]);
$allScenarios = $stmt->fetchAll();

$formValues = $savedModel ? [
    'scenario_label'        => $savedModel['scenario_label'],
    'business_valuation'    => $savedModel['business_valuation'],
    'seller_note_amount'    => $savedModel['seller_note_amount'],
    'seller_note_rate'      => $savedModel['seller_note_rate'] * 100,
    'seller_note_term_years'=> $savedModel['seller_note_term_years'],
    'cdfi_loan_amount'      => $savedModel['cdfi_loan_amount'],
    'cdfi_loan_rate'        => $savedModel['cdfi_loan_rate'] * 100,
    'cdfi_loan_term_years'  => $savedModel['cdfi_loan_term_years'],
    'member_count'          => $savedModel['member_count'],
] : [];

$outputs = $savedModel ? json_decode($savedModel['outputs'], true) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $formValues = [
            'scenario_label'        => trim($_POST['scenario_label'] ?? 'Scenario 1'),
            'business_valuation'    => (float)str_replace([',','$'], '', $_POST['business_valuation'] ?? '0'),
            'seller_note_amount'    => (float)str_replace([',','$'], '', $_POST['seller_note_amount'] ?? '0'),
            'seller_note_rate'      => (float)($_POST['seller_note_rate'] ?? '0'),
            'seller_note_term_years'=> (int)($_POST['seller_note_term_years'] ?? 5),
            'cdfi_loan_amount'      => (float)str_replace([',','$'], '', $_POST['cdfi_loan_amount'] ?? '0'),
            'cdfi_loan_rate'        => (float)($_POST['cdfi_loan_rate'] ?? '0'),
            'cdfi_loan_term_years'  => (int)($_POST['cdfi_loan_term_years'] ?? 10),
            'member_count'          => (int)($_POST['member_count'] ?? 1),
        ];

        if ($formValues['business_valuation'] <= 0) $errors[] = 'Business valuation must be greater than zero.';
        if ($formValues['member_count'] < 1)        $errors[] = 'Member count must be at least 1.';

        if (empty($errors)) {
            $sellerRate = $formValues['seller_note_rate'] / 100;
            $cdfiRate   = $formValues['cdfi_loan_rate'] / 100;

            $sellerMonthly = monthlyPayment(
                $formValues['seller_note_amount'], $sellerRate, $formValues['seller_note_term_years']
            );
            $cdfiMonthly = monthlyPayment(
                $formValues['cdfi_loan_amount'], $cdfiRate, $formValues['cdfi_loan_term_years']
            );
            $totalMonthly   = $sellerMonthly + $cdfiMonthly;
            $perMemberMonthly = $formValues['member_count'] > 0
                ? $totalMonthly / $formValues['member_count']
                : 0;

            $cashFlow = buildCashFlowTable(
                $formValues['seller_note_amount'], $sellerRate, $formValues['seller_note_term_years'],
                $formValues['cdfi_loan_amount'], $cdfiRate, $formValues['cdfi_loan_term_years'],
                $formValues['member_count']
            );

            $outputsArr = [
                'seller_monthly'    => $sellerMonthly,
                'cdfi_monthly'      => $cdfiMonthly,
                'total_monthly'     => $totalMonthly,
                'per_member_monthly'=> $perMemberMonthly,
                'cash_flow'         => $cashFlow,
            ];

            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare(
                'INSERT INTO cw_deal_models
                 (case_id, scenario_label, business_valuation, seller_note_amount, seller_note_rate,
                  seller_note_term_years, cdfi_loan_amount, cdfi_loan_rate, cdfi_loan_term_years,
                  member_count, outputs, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $case['id'],
                $formValues['scenario_label'],
                $formValues['business_valuation'],
                $formValues['seller_note_amount'],
                $sellerRate,
                $formValues['seller_note_term_years'],
                $formValues['cdfi_loan_amount'],
                $cdfiRate,
                $formValues['cdfi_loan_term_years'],
                $formValues['member_count'],
                json_encode($outputsArr),
                $now,
            ]);
            $newModelId = (int)$db->lastInsertId();

            // Advance status
            if ($case['status'] === 'modeling') {
                $stmt = $db->prepare('UPDATE cw_cases SET status=\'documents\', updated_at=? WHERE id=?');
                $stmt->execute([$now, $case['id']]);
            }

            setFlash('success', 'Scenario saved.');
            header('Location: ' . url('/deal-modeler.php?model_id=' . $newModelId));
            exit;
        }
    }
}

$flash = getFlash();
$structures = STRUCTURES;
$recommendedKey = $assessment['recommended_structure'] ?? null;
$recommendedName = $recommendedKey ? ($structures[$recommendedKey]['short'] ?? $recommendedKey) : null;

$termOptions = [3, 5, 7, 10, 15];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal Modeler — CoopConvert</title>
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

<div class="max-w-4xl mx-auto px-4 py-10">

    <?php if ($flash): ?>
    <div class="mb-6 px-4 py-3 rounded-md text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= h($flash['message']) ?>
    </div>
    <?php endif; ?>

    <div class="mb-8 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 mb-1">Deal Modeler</h1>
            <p class="text-gray-500 text-sm">Build and compare financing scenarios for your conversion.</p>
        </div>
        <?php if ($recommendedName): ?>
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 text-sm text-emerald-800 text-right whitespace-nowrap">
            <span class="text-emerald-600 text-xs block">Recommended structure</span>
            <?= h($recommendedName) ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($errors): ?>
    <div class="mb-6 bg-red-50 border border-red-200 rounded-md px-4 py-3">
        <ul class="list-disc list-inside text-red-600 text-sm space-y-0.5">
            <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- Form -->
        <div class="lg:col-span-2">
            <form method="POST" action="<?= url('/deal-modeler.php') ?>" class="space-y-5">
                <?= csrfField() ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                    <h2 class="font-semibold text-gray-800 text-sm border-b border-gray-100 pb-2">Scenario</h2>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Label</label>
                        <input type="text" name="scenario_label"
                               value="<?= h($formValues['scenario_label'] ?? 'Scenario 1') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Business valuation ($)</label>
                        <input type="number" name="business_valuation" min="0" step="1000"
                               value="<?= h($formValues['business_valuation'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Buying members</label>
                        <input type="number" name="member_count" min="1" step="1"
                               value="<?= h($formValues['member_count'] ?? '5') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                    <h2 class="font-semibold text-gray-800 text-sm border-b border-gray-100 pb-2">Seller Note</h2>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Amount ($)</label>
                        <input type="number" name="seller_note_amount" min="0" step="1000"
                               value="<?= h($formValues['seller_note_amount'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Rate (%)</label>
                            <input type="number" name="seller_note_rate" min="0" max="30" step="0.25"
                                   value="<?= h($formValues['seller_note_rate'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Term (yrs)</label>
                            <select name="seller_note_term_years"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <?php foreach ([3,5,7,10] as $yr): ?>
                                <option value="<?= $yr ?>" <?= (int)($formValues['seller_note_term_years'] ?? 5) === $yr ? 'selected' : '' ?>><?= $yr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                    <h2 class="font-semibold text-gray-800 text-sm border-b border-gray-100 pb-2">CDFI Loan</h2>
                    <p class="text-xs text-gray-400">Craft3 and Community Capital Development typically offer 4-7% for co-op conversions.</p>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Amount ($)</label>
                        <input type="number" name="cdfi_loan_amount" min="0" step="1000"
                               value="<?= h($formValues['cdfi_loan_amount'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Rate (%)</label>
                            <input type="number" name="cdfi_loan_rate" min="0" max="30" step="0.25"
                                   value="<?= h($formValues['cdfi_loan_rate'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Term (yrs)</label>
                            <select name="cdfi_loan_term_years"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <?php foreach ([5,7,10,15] as $yr): ?>
                                <option value="<?= $yr ?>" <?= (int)($formValues['cdfi_loan_term_years'] ?? 10) === $yr ? 'selected' : '' ?>><?= $yr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-emerald-700 hover:bg-emerald-600 text-white font-semibold py-3 rounded-lg transition-colors">
                    Save &amp; Calculate
                </button>
            </form>
        </div>

        <!-- Results -->
        <div class="lg:col-span-3 space-y-5">

            <?php if ($outputs): ?>

            <!-- Payment summary -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="font-semibold text-gray-800 mb-4">Monthly payments — <?= h($savedModel['scenario_label']) ?></h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <?php
                    $cards = [
                        ['Seller Note', formatCurrencyDecimal($outputs['seller_monthly'])],
                        ['CDFI Loan',   formatCurrencyDecimal($outputs['cdfi_monthly'])],
                        ['Total',       formatCurrencyDecimal($outputs['total_monthly'])],
                        ['Per Member',  formatCurrencyDecimal($outputs['per_member_monthly'])],
                    ];
                    foreach ($cards as [$label, $val]):
                    ?>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <p class="text-gray-500 text-xs mb-1"><?= h($label) ?></p>
                        <p class="font-bold text-gray-900 text-lg"><?= h($val) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Cash flow table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 overflow-x-auto">
                <h2 class="font-semibold text-gray-800 mb-4">5-Year cash flow summary</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs uppercase border-b border-gray-100">
                            <th class="text-left pb-2 font-semibold">Year</th>
                            <th class="text-right pb-2 font-semibold">Annual Service</th>
                            <th class="text-right pb-2 font-semibold">Seller Bal.</th>
                            <th class="text-right pb-2 font-semibold">CDFI Bal.</th>
                            <th class="text-right pb-2 font-semibold">Interest Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($outputs['cash_flow'] as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 font-medium text-gray-700">Year <?= (int)$row['year'] ?></td>
                            <td class="py-2 text-right text-gray-700"><?= h(formatCurrency($row['total_annual'])) ?></td>
                            <td class="py-2 text-right text-gray-600"><?= h(formatCurrency($row['seller_note_balance'])) ?></td>
                            <td class="py-2 text-right text-gray-600"><?= h(formatCurrency($row['cdfi_balance'])) ?></td>
                            <td class="py-2 text-right text-gray-500"><?= h(formatCurrency($row['interest_paid'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center text-gray-400">
                <p class="text-sm">Fill in the form and click "Save &amp; Calculate" to see your results here.</p>
            </div>
            <?php endif; ?>

            <!-- All saved scenarios -->
            <?php if ($allScenarios): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 text-sm mb-3">Saved scenarios</h2>
                <ul class="divide-y divide-gray-50">
                    <?php foreach ($allScenarios as $sc): ?>
                    <li class="py-2 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-800"><?= h($sc['scenario_label']) ?></p>
                            <p class="text-xs text-gray-400"><?= h(date('M j, Y g:ia', strtotime($sc['created_at']))) ?> &middot; <?= h(formatCurrency((float)$sc['business_valuation'])) ?> valuation</p>
                        </div>
                        <a href="<?= url('/deal-modeler.php?model_id=' . (int)$sc['id']) ?>"
                           class="text-emerald-600 hover:underline text-sm font-medium">Load</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($outputs): ?>
            <div class="flex justify-end">
                <a href="<?= url('/documents.php') ?>"
                   class="bg-emerald-700 hover:bg-emerald-600 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors">
                    Generate documents &rarr;
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
