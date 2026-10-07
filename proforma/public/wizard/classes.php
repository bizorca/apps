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

    $db->prepare('DELETE FROM pf_class_schedules WHERE business_id = ?')->execute([$bid]);

    $names     = $_POST['class_name']       ?? [];
    $types     = $_POST['class_type']       ?? [];
    $perWeeks  = $_POST['classes_per_week'] ?? [];
    $caps      = $_POST['room_capacity']    ?? [];
    $fills     = $_POST['avg_fill_rate']    ?? [];

    $stmt = $db->prepare(
        'INSERT INTO pf_class_schedules (business_id, class_name, class_type, classes_per_week, room_capacity, avg_fill_rate) VALUES (?,?,?,?,?,?)'
    );
    foreach ($names as $i => $name) {
        $name     = trim($name);
        $type     = $types[$i] ?? 'in_person';
        $perWeek  = max(0, (int)($perWeeks[$i] ?? 0));
        $capacity = ($type === 'online_live') ? null : (int)($caps[$i] ?? 20);
        $fill     = min(1.0, max(0, (float)($fills[$i] ?? 0.6) / 100));
        if ($name === '' && $perWeek === 0) continue;
        $stmt->execute([$bid, $name ?: 'General Class', $type, $perWeek, $capacity, $fill]);
    }

    flashSuccess('Class schedule saved.');
    redirect('/wizard/revenue.php');
}

$schedules   = getClassSchedules($bid);
$stepStatus  = wizardStepStatus($bid, $biz['business_type']);
$currentStep = 3;
$weeksPerMonth = $biz['weeks_per_year'] / 12;
$pageTitle   = 'Step 3: Classes — ProForma';
include PF_ROOT . '/templates/header.php';
include PF_ROOT . '/templates/wizard_nav.php';
?>

<div class="max-w-3xl">
    <h1 class="text-xl font-bold text-gray-900 mb-1">Step 3 — Class Schedule</h1>
    <p class="text-sm text-gray-500 mb-6">Define how many classes you offer and how full you expect them to be.</p>

    <form method="post">
        <?= csrf() ?>

        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-4">
            <div class="grid grid-cols-[2fr_1fr_100px_120px_130px_auto] text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200 px-4 py-2 gap-2">
                <span>Class name</span>
                <span>Type</span>
                <span class="text-right">Per week</span>
                <span class="text-right">Capacity</span>
                <span>Fill rate</span>
                <span></span>
            </div>

            <div id="class-rows">
                <?php
                $rows = count($schedules) > 0 ? $schedules : [
                    ['class_name' => 'General Class', 'class_type' => 'in_person', 'classes_per_week' => 10, 'room_capacity' => 20, 'avg_fill_rate' => 0.6]
                ];
                foreach ($rows as $row):
                    $fillPct = round(($row['avg_fill_rate'] ?? 0.6) * 100);
                    $isOnline = ($row['class_type'] ?? '') === 'online_live';
                ?>
                <div class="class-row grid grid-cols-[2fr_1fr_100px_120px_130px_auto] gap-2 items-center px-4 py-3 border-b border-gray-100 last:border-0">
                    <input type="text" name="class_name[]" value="<?= h($row['class_name'] ?? 'General Class') ?>"
                           placeholder="Class name"
                           class="border border-gray-200 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                    <select name="class_type[]" onchange="toggleCapacity(this)"
                            class="border border-gray-200 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <option value="in_person"   <?= !$isOnline ? 'selected' : '' ?>>In-person</option>
                        <option value="online_live" <?= $isOnline  ? 'selected' : '' ?>>Live online</option>
                    </select>
                    <input type="number" name="classes_per_week[]" value="<?= h($row['classes_per_week'] ?? 10) ?>"
                           min="0" step="1"
                           class="border border-gray-200 rounded px-2 py-1 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                    <div class="capacity-wrap <?= $isOnline ? 'opacity-25' : '' ?>">
                        <input type="number" name="room_capacity[]" value="<?= h($row['room_capacity'] ?? 20) ?>"
                               min="1" step="1" <?= $isOnline ? 'disabled' : '' ?>
                               class="border border-gray-200 rounded px-2 py-1 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                    </div>
                    <div class="flex items-center gap-1.5">
                        <input type="range" name="avg_fill_rate[]" value="<?= $fillPct ?>"
                               min="0" max="100" step="1"
                               oninput="this.nextElementSibling.textContent = this.value + '%'"
                               class="fill-slider flex-1 accent-indigo-600">
                        <span class="text-xs text-gray-600 w-8 text-right"><?= $fillPct ?>%</span>
                    </div>
                    <button type="button" onclick="removeRow(this)"
                            class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm text-gray-600 mb-4" id="student-summary">
            <!-- Updated live via JS -->
        </div>

        <div class="flex items-center justify-between mb-6">
            <button type="button" onclick="addRow()"
                    class="text-sm text-indigo-600 border border-indigo-200 px-4 py-2 rounded-lg hover:bg-indigo-50 transition-colors">
                + Add class type
            </button>
        </div>

        <div class="flex items-center justify-between">
            <a href="<?= PF_BASE ?>/wizard/expenses.php" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                Next: Revenue &rarr;
            </button>
        </div>
    </form>
