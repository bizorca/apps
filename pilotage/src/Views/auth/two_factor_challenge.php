<?php
/**
 * @var array<int,string> $errors
 */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;

ob_start(); ?>

<form method="post" action="<?= h(url('/login/2fa')) ?>" class="space-y-4">
    <?= Csrf::field() ?>

    <div>
        <label for="code" class="block text-sm font-medium mb-1">Authentication code</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9]*" maxlength="6" required autofocus
               class="w-full rounded border border-slate-300 px-3 py-2 text-center text-lg tracking-[0.4em] font-mono focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900">
    </div>

    <button type="submit"
            class="w-full rounded bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
        Verify
    </button>
</form>

<div class="mt-5 pt-5 border-t border-slate-200">
    <details>
        <summary class="text-sm text-slate-600 cursor-pointer hover:text-slate-900">Lost your device?</summary>
        <form method="post" action="<?= h(url('/login/2fa')) ?>" class="mt-3 space-y-3">
            <?= Csrf::field() ?>
            <input name="recovery_code" type="text" placeholder="recovery code"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm font-mono focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900">
            <button type="submit" class="w-full rounded border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">
                Use a recovery code
            </button>
        </form>
    </details>
</div>

<?php
$inner = (string) ob_get_clean();

echo View::render('auth.shell', [
    'heading' => 'Two-factor authentication',
    'sub'     => 'Enter the six-digit code from your authenticator app.',
    'inner'   => $inner,
    'errors'  => $errors,
    'notice'  => null,
], null);
