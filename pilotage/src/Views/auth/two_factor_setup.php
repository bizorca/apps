<?php
/**
 * TOTP enrolment. Mandatory for firm owners (FR-2.2).
 *
 * The secret is rendered for manual entry; the QR is drawn client-side from
 * the otpauth URI so the secret never travels to a third-party chart service.
 *
 * @var string $secret
 * @var string $uri
 * @var bool $mandatory
 * @var array<int,string> $errors
 * @var array<int,string>|null $recoveryCodes
 */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;

ob_start(); ?>

<?php if (!empty($recoveryCodes)): ?>

    <div class="rounded border border-amber-200 bg-amber-50 p-3 mb-4">
        <div class="text-sm font-semibold text-amber-900 mb-2">Save these recovery codes</div>
        <p class="text-xs text-amber-900 mb-3">
            Each works once, if you lose your device. This is the only time they are shown.
        </p>
        <div class="grid grid-cols-2 gap-1 font-mono text-xs text-amber-950">
            <?php foreach ($recoveryCodes as $code): ?>
                <div><?= h($code) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <a href="<?= h(url('/')) ?>"
       class="block w-full text-center rounded bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
        I have saved them — continue
    </a>

<?php else: ?>

    <div class="flex justify-center mb-4">
        <div id="qr" class="p-2 bg-white border border-slate-200 rounded"></div>
    </div>

    <div class="mb-4">
        <div class="text-xs text-slate-500 mb-1">Or enter this key manually</div>
        <code class="block break-all rounded bg-slate-50 border border-slate-200 px-2 py-1 text-xs font-mono"><?= h($secret) ?></code>
    </div>

    <form method="post" action="<?= h(url('/2fa/setup')) ?>" class="space-y-4">
        <?= Csrf::field() ?>
        <input type="hidden" name="secret" value="<?= h($secret) ?>">

        <div>
            <label for="code" class="block text-sm font-medium mb-1">Enter the code to confirm</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*"
                   maxlength="6" required autofocus autocomplete="one-time-code"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-center text-lg tracking-[0.4em] font-mono focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900">
        </div>

        <button type="submit"
                class="w-full rounded bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Turn on two-factor
        </button>
    </form>

    <?php if (!$mandatory): ?>
        <div class="mt-5 pt-5 border-t border-slate-200 text-center">
            <a href="<?= h(url('/')) ?>" class="text-sm text-slate-600 underline hover:text-slate-900">Skip for now</a>
        </div>
    <?php endif; ?>

    <script <?= \Bizorca\Pilotage\Core\Csp::attr() ?> src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
    <script <?= \Bizorca\Pilotage\Core\Csp::attr() ?>>
        // Rendered locally: the secret must never be sent to a chart service.
        (function () {
            var qr = qrcode(0, 'M');
            qr.addData(<?= json_encode($uri, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            qr.make();
            document.getElementById('qr').innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0 });
        })();
    </script>

<?php endif; ?>

<?php
$inner = (string) ob_get_clean();

echo View::render('auth.shell', [
    'heading' => $mandatory ? 'Two-factor is required' : 'Set up two-factor',
    'sub'     => $mandatory
        ? 'Firm owners hold the keys to every client in the practice, so this one is not optional.'
        : 'Scan the code with your authenticator app.',
    'inner'   => $inner,
    'errors'  => $errors,
    'notice'  => null,
], null);
