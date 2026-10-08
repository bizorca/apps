<?php $pageTitle = $event['title']; ?>
<?php
$start = new DateTime($event['starts_at']);
$end   = new DateTime($event['ends_at']);
?>

<div class="max-w-2xl">

    <div class="mb-4">
        <a href="<?= url('events') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Events</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-8">
        <h1 class="text-2xl font-bold text-gray-900"><?= h($event['title']) ?></h1>

        <div class="mt-4 space-y-2 text-sm text-gray-500">
            <p>
                <span class="font-medium text-gray-700">Starts:</span>
                <?= $start->format('l, F j, Y \a\t g:ia') ?>
            </p>
            <p>
                <span class="font-medium text-gray-700">Ends:</span>
                <?= $end->format('g:ia') ?>
            </p>
            <p>
                <span class="font-medium text-gray-700">Attending:</span>
                <?= $rsvpCount ?> people
            </p>
        </div>

        <?php if ($event['description']): ?>
            <div class="mt-6 text-gray-700 text-sm whitespace-pre-wrap"><?= h($event['description']) ?></div>
        <?php endif; ?>

        <?php if ($rsvped && $event['zoom_join_url'] && !$isPast): ?>
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg px-4 py-4">
                <p class="text-sm font-medium text-blue-700 mb-2">You're registered! Here's your Zoom link:</p>
                <a href="<?= h($event['zoom_join_url']) ?>" target="_blank" rel="noopener"
                   class="text-sm text-blue-600 hover:underline break-all">
                    <?= h($event['zoom_join_url']) ?>
                </a>
            </div>
        <?php endif; ?>

        <?php if (!$isPast): ?>
            <div class="mt-6">
                <?php if ($rsvped): ?>
                    <form method="POST" action="<?= url("events/{$event['id']}/cancel-rsvp") ?>">
                        <?= csrf_field() ?>
                        <button class="border border-gray-200 text-gray-600 text-sm px-5 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel my RSVP
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= url("events/{$event['id']}/rsvp") ?>">
                        <?= csrf_field() ?>
                        <button class="bg-indigo-600 text-white text-sm px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                            RSVP to this event
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="mt-6 text-sm text-gray-400">This event has passed.</p>
        <?php endif; ?>
    </div>

</div>
