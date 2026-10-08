<?php
require_once __DIR__ . '/_bootstrap.php';

if (isLoggedIn()) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$pageTitle = 'Train Your Thinking';
require TR_ROOT . '/templates/header.php';
?>

<div class="text-center py-16">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">🧠 Thinkrep</h1>
    <p class="text-xl text-gray-600 mb-2">Turn cognitive frameworks into daily practice.</p>
    <p class="text-lg text-gray-500 mb-8 max-w-2xl mx-auto">
        Face real-world decision scenarios. Identify which mental model applies. Write your reasoning.
        Get scored on both the answer and the quality of your thinking.
    </p>
    <a href="/account/register.php?next=<?= rawurlencode(url('/dashboard.php')) ?>" class="inline-block bg-tr-600 text-white px-8 py-3 rounded-lg text-lg font-semibold hover:bg-tr-700 transition">
        Get Started
    </a>
</div>

<div class="grid md:grid-cols-3 gap-8 mt-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Scenario-Based Training</h3>
        <p class="text-gray-600">Real-world decision scenarios drawn from business, startups, and leadership. No abstract theory — every scenario is something you might actually face.</p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Scored Reasoning</h3>
        <p class="text-gray-600">You are scored on both identifying the right mental model and the quality of your written reasoning. Picking the right answer is not enough — you have to explain why.</p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Blind Spot Detection</h3>
        <p class="text-gray-600">Over time, Thinkrep surfaces your cognitive blind spots — which models you overuse, which you miss, and where you are confidently wrong.</p>
    </div>
</div>

<div class="mt-16 text-center">
    <h2 class="text-2xl font-bold text-gray-900 mb-6">10 Mental Models</h2>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 max-w-4xl mx-auto">
        <?php
        $models = [
            'Sunk Cost Fallacy', 'First Principles', 'Second-Order Effects',
            'Inversion', 'Base Rate Neglect', 'Confirmation Bias',
            'Availability Heuristic', 'Anchoring', 'Opportunity Cost', "Occam's Razor"
        ];
        foreach ($models as $model): ?>
        <div class="bg-tr-50 border border-tr-200 rounded-lg px-4 py-3">
            <span class="text-sm font-medium text-tr-700"><?= h($model) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
