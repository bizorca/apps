<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once PF_ROOT . '/includes/config.php';
require_once PF_ROOT . '/includes/helpers.php';
require_once PF_ROOT . '/includes/auth.php';
require_once PF_ROOT . '/includes/db.php';

startSession();
requireAuth();

$user       = currentUser();
$userId     = (int)$user['id'];
$db         = getDb();

// Handle switch active business
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();

    if ($_POST['action'] === 'switch' && !empty($_POST['business_id'])) {
        $bid = (int)$_POST['business_id'];
        $biz = getBusinessById($bid, $userId);
        if ($biz) {
            $_SESSION['pf_business_id'] = $bid;
            flashSuccess('Switched to ' . $biz['business_name'] . '.');
        }
        redirect('/dashboard.php');
    }

    if ($_POST['action'] === 'create') {
        $name = trim(post('business_name', ''));
        $type = post('business_type', 'yoga');
        if (!array_key_exists($type, BUSINESS_TYPES)) $type = 'yoga';
        if ($name === '') $name = BUSINESS_TYPES[$type]['label'];

        $cfg    = BUSINESS_TYPES[$type];
        $salary = $cfg['owner_salary_default']   ?? 0;
        $weeks  = $cfg['weeks_per_year_default']  ?? 50;

        $stmt = $db->prepare(
            'INSERT INTO pf_businesses (user_id, business_type, business_name, owner_salary_annual, weeks_per_year) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $name, $salary, $weeks]);
        $newId = (int)$db->lastInsertId();

        // Seed expenses
        $expStmt = $db->prepare(
            'INSERT INTO pf_expenses (business_id, category, label, amount_monthly, is_variable, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($cfg['expense_defaults'] ?? [] as $i => $row) {
            $expStmt->execute([$newId, $row['category'], $row['label'], $row['amount_monthly'], $row['is_variable'] ?? 0, $i]);
        }

        // Seed class schedules
        $schStmt = $db->prepare(
            'INSERT INTO pf_class_schedules (business_id, class_name, class_type, classes_per_week, room_capacity, avg_fill_rate) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($cfg['class_schedule_defaults'] ?? [] as $row) {
            $schStmt->execute([$newId, $row['class_name'], $row['class_type'], $row['classes_per_week'], $row['room_capacity'] ?? null, $row['avg_fill_rate']]);
        }

        // Seed revenue streams
        $revStmt = $db->prepare(
            'INSERT INTO pf_revenue_streams (business_id, stream_type, label, price, units_included, estimated_monthly_units, is_enabled) VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        foreach ($cfg['revenue_stream_defaults'] ?? [] as $row) {
            $revStmt->execute([$newId, $row['stream_type'], $row['label'], $row['price'], $row['units_included'] ?? null, $row['estimated_monthly_units'] ?? 0]);
        }

        // Seed instructors (non-therapist types)
        $instStmt = $db->prepare(
            'INSERT INTO pf_instructors (business_id, name, pay_type, pay_per_class, revenue_share_pct, classes_per_week) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($cfg['instructor_defaults'] ?? [] as $row) {
            $instStmt->execute([$newId, $row['name'], $row['pay_type'], $row['pay_per_class'], $row['revenue_share_pct'], $row['classes_per_week']]);
        }

        // Therapist-specific seeding
        if ($type === 'therapist') {
            // Payers
            $payerStmt = $db->prepare('INSERT INTO pf_insurance_payers (business_id, payer_name, client_pct, is_cash_pay, sort_order) VALUES (?,?,?,?,?)');
            $payerIds  = [];
            foreach ($cfg['payer_defaults'] ?? [] as $i => $p) {
                $payerStmt->execute([$newId, $p['payer_name'], $p['client_pct'], $p['is_cash_pay'], $i]);
                $payerIds[$i] = (int)$db->lastInsertId();
            }

            // CPT codes
            $cptStmt = $db->prepare('INSERT INTO pf_cpt_codes (business_id, code, description, sessions_per_month, sort_order) VALUES (?,?,?,?,?)');
            $cptIds  = [];
            foreach ($cfg['cpt_defaults'] ?? [] as $j => $c) {
                $cptStmt->execute([$newId, $c['code'], $c['description'], $c['sessions_per_month'], $j]);
                $cptIds[$j] = (int)$db->lastInsertId();
            }

            // Rate matrix
            $rateStmt = $db->prepare('INSERT INTO pf_payer_rates (payer_id, cpt_id, rate) VALUES (?,?,?)');
            foreach ($cfg['rate_matrix_defaults'] ?? [] as $i => $row) {
                if (!isset($payerIds[$i])) continue;
                foreach ($row as $j => $rate) {
                    if (!isset($cptIds[$j])) continue;
                    $rateStmt->execute([$payerIds[$i], $cptIds[$j], $rate]);
                }
            }

            // Staff providers
            $provStmt = $db->prepare('INSERT INTO pf_staff_providers (business_id, name, credential, sessions_per_week, hours_per_week, pay_type, pay_rate, is_owner) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($cfg['provider_defaults'] ?? [] as $p) {
                $provStmt->execute([$newId, $p['name'], $p['credential'], $p['sessions_per_week'], $p['hours_per_week'], $p['pay_type'], $p['pay_rate'], $p['is_owner']]);
            }
        }

        $_SESSION['pf_business_id'] = $newId;
        flashSuccess('Business created with sample data. Review each step and adjust to match your actual numbers.');
        redirect('/wizard/setup.php');
    }

    if ($_POST['action'] === 'clone' && !empty($_POST['business_id'])) {
        $bid = (int)$_POST['business_id'];
        $newId = cloneBusiness($bid, $userId);
        if ($newId) {
            $_SESSION['pf_business_id'] = $newId;
            flashSuccess('Business cloned. Review and adjust the copy as needed.');
            redirect('/wizard/setup.php');
        }
        redirect('/dashboard.php');
    }

    if ($_POST['action'] === 'delete' && !empty($_POST['business_id'])) {
        $bid = (int)$_POST['business_id'];
        $biz = getBusinessById($bid, $userId);
        if ($biz) {
            $db->prepare('DELETE FROM pf_businesses WHERE id = ? AND user_id = ?')->execute([$bid, $userId]);
            if (($_SESSION['pf_business_id'] ?? 0) === $bid) {
                unset($_SESSION['pf_business_id']);
            }
            flashSuccess('Business deleted.');
        }
        redirect('/dashboard.php');
    }
}

