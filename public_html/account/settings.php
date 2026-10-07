<?php
/** Name and password for the shared account. */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/private_html/includes/bootstrap.php';
require_once TL_PRIVATE . '/includes/chrome.php';

$user   = tl_require_login();
$error  = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tl_csrf_ok($_POST['_csrf'] ?? null)) {
        $error = 'That form had gone stale. Try again.';
    } else {
        $name    = trim((string) ($_POST['name'] ?? ''));
        $current = (string) ($_POST['current'] ?? '');
        $new     = (string) ($_POST['password'] ?? '');

        if ($name === '') {
            $error = 'What should we call you?';
        } elseif ($new !== '' && !password_verify($current, $user['password_hash'])) {
            $error = 'Your current password is not right, so the password was not changed.';
        } elseif ($new !== '' && strlen($new) < 10) {
            $error = 'Use at least 10 characters for the new password.';
        } else {
            tl_db()->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $user['id']]);
            if ($new !== '') {
                tl_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                       ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            }
            tl_user(true);
            $user   = tl_user();
            $notice = 'Saved.';
        }
    }
}

tl_page_open('Your account');
tl_card_open('Your account', 'Signed in as <strong>' . tl_h($user['email']) . '</strong>.', $error, $notice);
?>
      <form method="post" class="space-y-4">
        <?= tl_csrf_field() ?>
        <?= tl_field('name', 'Your name', 'text', $user['name'], 'name') ?>
        <p class="text-sm text-slate-500 pt-2">To change your password, fill in both below. Leave them blank otherwise.</p>
        <?= tl_field('current', 'Current password', 'password', '', 'current-password', false) ?>
        <?= tl_field('password', 'New password (10+ characters)', 'password', '', 'new-password', false) ?>
        <button type="submit" class="<?= TL_BUTTON ?>">Save</button>
      </form>
<?php
tl_card_close();
tl_page_close();
