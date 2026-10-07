<?php
$user = auth_user();
$company = current_company();
$all_companies = $user ? user_companies($user['id']) : [];
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function nav_active(string $prefix): string {
    global $current_path;
    return str_starts_with($current_path, rtrim(APP_URL, '/') . $prefix) ? 'active' : '';
}
?>
<div class="flex h-full">
    <!-- Sidebar -->
    <aside class="w-56 shrink-0 flex flex-col bg-white border-r border-gray-200 min-h-screen">
        <!-- Logo -->
        <div class="px-4 py-5 border-b border-gray-100">
            <a href="/" class="block text-xs text-gray-400 hover:text-brand-600 mb-1">Bizorca Tools</a>
            <a href="<?= APP_URL ?>/dashboard.php" class="text-xl font-bold text-brand-700">TinyBooks</a>
        </div>

        <!-- Company switcher -->
        <?php if ($company): ?>
        <div class="px-3 py-3 border-b border-gray-100">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Company</p>
            <form method="post" action="<?= APP_URL ?>/companies/switch.php">
                <?= csrf_field() ?>
                <select name="company_id" onchange="this.form.submit()"
                    class="w-full text-sm border-gray-200 rounded-md text-gray-700 py-1 px-2 focus:ring-brand-500 focus:border-brand-500">
                    <?php foreach ($all_companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $company['id'] ? 'selected' : '' ?>>
                        <?= h($c['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="<?= APP_URL ?>/companies/create.php" class="text-xs text-brand-600 hover:underline mt-1 block">+ Add company</a>
        </div>
        <?php endif; ?>

        <!-- Nav links -->
        <nav class="flex-1 px-3 py-4 space-y-1">
            <?php if ($company): ?>
            <a href="<?= APP_URL ?>/dashboard.php" class="sidebar-link <?= nav_active('/dashboard') ?>">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
            <a href="<?= APP_URL ?>/transactions/index.php" class="sidebar-link <?= nav_active('/transactions') ?>">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Transactions
            </a>
            <a href="<?= APP_URL ?>/accounts/index.php" class="sidebar-link <?= nav_active('/accounts') ?>">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Accounts
            </a>
            <a href="<?= APP_URL ?>/reconciliation/index.php" class="sidebar-link <?= nav_active('/reconciliation') ?>">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Reconcile
            </a>

            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider pt-3 pb-1 px-1">Reports</p>
            <a href="<?= APP_URL ?>/reports/pl.php" class="sidebar-link <?= nav_active('/reports/pl') ?>">
                P&amp;L / Income
            </a>
            <a href="<?= APP_URL ?>/reports/balance_sheet.php" class="sidebar-link <?= nav_active('/reports/balance') ?>">
                Balance Sheet
            </a>
            <a href="<?= APP_URL ?>/reports/budget_actuals.php" class="sidebar-link <?= nav_active('/reports/budget') ?>">
                Budget vs. Actuals
            </a>
            <?php endif; ?>
        </nav>

        <!-- Bottom links -->
        <div class="px-3 py-4 border-t border-gray-100 space-y-1">
            <a href="<?= APP_URL ?>/settings/index.php" class="sidebar-link <?= nav_active('/settings') ?>">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
            <?php if ($user): ?>
            <div class="px-3 py-2 text-xs text-gray-500">
                <a href="/account/settings.php" class="hover:text-brand-600"><?= h($user['name']) ?></a><br>
                <a href="/account/logout.php" class="text-red-500 hover:underline">Sign out</a>
            </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main content wrapper -->
    <main class="flex-1 overflow-auto">
        <div class="px-8 py-6">
            <?php
            $flash = flash_get();
            if ($flash):
                $cls = match($flash['type']) {
                    'success' => 'bg-green-50 border-green-400 text-green-800',
                    'error'   => 'bg-red-50 border-red-400 text-red-800',
                    default   => 'bg-blue-50 border-blue-400 text-blue-800',
                };
            ?>
            <div data-flash class="border-l-4 p-4 mb-4 <?= $cls ?>"><?= h($flash['message']) ?></div>
            <?php endif; ?>
