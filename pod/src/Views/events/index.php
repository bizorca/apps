<?php $pageTitle = 'Events'; ?>

<div class="mb-6">
    <h1 class="text-2xl font-bold">Upcoming Events</h1>
</div>

<?php if (empty($events)): ?>
    <div class="text-center py-16 text-gray-400">No events scheduled.</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($events as $event): ?>
        <?php
        $start  = new DateTime($event['starts_at']);
        $rsvped = in_array($event['id'], $myRsvps);
        $isPast = $event['is_past'];
        ?>
        <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-start gap-6">
            <!-- Date block -->
            <div class="text-center shrink-0 w-14">
                <p class="text-xs uppercase text-gray-400 font-medium"><?= $start->format('M') ?></p>
                <p class="text-3xl font-bold text-indigo-600 leading-none"><?= $start->format('j') ?></p>
                <p class="text-xs text-gray-400"><?= $start->format('Y') ?></p>
            </div>

            <div class="flex-1 min-w-0">
                <a href="<?= url("events/{$event['id']}") ?>"
                   class="text-lg font-semibold text-gray-900 hover:text-indigo-600 transition-colors">
                    <?= h($event['title']) ?>
                </a>
                <p class="text-sm text-gray-500 mt-1">
                    <?= $start->format('l, F j \a\t g:ia') ?>
                    <?php if ($event['zoom_join_url']): ?>
                        &mdash; <span class="text-blue-600">Zoom</span>
                    <?php endif; ?>
                </p>
                <?php if ($event['description']): ?>
                    <p class="text-sm text-gray-500 mt-2 line-clamp-2"><?= h($event['description']) ?></p>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-2"><?= $event['rsvp_count'] ?> attending</p>
            </div>

            <?php if (!$isPast): ?>
                <div class="shrink-0">
                    <?php if ($rsvped): ?>
                        <form method="POST" action="<?= url("events/{$event['id']}/cancel-rsvp") ?>">
                            <?= csrf_field() ?>
                            <button class="text-sm border border-gray-200 text-gray-500 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel RSVP
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= url("events/{$event['id']}/rsvp") ?>">
                            <?= csrf_field() ?>
                            <button class="text-sm bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                                RSVP
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
