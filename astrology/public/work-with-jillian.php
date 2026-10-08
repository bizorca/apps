<?php
require __DIR__ . '/_bootstrap.php';

$user = getCurrentUser();
$db = getDB();

// PLACEHOLDER pricing and program details — update when Jillian sets real
// rates, session lengths, and cohort schedules. The "request a spot" flow is
// real: interest is recorded in offer_interest and visible at admin-interest.php.
$offers = [
    'reading_1on1' => [
        'step'        => 1,
        'name'        => 'Star Seed Reading 1:1',
        'tagline'     => 'A guided remembering of what you carry',
        'price'       => '$111',
        'unit'        => 'per session',
        'format'      => '75-minute private session &middot; held remotely by video',
        'description' => 'The reading on this site was generated from your quiz answers. A live reading with Jillian goes much deeper — your full lineage combination, read alongside your Ba Zi chart and Western signatures, in a guided conversation about what you came here carrying and what it asks of you now.',
        'includes'    => ['Full review of your quiz results and resonance profile', 'Your Ba Zi and Western charts woven into the reading', 'Space for the questions your reading stirred up', 'A recording of your session to return to'],
        'bg' => 'bg-violet-50', 'border' => 'border-violet-200', 'text' => 'text-violet-700', 'btn' => 'bg-violet-700 hover:bg-violet-800', 'glyph' => '✦',
    ],
    'activation_group' => [
        'step'        => 2,
        'name'        => 'Star Seed Activation Group',
        'tagline'     => 'Wake what you came here with',
        'price'       => '$333',
        'unit'        => 'per cohort',
        'format'      => '6-week small group &middot; weekly live circles',
        'description' => 'A small circle working through lineage-specific activation practices together — sound, guided journey work, and embodiment practices matched to the gifts of your lineage. Each week moves them from something you read about to something you live.',
        'includes'    => ['Six weekly live group circles', 'Activation practices matched to your lineage', 'Sound and guided journey work', 'A small, consistent circle — not a webinar'],
        'bg' => 'bg-rose-50', 'border' => 'border-rose-200', 'text' => 'text-rose-700', 'btn' => 'bg-rose-600 hover:bg-rose-700', 'glyph' => '❋',
    ],
    'timeline_clearing' => [
        'step'        => 3,
        'name'        => 'Timeline Clearing Group',
        'tagline'     => 'Find the freedom beneath your ancestral lineage',
        'price'       => '$444',
        'unit'        => 'per cohort',
        'format'      => '4-week small group &middot; weekly live sessions',
        'description' => 'Focused group work on clearing inherited and past-timeline patterns — the ancestral patterning, old contracts, and stories that keep replaying beneath the surface. Shorter and deeper than the activation group; most members arrive after it.',
        'includes'    => ['Four weekly live clearing sessions', 'Guided timeline and ancestral clearing journeys', 'Integration prompts after each session', 'Held in a small, private circle'],
        'bg' => 'bg-teal-50', 'border' => 'border-teal-200', 'text' => 'text-teal-700', 'btn' => 'bg-teal-600 hover:bg-teal-700', 'glyph' => '◎',
    ],
    'integration_journey' => [
        'step'        => 4,
        'name'        => 'Integration Journey Group',
        'tagline'     => 'Live it, don\'t just know it',
        'price'       => '$1,111',
        'unit'        => 'per journey',
        'format'      => '12-week deep container &middot; live sessions + ongoing support',
        'description' => 'The deepest container Jillian holds. Twelve weeks of integrating everything — reading, activation, clearing — into how you actually live, work, and relate. For people who are done collecting insights and ready to embody them.',
        'includes'    => ['Twelve weeks of live group sessions', 'Ongoing support between sessions', 'A personalized integration path for your lineage', 'Priority access to 1:1 time with Jillian'],
        'bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-700', 'btn' => 'bg-amber-600 hover:bg-amber-700', 'glyph' => '♦',
    ],
];

// Which offers has this user already requested?
$requested = [];
if ($user) {
    $stmt = $db->prepare("SELECT offer_key FROM as_offer_interest WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $requested = array_column($stmt->fetchAll(), 'offer_key');
}

// POST: request a spot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offer_key'])) {
    if (!$user) {
        header('Location: ' . authUrl('register', url('/work-with-jillian.php')));
        exit;
    }
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/work-with-jillian.php'));
        exit;
    }
    $offerKey = $_POST['offer_key'];
    if (isset($offers[$offerKey])) {
        $stmt = $db->prepare("INSERT IGNORE INTO as_offer_interest (user_id, offer_key) VALUES (?, ?)");
        $stmt->execute([$user['id'], $offerKey]);

        // Notify on new requests only — INSERT IGNORE affects 0 rows on repeats
        if ($stmt->rowCount() > 0) {
            require_once AS_ROOT . '/includes/mail.php';
            $ss = $db->prepare("SELECT primary_lineage, secondary_lineage FROM as_starseed_results WHERE user_id = ?");
            $ss->execute([$user['id']]);
            sendOfferInterestEmail($offers[$offerKey]['name'], $user, $ss->fetch() ?: null);
        }

        setFlash('success', 'You\'re on the list for ' . h($offers[$offerKey]['name']) . '. Jillian will reach out to you at ' . h($user['email']) . '.');
    }
    header('Location: ' . url('/work-with-jillian.php') . '#offer-' . urlencode($offerKey));
    exit;
}

