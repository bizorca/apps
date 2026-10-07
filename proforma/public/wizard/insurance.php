<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once PF_ROOT . '/includes/config.php';
require_once PF_ROOT . '/includes/helpers.php';
require_once PF_ROOT . '/includes/auth.php';
require_once PF_ROOT . '/includes/db.php';

startSession();
requireAuth();

$user   = currentUser();
$userId = (int)$user['id'];
$db     = getDb();

if (empty($_SESSION['pf_business_id'])) redirect('/dashboard.php');
$bid = (int)$_SESSION['pf_business_id'];
$biz = getBusinessById($bid, $userId);
if (!$biz || $biz['business_type'] !== 'therapist') redirect('/dashboard.php');

// ============================================================
// POST — save matrix from JSON blob
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $raw = $_POST['matrix_json'] ?? '';
    $data = json_decode($raw, true);

    if (!$data || empty($data['payers']) || empty($data['codes'])) {
        flashError('No data received. Please fill in at least one payer and one billing code.');
        redirect('/wizard/insurance.php');
    }

    // Wipe existing data
    $db->prepare('DELETE FROM pf_payer_rates WHERE payer_id IN (SELECT id FROM pf_insurance_payers WHERE business_id = ?)')->execute([$bid]);
    $db->prepare('DELETE FROM pf_insurance_payers WHERE business_id = ?')->execute([$bid]);
    $db->prepare('DELETE FROM pf_cpt_codes WHERE business_id = ?')->execute([$bid]);

    // Insert payers — collect IDs
    $payerStmt = $db->prepare('INSERT INTO pf_insurance_payers (business_id, payer_name, client_pct, is_cash_pay, sort_order) VALUES (?,?,?,?,?)');
    $payerIds  = [];
    foreach ($data['payers'] as $i => $p) {
        $name = trim($p['name'] ?? '');
        if ($name === '') continue;
        $pct  = min(1.0, max(0.0, (float)($p['pct'] ?? 0) / 100));
        $cash = !empty($p['is_cash']) ? 1 : 0;
        $payerStmt->execute([$bid, $name, $pct, $cash, $i]);
        $payerIds[$i] = (int)$db->lastInsertId();
    }

    // Insert CPT codes — collect IDs
    $cptStmt = $db->prepare('INSERT INTO pf_cpt_codes (business_id, code, description, sessions_per_month, sort_order) VALUES (?,?,?,?,?)');
    $cptIds  = [];
    foreach ($data['codes'] as $j => $c) {
        $code = trim($c['code'] ?? '');
        if ($code === '') continue;
        $cptStmt->execute([$bid, $code, trim($c['description'] ?? ''), (float)($c['sessions'] ?? 0), $j]);
        $cptIds[$j] = (int)$db->lastInsertId();
    }

    // Insert rates
    $rateStmt = $db->prepare('INSERT INTO pf_payer_rates (payer_id, cpt_id, rate) VALUES (?,?,?)');
    foreach ($data['rates'] as $iStr => $col) {
        $i = (int)$iStr;
        if (!isset($payerIds[$i])) continue;
        foreach ($col as $jStr => $rate) {
            $j = (int)$jStr;
            if (!isset($cptIds[$j])) continue;
            $rateStmt->execute([$payerIds[$i], $cptIds[$j], (float)$rate]);
        }
    }

    flashSuccess('Insurance matrix saved.');
    redirect('/wizard/providers.php');
}

// ============================================================
// Build initial JS state from DB (or defaults if new)
// ============================================================
$dbPayers = getInsurancePayers($bid);
$dbCodes  = getCptCodes($bid);

if (empty($dbPayers)) {
    // Seed from config defaults
    $cfg     = BUSINESS_TYPES['therapist'];
    $payers  = array_map(fn($p) => ['name' => $p['payer_name'], 'pct' => round($p['client_pct'] * 100), 'is_cash' => $p['is_cash_pay']], $cfg['payer_defaults']);
    $codes   = array_map(fn($c) => ['code' => $c['code'], 'description' => $c['description'], 'sessions' => $c['sessions_per_month']], $cfg['cpt_defaults']);
    $rates   = [];
    foreach ($cfg['rate_matrix_defaults'] as $i => $row) {
        foreach ($row as $j => $rate) {
            $rates[$i][$j] = $rate;
        }
    }
} else {
    $payers = array_map(fn($p) => ['name' => $p['payer_name'], 'pct' => round($p['client_pct'] * 100), 'is_cash' => (int)$p['is_cash_pay']], $dbPayers);
    $codes  = array_map(fn($c) => ['code' => $c['code'], 'description' => $c['description'], 'sessions' => $c['sessions_per_month']], $dbCodes);

    // Build rates indexed by payer position × code position
    $rates = [];
    $payerIdxMap = array_flip(array_column($dbPayers, 'id'));
    $cptIdxMap   = array_flip(array_column($dbCodes,   'id'));
    foreach (getPayerRates($bid) as $pr) {
        $i = $payerIdxMap[$pr['payer_id']] ?? null;
        $j = $cptIdxMap[$pr['cpt_id']]    ?? null;
        if ($i !== null && $j !== null) $rates[$i][$j] = $pr['rate'];
    }
}

