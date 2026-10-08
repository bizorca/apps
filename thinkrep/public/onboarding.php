<?php
require_once __DIR__ . '/_bootstrap.php';
requireLogin();

$user = getCurrentUser();

// Already onboarded? Go to dashboard
if (!empty($user['onboarded_at'])) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission. Please try again.');
        header('Location: ' . url('/onboarding.php'));
        exit;
    }

    $roleTitle = trim($_POST['role_title'] ?? '');
    $industry = trim($_POST['industry'] ?? '');

    saveProfile((int) $user['id'], [
        'role_title'   => $roleTitle ?: null,
        'industry'     => $industry ?: null,
        'onboarded_at' => gmdate('Y-m-d H:i:s'),   // UTC, like NOW() on the shared connection
    ]);

    setFlash('success', 'Welcome to Thinkrep! Let\'s start training.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$pageTitle = 'Get Started';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Welcome to Thinkrep</h1>
    <p class="text-gray-600 mb-6">Tell us a bit about yourself so we can tailor scenarios to your world.</p>

    <form method="POST" class="bg-white rounded-lg shadow p-6 space-y-6">
        <?= csrfField() ?>

        <div>
            <label for="role_title" class="block text-sm font-medium text-gray-700 mb-1">Your Role</label>
            <select name="role_title" id="role_title" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                <option value="">Select your role (optional)</option>
                <option value="founder">Founder / CEO</option>
                <option value="product_manager">Product Manager</option>
                <option value="engineering_manager">Engineering Manager</option>
                <option value="sales_director">Sales Director</option>
                <option value="marketing_director">Marketing Director</option>
                <option value="hr_director">HR Director</option>
                <option value="consultant">Consultant</option>
                <option value="investor">Investor</option>
                <option value="data_analyst">Data Analyst</option>
                <option value="operations">Operations</option>
                <option value="nonprofit_director">Nonprofit Director</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div>
            <label for="industry" class="block text-sm font-medium text-gray-700 mb-1">Your Industry</label>
            <select name="industry" id="industry" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                <option value="">Select your industry (optional)</option>
                <option value="saas">SaaS / Software</option>
                <option value="technology">Technology</option>
                <option value="finance">Finance</option>
                <option value="fintech">Fintech</option>
                <option value="healthcare">Healthcare</option>
                <option value="ecommerce">E-commerce / Retail</option>
                <option value="consulting">Consulting</option>
                <option value="professional_services">Professional Services</option>
                <option value="manufacturing">Manufacturing</option>
                <option value="education">Education</option>
                <option value="nonprofit">Nonprofit</option>
                <option value="other">Other</option>
            </select>
        </div>

        <button type="submit" class="w-full bg-tr-600 text-white py-3 rounded-lg font-semibold hover:bg-tr-700 transition">
            Start Training
        </button>

        <p class="text-sm text-gray-500 text-center">You can change these later in Settings.</p>
    </form>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
