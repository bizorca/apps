<?php
/** Consume a reset token and set a new password. */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/private_html/includes/bootstrap.php';
require_once TL_PRIVATE . '/includes/chrome.php';

$token = (string) ($_POST['t'] ?? $_GET['t'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tl_csrf_ok($_POST['_csrf'] ?? null)) {
        $error = 'That form had gone stale. Try again.';
    } else {
        [$ok, $msg] = tl_reset_consume($token, (string) ($_POST['password'] ?? ''));
        if ($ok) {
            tl_page_open('Password changed');
            tl_card_open('Password changed', '', '', 'Your new password is set.');
            echo '      <a href="/account/login.php" class="block text-center ' . TL_BUTTON . '">Sign in</a>' . "\n";
            tl_card_close();
            tl_page_close();
            exit;
        }
        $error = $msg;
    }
}

tl_page_open('Choose a new password');
tl_card_open('Choose a new password', '', $error);
?>
      <form method="post" class="space-y-4">
        <?= tl_csrf_field() ?>
        <input type="hidden" name="t" value="<?= tl_h($token) ?>">
        <?= tl_field('password', 'New password (10+ characters)', 'password', '', 'new-password') ?>
        <button type="submit" class="<?= TL_BUTTON ?>">Set password</button>
      </form>
<?php
tl_card_close();
tl_page_close();
