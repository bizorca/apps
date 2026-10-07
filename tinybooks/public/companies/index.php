<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';

$user      = auth_require();
$companies = user_companies($user['id']);

$page_title = 'My Companies';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">My Companies</h1>
    <a href="create.php" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
        + Add Company
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
    <?php foreach ($companies as $c): ?>
    <div class="px-5 py-4 flex items-center justify-between">
        <div>
            <p class="font-medium text-gray-900"><?= h($c['name']) ?></p>
            <p class="text-xs text-gray-400">
                <?= $c['type'] === 'non_profit' ? 'Non-Profit' : 'For-Profit' ?>
                &middot;
                <?= $c['accounting_method'] === 'double' ? 'Double Entry' : 'Single Entry' ?>
            </p>
        </div>
        <div class="flex gap-3">
            <form method="post" action="switch.php">
                <?= csrf_field() ?>
                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                <button class="text-sm text-indigo-600 hover:underline">Switch to</button>
            </form>
            <a href="edit.php?id=<?= $c['id'] ?>" class="text-sm text-gray-500 hover:underline">Edit</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
