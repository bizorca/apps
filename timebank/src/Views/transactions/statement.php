<?php $pageTitle = 'Account Statement'; ?>
<?php
$currencyName   = $tenant['currency_name'] ?? 'Hour';
$currencyPlural = $tenant['currency_name_plural'] ?? 'Hours';
$currentUser    = \TimeBank\Core\Auth::user() ?? [];
?>

<div class="flex items-center justify-between mb-6 print:hidden">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Account Statement</h1>
        <p class="text-sm text-gray-500 mt-1">A running record of your time exchanges</p>
    </div>
    <button onclick="window.print()"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-xl hover:bg-gray-900 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Print
    </button>
</div>

<!-- Date filter -->
<form method="GET" action="<?= url('/transactions/statement') ?>"
      class="bg-white rounded-2xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end print:hidden">
            <?= route_field('/transactions/statement') ?>
    <div>
        <label for="from" class="block text-xs font-medium text-gray-600 mb-1">From</label>
        <input type="date" id="from" name="from"
               value="<?= e($_GET['from'] ?? date('Y-m-01')) ?>"
               class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
    </div>
    <div>
        <label for="to" class="block text-xs font-medium text-gray-600 mb-1">To</label>
        <input type="date" id="to" name="to"
               value="<?= e($_GET['to'] ?? date('Y-m-d')) ?>"
               class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
    </div>
    <button type="submit"
            class="px-5 py-2 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors">
        Filter
    </button>
    <a href="<?= url('/transactions/statement') ?>"
       class="px-5 py-2 bg-gray-100 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
        Reset
    </a>
</form>

<!-- Print header (only shown when printing) -->
<div class="hidden print:block mb-6">
    <h1 class="text-2xl font-bold text-gray-900"><?= e($tenant['name'] ?? 'TimeBank') ?> &mdash; Account Statement</h1>
    <p class="text-sm text-gray-600 mt-1">
        Member: <?= e($currentUser['display_name'] ?: trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))) ?> &bull;
        Generated: <?= date('F j, Y') ?>
        <?php if (!empty($_GET['from']) || !empty($_GET['to'])): ?>
            &bull; Period: <?= e($_GET['from'] ?? '') ?> to <?= e($_GET['to'] ?? '') ?>
        <?php endif; ?>
    </p>
</div>

<!-- Statement table -->
<?php if (!empty($transactions)): ?>
    <?php
    $runningBalance = (float)($openingBalance ?? 0);
    $totalIn  = 0.0;
    $totalOut = 0.0;
    $currentUserId = \TimeBank\Core\Auth::id();
    ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6 print:shadow-none print:border-gray-400">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[750px]">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Provider</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Receiver</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">In</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Out</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <!-- Opening balance row -->
                    <?php if (isset($openingBalance)): ?>
                        <tr class="bg-gray-50">
                            <td class="px-5 py-2.5 text-xs text-gray-400">Opening</td>
                            <td class="px-5 py-2.5 text-xs text-gray-500 italic" colspan="5">Balance at start of period</td>
                            <td class="px-5 py-2.5 text-right text-sm font-semibold text-gray-700"><?= number_format($runningBalance, 2) ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($transactions as $tx): ?>
                        <?php
                        $isProvider = (int)($tx['provider_id'] ?? 0) === (int)$currentUserId;
                        $hours = (float)($tx['hours'] ?? 0);
                        if ($isProvider) {
                            $totalIn    += $hours;
                            $runningBalance += $hours;
                            $inHours  = $hours;
                            $outHours = null;
                        } else {
                            $totalOut   += $hours;
                            $runningBalance -= $hours;
                            $inHours  = null;
                            $outHours = $hours;
                        }
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 text-gray-500 whitespace-nowrap text-xs">
                                <?= e(date('M j, Y', strtotime($tx['service_date'] ?? $tx['created_at'] ?? ''))) ?>
                            </td>
                            <td class="px-5 py-3 text-gray-800 max-w-xs">
                                <span class="block truncate text-xs"><?= e(truncate($tx['description'] ?? '', 60)) ?></span>
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-600 whitespace-nowrap"><?= e($tx['provider_name'] ?? '') ?></td>
                            <td class="px-5 py-3 text-xs text-gray-600 whitespace-nowrap"><?= e($tx['receiver_name'] ?? '') ?></td>
                            <td class="px-5 py-3 text-right text-xs font-medium text-teal-600 whitespace-nowrap">
                                <?= $inHours !== null ? number_format($inHours, 2) : '' ?>
                            </td>
                            <td class="px-5 py-3 text-right text-xs font-medium text-orange-500 whitespace-nowrap">
                                <?= $outHours !== null ? number_format($outHours, 2) : '' ?>
                            </td>
                            <td class="px-5 py-3 text-right text-sm font-semibold whitespace-nowrap <?= $runningBalance >= 0 ? 'text-gray-800' : 'text-red-600' ?>">
                                <?= number_format($runningBalance, 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300 bg-gray-50 font-semibold">
                        <td colspan="4" class="px-5 py-3 text-sm text-gray-700">Totals</td>
                        <td class="px-5 py-3 text-right text-sm text-teal-600"><?= number_format($totalIn, 2) ?></td>
                        <td class="px-5 py-3 text-right text-sm text-orange-500"><?= number_format($totalOut, 2) ?></td>
                        <td class="px-5 py-3 text-right text-sm text-gray-900"><?= number_format($runningBalance, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No transactions found for the selected period.</p>
    </div>
<?php endif; ?>