$businesses = getUserBusinesses($userId);
$activeBid  = (int)($_SESSION['pf_business_id'] ?? 0);

// Auto-select first business if none active
if (!$activeBid && count($businesses) > 0) {
    $activeBid = (int)$businesses[0]['id'];
    $_SESSION['pf_business_id'] = $activeBid;
}

// Run projections for each business card
$projections = [];
foreach ($businesses as $biz2) {
    $bid2 = (int)$biz2['id'];
    try {
        $exp2 = getExpenses($bid2);
        $sch2 = getClassSchedules($bid2);
        $str2 = getRevenueStreams($bid2);
        $ins2 = getInstructors($bid2);
        $ext2 = ['settings' => getUserSettings($userId)];
        if ($biz2['business_type'] === 'therapist') {
            $ext2['payers']    = getInsurancePayers($bid2);
            $ext2['codes']     = getCptCodes($bid2);
            $ext2['rates']     = getPayerRates($bid2);
            $ext2['providers'] = getStaffProviders($bid2);
        }
        $calc2 = getCalculator($biz2, $exp2, $sch2, $str2, $ins2, $ext2);
        $rep2  = $calc2->report();
        $projections[$bid2] = (!isset($rep2['error']) && $rep2['gross_revenue'] > 0) ? $rep2 : null;
    } catch (\Throwable $e) {
        $projections[$bid2] = null;
    }
}

$pageTitle = 'Dashboard — ProForma';
include PF_ROOT . '/templates/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Your Businesses</h1>
    <button onclick="document.getElementById('new-biz-form').classList.toggle('hidden')"
            class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors">
        + New Business
    </button>
</div>