$initialJson = json_encode(['payers' => $payers, 'codes' => $codes, 'rates' => $rates]);

$stepStatus  = wizardStepStatus($bid, 'therapist');
$currentStep = 3;
$pageTitle   = 'Step 3: Insurance — ProForma';
include PF_ROOT . '/templates/header.php';
include PF_ROOT . '/templates/wizard_nav.php';
?>

<div class="max-w-5xl">
    <div class="flex items-start justify-between mb-1">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Step 3 — Insurance Reimbursement Matrix</h1>
            <p class="text-sm text-gray-500 mt-1">
                Enter your payers across the top, your billing codes down the side, and the reimbursement rate for each combination.
                The blended rate column shows your actual weighted yield per session.
            </p>
        </div>
        <div class="text-right text-xs text-gray-400 ml-4 flex-shrink-0 mt-1">
            Pre-loaded with CT market estimates.<br>Update with your actual contract rates.
        </div>
    </div>

    <!-- Payer % validation banner -->
    <div id="pct-warning" class="hidden flash flash-error mt-4">
        Payer percentages must add up to 100%. Current total: <strong id="pct-total">0</strong>%.
    </div>
    <div id="pct-ok" class="hidden flash flash-success mt-4">
        Payer mix totals 100%. &#10003;
    </div>

    <form method="post" id="matrix-form" class="mt-4">
        <?= csrf() ?>
        <input type="hidden" name="matrix_json" id="matrix-json">

        <!-- ===============================================================
             PAYER HEADER CONTROLS (above the table)
        =============================================================== -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 mb-3">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-700">Payers &amp; Client Mix</h2>
                <button type="button" onclick="addPayer()"
                        class="text-xs text-indigo-600 border border-indigo-200 px-3 py-1.5 rounded hover:bg-indigo-50">
                    + Add payer
                </button>
            </div>
            <div id="payer-controls" class="grid gap-2">
                <!-- Rendered by JS -->
            </div>
            <p class="text-xs text-gray-400 mt-2">
                Percentages represent the share of your client panel on each insurance.
                Cash pay / self-pay is its own column — check the box to flag it.
            </p>
        </div>

        <!-- ===============================================================
             RATE MATRIX TABLE
        =============================================================== -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto mb-4">
            <div class="flex items-center justify-between px-4 pt-4 pb-2 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Reimbursement Rates by CPT Code</h2>
                <button type="button" onclick="addCode()"
                        class="text-xs text-indigo-600 border border-indigo-200 px-3 py-1.5 rounded hover:bg-indigo-50">
                    + Add billing code
                </button>
            </div>
            <div class="p-4">
                <table class="w-full text-sm border-collapse" id="rate-table">
                    <!-- Rendered by JS -->
                </table>
            </div>
        </div>

        <!-- Summary bar -->
        <div class="bg-indigo-50 border border-indigo-100 rounded-xl px-5 py-4 mb-5 text-sm">
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <div class="text-xs text-indigo-500 font-medium mb-0.5">Projected monthly sessions</div>
                    <div class="font-bold text-indigo-800" id="summary-sessions">—</div>
                </div>
                <div>
                    <div class="text-xs text-indigo-500 font-medium mb-0.5">Blended avg rate / session</div>
                    <div class="font-bold text-indigo-800" id="summary-blended">—</div>
                </div>
                <div>
                    <div class="text-xs text-indigo-500 font-medium mb-0.5">Projected monthly revenue</div>
                    <div class="font-bold text-indigo-800" id="summary-revenue">—</div>
                </div>
                <div>
                    <div class="text-xs text-indigo-500 font-medium mb-0.5">Cash-pay equivalent revenue</div>
                    <div class="font-bold text-green-700" id="summary-cashpay">—</div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="<?= PF_BASE ?>/wizard/expenses.php" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <button type="submit" onclick="serializeMatrix()"
                    class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                Next: Providers &rarr;
            </button>
        </div>
    </form>
