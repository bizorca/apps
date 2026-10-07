<?php
/** Sign in to the shared tools account. */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/private_html/includes/bootstrap.php';
require_once TL_PRIVATE . '/includes/chrome.php';

$next = tl_safe_next($_GET['next'] ?? null);

if (tl_logged_in()) {
    header('Location: ' . $next);
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    if (!tl_csrf_ok($_POST['_csrf'] ?? null)) {
        $error = 'That form had gone stale. Try again.';
    } else {
        [$ok, $msg] = tl_login($email, (string) ($_POST['password'] ?? ''));
        if ($ok) {
            header('Location: ' . $next, true, 303);
            exit;
        }
        $error = $msg;
    }
}

tl_page_open('Sign in');
tl_card_open('Sign in', 'One account works across every tool on this site.', $error);
?>
      <form method="post" class="space-y-4">
        <?= tl_csrf_field() ?>
        <?= tl_field('email', 'Email', 'email', $email, 'email') ?>
        <?= tl_field('password', 'Password', 'password', '', 'current-password') ?>
        <button type="submit" class="<?= TL_BUTTON ?>">Sign in</button>
        <p class="text-sm text-slate-500 text-center">
          <a href="/account/forgot.php<?= tl_h(tl_next_query()) ?>" class="hover:text-brand-600">Forgot your password?</a>
          &middot;
          <a href="/account/register.php<?= tl_h(tl_next_query()) ?>" class="hover:text-brand-600">Create an account</a>
        </p>
      </form>
<?php
tl_card_close();
tl_page_close();
