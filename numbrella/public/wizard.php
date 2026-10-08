<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once NB_ROOT . '/includes/multiples.php';

$user   = requireLogin();
$userId = (int) $user['id'];
$db     = getDB();
$step   = max(1, min(3, (int) ($_GET['step'] ?? 1)));
// The forms carry the id as a hidden field; the old page read only the query
// string, so re-saving step 1 of an existing draft created a duplicate report.
$id     = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

// ── Load or verify ownership ───────────────────────────────────────────────
$report = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM nb_reports WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $report = $stmt->fetch() ?: null;
    if (!$report) {
        notFound();
    }
    // A bought report is final. While reports are free, editing one simply
    // re-runs the valuation when step 3 is saved again.
    if ($report['status'] === 'complete' && NB_PAYMENTS_ENABLED) {
        redirect('/report.php?id=' . $id);
    }
}

/** A blank field is NULL ("not provided"); anything else is parsed. */
function amountOrNull(mixed $raw): ?float
{
    return trim((string) $raw) === '' ? null : parseAmount($raw);
}

/**
 * Save wizard fields. Changing a finished report's inputs sends it back to
 * draft until step 3 re-runs the valuation: the report page reads some figures
 * from these columns and the rest from the stored valuation, so they must not
 * drift apart.
 */
function updateReport(PDO $db, array $data, int $id, int $userId, bool $backToDraft = true): void
{
    if ($backToDraft) {
        $data['status'] = 'draft';
    }
    $setClauses = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
    $stmt = $db->prepare("UPDATE nb_reports SET $setClauses WHERE id = ? AND user_id = ?");
    $stmt->execute([...array_values($data), $id, $userId]);
}

// ── POST handler ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf'] ?? null)) {
        setFlash('error', 'Invalid form submission. Please try again.');
        redirect('/wizard.php?step=' . $step . ($id ? '&id=' . $id : ''));
    }

    $postStep = (int) ($_POST['step'] ?? $step);

    if ($report && $report['status'] === 'complete' && $postStep < 3) {
        require_once NB_ROOT . '/includes/pdf.php';
        deleteReportPdf($userId, $id);
    }

    if ($postStep === 1) {
        $asking = amountOrNull($_POST['asking_price'] ?? '');
        $data = [
            'business_name'      => mb_substr(trim((string) ($_POST['business_name'] ?? '')), 0, 255),
            'industry_key'       => (string) ($_POST['industry_key'] ?? 'other'),
            'years_in_operation' => max(0, min(200, (int) ($_POST['years_in_operation'] ?? 0))),
            'num_employees'      => max(0, min(65000, (int) ($_POST['num_employees'] ?? 0))),
            'business_type'      => (string) ($_POST['business_type'] ?? 'llc'),
            'asking_price'       => $asking !== null && $asking > 0 ? $asking : null,
            'wizard_step'        => max((int) ($report['wizard_step'] ?? 1), 2),
        ];

        if (!array_key_exists($data['industry_key'], getIndustryMultiples())) {
            $data['industry_key'] = 'other';
        }
        if (!array_key_exists($data['business_type'], NB_BUSINESS_TYPES)) {
            $data['business_type'] = 'llc';
        }

        if ($id > 0) {
            updateReport($db, $data, $id, $userId);
        } else {
            $data['user_id'] = $userId;
            $columns      = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO nb_reports ($columns) VALUES ($placeholders)")->execute(array_values($data));
            $id = (int) $db->lastInsertId();
        }

        redirect('/wizard.php?step=2&id=' . $id);
    }

    if ($postStep === 2 && $id > 0) {
        $addbackDescs   = (array) ($_POST['addback_desc']   ?? []);
        $addbackAmounts = (array) ($_POST['addback_amount'] ?? []);
        $addbacks = [];
        foreach ($addbackDescs as $i => $desc) {
            $desc = mb_substr(trim((string) $desc), 0, 200);
            $amt  = parseAmount($addbackAmounts[$i] ?? '0');
            if ($desc !== '' && $amt > 0) {
                $addbacks[] = ['desc' => $desc, 'amount' => $amt];
            }
        }

        $data = [
            'revenue_y1'    => amountOrNull($_POST['revenue_y1']    ?? ''),
            'revenue_y2'    => amountOrNull($_POST['revenue_y2']    ?? ''),
            'revenue_y3'    => amountOrNull($_POST['revenue_y3']    ?? ''),
            'net_profit_y1' => amountOrNull($_POST['net_profit_y1'] ?? ''),
            'net_profit_y2' => amountOrNull($_POST['net_profit_y2'] ?? ''),
            'net_profit_y3' => amountOrNull($_POST['net_profit_y3'] ?? ''),
            'owner_salary'  => max(0.0, parseAmount($_POST['owner_salary'] ?? '0')),
            'addbacks_json' => $addbacks ? json_encode($addbacks) : null,
        ];

        // The most recent year is the one thing the valuation cannot do without.
        if (($data['revenue_y3'] ?? 0) <= 0) {
            updateReport($db, $data, $id, $userId);
            setFlash('error', 'Enter revenue for the most recent full year. Every method starts from it.');
            redirect('/wizard.php?step=2&id=' . $id);
        }
        $data['net_profit_y3'] ??= 0.0;
        $data['wizard_step'] = max((int) ($report['wizard_step'] ?? 1), 3);

        updateReport($db, $data, $id, $userId);
        redirect('/wizard.php?step=3&id=' . $id);
    }

    if ($postStep === 3 && $id > 0 && (int) $report['wizard_step'] >= 3) {
        $data = [
            'customer_concentration_pct' => max(0, min(100, (int) ($_POST['customer_concentration_pct'] ?? 0))),
            'has_written_contracts'      => isset($_POST['has_written_contracts']) ? 1 : 0,
            'owner_works_full_time'      => isset($_POST['owner_works_full_time']) ? 1 : 0,
            'has_key_employees'          => isset($_POST['has_key_employees']) ? 1 : 0,
            'lease_years_remaining'      => max(0, min(99, (int) ($_POST['lease_years_remaining'] ?? 0))),
            'notes'                      => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 5000),
            'wizard_step'                => 3,
        ];
        updateReport($db, $data, $id, $userId, false);

        if (NB_PAYMENTS_ENABLED) {
            redirect('/checkout.php?id=' . $id);
        }

        // Free: every report is the full report.
        $stmt = $db->prepare('SELECT * FROM nb_reports WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        finalizeReport($stmt->fetch(), 'premium');
        redirect('/report.php?id=' . $id);
    }

    // Fallback
    redirect('/wizard.php?step=' . $step . ($id ? '&id=' . $id : ''));
}

