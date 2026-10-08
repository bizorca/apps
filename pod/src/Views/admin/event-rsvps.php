<?php $pageTitle = 'RSVPs — ' . $event['title']; ?>

<div class="mb-6">
    <a href="<?= url('admin/events') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Events</a>
    <h1 class="text-2xl font-bold mt-1"><?= h($event['title']) ?> &mdash; RSVPs</h1>
    <p class="text-sm text-gray-400 mt-1">
        <?= date('M j, Y g:i A', strtotime($event['starts_at'])) ?>
        &middot; <?= count($rsvps) ?> RSVP<?= count($rsvps) !== 1 ? 's' : '' ?>
    </p>
</div>

<?php if (empty($rsvps)): ?>
    <div class="bg-white border border-gray-200 rounded-xl px-6 py-10 text-center">
        <p class="text-gray-400 text-sm">No RSVPs yet.</p>
    </div>
<?php else: ?>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden max-w-2xl">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Name</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">RSVP'd</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($rsvps as $rsvp): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <?= h($rsvp['first_name'] . ' ' . $rsvp['last_name']) ?>
                        </td>
                        <td class="px-4 py-3 text-gray-500"><?= h($rsvp['email']) ?></td>
                        <td class="px-4 py-3 text-gray-400"><?= timeAgo($rsvp['rsvped_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-3">To export, copy the table or use a direct DB query on event_rsvps.</p>
<?php endif; ?>