$pageTitle = 'Work with Jillian';
$fullWidth = true;
require AS_ROOT . '/templates/header.php';
?>

<!-- Hero -->
<div class="bg-gradient-to-br from-violet-100 via-rose-50 to-amber-50 py-16 border-b border-violet-100">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="text-4xl mb-4">&#10022;</div>
        <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 tracking-tight">Work with Jillian</h1>
        <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">The quiz, readings, and forecasts on this site are free — and they're the beginning, not the destination. When you're ready to go deeper into the journey of remembering, this is the path.</p>
    </div>
</div>

<div class="bg-gray-50 py-16">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- About Jillian -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 mb-12">
            <h2 class="text-lg font-bold text-gray-900 mb-2">Jillian Ribbons &middot; Sacred Flow Healing Arts</h2>
            <p class="text-sm text-gray-700 leading-relaxed mb-3">Jillian is an East Asian Medicine practitioner, sound healer, and Akashic Records guide, and the host of The Liminal Frequencies Podcast. Her in-person practice — acupuncture, osteopathic bodywork, and holographic sound healing — is rooted in the same tradition as the Ba Zi and Five Element wisdom behind this site. Star seed work is held remotely, wherever you are.</p>
            <a href="https://www.sacredflowhealingarts.com/" target="_blank" rel="noopener" class="text-sm text-violet-700 hover:text-violet-800 font-medium">Visit Sacred Flow Healing Arts &rarr;</a>
        </div>

        <div class="text-center mb-10">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">The Journey</h2>
            <p class="text-gray-500">Four steps, each one building on the last. Most people start with a 1:1 reading.</p>
        </div>

        <div class="space-y-6">
        <?php foreach ($offers as $key => $offer): ?>
            <div id="offer-<?= h($key) ?>" class="<?= $offer['bg'] ?> <?= $offer['border'] ?> border rounded-2xl p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center font-bold <?= $offer['text'] ?> flex-shrink-0"><?= $offer['step'] ?></div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900"><?= h($offer['name']) ?></h3>
                            <div class="text-sm <?= $offer['text'] ?> font-medium"><?= h($offer['tagline']) ?></div>
                        </div>
                    </div>
                    <div class="text-3xl leading-none hidden sm:block"><?= $offer['glyph'] ?></div>
                </div>

                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3"><?= $offer['format'] ?></div>
                <p class="text-sm text-gray-700 leading-relaxed mb-4"><?= h($offer['description']) ?></p>

                <ul class="space-y-1.5 mb-5">
                    <?php foreach ($offer['includes'] as $item): ?>
                    <li class="text-sm text-gray-700 flex items-start gap-2">
                        <span class="<?= $offer['text'] ?> mt-0.5 flex-shrink-0">&#9658;</span>
                        <?= h($item) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-4 border-t <?= $offer['border'] ?>">
                    <div>
                        <span class="text-2xl font-extrabold text-gray-900"><?= h($offer['price']) ?></span>
                        <span class="text-sm text-gray-500 ml-1"><?= h($offer['unit']) ?></span>
                        <div class="text-xs text-gray-400 mt-0.5">Founding rate &mdash; subject to change</div>
                    </div>
                    <?php if ($user && in_array($key, $requested)): ?>
                    <div class="text-sm font-semibold <?= $offer['text'] ?>">&#10003; You're on the list &mdash; Jillian will reach out</div>
                    <?php elseif ($user): ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="offer_key" value="<?= h($key) ?>">
                        <button type="submit" class="<?= $offer['btn'] ?> text-white px-6 py-2.5 rounded-lg font-semibold transition">Request a Spot</button>
                    </form>
                    <?php else: ?>
                    <a href="<?= h(authUrl('register', url('/work-with-jillian.php#offer-' . $key))) ?>" class="<?= $offer['btn'] ?> text-white px-6 py-2.5 rounded-lg font-semibold transition inline-block text-center">Request a Spot</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <div class="text-center mt-10">
            <p class="text-sm text-gray-500 max-w-xl mx-auto">Requesting a spot doesn't charge you anything &mdash; it puts you on the list, and Jillian reaches out personally to find the right fit and timing.</p>
            <?php if (!$user): ?>
            <p class="text-sm text-gray-500 mt-3">Haven't taken the quiz yet? <a href="<?= url('/starseed-quiz.php') ?>" class="text-violet-700 hover:text-violet-800 font-medium">Start there — it's free.</a></p>
            <?php endif; ?>
        </div>

        <p class="text-xs text-gray-400 max-w-2xl mx-auto mt-12 text-center leading-relaxed">All healing and metaphysical information offered here is for spiritual, educational, and informational purposes only. It is not a replacement for medical advice or treatment. Please consult a licensed healthcare professional for any medical concerns.</p>

    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
