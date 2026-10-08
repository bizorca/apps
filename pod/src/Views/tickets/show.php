<?php $pageTitle = $ticket['subject']; ?>
<?php
$statusColors = [
    'open'     => 'bg-yellow-50 text-yellow-700',
    'answered' => 'bg-blue-50 text-blue-700',
    'closed'   => 'bg-gray-100 text-gray-500',
];
$isStaff = !empty($user['is_staff']) || !empty($user['is_admin']);
?>

<div class="max-w-3xl">

    <div class="mb-4 flex items-center gap-3">
        <a href="<?= url('tickets') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Tickets</a>
        <span class="text-xs px-2 py-0.5 rounded-full <?= $statusColors[$ticket['status']] ?> capitalize">
            <?= h($ticket['status']) ?>
        </span>
        <?php if ($assignee): ?>
            <span class="text-xs text-gray-400">Assigned to <?= h($assignee['first_name'] . ' ' . $assignee['last_name']) ?></span>
        <?php endif; ?>
    </div>

    <h1 class="text-xl font-bold mb-1"><?= h($ticket['subject']) ?></h1>
    <p class="text-xs text-gray-400 mb-6">
        From <?= h($owner['first_name'] . ' ' . $owner['last_name']) ?>
        (<?= h($owner['email']) ?>)
    </p>

    <!-- Staff: assignment panel -->
    <?php if ($isStaff): ?>
        <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 mb-6 flex items-center gap-3">
            <span class="text-sm text-gray-500 shrink-0">Assign to:</span>
            <form method="POST" action="<?= url("tickets/{$ticket['id']}/assign") ?>" class="flex items-center gap-2">
                <?= csrf_field() ?>
                <select name="staff_id"
                        class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    <option value="0">-- Unassigned --</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($ticket['assigned_to'] == $s['id']) ? 'selected' : '' ?>>
                            <?= h($s['first_name'] . ' ' . $s['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"
                        class="bg-gray-800 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-gray-900 transition-colors">
                    Assign
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Messages -->
    <div id="bottom" class="space-y-4 mb-8">
        <?php foreach ($messages as $msg): ?>
            <div class="bg-white rounded-xl border <?= $msg['is_staff'] ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200' ?> px-6 py-4">
                <p class="text-xs text-gray-400 mb-2">
                    <?= h($msg['first_name'] . ' ' . $msg['last_name']) ?>
                    <?php if ($msg['is_staff']): ?>
                        <span class="ml-1 bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded text-xs">Staff</span>
                    <?php endif; ?>
                    &middot; <?= timeAgo($msg['created_at']) ?>
                </p>
                <div class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($msg['body']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Reply form -->
    <?php if ($ticket['status'] !== 'closed'): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="font-medium text-gray-900 mb-3">Reply</h3>
            <form method="POST" action="<?= url("tickets/{$ticket['id']}/reply") ?>">
                <?= csrf_field() ?>
                <textarea name="body" rows="4" required
                          placeholder="Your message…"
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none"></textarea>

                <?php if ($isStaff): ?>
                    <div class="mt-3 flex items-center gap-3">
                        <select name="status"
                                class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                            <option value="open"     <?= $ticket['status'] === 'open'     ? 'selected' : '' ?>>Open</option>
                            <option value="answered" <?= $ticket['status'] === 'answered' ? 'selected' : '' ?>>Answered</option>
                            <option value="closed"   <?= $ticket['status'] === 'closed'   ? 'selected' : '' ?>>Closed</option>
                        </select>
                        <button type="submit"
                                class="ml-auto bg-indigo-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                            Send reply
                        </button>
                    </div>
                <?php else: ?>
                    <div class="mt-3 text-right">
                        <button type="submit"
                                class="bg-indigo-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                            Send reply
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    <?php else: ?>
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-400">This ticket is closed.</p>
            <form method="POST" action="<?= url("tickets/{$ticket['id']}/reply") ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="body" value="[Ticket reopened]">
                <input type="hidden" name="status" value="open">
                <button class="text-sm text-indigo-600 hover:underline">Reopen ticket</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Close button for owners -->
    <?php if ($ticket['status'] !== 'closed'): ?>
        <form method="POST" action="<?= url("tickets/{$ticket['id']}/close") ?>" class="mt-4">
            <?= csrf_field() ?>
            <button class="text-xs text-gray-400 hover:text-gray-600 transition-colors">Close ticket</button>
        </form>
    <?php endif; ?>

</div>
