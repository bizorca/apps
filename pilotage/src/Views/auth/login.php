<?php
/**
 * Shown only when someone is signed in to their tools account but is not an
 * active member of this firm. Signing in itself happens on the shared
 * /account pages; AuthController::showLogin() sends people there and back.
 *
 * @var array<int,string> $errors
 * @var string|null $notice
 * @var string $accountEmail
 */
use Bizorca\Pilotage\Core\View;

ob_start(); ?>

<p class="text-sm text-slate-700">
    You are signed in as <strong><?= h($accountEmail) ?></strong>, and that account is not a
    member of this workspace.
</p>
<p class="mt-3 text-sm text-slate-600">
    If you were invited, open the invitation link from your email. If you were invited at a
    different address, sign out and sign in with that one.
</p>

<div class="mt-5 grid gap-2">
    <a href="/account/logout.php"
       class="block w-full rounded bg-slate-900 px-3 py-2 text-center text-sm font-medium text-white hover:bg-slate-800">
        Sign in as someone else
    </a>
    <a href="<?= h(app_url('/signin')) ?>"
       class="block w-full rounded border border-slate-300 px-3 py-2 text-center text-sm font-medium hover:bg-slate-50">
        Find my workspaces
    </a>
</div>

<?php
$inner = (string) ob_get_clean();

echo View::render('auth.shell', [
    'heading' => 'Not a member here',
    'sub'     => null,
    'inner'   => $inner,
    'errors'  => $errors,
    'notice'  => $notice,
], null);