// ── Redirect to correct step if out of sync ────────────────────────────────
if ($report && $step > (int) $report['wizard_step']) {
    redirect('/wizard.php?step=' . (int) $report['wizard_step'] . '&id=' . $id);
}
if (!$report && $step > 1) {
    redirect('/wizard.php?step=1');
}

// ── Helpers ────────────────────────────────────────────────────────────────
$flash        = getFlash();
$industries   = getIndustryOptions();
$businessTypes = NB_BUSINESS_TYPES;

$addbacks = [];
if (!empty($report['addbacks_json'])) {
    $addbacks = json_decode((string) $report['addbacks_json'], true) ?? [];
}

$stepLabels = ['Business Info', 'Financials', 'Market Position'];

function stepUrl(int $n, int $id): string
{
    return url('/wizard.php?step=' . $n . ($id ? '&id=' . $id : ''));
}

$completedStep = (int)($report['wizard_step'] ?? 1) - 1;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Valuation — <?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<?php include NB_ROOT . '/templates/header.php'; ?>

<main class="max-w-2xl mx-auto px-4 py-10">

    <!-- Step indicator -->
    <nav class="flex items-center gap-0 mb-10" aria-label="Progress">
        <?php foreach ($stepLabels as $i => $label):
            $n       = $i + 1;
            $active  = $n === $step;
            $done    = $n < $step || $n <= $completedStep;
            $canLink = $done && $id > 0;
        ?>
        <div class="flex items-center <?= $i > 0 ? 'flex-1' : '' ?>">
            <?php if ($i > 0): ?>
                <div class="flex-1 h-0.5 <?= $done ? 'bg-indigo-600' : 'bg-gray-200' ?>"></div>
            <?php endif; ?>
            <?php if ($canLink): ?>
                <a href="<?= h(stepUrl($n, $id)) ?>" class="flex flex-col items-center group">
            <?php else: ?>
                <span class="flex flex-col items-center">
            <?php endif; ?>
                <span class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold
                    <?= $active  ? 'bg-indigo-600 text-white ring-2 ring-indigo-600 ring-offset-2' :
                       ($done    ? 'bg-indigo-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-400') ?>">
                    <?= $done && !$active ? '✓' : $n ?>
                </span>
                <span class="mt-1 text-xs font-medium <?= $active ? 'text-indigo-600' : ($done ? 'text-gray-600' : 'text-gray-400') ?>"><?= h($label) ?></span>
            <?php if ($canLink): ?></a><?php else: ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </nav>

    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-lg <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200' ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- ── STEP 1: Business Basics ────────────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Tell us about the business</h1>
        <p class="text-gray-500 text-sm mb-8">Basic information about what you're evaluating.</p>

        <form method="POST" action="<?= h(url('/wizard.php?step=1' . ($id ? '&id=' . $id : ''))) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="step" value="1">
            <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

            <div class="space-y-6">

                <div>
                    <label for="business_name" class="block text-sm font-medium text-gray-700 mb-1">Business name or description</label>
                    <input type="text" id="business_name" name="business_name"
                        value="<?= h($report['business_name'] ?? '') ?>"
                        placeholder="e.g. Main Street Auto Repair"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    <p class="mt-1 text-xs text-gray-400">Used only for your report — not shared.</p>
                </div>

                <div>
                    <label for="industry_key" class="block text-sm font-medium text-gray-700 mb-1">Industry</label>
                    <select id="industry_key" name="industry_key" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white">
                        <?php foreach ($industries as $key => $label): ?>
                            <option value="<?= h($key) ?>" <?= ($report['industry_key'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Determines which valuation multiples are applied.</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="years_in_operation" class="block text-sm font-medium text-gray-700 mb-1">Years in operation</label>
                        <input type="number" id="years_in_operation" name="years_in_operation" min="0" max="100"
                            value="<?= h($report['years_in_operation'] ?? '') ?>"
                            placeholder="e.g. 8"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                    <div>
                        <label for="num_employees" class="block text-sm font-medium text-gray-700 mb-1">Number of employees</label>
                        <input type="number" id="num_employees" name="num_employees" min="0"
                            value="<?= h($report['num_employees'] ?? '') ?>"
                            placeholder="e.g. 4"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <p class="mt-1 text-xs text-gray-400">Include owner(s).</p>
                    </div>
                </div>

                <div>
                    <label for="business_type" class="block text-sm font-medium text-gray-700 mb-1">Entity type</label>
                    <select id="business_type" name="business_type"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white">
                        <?php foreach ($businessTypes as $val => $label): ?>
                            <option value="<?= h($val) ?>" <?= ($report['business_type'] ?? 'llc') === $val ? 'selected' : '' ?>>
                                <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="asking_price" class="block text-sm font-medium text-gray-700 mb-1">
                        Asking price <span class="font-normal text-gray-400">(optional)</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                        <input type="text" id="asking_price" name="asking_price"
                            value="<?= $report && $report['asking_price'] ? h(number_format((float)$report['asking_price'], 0)) : '' ?>"
                            placeholder="e.g. 350,000"
                            class="w-full pl-7 border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                    <p class="mt-1 text-xs text-gray-400">If provided, the report will flag whether it's above, below, or within the estimated range.</p>
                </div>

            </div>

            <div class="mt-8 flex justify-end">
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                    Continue to Financials →
                </button>
            </div>
        </form>
    </div>

    <?php elseif ($step === 2): ?>
    <!-- ── STEP 2: Financials ─────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Financial history</h1>
        <p class="text-gray-500 text-sm mb-2">Up to three years of revenue and net profit, plus the owner's pay.</p>
        <p class="text-xs text-gray-400 mb-8">Use figures from tax returns or P&amp;L statements. The most recent year is required; leave earlier years blank if the business is younger than that, and they'll be left out rather than counted as zero.</p>

        <form method="POST" action="<?= h(url('/wizard.php?step=2' . ($id ? '&id=' . $id : ''))) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="step" value="2">
            <input type="hidden" name="id" value="<?= $id ?>">

            <!-- Revenue table -->
            <div class="mb-8">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Annual Revenue</h2>
                <div class="space-y-3">
                    <?php
                    $yearLabels = [
                        'y1' => '3 years ago',
                        'y2' => '2 years ago',
                        'y3' => 'Most recent full year',
                    ];
                    foreach ($yearLabels as $yk => $yl):
                    ?>
                    <div class="flex items-center gap-4">
                        <label class="w-44 text-sm text-gray-600 shrink-0"><?= h($yl) ?></label>
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                            <input type="text" name="revenue_<?= $yk ?>"
                                value="<?= $report && $report['revenue_' . $yk] !== null ? h(number_format((float)$report['revenue_' . $yk], 0)) : '' ?>"
                                placeholder="0"
                                class="w-full pl-7 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Net profit table -->
            <div class="mb-8">
                <h2 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Net Profit</h2>
                <p class="text-xs text-gray-400 mb-4">The bottom line as reported: Schedule C line 31, or ordinary business income on an 1120-S or 1065, or the P&amp;L's net income. Don't add anything back yet. Enter a loss as a negative number.</p>
                <div class="space-y-3">
                    <?php foreach ($yearLabels as $yk => $yl): ?>
                    <div class="flex items-center gap-4">
                        <label class="w-44 text-sm text-gray-600 shrink-0"><?= h($yl) ?></label>
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                            <input type="text" name="net_profit_<?= $yk ?>"
                                value="<?= $report && $report['net_profit_' . $yk] !== null ? h(number_format((float)$report['net_profit_' . $yk], 0)) : '' ?>"
                                placeholder="0"
                                class="w-full pl-7 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Owner compensation -->
            <div class="mb-8">
                <h2 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Owner Pay Already Deducted</h2>
                <p class="text-xs text-gray-400 mb-4">Only pay that was taken as an <em>expense</em> before the net profit above: an S-corp owner's W-2 wages, a partner's guaranteed payments, an officer's salary. <strong>Sole proprietor or single-member LLC? Enter 0.</strong> Draws are not an expense on Schedule C, so they are already inside the net profit, and adding them again would overstate the business.</p>
                <div class="flex items-center gap-4">
                    <label class="w-44 text-sm text-gray-600 shrink-0">Owner wages deducted</label>
                    <div class="relative flex-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                        <input type="text" name="owner_salary"
                            value="<?= $report && $report['owner_salary'] ? h(number_format((float)$report['owner_salary'], 0)) : '' ?>"
                            placeholder="0"
                            class="w-full pl-7 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                </div>
            </div>

            <!-- Add-backs -->
            <div class="mb-8">
                <h2 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Add-backs <span class="font-normal normal-case text-gray-400">(optional)</span></h2>
                <p class="text-xs text-gray-400 mb-4">Non-recurring or personal expenses run through the business that a new owner wouldn't incur — e.g., a personal vehicle, a family member's salary, or a one-time legal expense. These are added back to compute true SDE.</p>

                <div id="addbacks-container" class="space-y-2 mb-3">
                    <?php if (empty($addbacks)): ?>
                    <div class="addback-row flex gap-2">
                        <input type="text" name="addback_desc[]" placeholder="Description (e.g. Owner's vehicle)"
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        <div class="relative w-36">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                            <input type="text" name="addback_amount[]" placeholder="0"
                                class="w-full pl-7 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <button type="button" onclick="removeAddback(this)" class="text-gray-400 hover:text-red-500 text-xl leading-none px-1">×</button>
                    </div>
                    <?php else: ?>
                        <?php foreach ($addbacks as $ab): ?>
                        <div class="addback-row flex gap-2">
                            <input type="text" name="addback_desc[]" value="<?= h($ab['desc']) ?>" placeholder="Description"
                                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                            <div class="relative w-36">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                <input type="text" name="addback_amount[]" value="<?= h(number_format((float)$ab['amount'], 0)) ?>" placeholder="0"
                                    class="w-full pl-7 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>
                            <button type="button" onclick="removeAddback(this)" class="text-gray-400 hover:text-red-500 text-xl leading-none px-1">×</button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addAddback()"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    + Add another add-back
                </button>
            </div>

            <div class="mt-8 flex justify-between">
                <a href="<?= h(url('/wizard.php?step=1&id=' . $id)) ?>"
                    class="text-sm text-gray-500 hover:text-gray-700 font-medium py-2.5 px-4">
                    ← Back
                </a>
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                    Continue to Market Position →
                </button>
            </div>
        </form>
    </div>

    <?php elseif ($step === 3): ?>
    <!-- ── STEP 3: Market Position ────────────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Market position & risk factors</h1>
        <p class="text-gray-500 text-sm mb-8">These qualitative inputs drive the risk section of your report.</p>

        <form method="POST" action="<?= h(url('/wizard.php?step=3' . ($id ? '&id=' . $id : ''))) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="step" value="3">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="space-y-8">

                <!-- Customer concentration -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        What percentage of revenue comes from your single largest customer?
                    </label>
                    <p class="text-xs text-gray-400 mb-3">If revenue is spread across hundreds of customers with no single dominant one, enter 0–5%.</p>
                    <div class="flex items-center gap-4">
                        <input type="range" name="customer_concentration_pct" id="concentration_range"
                            min="0" max="100" step="5"
                            value="<?= (int)($report['customer_concentration_pct'] ?? 0) ?>"
                            oninput="document.getElementById('concentration_val').textContent = this.value + '%'"
                            class="flex-1 accent-indigo-600">
                        <span id="concentration_val" class="w-12 text-right text-sm font-semibold text-gray-700">
                            <?= (int)($report['customer_concentration_pct'] ?? 0) ?>%
                        </span>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>0% — spread evenly</span>
                        <span>100% — one customer</span>
                    </div>
                </div>

                <!-- Yes/no questions -->
                <div class="space-y-5">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="has_written_contracts"
                            <?= ($report['has_written_contracts'] ?? 0) ? 'checked' : '' ?>
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-700">Customer relationships are backed by written contracts</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Signed agreements, recurring service contracts, or subscription agreements — not just verbal understandings.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="owner_works_full_time"
                            <?= ($report['owner_works_full_time'] ?? 1) ? 'checked' : '' ?>
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-700">The current owner works in the business full-time</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Check this if the owner is actively involved in day-to-day operations, client relationships, or production.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="has_key_employees"
                            <?= ($report['has_key_employees'] ?? 0) ? 'checked' : '' ?>
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-700">There are key employees beyond the owner who could carry the business through a transition</span>
                            <span class="block text-xs text-gray-400 mt-0.5">A general manager, lead technician, or long-tenured employee who holds operational knowledge.</span>
                        </span>
                    </label>
                </div>

                <!-- Lease -->
                <div>
                    <label for="lease_years" class="block text-sm font-semibold text-gray-700 mb-1">
                        How many years remain on the current lease?
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Enter 0 if the business has no physical location or owns its building.</p>
                    <div class="flex items-center gap-3">
                        <input type="number" id="lease_years" name="lease_years_remaining" min="0" max="30"
                            value="<?= (int)($report['lease_years_remaining'] ?? 0) ?>"
                            class="w-28 border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        <span class="text-sm text-gray-500">years</span>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-semibold text-gray-700 mb-1">
                        Anything else worth noting? <span class="font-normal text-gray-400">(optional)</span>
                    </label>
                    <textarea id="notes" name="notes" rows="4"
                        placeholder="Pending litigation, a key supplier relationship, unusual revenue spike, recent equipment purchases, etc."
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none resize-y"><?= h($report['notes'] ?? '') ?></textarea>
                </div>

            </div>

            <div class="mt-8 flex justify-between">
                <a href="<?= h(url('/wizard.php?step=2&id=' . $id)) ?>"
                    class="text-sm text-gray-500 hover:text-gray-700 font-medium py-2.5 px-4">
                    ← Back
                </a>
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                    <?= NB_PAYMENTS_ENABLED ? 'Continue to Checkout →' : ($report && $report['status'] === 'complete' ? 'Update Report →' : 'Generate Report →') ?>
                </button>
            </div>
        </form>
    </div>

    <?php endif; ?>

</main>

<script>
function addAddback() {
    const container = document.getElementById('addbacks-container');
    const row = document.createElement('div');
    row.className = 'addback-row flex gap-2';
    row.innerHTML = `
        <input type="text" name="addback_desc[]" placeholder="Description"
            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        <div class="relative w-36">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
            <input type="text" name="addback_amount[]" placeholder="0"
                class="w-full pl-7 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <button type="button" onclick="removeAddback(this)" class="text-gray-400 hover:text-red-500 text-xl leading-none px-1">×</button>
    `;
    container.appendChild(row);
}

function removeAddback(btn) {
    const row = btn.closest('.addback-row');
    const container = document.getElementById('addbacks-container');
    // Keep at least one row
    if (container.querySelectorAll('.addback-row').length > 1) {
        row.remove();
    } else {
        row.querySelectorAll('input').forEach(i => i.value = '');
    }
}
</script>

</body>
</html>
