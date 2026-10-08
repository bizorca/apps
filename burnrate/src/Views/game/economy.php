<?php $h = \App\Core\Helpers::class; ?>

<h3 class="text-lg font-bold text-gray-200 mb-1">Economic Chaos</h3>
<p class="text-sm text-gray-400 mb-6">The forces conspiring to make everything more expensive and your assets less valuable.</p>

<!-- Key Indicators -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-gradient-to-br from-red-600 to-red-800 rounded-xl p-5 text-white">
        <p class="text-sm text-red-200">Interest Rate</p>
        <p class="text-3xl font-bold"><?= number_format($economy['interestRate'], 2) ?>%</p>
        <p class="text-xs text-red-300 mt-1">Affects bank interest and property costs</p>
    </div>
    <div class="bg-gradient-to-br from-amber-600 to-amber-800 rounded-xl p-5 text-white">
        <p class="text-sm text-amber-200">Inflation Rate</p>
        <p class="text-3xl font-bold"><?= number_format($economy['inflationRate'], 2) ?>%</p>
        <p class="text-xs text-amber-300 mt-1">Everything gets more expensive</p>
    </div>
    <div class="bg-gradient-to-br from-red-700 to-red-900 rounded-xl p-5 text-white">
        <p class="text-sm text-red-200">Consumer Price Index</p>
        <p class="text-3xl font-bold"><?= number_format($economy['cpi'], 1) ?></p>
        <p class="text-xs text-red-300 mt-1">Base: 100 at game start</p>
    </div>
    <div class="bg-gradient-to-br from-amber-700 to-amber-900 rounded-xl p-5 text-white">
        <p class="text-sm text-amber-200">$1 Multiplier</p>
        <p class="text-3xl font-bold">$<?= number_format($economy['oneDollar'], 2) ?></p>
        <p class="text-xs text-amber-300 mt-1">What $1 at game start costs now</p>
    </div>
    <div class="bg-gradient-to-br from-red-800 to-gray-900 rounded-xl p-5 text-white">
        <p class="text-sm text-red-200">Market Chaos Index</p>
        <p class="text-3xl font-bold"><?= number_format($economy['stockMarketRate'], 2) ?>%</p>
        <p class="text-xs text-red-300 mt-1">Volatility of toys and investments</p>
    </div>
</div>

<!-- Active Crisis Section -->
<?php if ($activeCrisis !== null): ?>
<div class="mb-8 bg-red-900 border-2 border-red-500 rounded-xl p-6 shadow-lg">
    <div class="flex items-center space-x-3 mb-3">
        <span class="inline-block px-3 py-1 bg-red-600 text-white text-xs font-bold rounded-full uppercase tracking-wide animate-pulse">
            Active Crisis
        </span>
        <span class="text-xs text-red-300"><?= $activeCrisis['turnsRemaining'] ?> turn<?= $activeCrisis['turnsRemaining'] !== 1 ? 's' : '' ?> remaining</span>
    </div>
    <h4 class="text-2xl font-black text-red-400 mb-2"><?= htmlspecialchars($activeCrisis['name']) ?></h4>
    <p class="text-sm text-red-200 mb-4"><?= htmlspecialchars($activeCrisis['description']) ?></p>

    <!-- Crisis Effects -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php
        $effects = [
            'Property Costs' => $activeCrisis['propertyEffect'],
            'Toy Depreciation' => $activeCrisis['toyEffect'],
            'Investment Losses' => $activeCrisis['investmentEffect'],
            'Lifestyle Costs' => $activeCrisis['lifestyleEffect'],
        ];
        ?>
        <?php foreach ($effects as $label => $multiplier): ?>
        <div class="bg-red-800/50 rounded-lg p-3 border border-red-700">
            <p class="text-xs text-red-300"><?= $label ?></p>
            <p class="text-lg font-bold <?= $multiplier > 1.0 ? 'text-red-400' : 'text-gray-400' ?>">
                <?= $multiplier > 1.0 ? number_format($multiplier, 1) . 'x' : 'Normal' ?>
            </p>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($activeCrisis['categories'])): ?>
    <div class="mt-3 flex flex-wrap gap-1">
        <?php foreach ($activeCrisis['categories'] as $cat): ?>
        <span class="text-xs px-2 py-0.5 rounded-full bg-red-700 text-red-200"><?= htmlspecialchars($cat) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="mb-8 bg-gray-800 border border-gray-700 rounded-xl p-6 text-center">
    <p class="text-lg text-gray-400 font-medium">The calm before the storm.</p>
    <p class="text-sm text-gray-500 mt-1">Enjoy it while it lasts. A crisis could hit at any moment.</p>