</div>

<template id="class-row-template">
    <div class="class-row grid grid-cols-[2fr_1fr_100px_120px_130px_auto] gap-2 items-center px-4 py-3 border-b border-gray-100 last:border-0">
        <input type="text" name="class_name[]" placeholder="Class name"
               class="border border-gray-200 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        <select name="class_type[]" onchange="toggleCapacity(this)"
                class="border border-gray-200 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <option value="in_person">In-person</option>
            <option value="online_live">Live online</option>
        </select>
        <input type="number" name="classes_per_week[]" value="5" min="0" step="1"
               class="border border-gray-200 rounded px-2 py-1 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        <div class="capacity-wrap">
            <input type="number" name="room_capacity[]" value="20" min="1" step="1"
                   class="border border-gray-200 rounded px-2 py-1 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        </div>
        <div class="flex items-center gap-1.5">
            <input type="range" name="avg_fill_rate[]" value="60" min="0" max="100" step="1"
                   oninput="this.nextElementSibling.textContent = this.value + '%'"
                   class="fill-slider flex-1 accent-indigo-600">
            <span class="text-xs text-gray-600 w-8 text-right">60%</span>
        </div>
        <button type="button" onclick="removeRow(this)"
                class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
    </div>
</template>

<script>
const weeksPerMonth = <?= number_format($weeksPerMonth, 4) ?>;

function toggleCapacity(select) {
    const wrap = select.closest('.class-row').querySelector('.capacity-wrap');
    const inp  = wrap.querySelector('input');
    if (select.value === 'online_live') {
        wrap.classList.add('opacity-25');
        inp.disabled = true;
    } else {
        wrap.classList.remove('opacity-25');
        inp.disabled = false;
    }
    updateSummary();
}

function addRow() {
    const tpl   = document.getElementById('class-row-template');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('class-rows').appendChild(clone);
    updateSummary();
}

function removeRow(btn) {
    btn.closest('.class-row').remove();
    updateSummary();
}

function updateSummary() {
    let totalClasses = 0, totalStudents = 0;
    document.querySelectorAll('.class-row').forEach(row => {
        const perWeek   = parseFloat(row.querySelector('[name="classes_per_week[]"]').value) || 0;
        const cap       = parseFloat(row.querySelector('[name="room_capacity[]"]').value)    || 0;
        const fill      = parseFloat(row.querySelector('[name="avg_fill_rate[]"]').value)    || 0;
        const classType = row.querySelector('[name="class_type[]"]').value;
        const monthlyClasses = perWeek * weeksPerMonth;
        totalClasses += monthlyClasses;
        if (classType !== 'online_live') {
            totalStudents += monthlyClasses * cap * (fill / 100);
        }
    });
    const avgPerClass = totalClasses > 0 ? (totalStudents / totalClasses) : 0;
    document.getElementById('student-summary').innerHTML =
        `Projected: <strong>${Math.round(totalClasses)}</strong> classes/month &mdash; ` +
        `<strong>${Math.round(totalStudents)}</strong> in-person student visits/month &mdash; ` +
        `<strong>${avgPerClass.toFixed(1)}</strong> avg students/class`;
}

updateSummary();

// Bind fill slider labels for pre-rendered rows
document.querySelectorAll('.fill-slider').forEach(sl => {
    sl.addEventListener('input', updateSummary);
});
document.querySelectorAll('[name="classes_per_week[]"], [name="room_capacity[]"]').forEach(el => {
    el.addEventListener('input', updateSummary);
});
document.querySelectorAll('[name="class_type[]"]').forEach(el => {
    el.addEventListener('change', updateSummary);
});
</script>

<?php include PF_ROOT . '/templates/footer.php'; ?>
