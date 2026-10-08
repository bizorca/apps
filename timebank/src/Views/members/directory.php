<?php $pageTitle = 'Member Directory'; ?>
<?php $currencyName = $tenant['currency_name'] ?? 'Hour'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Member Directory</h1>
        <p class="text-sm text-gray-500 mt-1">
            <?= number_format((int)($members['total'] ?? 0)) ?> member<?= ($members['total'] ?? 0) != 1 ? 's' : '' ?> in <?= e($tenant['name'] ?? 'the community') ?>
        </p>
    </div>
</div>

<!-- Filter bar -->
<form method="GET" action="<?= url('/members') ?>" class="bg-white rounded-2xl border border-gray-200 p-4 mb-6 flex flex-col sm:flex-row gap-3">
            <?= route_field('/members') ?>
    <div class="flex-1">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" value="<?= e($filters['search'] ?? '') ?>"
                   placeholder="Search by name..."
                   class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
        </div>
    </div>
    <div class="sm:w-48">
        <input type="text" name="city" value="<?= e($filters['city'] ?? '') ?>"
               placeholder="Filter by city..."
               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
    </div>
    <button type="submit"
            class="px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors whitespace-nowrap">
        Filter
    </button>
    <?php if (!empty($filters['search']) || !empty($filters['city'])): ?>
        <a href="<?= url('/members') ?>" class="px-5 py-2.5 bg-gray-100 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors whitespace-nowrap">
            Clear
        </a>
    <?php endif; ?>
</form>

<!-- Members grid -->
<?php if (!empty($members['data'])): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        <?php foreach ($members['data'] as $m): ?>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-col hover:shadow-md transition-shadow">
                <div class="flex items-start gap-4 mb-4">
                    <?php if (!empty($m['avatar_path'])): ?>
                        <img src="<?= e(media_url($m['avatar_path'])) ?>" alt=""
                             class="w-14 h-14 rounded-full object-cover flex-shrink-0 ring-2 ring-teal-100">
                    <?php else: ?>
                        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                            <?= e(mb_strtoupper(mb_substr($m['first_name'] ?? 'M', 0, 1))) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900 truncate">
                            <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                        </h3>
                        <?php if (!empty($m['city'])): ?>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <svg class="w-3 h-3 inline mr-0.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <?= e($m['city']) ?><?= !empty($m['state']) ? ', ' . e($m['state']) : '' ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-xs font-semibold text-teal-600 mt-1">
                            <?= number_format((float)($m['balance'] ?? 0), 2) ?> <?= e($currencyName) ?>s
                        </p>
                    </div>
                </div>

                <?php if (!empty($m['bio'])): ?>
                    <p class="text-sm text-gray-600 leading-relaxed flex-1 mb-4">
                        <?= e(truncate($m['bio'], 100)) ?>
                    </p>
                <?php else: ?>
                    <div class="flex-1"></div>
                <?php endif; ?>

                <a href="<?= url('/members/' . ($m['id'] ?? '')) ?>"
                   class="block w-full text-center px-4 py-2 bg-teal-50 text-teal-700 text-sm font-medium rounded-xl hover:bg-teal-100 transition-colors">
                    View Profile
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if (($members['pages'] ?? 1) > 1): ?>
        <div class="flex items-center justify-center gap-2">
            <?php
            $currentPage = (int)($members['current'] ?? 1);
            $totalPages  = (int)($members['pages'] ?? 1);
            $queryParams = array_merge($filters ?? [], ['page' => null]);
            ?>
            <?php if ($currentPage > 1): ?>
                <a href="<?= url('/members?' . http_build_query(array_merge($filters ?? [], ['page' => $currentPage - 1]))) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    &lsaquo; Prev
                </a>
            <?php endif; ?>

            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                <a href="<?= url('/members?' . http_build_query(array_merge($filters ?? [], ['page' => $p]))) ?>"
                   class="px-3 py-2 text-sm rounded-lg transition-colors <?= $p === $currentPage ? 'bg-teal-600 text-white font-semibold' : 'text-gray-600 bg-white border border-gray-300 hover:bg-gray-50' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= url('/members?' . http_build_query(array_merge($filters ?? [], ['page' => $currentPage + 1]))) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Next &rsaquo;
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No members found matching your search.</p>
    </div>
<?php endif; ?>