</div>
<?php endif; ?>

<!-- Economy Rate History Chart -->
<div class="bg-gray-800 border border-gray-700 rounded-xl p-6 mb-6">
    <h4 class="font-semibold text-gray-200 mb-4">Rate History</h4>
    <canvas id="economyChart" height="200"></canvas>
</div>

<!-- Net Worth History Chart -->
<div class="bg-gray-800 border border-gray-700 rounded-xl p-6 mb-6">
    <h4 class="font-semibold text-gray-200 mb-4">Net Worth History</h4>
    <canvas id="netWorthChart" height="200"></canvas>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Economy Rate History
    fetch('<?= url('/api/chart/economy') ?>')
        .then(r => r.json())
        .then(data => {
            new Chart(document.getElementById('economyChart'), {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Interest Rate',
                            data: data.interestRate,
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.1)',
                            tension: 0.3,
                            fill: false,
                        },
                        {
                            label: 'Inflation Rate',
                            data: data.inflationRate,
                            borderColor: '#d97706',
                            backgroundColor: 'rgba(217, 119, 6, 0.1)',
                            tension: 0.3,
                            fill: false,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            labels: { color: '#9ca3af' }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: v => v + '%',
                                color: '#9ca3af'
                            },
                            grid: { color: '#374151' }
                        },
                        x: {
                            title: { display: true, text: 'Turn', color: '#9ca3af' },
                            ticks: { color: '#9ca3af' },
                            grid: { color: '#374151' }
                        }
                    }
                }
            });
        });

    // Net Worth History
    fetch('<?= url('/api/chart/networth') ?>')
        .then(r => r.json())
        .then(data => {
            new Chart(document.getElementById('netWorthChart'), {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Total Net Worth',
                            data: data.netWorth,
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.1)',
                            tension: 0.3,
                            fill: true,
                            borderWidth: 2,
                        },
                        {
                            label: 'Bank Balance',
                            data: data.bankBalance,
                            borderColor: '#d97706',
                            backgroundColor: 'rgba(217, 119, 6, 0.05)',
                            tension: 0.3,
                            fill: false,
                            borderWidth: 1,
                            borderDash: [5, 5],
                        },
                        {
                            label: 'Property Value',
                            data: data.propertyNetWorth,
                            borderColor: '#ef4444',
                            tension: 0.3,
                            fill: false,
                            borderWidth: 1,
                            borderDash: [3, 3],
                        },
                        {
                            label: 'Toy Value',
                            data: data.toyNetWorth,
                            borderColor: '#f59e0b',
                            tension: 0.3,
                            fill: false,
                            borderWidth: 1,
                            borderDash: [3, 3],
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            labels: { color: '#9ca3af' }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: v => '$' + v.toLocaleString(),
                                color: '#9ca3af'
                            },
                            grid: { color: '#374151' }
                        },
                        x: {
                            title: { display: true, text: 'Turn', color: '#9ca3af' },
                            ticks: { color: '#9ca3af' },
                            grid: { color: '#374151' }
                        }
                    }
                }
            });
        });
});
</script>

<!-- How the Economy Hurts You -->
<div class="bg-gray-800 border border-red-700 rounded-xl p-4">
    <h4 class="font-semibold text-red-400 mb-2">How the Economy Destroys Your Wealth</h4>
    <ul class="text-sm text-gray-400 space-y-1 list-disc list-inside">
        <li><b class="text-red-400">Interest Rate</b>: Higher rates mean your bank balance earns slightly more, but property and toy costs also escalate.</li>
        <li><b class="text-amber-400">Inflation</b>: Everything costs more every turn. Staff, maintenance, upkeep - it all scales with the dollar multiplier.</li>
        <li><b class="text-red-400">CPI</b>: Tracks the overall price level. Started at 100. It only goes up.</li>
        <li><b class="text-amber-400">$1 Multiplier</b>: What a dollar was worth at game start now costs this much. Your money is worth less every turn.</li>
        <li><b class="text-red-400">Market Chaos Index</b>: Controls the volatility of your toys and investments. Higher = wilder value swings.</li>
    </ul>
</div>
