<?php
/**
 * Sign out. A POST with a CSRF token does it; a plain GET (the nav link) shows
 * a one-button confirmation, so a stray link elsewhere cannot sign anyone out.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/private_html/includes/bootstrap.php';
require_once TL_PRIVATE . '/includes/chrome.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && tl_csrf_ok($_POST['_csrf'] ?? null)) {
    tl_logout();
    header('Location: /', true, 303);
    exit;
}

if (!tl_logged_in()) {
    header('Location: /');
    exit;
}

tl_page_open('Sign out');
tl_card_open('Sign out?', 'This signs you out of every tool on this site.');
?>
      <form method="post">
        <?= tl_csrf_field() ?>
        <button type="submit" class="<?= TL_BUTTON ?>">Sign out</button>
      </form>
<?php
tl_card_close();
tl_page_close();
