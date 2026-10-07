<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';
require_once TB_ROOT . '/includes/chart_of_accounts.php';

$user  = auth_require();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name   = trim($_POST['name'] ?? '');
    $type   = $_POST['type'] ?? 'for_profit';
    $method = $_POST['accounting_method'] ?? 'double';
    $seed   = !empty($_POST['seed_coa']);

    if (!$name) {
        $error = 'Company name is required.';
    } else {
        db()->prepare("INSERT INTO tb_companies (name, type, accounting_method) VALUES (?, ?, ?)")
            ->execute([$name, $type, $method]);
        $cid = (int)db()->lastInsertId();

        db()->prepare("INSERT INTO tb_user_companies (user_id, company_id, role) VALUES (?, ?, 'owner')")
            ->execute([$user['id'], $cid]);

        if ($seed) seed_chart_of_accounts($cid, $type);

        $_SESSION['tb_company_id'] = $cid;
        flash_set('success', "{$name} created.");
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

$page_title = 'Add Company';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="max-w-lg">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Add Company</h1>

    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-3 text-sm rounded mb-4"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6">
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Organization Name</label>
                <input type="text" name="name" required value="<?= h($_POST['name'] ?? '') ?>"
                    class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="for_profit">For-Profit</option>
                        <option value="non_profit">Non-Profit</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bookkeeping Style</label>
                    <select name="accounting_method" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="double">Double Entry</option>
                        <option value="single">Single Entry</option>
                    </select>
                </div>
            </div>

            <div class="flex items-start gap-2">
                <input type="checkbox" name="seed_coa" id="seed_coa" checked class="mt-0.5 rounded border-gray-300 text-indigo-600">
                <label for="seed_coa" class="text-sm text-gray-600">
                    Start with a default chart of accounts (recommended — you can add or remove accounts later)
                </label>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                    Create Company
                </button>
                <a href="<?= APP_URL ?>/dashboard.php" class="text-sm text-gray-500 hover:underline px-3 py-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
