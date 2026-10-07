<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once PF_ROOT . '/includes/config.php';
require_once PF_ROOT . '/includes/helpers.php';
require_once PF_ROOT . '/includes/auth.php';
require_once PF_ROOT . '/includes/db.php';

startSession();

// Redirect logged-in users straight to dashboard
if (currentUser()) {
    redirect('/dashboard.php');
}

$pageTitle = 'ProForma — Studio Pro Forma Modeling';
include PF_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto text-center py-16">
    <h1 class="text-4xl font-extrabold text-gray-900 mb-4">Know your numbers before you open the doors.</h1>
    <p class="text-lg text-gray-600 mb-8">
        ProForma models the financial reality of your yoga studio — class fill rates, instructor pay, overhead,
        subscriptions, and what you can actually pay yourself — in one place.
    </p>
    <div class="flex justify-center gap-4">
        <a href="/account/register.php?next=<?= rawurlencode(PF_BASE . '/dashboard.php') ?>" class="bg-indigo-600 text-white px-6 py-3 rounded-lg text-base font-semibold hover:bg-indigo-700 transition-colors">
            Get started free
        </a>
        <a href="/account/login.php?next=<?= rawurlencode(PF_BASE . '/dashboard.php') ?>" class="border border-gray-300 text-gray-700 px-6 py-3 rounded-lg text-base font-semibold hover:bg-gray-50 transition-colors">
            Log in
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="text-2xl mb-3">&#128200;</div>
        <h2 class="font-semibold text-gray-900 mb-1">Break-even at a glance</h2>
        <p class="text-sm text-gray-600">See exactly what fill rate you need to cover rent, instructor pay, and your own salary.</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="text-2xl mb-3">&#128176;</div>
        <h2 class="font-semibold text-gray-900 mb-1">Pricing that pencils out</h2>
        <p class="text-sm text-gray-600">Model drop-ins, class passes, unlimited memberships, and private sessions side by side.</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="text-2xl mb-3">&#128736;</div>
        <h2 class="font-semibold text-gray-900 mb-1">Instructor pay sanity check</h2>
        <p class="text-sm text-gray-600">Know whether your flat rates and revenue shares are sustainable before you hire.</p>
    </div>
</div>

<?php include PF_ROOT . '/templates/footer.php'; ?>
