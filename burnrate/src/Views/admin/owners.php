<?php $title = 'All Owners - Admin'; $h = \App\Core\Helpers::class; ?>
<?php ob_start(); ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">All Owners (<?= number_format($total) ?>)</h1>
        <a href="<?= url('/admin') ?>" class="text-red-600 hover:text-red-700 font-medium text-sm">Back to Admin</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">ID</th>
                    <th class="px-4 py-2 text-left">Email</th>
                    <th class="px-4 py-2 text-left">Name</th>
                    <th class="px-4 py-2 text-left">Level</th>
                    <th class="px-4 py-2 text-left">Signed Up</th>
                    <th class="px-4 py-2 text-left">Last Login</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($owners as $o): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2"><?= $o->OwnerID ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($o->Email) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars(($o->FirstName ?? '') . ' ' . ($o->LastName ?? '')) ?></td>
                    <td class="px-4 py-2"><?= $o->MemberLevel ?></td>
                    <td class="px-4 py-2 text-gray-500"><?= $o->SignUpDate ?? '-' ?></td>
                    <td class="px-4 py-2 text-gray-500"><?= $o->LastLoginDate ?? '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php $totalPages = ceil($total / $perPage); ?>
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center space-x-2">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="<?= url('/admin/owners') ?>?page=<?= $i ?>"
                class="px-3 py-1 rounded <?= $i === $page ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