</div>

<script>
// ================================================================
// STATE
// ================================================================
let state = <?= $initialJson ?>;
// Ensure rates object exists
if (!state.rates) state.rates = {};

// ================================================================
// RENDER
// ================================================================
function fmt(n) {
    return '$' + Number(n).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
}
function fmtDec(n) {
    return '$' + Number(n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function render() {
    renderPayerControls();
    renderMatrix();
    updateSummary();
    validatePct();
}

function renderPayerControls() {
    const container = document.getElementById('payer-controls');
    container.innerHTML = '';

    state.payers.forEach((p, i) => {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-3';
        row.innerHTML = `
            <input type="text" value="${escHtml(p.name)}" placeholder="Payer name"
                   class="flex-1 border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400"
                   oninput="state.payers[${i}].name = this.value; renderMatrix()">
            <div class="relative w-24">
                <input type="number" value="${p.pct}" min="0" max="100" step="1"
                       class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 pr-6"
                       oninput="state.payers[${i}].pct = +this.value; validatePct(); updateSummary()">
                <span class="absolute right-2 top-1.5 text-gray-400 text-xs">%</span>
            </div>
            <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer whitespace-nowrap">
                <input type="checkbox" ${p.is_cash ? 'checked' : ''}
                       onchange="state.payers[${i}].is_cash = this.checked ? 1 : 0; renderMatrix()"
                       class="rounded border-gray-300 text-indigo-600">
                Cash pay
            </label>
            <button type="button" onclick="removePayer(${i})"
                    class="text-red-400 hover:text-red-600 text-lg leading-none px-1 flex-shrink-0">&times;</button>
        `;
        container.appendChild(row);
    });
}

function renderMatrix() {
    const table = document.getElementById('rate-table');
    if (!state.payers.length || !state.codes.length) {
        table.innerHTML = '<tr><td class="text-gray-400 text-sm py-4 text-center" colspan="99">Add at least one payer and one billing code.</td></tr>';
        return;
    }

    let html = '<thead><tr class="bg-gray-50">';
    html += '<th class="text-left px-2 py-2 text-xs font-semibold text-gray-500 w-20 border-b border-gray-200">CPT Code</th>';
    html += '<th class="text-left px-2 py-2 text-xs font-semibold text-gray-500 border-b border-gray-200">Description</th>';
    html += '<th class="text-right px-2 py-2 text-xs font-semibold text-gray-500 w-20 border-b border-gray-200">Sess/mo</th>';
    state.payers.forEach((p, i) => {
        const cashBadge = p.is_cash ? ' <span class="text-indigo-500 font-normal">(cash)</span>' : '';
        html += `<th class="text-right px-2 py-2 text-xs font-semibold text-gray-500 w-24 border-b border-gray-200 whitespace-nowrap">${escHtml(p.name || 'Payer ' + (i+1))}${cashBadge}</th>`;
    });
    html += '<th class="text-right px-2 py-2 text-xs font-semibold text-indigo-600 w-24 border-b border-gray-200">Blended</th>';
    html += '<th class="w-6 border-b border-gray-200"></th>';
    html += '</tr></thead><tbody>';

    state.codes.forEach((c, j) => {
        html += `<tr class="border-b border-gray-50 hover:bg-gray-50">`;
        html += `<td class="px-2 py-1.5">
            <input type="text" value="${escHtml(c.code)}" placeholder="90837"
                   class="w-full border border-gray-200 rounded px-2 py-1 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-indigo-400"
                   oninput="state.codes[${j}].code = this.value; renderPayerControls()">
        </td>`;
        html += `<td class="px-2 py-1.5">
            <input type="text" value="${escHtml(c.description)}" placeholder="Description"
                   class="w-full border border-gray-200 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400"
                   oninput="state.codes[${j}].description = this.value">
        </td>`;
        html += `<td class="px-2 py-1.5">
            <input type="number" value="${c.sessions}" min="0" step="1"
                   class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                   oninput="state.codes[${j}].sessions = +this.value; updateSummary()">
        </td>`;
        state.payers.forEach((p, i) => {
            const rate = (state.rates[i] && state.rates[i][j] != null) ? state.rates[i][j] : '';
            const cashCls = p.is_cash ? 'bg-indigo-50' : '';
            html += `<td class="px-2 py-1.5 ${cashCls}">
                <div class="relative">
                    <span class="absolute left-2 top-1 text-gray-400 text-xs">$</span>
                    <input type="number" value="${rate}" min="0" step="0.01" placeholder="—"
                           class="w-full border border-gray-200 rounded pl-5 pr-1 py-1 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                           oninput="setRate(${i}, ${j}, +this.value)">
                </div>
            </td>`;
        });
        // Blended rate cell
        html += `<td class="px-2 py-1.5 text-right text-sm font-semibold text-indigo-700" id="blended-${j}">—</td>`;
        html += `<td class="px-1 py-1.5">
            <button type="button" onclick="removeCode(${j})"
                    class="text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
        </td>`;
        html += '</tr>';
    });

    html += '</tbody>';
    table.innerHTML = html;
    updateBlended();
}

function setRate(i, j, val) {
    if (!state.rates[i]) state.rates[i] = {};
    state.rates[i][j] = val;
    updateBlended();
    updateSummary();
}

function blendedRateForCode(j) {
    let totalPct = 0, weightedRate = 0;
    state.payers.forEach((p, i) => {
        const rate = state.rates[i] && state.rates[i][j] != null ? +state.rates[i][j] : 0;
        const pct  = +p.pct / 100;
        weightedRate += rate * pct;
        totalPct     += pct;
    });
    return totalPct > 0 ? weightedRate / totalPct : 0;
}

function cashPayRateForCode(j) {
    let rate = 0;
    state.payers.forEach((p, i) => {
        if (p.is_cash) {
            rate = state.rates[i] && state.rates[i][j] != null ? +state.rates[i][j] : 0;
        }
    });
    return rate;
}

function updateBlended() {
    state.codes.forEach((c, j) => {
        const cell = document.getElementById('blended-' + j);
        if (!cell) return;
        const blended = blendedRateForCode(j);
        cell.textContent = blended > 0 ? fmtDec(blended) : '—';
    });
}

function updateSummary() {
    let totalSessions = 0, totalRevenue = 0, totalCashRevenue = 0;
    state.codes.forEach((c, j) => {
        const sess    = +c.sessions || 0;
        const blended = blendedRateForCode(j);
        const cash    = cashPayRateForCode(j);
        totalSessions    += sess;
        totalRevenue     += sess * blended;
        totalCashRevenue += sess * cash;
    });
    const blendedAvg = totalSessions > 0 ? totalRevenue / totalSessions : 0;
    document.getElementById('summary-sessions').textContent = totalSessions.toLocaleString();
    document.getElementById('summary-blended').textContent  = blendedAvg > 0 ? fmtDec(blendedAvg) : '—';
    document.getElementById('summary-revenue').textContent  = fmt(totalRevenue);
    document.getElementById('summary-cashpay').textContent  = fmt(totalCashRevenue);
}

function validatePct() {
    const total = state.payers.reduce((sum, p) => sum + (+p.pct || 0), 0);
    document.getElementById('pct-total').textContent = total;
    document.getElementById('pct-warning').classList.toggle('hidden', Math.abs(total - 100) < 0.5);
    document.getElementById('pct-ok').classList.toggle('hidden',      Math.abs(total - 100) >= 0.5);
}

// ================================================================
// MUTATIONS
// ================================================================
function addPayer() {
    state.payers.push({name: '', pct: 0, is_cash: 0});
    render();
}
function removePayer(i) {
    state.payers.splice(i, 1);
    // Remap rates
    const newRates = {};
    Object.keys(state.rates).forEach(k => {
        const ki = +k;
        if (ki < i)       newRates[ki]   = state.rates[k];
        else if (ki > i)  newRates[ki-1] = state.rates[k];
    });
    state.rates = newRates;
    render();
}
function addCode() {
    state.codes.push({code: '', description: '', sessions: 0});
    render();
}
function removeCode(j) {
    state.codes.splice(j, 1);
    // Remap rates
    const newRates = {};
    Object.keys(state.rates).forEach(i => {
        newRates[i] = {};
        Object.keys(state.rates[i]).forEach(k => {
            const kj = +k;
            if (kj < j)       newRates[i][kj]   = state.rates[i][k];
            else if (kj > j)  newRates[i][kj-1] = state.rates[i][k];
        });
    });
    state.rates = newRates;
    render();
}

function serializeMatrix() {
    document.getElementById('matrix-json').value = JSON.stringify(state);
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Initial render
render();
</script>

<?php include PF_ROOT . '/templates/footer.php'; ?>