<!-- New business form (hidden by default) -->
<div id="new-biz-form" class="hidden bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="font-semibold text-gray-800 mb-4">Create a new business</h2>
    <form method="post" class="flex flex-wrap gap-3 items-end">
        <?= csrf() ?>
        <input type="hidden" name="action" value="create">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Business name</label>
            <input type="text" name="business_name" placeholder="Downtown Flow Yoga"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none w-64">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Business type</label>
            <select name="business_type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <?php foreach (BUSINESS_TYPES as $key => $bt): ?>
                <option value="<?= h($key) ?>"><?= h($bt['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors">
            Create &amp; set up
        </button>
    </form>
</div>

<?php if (empty($businesses)): ?>
<div class="bg-white border border-dashed border-gray-300 rounded-xl p-12 text-center">
    <p class="text-gray-500 mb-4">No businesses yet. Create one to get started.</p>
    <button onclick="document.getElementById('new-biz-form').classList.remove('hidden')"
            class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors">
        Create your first business
    </button>
</div>
<?php else: ?>
<div class="grid gap-4">
    <?php foreach ($businesses as $biz):
        $bid    = (int)$biz['id'];
        $active = $bid === $activeBid;
        $status = wizardStepStatus($bid, $biz['business_type']);
        $done   = array_sum(array_slice($status, 0, 5, true)); // steps 1-5
        $proj   = $projections[$bid] ?? null;
    ?>
    <div class="bg-white border rounded-xl p-5 flex items-center justify-between gap-4
        <?= $active ? 'border-indigo-400 shadow-sm' : 'border-gray-200' ?>">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="font-semibold text-gray-900 truncate"><?= h($biz['business_name']) ?></span>
                <?php if ($active): ?>
                <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded font-medium">Active</span>
                <?php endif; ?>
            </div>
            <div class="text-xs text-gray-500">
                <?= h(BUSINESS_TYPES[$biz['business_type']]['label'] ?? $biz['business_type']) ?>
                &mdash; <?= $done ?>/5 setup steps complete
            </div>
            <?php if ($proj): ?>
            <div class="flex items-center gap-3 mt-2 flex-wrap">
                <span class="text-xs font-semibold <?= $proj['net_income'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                    Net <?= money($proj['net_income'], true) ?>/mo
                </span>
                <span class="text-gray-300 text-xs">&middot;</span>
                <span class="text-xs text-gray-500">Rev <?= money($proj['gross_revenue']) ?>/mo</span>
                <span class="text-gray-300 text-xs">&middot;</span>
                <?php if ($biz['business_type'] === 'therapist'): ?>
                <span class="text-xs text-gray-500">B/E <?= number_format(ceil((float)($proj['break_even_sessions'] ?? 0))) ?> sessions/mo</span>
                <?php else: ?>
                <span class="text-xs text-gray-500">B/E <?= pct((float)($proj['break_even_fill_rate'] ?? 0)) ?> fill</span>
                <?php endif; ?>
                <?php $oppCount = getRecommendationCount($bid); if ($oppCount > 0): ?>
                <span class="text-gray-300 text-xs">&middot;</span>
                <a href="<?= PF_BASE ?>/localrev.php" class="text-xs text-indigo-600 hover:underline"><?= $oppCount ?> revenue <?= $oppCount === 1 ? 'opportunity' : 'opportunities' ?></a>
                <?php endif; ?>
            </div>
            <?php elseif ($done >= 2): ?>
            <div class="text-xs text-gray-400 mt-2">Finish setup to see projections</div>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <?php if ($active): ?>
                <a href="<?= PF_BASE ?>/wizard/setup.php" class="text-sm text-indigo-600 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors">
                    Edit
                </a>
                <a href="<?= PF_BASE ?>/wizard/report.php" class="text-sm bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition-colors">
                    View report
                </a>
            <?php else: ?>
                <form method="post" class="inline">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="switch">
                    <input type="hidden" name="business_id" value="<?= $bid ?>">
                    <button type="submit" class="text-sm text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors">
                        Switch to this
                    </button>
                </form>
            <?php endif; ?>
            <form method="post" class="inline">
                <?= csrf() ?>
                <input type="hidden" name="action" value="clone">
                <input type="hidden" name="business_id" value="<?= $bid ?>">
                <button type="submit" class="text-sm text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors">
                    Clone
                </button>
            </form>
            <form method="post" class="inline"
                  onsubmit="return confirm('Delete <?= h(addslashes($biz['business_name'])) ?>? This cannot be undone.')">
                <?= csrf() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="business_id" value="<?= $bid ?>">
                <button type="submit" class="text-sm text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                    Delete
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include PF_ROOT . '/templates/footer.php'; ?>
