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
if (!$biz) redirect('/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $db->prepare('DELETE FROM pf_revenue_streams WHERE business_id = ?')->execute([$bid]);

    $stmt = $db->prepare(
        'INSERT INTO pf_revenue_streams (business_id, stream_type, label, price, units_included, estimated_monthly_units, conversion_source, conversion_rate, is_enabled)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );

    // Helper to insert a stream
    $insert = function(string $type, string $label, float $price, ?int $units, float $monthlyUnits, ?string $convSrc = null, ?float $convRate = null, int $enabled = 1) use ($stmt, $bid) {
        if ($monthlyUnits <= 0 && $type !== 'subscription' && $type !== 'intro_offer') {
            if ($price <= 0) return;
        }
        $stmt->execute([$bid, $type, $label, $price, $units, $monthlyUnits, $convSrc, $convRate, $enabled]);
    };

    // Drop-in
    $diPrice = postFloat('dropin_price');
    $diUnits = postFloat('dropin_units');
    if ($diPrice > 0) $insert('drop_in', 'Drop-in Class', $diPrice, null, $diUnits);

    // Class passes (multiple)
    $passLabels = $_POST['pass_label']   ?? [];
    $passPrices = $_POST['pass_price']   ?? [];
    $passUnits  = $_POST['pass_units']   ?? [];  // classes included per pass
    $passSold   = $_POST['pass_sold']    ?? [];  // passes sold/month
    foreach ($passLabels as $i => $lbl) {
        $pr = (float)($passPrices[$i] ?? 0);
        $un = (int)($passUnits[$i] ?? 0);
        $sl = (float)($passSold[$i] ?? 0);
        if ($pr > 0 && $un > 0) $insert('class_pass', $lbl ?: 'Class Pass', $pr, $un, $sl);
    }

    // Subscriptions (multiple tiers)
    $subLabels = $_POST['sub_label']       ?? [];
    $subPrices = $_POST['sub_price']       ?? [];
    $subMembrs = $_POST['sub_members']     ?? [];
    foreach ($subLabels as $i => $lbl) {
        $pr = (float)($subPrices[$i] ?? 0);
        $mb = (float)($subMembrs[$i] ?? 0);
        if ($pr > 0) $insert('subscription', $lbl ?: 'Unlimited Monthly', $pr, null, $mb);
    }

    // Private sessions
    $privPrice = postFloat('private_price');
    $privUnits = postFloat('private_units');
    if ($privPrice > 0) $insert('private', 'Private Session', $privPrice, null, $privUnits);

    // Live online
    $livePrice = postFloat('live_price');
    $liveUnits = postFloat('live_units');
    if ($livePrice > 0) $insert('online_live', 'Live Online Class', $livePrice, null, $liveUnits);

    // Recorded / on-demand
    $recPrice  = postFloat('recorded_price');
    $recUnits  = postFloat('recorded_units');
    $recLabel  = trim(post('recorded_label', 'On-Demand Access'));
    if ($recPrice > 0) $insert('online_recorded', $recLabel ?: 'On-Demand Access', $recPrice, null, $recUnits);

    // Introductory offer
    $introEnabled = !empty($_POST['intro_enabled']);
    if ($introEnabled) {
        $introPrice = postFloat('intro_price');
        $introUnits = postFloat('intro_units');
        $stmt->execute([$bid, 'intro_offer', 'Intro Offer', $introPrice, null, $introUnits, null, null, 1]);

        // Conversion targets
        $convTargets = $_POST['conv_target'] ?? [];
        $convRates   = $_POST['conv_rate']   ?? [];
        foreach ($convTargets as $i => $target) {
            $rate = (float)($convRates[$i] ?? 0) / 100;
            if ($target && $rate > 0) {
                $stmt->execute([$bid, 'intro_conversion', 'Intro → ' . $target, 0, null, $introUnits * $rate, 'intro_offer', $rate, 1]);
            }
        }
    }

    flashSuccess('Revenue streams saved.');
    redirect('/wizard/instructors.php');
}

// Load existing streams
$streams = getRevenueStreams($bid);
$byType  = [];
foreach ($streams as $s) {
    $byType[$s['stream_type']][] = $s;
}

$dropIn     = $byType['drop_in'][0]      ?? null;
$passes     = $byType['class_pass']      ?? [['label' => '10-Class Pass', 'price' => 150, 'units_included' => 10, 'estimated_monthly_units' => 0]];
$subs       = $byType['subscription']    ?? [['label' => 'Unlimited Monthly', 'price' => 99, 'estimated_monthly_units' => 0]];
$private    = $byType['private'][0]      ?? null;
$liveOnline = $byType['online_live'][0]  ?? null;
$recorded   = $byType['online_recorded'][0] ?? null;
$intro      = $byType['intro_offer'][0]  ?? null;
$convRows   = $byType['intro_conversion'] ?? [];

$stepStatus  = wizardStepStatus($bid, $biz['business_type']);
$currentStep = 4;
$pageTitle   = 'Step 4: Revenue — ProForma';
include PF_ROOT . '/templates/header.php';
include PF_ROOT . '/templates/wizard_nav.php';
?>

<div class="max-w-3xl">
    <h1 class="text-xl font-bold text-gray-900 mb-1">Step 4 — Revenue Streams</h1>
    <p class="text-sm text-gray-500 mb-6">Configure every way money comes in. Enable only what you actually offer.</p>

    <form method="post" id="revenue-form">
        <?= csrf() ?>

        <!-- ================================================================
             DROP-IN
        ================================================================ -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
            <h2 class="font-semibold text-gray-800 mb-3">Drop-in Classes</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Price per class</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                        <input type="number" name="dropin_price" value="<?= h($dropIn['price'] ?? 20) ?>"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Drop-in students per month</label>
                    <input type="number" name="dropin_units" value="<?= h($dropIn['estimated_monthly_units'] ?? 0) ?>"
                           min="0" step="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- ================================================================
             CLASS PASSES
        ================================================================ -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-800">Class Passes</h2>
                <button type="button" onclick="addPassRow()"
                        class="text-xs text-indigo-600 border border-indigo-200 px-3 py-1 rounded hover:bg-indigo-50">
                    + Add pass type
                </button>
            </div>
            <div class="grid grid-cols-[2fr_100px_100px_120px_auto] text-xs font-medium text-gray-500 mb-1 gap-2 px-1">
                <span>Pass name</span><span class="text-right">Price</span><span class="text-right"># classes</span><span class="text-right">Passes sold/mo</span><span></span>
            </div>
            <div id="pass-rows">
                <?php foreach ($passes as $pass): ?>
                <div class="pass-row grid grid-cols-[2fr_100px_100px_120px_auto] gap-2 items-center mb-2">
                    <input type="text" name="pass_label[]" value="<?= h($pass['label'] ?? '10-Class Pass') ?>"
                           class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                    <div class="relative">
                        <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
                        <input type="number" name="pass_price[]" value="<?= h($pass['price'] ?? 150) ?>" min="0" step="0.01"
                               class="w-full border border-gray-200 rounded pl-5 pr-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400">
                    </div>
                    <input type="number" name="pass_units[]" value="<?= h($pass['units_included'] ?? 10) ?>" min="1" step="1"
                           class="border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                    <input type="number" name="pass_sold[]" value="<?= h($pass['estimated_monthly_units'] ?? 0) ?>" min="0" step="1"
                           class="border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                    <button type="button" onclick="this.closest('.pass-row').remove()"
                            class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-2">The report shows both cash collected (passes sold) and earned revenue (redemptions).</p>
        </div>

        <!-- ================================================================
             SUBSCRIPTIONS
        ================================================================ -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-800">Unlimited Subscriptions</h2>
                <button type="button" onclick="addSubRow()"
                        class="text-xs text-indigo-600 border border-indigo-200 px-3 py-1 rounded hover:bg-indigo-50">
                    + Add tier
                </button>
            </div>
            <div class="grid grid-cols-[2fr_120px_120px_auto] text-xs font-medium text-gray-500 mb-1 gap-2 px-1">
                <span>Tier name</span><span class="text-right">Monthly price</span><span class="text-right">Active members</span><span></span>
            </div>
            <div id="sub-rows">
                <?php foreach ($subs as $sub): ?>
                <div class="sub-row grid grid-cols-[2fr_120px_120px_auto] gap-2 items-center mb-2">
                    <input type="text" name="sub_label[]" value="<?= h($sub['label'] ?? 'Unlimited Monthly') ?>"
                           class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                    <div class="relative">
                        <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
                        <input type="number" name="sub_price[]" value="<?= h($sub['price'] ?? 99) ?>" min="0" step="0.01"
                               class="sub-price-input w-full border border-gray-200 rounded pl-5 pr-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                               oninput="updateSubMRR()">
                    </div>
                    <input type="number" name="sub_members[]" value="<?= h($sub['estimated_monthly_units'] ?? 0) ?>" min="0" step="1"
                           class="sub-members-input border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full"
                           oninput="updateSubMRR()">
                    <button type="button" onclick="this.closest('.sub-row').remove(); updateSubMRR()"
                            class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-600 mt-2">Subscription MRR: <strong id="sub-mrr">$0</strong></p>
        </div>

        <!-- ================================================================
             PRIVATE SESSIONS
        ================================================================ -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
            <h2 class="font-semibold text-gray-800 mb-3">Private Sessions</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Price per session</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                        <input type="number" name="private_price" value="<?= h($private['price'] ?? 80) ?>"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Sessions per month</label>
                    <input type="number" name="private_units" value="<?= h($private['estimated_monthly_units'] ?? 0) ?>"
                           min="0" step="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- ================================================================
             LIVE ONLINE
        ================================================================ -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
            <h2 class="font-semibold text-gray-800 mb-1">Live Online Classes</h2>
            <p class="text-xs text-gray-500 mb-3">Instructor teaches in real time — same pay structure as in-person, no physical capacity limit.</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Price per class</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                        <input type="number" name="live_price" value="<?= h($liveOnline['price'] ?? 15) ?>"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Students per month</label>
                    <input type="number" name="live_units" value="<?= h($liveOnline['estimated_monthly_units'] ?? 0) ?>"
                           min="0" step="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- ================================================================
             RECORDED / ON-DEMAND
        ================================================================ -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
            <h2 class="font-semibold text-gray-800 mb-1">Recorded / On-Demand</h2>
            <p class="text-xs text-gray-500 mb-3">Subscription access or one-time purchase. No per-class instructor cost.</p>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Label</label>
                    <input type="text" name="recorded_label" value="<?= h($recorded['label'] ?? 'On-Demand Access') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Price (monthly)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                        <input type="number" name="recorded_price" value="<?= h($recorded['price'] ?? 19) ?>"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Subscribers</label>
                    <input type="number" name="recorded_units" value="<?= h($recorded['estimated_monthly_units'] ?? 0) ?>"
                           min="0" step="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- ================================================================
             INTRODUCTORY OFFER
        ================================================================ -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
            <div class="flex items-center gap-3 mb-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="intro_enabled" id="intro-toggle"
                           <?= $intro ? 'checked' : '' ?>
                           onchange="document.getElementById('intro-body').classList.toggle('hidden', !this.checked)"
                           class="rounded border-gray-300 text-indigo-600">
                    <span class="font-semibold text-gray-800">Introductory Offer</span>
                </label>
            </div>
            <div id="intro-body" class="<?= $intro ? '' : 'hidden' ?>">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Intro price (0 = free)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                            <input type="number" name="intro_price" value="<?= h($intro['price'] ?? 30) ?>"
                                   min="0" step="0.01"
                                   class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">New intro students per month</label>
                        <input type="number" name="intro_units" value="<?= h($intro['estimated_monthly_units'] ?? 0) ?>"
                               min="0" step="1" id="intro-units"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="border border-gray-100 rounded-lg p-4 bg-gray-50">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-medium text-gray-700">Conversion splits</h3>
                        <button type="button" onclick="addConvRow()"
                                class="text-xs text-indigo-600 border border-indigo-200 px-3 py-1 rounded hover:bg-indigo-50">
                            + Add destination
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">Where do converted intro students land? Percentages should sum to 100%.</p>
                    <div class="grid grid-cols-[2fr_100px_auto] text-xs font-medium text-gray-500 mb-1 gap-2 px-1">
                        <span>Destination (e.g. "Unlimited Monthly")</span><span class="text-right">% of converts</span><span></span>
                    </div>
                    <div id="conv-rows">
                        <?php if (empty($convRows)): ?>
                        <div class="conv-row grid grid-cols-[2fr_100px_auto] gap-2 items-center mb-1.5">
                            <input type="text" name="conv_target[]" placeholder="Unlimited Monthly"
                                   class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                            <div class="relative">
                                <input type="number" name="conv_rate[]" value="50" min="0" max="100" step="1"
                                       class="conv-rate-input w-full border border-gray-200 rounded px-2 pr-6 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                       oninput="updateConvTotal()">
                                <span class="absolute right-2 top-1.5 text-gray-400 text-xs">%</span>
                            </div>
                            <button type="button" onclick="this.closest('.conv-row').remove(); updateConvTotal()"
                                    class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
                        </div>
                        <?php else: ?>
                        <?php foreach ($convRows as $cr): ?>
                        <div class="conv-row grid grid-cols-[2fr_100px_auto] gap-2 items-center mb-1.5">
                            <input type="text" name="conv_target[]" value="<?= h($cr['label'] ? str_replace('Intro → ', '', $cr['label']) : '') ?>"
                                   class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                            <div class="relative">
                                <input type="number" name="conv_rate[]" value="<?= h(round(($cr['conversion_rate'] ?? 0) * 100)) ?>"
                                       min="0" max="100" step="1"
                                       class="conv-rate-input w-full border border-gray-200 rounded px-2 pr-6 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                       oninput="updateConvTotal()">
                                <span class="absolute right-2 top-1.5 text-gray-400 text-xs">%</span>
                            </div>
                            <button type="button" onclick="this.closest('.conv-row').remove(); updateConvTotal()"
                                    class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs mt-2">
                        Total allocated: <strong id="conv-total">0</strong>%
                        <span id="conv-warning" class="text-amber-600 ml-2 hidden">Doesn't add up to 100%</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="<?= PF_BASE ?>/wizard/classes.php" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                Next: Instructors &rarr;
            </button>
        </div>
    </form>
</div>

<script>
// Subscription MRR
function updateSubMRR() {
    let mrr = 0;
    document.querySelectorAll('.sub-row').forEach(row => {
        const price   = parseFloat(row.querySelector('.sub-price-input').value)   || 0;
        const members = parseFloat(row.querySelector('.sub-members-input').value) || 0;
        mrr += price * members;
    });
    document.getElementById('sub-mrr').textContent =
        '$' + mrr.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function addSubRow() {
    const row = document.createElement('div');
    row.className = 'sub-row grid grid-cols-[2fr_120px_120px_auto] gap-2 items-center mb-2';
    row.innerHTML = `
        <input type="text" name="sub_label[]" placeholder="Tier name"
               class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        <div class="relative">
            <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
            <input type="number" name="sub_price[]" value="0" min="0" step="0.01"
                   class="sub-price-input w-full border border-gray-200 rounded pl-5 pr-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                   oninput="updateSubMRR()">
        </div>
        <input type="number" name="sub_members[]" value="0" min="0" step="1"
               class="sub-members-input border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full"
               oninput="updateSubMRR()">
        <button type="button" onclick="this.closest('.sub-row').remove(); updateSubMRR()"
                class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
    `;
    document.getElementById('sub-rows').appendChild(row);
}

function addPassRow() {
    const row = document.createElement('div');
    row.className = 'pass-row grid grid-cols-[2fr_100px_100px_120px_auto] gap-2 items-center mb-2';
    row.innerHTML = `
        <input type="text" name="pass_label[]" placeholder="Pass name"
               class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        <div class="relative">
            <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
            <input type="number" name="pass_price[]" value="0" min="0" step="0.01"
                   class="w-full border border-gray-200 rounded pl-5 pr-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400">
        </div>
        <input type="number" name="pass_units[]" value="10" min="1" step="1"
               class="border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        <input type="number" name="pass_sold[]" value="0" min="0" step="1"
               class="border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        <button type="button" onclick="this.closest('.pass-row').remove()"
                class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
    `;
    document.getElementById('pass-rows').appendChild(row);
}

// Conversion totals
function updateConvTotal() {
    let total = 0;
    document.querySelectorAll('.conv-rate-input').forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });
    document.getElementById('conv-total').textContent = total;
    const warn = document.getElementById('conv-warning');
    const hasRows = document.querySelectorAll('.conv-row').length > 0;
    warn.classList.toggle('hidden', !hasRows || total === 100);
}

function addConvRow() {
    const row = document.createElement('div');
    row.className = 'conv-row grid grid-cols-[2fr_100px_auto] gap-2 items-center mb-1.5';
    row.innerHTML = `
        <input type="text" name="conv_target[]" placeholder="Destination tier"
               class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        <div class="relative">
            <input type="number" name="conv_rate[]" value="0" min="0" max="100" step="1"
                   class="conv-rate-input w-full border border-gray-200 rounded px-2 pr-6 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                   oninput="updateConvTotal()">
            <span class="absolute right-2 top-1.5 text-gray-400 text-xs">%</span>
        </div>
        <button type="button" onclick="this.closest('.conv-row').remove(); updateConvTotal()"
                class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
    `;
    document.getElementById('conv-rows').appendChild(row);
    updateConvTotal();
}

updateSubMRR();
updateConvTotal();
</script>

<?php include PF_ROOT . '/templates/footer.php'; ?>
