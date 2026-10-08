<?php
declare(strict_types=1);

/**
 * Tier picker + Stripe Checkout. DORMANT while NB_PAYMENTS_ENABLED is false:
 * reports are free then, and this page just sends the visitor on.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once NB_ROOT . '/includes/multiples.php';

$user   = requireLogin();
$report = findOwnReport((int) ($_GET['id'] ?? 0));
if (!$report || (int) $report['user_id'] !== (int) $user['id']) {
    notFound();
}
$id = (int) $report['id'];

if ($report['status'] === 'complete') {
    redirect('/report.php?id=' . $id);
}
if ((int) $report['wizard_step'] < 3) {
    redirect('/wizard.php?step=' . (int) $report['wizard_step'] . '&id=' . $id);
}
if (!NB_PAYMENTS_ENABLED) {
    // Free: step 3 of the wizard generates the report.
    redirect('/wizard.php?step=3&id=' . $id);
}

require_once NB_ROOT . '/includes/payments.php';

// ── POST: redirect to Stripe ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf'] ?? null)) {
        setFlash('error', 'Invalid form submission. Please try again.');
        redirect('/checkout.php?id=' . $id);
    }

    $tier = ($_POST['tier'] ?? 'standard') === 'premium' ? 'premium' : 'standard';

    try {
        header('Location: ' . createCheckoutUrl($report, $user, $tier), true, 303);
        exit;
    } catch (Throwable $e) {
        // Stripe's own message stays in the log, not on the page.
        error_log('numbrella checkout ' . $id . ': ' . $e->getMessage());
        setFlash('error', 'Payment setup failed. Please try again in a minute.');
        redirect('/checkout.php?id=' . $id);
    }
}

$flash    = getFlash();
$industry = getMultiplesForIndustry($report['industry_key'])['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Report — <?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<?php include NB_ROOT . '/templates/header.php'; ?>

<main class="max-w-3xl mx-auto px-4 py-10">

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Choose your report</h1>
        <p class="text-gray-500 mt-1 text-sm">
            Valuation for: <strong><?= h($report['business_name'] ?: 'Unnamed Business') ?></strong>
            &middot; <?= h($industry) ?>
        </p>
    </div>

    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-lg <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200' ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['cancelled'])): ?>
        <div class="mb-6 p-4 rounded-lg bg-yellow-50 text-yellow-800 border border-yellow-200 text-sm">
            Your payment was cancelled. Your data has been saved — you can pick up where you left off whenever you're ready.
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= h(url('/checkout.php?id=' . $id)) ?>">
        <?= csrfField() ?>
        <input type="hidden" name="tier" id="selected_tier" value="standard">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

            <!-- Standard -->
            <div id="card-standard" onclick="selectTier('standard')"
                class="tier-card cursor-pointer rounded-2xl border-2 border-indigo-600 bg-white p-6 relative transition-all">
                <div class="absolute top-4 right-4 text-xs font-semibold bg-indigo-600 text-white px-2 py-0.5 rounded-full">Most popular</div>
                <h2 class="text-lg font-bold text-gray-900 mb-1">Standard Report</h2>
                <p class="text-3xl font-extrabold text-indigo-600 mb-4"><?= h(tl_money(NB_PRICE_STANDARD)) ?> <span class="text-base font-normal text-gray-400">one-time</span></p>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex gap-2"><span class="text-indigo-500 font-bold">✓</span> Three valuation methods (SDE, Revenue, DCF)</li>
                    <li class="flex gap-2"><span class="text-indigo-500 font-bold">✓</span> Consensus valuation range</li>
                    <li class="flex gap-2"><span class="text-indigo-500 font-bold">✓</span> Asking price assessment</li>
                    <li class="flex gap-2"><span class="text-indigo-500 font-bold">✓</span> Risk flags with plain-language explanations</li>
                    <li class="flex gap-2"><span class="text-indigo-500 font-bold">✓</span> Step-by-step methodology shown</li>
                    <li class="flex gap-2"><span class="text-indigo-500 font-bold">✓</span> Downloadable PDF</li>
                    <li class="flex gap-2"><span class="text-indigo-500 font-bold">✓</span> Shareable link</li>
                </ul>
            </div>

            <!-- Premium -->
            <div id="card-premium" onclick="selectTier('premium')"
                class="tier-card cursor-pointer rounded-2xl border-2 border-gray-200 bg-white p-6 relative transition-all hover:border-gray-400">
                <h2 class="text-lg font-bold text-gray-900 mb-1">Premium Report</h2>
                <p class="text-3xl font-extrabold text-gray-900 mb-4"><?= h(tl_money(NB_PRICE_PREMIUM)) ?> <span class="text-base font-normal text-gray-400">one-time</span></p>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex gap-2"><span class="text-green-500 font-bold">✓</span> Everything in Standard</li>
                    <li class="flex gap-2"><span class="text-green-500 font-bold">✓</span> 3-year cash flow projection</li>
                    <li class="flex gap-2"><span class="text-green-500 font-bold">✓</span> Scenario analysis (5 price points)</li>
                                        <li class="flex gap-2"><span class="text-green-500 font-bold">✓</span> Industry benchmark context</li>
                </ul>
            </div>
        </div>

        <div class="flex justify-between items-center">
            <a href="<?= h(url('/wizard.php?step=3&id=' . $id)) ?>"
                class="text-sm text-gray-500 hover:text-gray-700 font-medium py-2.5">
                ← Back to Market Position
            </a>
            <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-8 py-3 rounded-lg text-sm transition-colors">
                Continue to Payment →
            </button>
        </div>

        <p class="mt-4 text-center text-xs text-gray-400">
            Secure payment via Stripe. This is a one-time charge — no subscription, no recurring fees.
        </p>
    </form>

</main>

<script>
function selectTier(tier) {
    document.getElementById('selected_tier').value = tier;
    const cards = document.querySelectorAll('.tier-card');
    cards.forEach(c => {
        c.classList.remove('border-indigo-600', 'border-gray-200');
        c.classList.add('border-gray-200');
    });
    const selected = document.getElementById('card-' + tier);
    selected.classList.remove('border-gray-200');
    selected.classList.add('border-indigo-600');
}
</script>

</body>
</html>
