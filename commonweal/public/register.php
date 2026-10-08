<?php
/**
 * Join Commonweal with your shared tools account.
 * Was: create a separate Commonweal login. Now the account comes from /account;
 * this page records the role (business owner or volunteer coordinator).
 * Coordinators still need admin approval, as before.
 */
require __DIR__ . '/_bootstrap.php';

$isCoordinator = isset($_GET['role']) && $_GET['role'] === 'coordinator';

if (!isLoggedIn()) {
    $next = url('/register.php') . ($isCoordinator ? '?role=coordinator' : '');
    header('Location: /account/register.php?next=' . rawurlencode($next));
    exit;
}

$me       = getCurrentUser();
$account  = tl_user();
$errors   = [];
$why      = '';

// Already a member: send them where they belong, unless an approval is pending.
if ($me) {
    if ($me['role'] === 'coordinator' && (int)$me['coordinator_approved'] === 0 && !isAdmin()) {
        $pageTitle = 'Application received';
        require CW_ROOT . '/templates/header.php';
        echo '<div class="max-w-lg mx-auto bg-white rounded-xl shadow-sm border border-gray-100 p-8">'
           . '<h1 class="text-2xl font-bold text-gray-900 mb-3">Your coordinator application is pending</h1>'
           . '<p class="text-gray-600">An administrator will review it. You\'ll get an email when your account is approved.</p></div>';
        require CW_ROOT . '/templates/footer.php';
        exit;
    }
    header('Location: ' . url(isAdmin() ? '/admin.php' : (isCoordinator() ? '/coordinator.php' : '/dashboard.php')));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['form'] = 'Invalid security token. Please try again.';
    }
    $role = ($_POST['role'] ?? 'client') === 'coordinator' ? 'coordinator' : 'client';
    $isCoordinator = $role === 'coordinator';
    $why = trim($_POST['why'] ?? '');
    if ($isCoordinator && $why === '') {
        $errors['why'] = 'Please tell us why you want to volunteer as a coordinator.';
    }

    if (empty($errors)) {
        getDB()->prepare('INSERT IGNORE INTO cw_members (user_id, role, coordinator_approved, coordinator_why) VALUES (?, ?, 0, ?)')
               ->execute([(int)$account['id'], $role, $isCoordinator ? $why : null]);
        if ($isCoordinator) {
            setFlash('success', 'Your coordinator application has been submitted. An administrator will review and approve your account before you can access the coordinator dashboard.');
            header('Location: ' . url('/register.php'));
        } else {
            setFlash('success', 'Welcome to CoopConvert. Start with your business intake.');
            header('Location: ' . url('/dashboard.php'));
        }
        exit;
    }
}

$pageTitle = $isCoordinator ? 'Volunteer as a Coordinator' : 'Start Your Conversion';
require CW_ROOT . '/templates/header.php';
?>
<div class="max-w-lg mx-auto">
    <div class="mb-8">
        <?php if ($isCoordinator): ?>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Become a Coordinator</h1>
            <p class="text-gray-600">Coordinator accounts require admin approval. Once approved, you'll have access to the coordinator dashboard to guide business owners through the conversion process.</p>
        <?php else: ?>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Start Your Conversion</h1>
            <p class="text-gray-600">You're signed in as <strong><?= h($account['name']) ?></strong> (<?= h($account['email']) ?>). Join CoopConvert as a business owner to begin your intake.</p>
        <?php endif; ?>
    </div>
    <?php if (!empty($errors['form'])): ?>
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm"><?= h($errors['form']) ?></div>
    <?php endif; ?>
    <form method="post" class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 space-y-5">
        <?= csrfField() ?>
        <input type="hidden" name="role" value="<?= $isCoordinator ? 'coordinator' : 'client' ?>">
        <?php if ($isCoordinator): ?>
            <div>
                <label for="why" class="block text-sm font-medium text-gray-700 mb-1">Why do you want to volunteer?</label>
                <textarea id="why" name="why" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2"><?= h($why) ?></textarea>
                <?php if (!empty($errors['why'])): ?><p class="text-red-600 text-sm mt-1"><?= h($errors['why']) ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-3 rounded-lg">
            <?= $isCoordinator ? 'Submit Coordinator Application' : 'Join and Start My Intake' ?>
        </button>
        <p class="text-sm text-gray-500 text-center">
            <?php if ($isCoordinator): ?>
                Own a business? <a class="text-brand-700 underline" href="<?= url('/register.php') ?>">Join as a business owner</a>
            <?php else: ?>
                Want to help instead? <a class="text-brand-700 underline" href="<?= url('/register.php?role=coordinator') ?>">Volunteer as a coordinator</a>
            <?php endif; ?>
        </p>
    </form>
</div>
<?php require CW_ROOT . '/templates/footer.php';
