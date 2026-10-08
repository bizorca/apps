<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
requireLogin();

if (!isAdmin()) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/admin-members.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';
    $memberId = (int)($_POST['member_id'] ?? 0);

    if ($memberId <= 0) {
        setFlash('error', 'Invalid member.');
        header('Location: ' . url('/admin-members.php'));
        exit;
    }

    // Don't allow admin to delete themselves
    $adminUser = getCurrentUser();

    if ($action === 'delete' && $memberId !== $adminUser['id']) {
        // The account is shared by every tool on the site, so this removes the
        // person's Astrology data only; the account itself stays. (The original
        // deleted the users row, and crashed first on a password_resets column
        // that does not exist.)
        removeAstrologyMember($memberId);
        setFlash('success', 'Member removed from Astrology. Their tools account is unchanged.');
        header('Location: ' . url('/admin-members.php'));
        exit;
    }

    header('Location: ' . url('/admin-members.php'));
    exit;
}

// View single member
$viewId = (int)($_GET['view'] ?? 0);
if ($viewId > 0) {
    $stmt = $db->prepare("SELECT u.*, m.primary_profile_id, m.timezone, m.created_at AS joined_at
                          FROM users u JOIN as_members m ON m.user_id = u.id WHERE u.id = ?");
    $stmt->execute([$viewId]);
    $member = $stmt->fetch();

    if (!$member) {
        setFlash('error', 'Member not found.');
        header('Location: ' . url('/admin-members.php'));
        exit;
    }

    // Load their profiles
    $stmt = $db->prepare("SELECT * FROM as_profiles WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$viewId]);
    $profiles = $stmt->fetchAll();

    // Load their readings count
    $stmt = $db->prepare("SELECT reading_type, COUNT(*) as cnt FROM as_readings WHERE user_id = ? GROUP BY reading_type");
    $stmt->execute([$viewId]);
    $readingCounts = [];
    while ($row = $stmt->fetch()) {
        $readingCounts[$row['reading_type']] = $row['cnt'];
    }

    $pageTitle = 'Member: ' . $member['name'];
    require AS_ROOT . '/templates/header.php';
    ?>

    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <a href="<?= url('/admin-members.php') ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">&larr; All Members</a>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?= h($member['name']) ?></h1>
                    <p class="text-gray-500"><?= h($member['email']) ?></p>
                </div>
                <?php if ((int)$member['id'] !== (int)getCurrentUserId()): ?>
                <form method="POST" onsubmit="return confirm('Remove this member\'s Astrology profiles, readings and results? Their tools account stays. This cannot be undone.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                    <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium px-3 py-1.5 rounded-lg hover:bg-red-50 transition">Remove from Astrology</button>
                </form>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 text-sm">
                <div>
                    <div class="text-gray-500">Joined</div>
                    <div class="font-medium text-gray-900"><?= date('M j, Y', strtotime($member['joined_at'])) ?></div>
                </div>
                <div>
                    <div class="text-gray-500">Plan</div>
                    <div class="font-medium text-gray-900"><?= h(ucfirst(getEffectivePlan($member['id']))) ?></div>
                </div>
                <div>
                    <div class="text-gray-500">Email Verified</div>
                    <div class="font-medium text-gray-900"><?= !empty($member['email_verified_at']) ? 'Yes' : 'No' ?></div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-200">
                <p class="text-sm text-gray-500">Everything is free during early access, so there is no plan to manage. The account itself (name, email, password) is the shared Bizorca Tools account.</p>
            </div>
        </div>

        <!-- Reading Stats -->
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="font-bold text-gray-900 mb-3">Readings</h2>
            <?php if (empty($readingCounts)): ?>
            <p class="text-sm text-gray-500">No readings generated yet.</p>
            <?php else: ?>
            <div class="flex gap-4 text-sm">
                <?php foreach ($readingCounts as $type => $cnt): ?>
                <div class="bg-gray-50 rounded-lg px-4 py-2">
                    <span class="font-medium text-gray-900"><?= $cnt ?></span>
                    <span class="text-gray-500"><?= h(ucfirst($type)) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Profiles -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="font-bold text-gray-900 mb-3">Astrology Profiles</h2>
            <?php if (empty($profiles)): ?>
            <p class="text-sm text-gray-500">No profiles created yet.</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($profiles as $p):
                    $pCss = getElementCSS($p['zodiac_element']);
                    $pEmoji = getAnimalEmoji($p['zodiac_animal']);
                    $isPrimary = ($member['primary_profile_id'] == $p['id']);
                ?>
                <div class="flex items-center gap-4 p-3 rounded-lg <?= $isPrimary ? 'bg-brand-50 border border-brand-200' : 'bg-gray-50' ?>">
                    <div class="text-2xl"><?= $pEmoji ?></div>
                    <div class="flex-1">
                        <div class="font-medium text-gray-900">
                            <?= h($p['zodiac_element']) ?> <?= h($p['zodiac_animal']) ?>
                            <?php if ($isPrimary): ?><span class="text-xs text-brand-600 font-normal">(primary)</span><?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            Born <?= h($p['birth_year']) ?>
                            <?php if ($p['birth_month']): ?>/<?= h($p['birth_month']) ?><?php endif; ?>
                            <?php if ($p['birth_day']): ?>/<?= h($p['birth_day']) ?><?php endif; ?>
                            <?php if ($p['birth_hour'] !== null): ?> at <?= h($p['birth_hour']) ?>:00<?php endif; ?>
                            &middot; <?= h($p['yin_yang']) ?>
                            &middot; <?= h($p['heavenly_stem']) ?> / <?= h($p['earthly_branch']) ?>
                        </div>
                    </div>
                    <a href="<?= url('/profile.php') ?>?id=<?= $p['id'] ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">View</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    require AS_ROOT . '/templates/footer.php';
    exit;
}

// List all members
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE (u.name LIKE ? OR u.email LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

// Get total count
// Members are people who have used Astrology (an as_members row), not every
// account on the shared site.
$stmt = $db->prepare("SELECT COUNT(*) as total FROM users u JOIN as_members m ON m.user_id = u.id $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

// Get members with their primary profile
$sql = "SELECT u.*, m.created_at AS joined_at, ap.zodiac_animal, ap.zodiac_element, ap.birth_year as profile_birth_year
        FROM users u
        JOIN as_members m ON m.user_id = u.id
        LEFT JOIN as_profiles ap ON ap.id = m.primary_profile_id
        $where
        ORDER BY m.created_at DESC, u.id DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

$pageTitle = 'Members';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Members</h1>
            <p class="text-sm text-gray-500"><?= $total ?> total</p>
        </div>
        <a href="<?= url('/admin-settings.php') ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">&larr; Admin Settings</a>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-6">
        <div class="flex gap-3">
            <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search by name or email..."
                   class="flex-1 rounded-lg border-gray-300 px-4 py-2.5 border focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
            <button type="submit" class="bg-brand-600 text-white px-5 py-2.5 rounded-lg hover:bg-brand-700 font-medium">Search</button>
            <?php if ($search !== ''): ?>
            <a href="<?= url('/admin-members.php') ?>" class="px-4 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Members Table -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Member</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 hidden sm:table-cell">Zodiac</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Plan</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Joined</th>
                    <th class="text-right px-4 py-3 font-medium text-gray-600"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($members)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No members found.</td></tr>
                <?php endif; ?>
                <?php foreach ($members as $m):
                    $isAdminUser = !empty($m['is_admin']) || isAstrologyAdmin((int)$m['id']);
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">
                            <?= h($m['name']) ?>
                            <?php if ($isAdminUser): ?><span class="text-xs text-amber-600 font-normal">(admin)</span><?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-500"><?= h($m['email']) ?></div>
                    </td>
                    <td class="px-4 py-3 hidden sm:table-cell">
                        <?php if ($m['zodiac_animal']): ?>
                        <span class="mr-1"><?= getAnimalEmoji($m['zodiac_animal']) ?></span>
                        <span class="text-gray-700"><?= h($m['zodiac_element']) ?> <?= h($m['zodiac_animal']) ?></span>
                        <?php else: ?>
                        <span class="text-gray-400">No profile</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 hidden md:table-cell">
                        <span class="text-gray-500">Tools account</span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 hidden md:table-cell"><?= date('M j, Y', strtotime($m['joined_at'])) ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= url('/admin-members.php') ?>?view=<?= $m['id'] ?>" class="text-brand-600 hover:text-brand-700 font-medium">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between mt-4">
        <div class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></div>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
            <a href="<?= url('/admin-members.php') ?>?page=<?= $page - 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">&larr; Prev</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
            <a href="<?= url('/admin-members.php') ?>?page=<?= $page + 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Next &rarr;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
