<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';

$user = auth_require();
$id   = (int)($_GET['id'] ?? 0);

// Verify access
$stmt = db()->prepare("SELECT c.* FROM tb_companies c JOIN tb_user_companies uc ON uc.company_id = c.id WHERE c.id = ? AND uc.user_id = ?");
$stmt->execute([$id, $user['id']]);
$company = $stmt->fetch();
if (!$company) { header('Location: index.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name   = trim($_POST['name'] ?? '');
    $type   = $_POST['type'] ?? $company['type'];
    $method = $_POST['accounting_method'] ?? $company['accounting_method'];
    $fy     = (int)($_POST['fiscal_year_start'] ?? 1);

    if (!$name) {
        $error = 'Name required.';
    } else {
        db()->prepare("UPDATE tb_companies SET name = ?, type = ?, accounting_method = ?, fiscal_year_start = ? WHERE id = ?")
            ->execute([$name, $type, $method, $fy, $id]);
        flash_set('success', 'Company updated.');
        header('Location: ' . APP_URL . '/companies/index.php');
        exit;
    }
}

$months = array_combine(range(1,12), array_map(fn($m) => date('F', mktime(0,0,0,$m,1)), range(1,12)));

$page_title = 'Edit Company';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="max-w-lg">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Company</h1>

    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-3 text-sm rounded mb-4"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6">
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" required value="<?= h($_POST['name'] ?? $company['name']) ?>"
                    class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="for_profit" <?= $company['type'] === 'for_profit' ? 'selected' : '' ?>>For-Profit</option>
                        <option value="non_profit"  <?= $company['type'] === 'non_profit'  ? 'selected' : '' ?>>Non-Profit</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bookkeeping Style</label>
                    <select name="accounting_method" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="double" <?= $company['accounting_method'] === 'double' ? 'selected' : '' ?>>Double Entry</option>
                        <option value="single" <?= $company['accounting_method'] === 'single' ? 'selected' : '' ?>>Single Entry</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fiscal Year Start</label>
                <select name="fiscal_year_start" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                    <?php foreach ($months as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $company['fiscal_year_start'] == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                    Save Changes
                </button>
                <a href="index.php" class="text-sm text-gray-500 hover:underline px-3 py-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
