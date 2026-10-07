<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';

$user    = auth_require();
$company = current_company();
if (!$company) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM tb_accounts WHERE id = ? AND company_id = ?");
$stmt->execute([$id, $company['id']]);
$account = $stmt->fetch();
if (!$account) { header('Location: index.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['deactivate'])) {
        db()->prepare("UPDATE tb_accounts SET is_active = 0 WHERE id = ?")->execute([$id]);
        flash_set('success', 'Account deactivated.');
        header('Location: index.php'); exit;
    }

    $name    = trim($_POST['name'] ?? '');
    $type    = $_POST['type'] ?? $account['type'];
    $number  = trim($_POST['account_number'] ?? '');
    $subtype = trim($_POST['subtype'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $active  = !empty($_POST['is_active']) ? 1 : 0;

    if (!$name) {
        $error = 'Name required.';
    } else {
        db()->prepare("UPDATE tb_accounts SET account_number = ?, name = ?, type = ?, subtype = ?, description = ?, is_active = ? WHERE id = ?")
            ->execute([$number ?: null, $name, $type, $subtype ?: null, $desc ?: null, $active, $id]);
        flash_set('success', 'Account updated.');
        header('Location: index.php'); exit;
    }
}

$page_title = 'Edit Account';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="max-w-lg">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Account</h1>

    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-3 text-sm rounded mb-4"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6">
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account #</label>
                    <input type="text" name="account_number" value="<?= h($_POST['account_number'] ?? $account['account_number'] ?? '') ?>"
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border font-mono">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                    <input type="text" name="name" required value="<?= h($_POST['name'] ?? $account['name']) ?>"
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <?php foreach (account_types() as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($account['type'] === $val) ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subtype</label>
                    <input type="text" name="subtype" value="<?= h($_POST['subtype'] ?? $account['subtype'] ?? '') ?>"
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input type="text" name="description" value="<?= h($_POST['description'] ?? $account['description'] ?? '') ?>"
                    class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" <?= $account['is_active'] ? 'checked' : '' ?>
                    class="rounded border-gray-300 text-indigo-600">
                <label for="is_active" class="text-sm text-gray-600">Account is active</label>
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
