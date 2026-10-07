<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';

/*
 * Settings, after the move to the shared tools account.
 *
 * Gone from here: the password form (now /account/settings.php), "add a user"
 * with a temporary password (people create their own account), and the
 * Anthropic key field (now a server setting, private_html/.env.php).
 *
 * What is left is TinyBooks' own business: who can open the current company.
 * The old "add user" made a login but never gave it a company, so the person
 * it was for could see nothing; adding a member here fixes that.
 */

$user    = auth_require();
$company = current_company();
if (!$company) { header('Location: ' . APP_URL . '/companies/create.php'); exit; }

$company_id = (int)$company['id'];
$is_owner   = user_company_role($user['id'], $company_id) === 'owner';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!$is_owner) {
        flash_set('error', 'Only an owner of this company can change who has access.');
        header('Location: index.php'); exit;
    }

    if (isset($_POST['add_member'])) {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role  = ($_POST['role'] ?? 'member') === 'owner' ? 'owner' : 'member';
        $find  = db()->prepare("SELECT id, name FROM users WHERE email = ?");
        $find->execute([$email]);
        $member = $find->fetch();

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Enter a valid email address.');
        } elseif (!$member) {
            flash_set('error', "There is no Bizorca Tools account for {$email} yet. Ask them to create one at tools.bizorca.com/account/register.php, then add them here.");
        } elseif (user_can_access_company((int)$member['id'], $company_id)) {
            flash_set('info', "{$member['name']} already has access.");
        } else {
            db()->prepare("INSERT INTO tb_user_companies (user_id, company_id, role) VALUES (?, ?, ?)")
                ->execute([$member['id'], $company_id, $role]);
            flash_set('success', "{$member['name']} can now open {$company['name']}.");
        }
    }

    if (isset($_POST['remove_member'])) {
        $member_id = (int)$_POST['remove_member'];
        $owners = db()->prepare("SELECT COUNT(*) FROM tb_user_companies WHERE company_id = ? AND role = 'owner' AND user_id <> ?");
        $owners->execute([$company_id, $member_id]);
        if (user_company_role($member_id, $company_id) === 'owner' && (int)$owners->fetchColumn() === 0) {
            flash_set('error', 'A company needs at least one owner. Make someone else an owner first.');
        } else {
            db()->prepare("DELETE FROM tb_user_companies WHERE company_id = ? AND user_id = ?")
                ->execute([$company_id, $member_id]);
            flash_set('success', 'Access removed.');
        }
    }

    header('Location: index.php'); exit;
}

$members = db()->prepare("
    SELECT u.id, u.name, u.email, uc.role
    FROM tb_user_companies uc
    JOIN users u ON u.id = uc.user_id
    WHERE uc.company_id = ?
    ORDER BY uc.role = 'owner' DESC, u.name
");
$members->execute([$company_id]);
$members = $members->fetchAll();

$ai_ready = (string)tl_env('ANTHROPIC_API_KEY', '') !== '';

$page_title = 'Settings';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<h1 class="text-2xl font-bold text-gray-900 mb-6">Settings</h1>

<div class="max-w-lg space-y-6">

    <!-- Company members -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6">
        <h2 class="font-semibold text-gray-900 mb-1">Who can open <?= h($company['name']) ?></h2>
        <p class="text-sm text-gray-500 mb-4">Everyone listed sees and edits these books. Owners can also add and remove people.</p>
        <table class="w-full text-sm mb-5">
            <thead>
                <tr class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    <th class="pb-2 text-left">Name</th>
                    <th class="pb-2 text-left">Email</th>
                    <th class="pb-2 text-left">Role</th>
                    <th class="pb-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($members as $m): ?>
                <tr>
                    <td class="py-2 text-gray-900"><?= h($m['name']) ?></td>
                    <td class="py-2 text-gray-500"><?= h($m['email']) ?></td>
                    <td class="py-2 text-gray-500"><?= $m['role'] === 'owner' ? 'Owner' : 'Member' ?></td>
                    <td class="py-2 text-right">
                        <?php if ($is_owner): ?>
                        <form method="post" onsubmit="return confirm('Remove <?= h($m['name']) ?>\'s access to these books?')">
                            <?= csrf_field() ?>
                            <button type="submit" name="remove_member" value="<?= (int)$m['id'] ?>" class="text-xs text-red-500 hover:underline">Remove</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($is_owner): ?>
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Give someone access</h3>
        <form method="post" class="space-y-3">
            <?= csrf_field() ?>
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Their Bizorca Tools email</label>
                    <input type="email" name="email" required class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Role</label>
                    <select name="role" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="member">Member</option>
                        <option value="owner">Owner</option>
                    </select>
                </div>
            </div>
            <p class="text-xs text-gray-400">They need their own account first, at tools.bizorca.com. Nobody gets a password from you.</p>
            <button type="submit" name="add_member" value="1" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg">
                Add
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- AI status -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6">
        <h2 class="font-semibold text-gray-900 mb-1">AI Categorization</h2>
        <p class="text-sm text-gray-500">
            TinyBooks uses Claude Haiku to suggest categories, flag likely duplicates and help with reconciliation.
            <?php if ($ai_ready): ?>
            <span class="text-green-600">&#10003; It is switched on.</span>
            <?php else: ?>
            It is switched off on this server right now. Everything else works normally.
            <?php endif; ?>
        </p>
    </div>

    <!-- Account -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6">
        <h2 class="font-semibold text-gray-900 mb-1">Your name and password</h2>
        <p class="text-sm text-gray-500">
            These belong to your Bizorca Tools account, which works across every tool.
            <a href="/account/settings.php" class="text-indigo-600 hover:underline">Change them here.</a>
        </p>
    </div>

</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
