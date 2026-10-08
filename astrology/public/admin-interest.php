<?php
require __DIR__ . '/_bootstrap.php';
requireLogin();

if (!isAdmin()) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();

$offerNames = [
    'reading_1on1'        => 'Star Seed Reading 1:1',
    'activation_group'    => 'Star Seed Activation Group',
    'timeline_clearing'   => 'Timeline Clearing Group',
    'integration_journey' => 'Integration Journey Group',
];

$rows = $db->query("
    SELECT oi.offer_key, oi.created_at, u.name, u.email, s.primary_lineage, s.secondary_lineage
    FROM as_offer_interest oi
    JOIN users u ON u.id = oi.user_id
    LEFT JOIN as_starseed_results s ON s.user_id = oi.user_id
    ORDER BY oi.created_at DESC
")->fetchAll();

$counts = [];
foreach ($rows as $r) {
    $counts[$r['offer_key']] = ($counts[$r['offer_key']] ?? 0) + 1;
}

$pageTitle = 'Offer Interest';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Offer Interest</h1>
        <a href="<?= url('/admin-settings.php') ?>" class="text-sm text-gray-500 hover:text-brand-600">&larr; Admin Settings</a>
    </div>

    <!-- Counts -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php foreach ($offerNames as $key => $name): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <div class="text-2xl font-extrabold text-gray-900"><?= (int)($counts[$key] ?? 0) ?></div>
            <div class="text-xs text-gray-500 mt-1"><?= h($name) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Lead list -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <?php if (empty($rows)): ?>
        <p class="p-6 text-sm text-gray-500">No interest requests yet. Leads will appear here when members click "Request a Spot" on the Work with Jillian page.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3">Requested</th>
                        <th class="px-4 py-3">Offer</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Lineage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                        <td class="px-4 py-3 font-medium text-gray-900"><?= h($offerNames[$r['offer_key']] ?? $r['offer_key']) ?></td>
                        <td class="px-4 py-3 text-gray-700"><?= h($r['name']) ?></td>
                        <td class="px-4 py-3"><a href="mailto:<?= h($r['email']) ?>" class="text-brand-600 hover:text-brand-700"><?= h($r['email']) ?></a></td>
                        <td class="px-4 py-3 text-gray-500"><?= $r['primary_lineage'] ? h(ucfirst($r['primary_lineage'])) . ($r['secondary_lineage'] ? ' / ' . h(ucfirst($r['secondary_lineage'])) : '') : '&mdash;' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
