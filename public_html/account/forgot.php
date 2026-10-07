<?php
/** Request a password reset link. Same answer whether or not the account exists. */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/private_html/includes/bootstrap.php';
require_once TL_PRIVATE . '/includes/chrome.php';
require_once TL_PRIVATE . '/includes/mailer.php';

$error  = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tl_csrf_ok($_POST['_csrf'] ?? null)) {
        $error = 'That form had gone stale. Try again.';
    } else {
        $reset = tl_reset_create((string) ($_POST['email'] ?? ''));
        if ($reset) {
            $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') ? 'https' : 'http';
            $link   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'tools.bizorca.com')
                    . '/account/reset.php?t=' . rawurlencode($reset['token']);
            tl_mail(
                $reset['user']['email'],
                'Reset your Bizorca Tools password',
                "Hi {$reset['user']['name']},\n\nSomeone asked to reset the password on your Bizorca Tools account. "
                . "If that was you, this link works for one hour:\n\n{$link}\n\n"
                . "If it wasn't, ignore this and nothing changes.\n"
            );
        }
        $notice = 'If there is an account with that address, a reset link is on its way. It works for one hour.';
    }
}

tl_page_open('Reset your password');
tl_card_open('Forgot your password?', 'Enter your email and we will send a link to set a new one.', $error, $notice);
?>
      <form method="post" class="space-y-4">
        <?= tl_csrf_field() ?>
        <?= tl_field('email', 'Email', 'email', '', 'email') ?>
        <button type="submit" class="<?= TL_BUTTON ?>">Send reset link</button>
        <p class="text-sm text-slate-500 text-center">
          <a href="/account/login.php<?= tl_h(tl_next_query()) ?>" class="hover:text-brand-600">Back to sign in</a>
        </p>
      </form>
<?php
tl_card_close();
tl_page_close();
