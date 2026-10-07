<?php
/** Create a shared tools account. Open to anyone. */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/private_html/includes/bootstrap.php';
require_once TL_PRIVATE . '/includes/chrome.php';

$next = tl_safe_next($_GET['next'] ?? null);

if (tl_logged_in()) {
    header('Location: ' . $next);
    exit;
}

$error = '';
$name  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    if (!tl_csrf_ok($_POST['_csrf'] ?? null)) {
        $error = 'That form had gone stale. Try again.';
    } else {
        [$ok, $msg] = tl_register($email, (string) ($_POST['password'] ?? ''), $name);
        if ($ok) {
            header('Location: ' . $next, true, 303);
            exit;
        }
        $error = $msg;
    }
}

tl_page_open('Create an account');
tl_card_open('Create an account', 'Free. One account covers every tool here, and nothing gets shared with anyone.', $error);
?>
      <form method="post" class="space-y-4">
        <?= tl_csrf_field() ?>
        <?= tl_field('name', 'Your name', 'text', $name, 'name') ?>
        <?= tl_field('email', 'Email', 'email', $email, 'email') ?>
        <?= tl_field('password', 'Password (10+ characters)', 'password', '', 'new-password') ?>
        <button type="submit" class="<?= TL_BUTTON ?>">Create account</button>
        <p class="text-sm text-slate-500 text-center">
          Already have one? <a href="/account/login.php<?= tl_h(tl_next_query()) ?>" class="hover:text-brand-600">Sign in</a>
        </p>
      </form>
<?php
tl_card_close();
tl_page_close();
