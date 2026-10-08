<?php
/**
 * Base layout. Tailwind via CDN, no build step.
 *
 * Tenant branding is applied as CSS custom properties rather than by
 * generating Tailwind classes — the palette is per-tenant and dynamic, and a
 * CDN Tailwind build cannot know about it. `--brand` falls back to slate-900
 * so an unbranded firm looks deliberate rather than broken.
 *
 * @var string $content
 * @var string $title
 */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\Tenant;

$tenant = Tenant::current();
$impersonated = \Bizorca\Pilotage\Auth\Session::isImpersonated();

$brand  = $tenant['primary_color'] ?? null;
$accent = $tenant['accent_color'] ?? null;

// Belt and braces: these are validated on save, but they are being written
// into a style attribute, so re-check rather than trust the column.
$hex = static fn (?string $v): ?string => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : null;
$brand = $hex($brand);
$accent = $hex($accent);
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?><?= $tenant !== null ? ' — ' . h($tenant['name']) : '' ?></title>
    <meta name="robots" content="noindex, nofollow">
    <script <?= \Bizorca\Pilotage\Core\Csp::attr() ?> src="https://cdn.tailwindcss.com"></script>
    <style>
      :root {
        --brand: <?= $brand ?? '#0f172a' ?>;
        --accent: <?= $accent ?? '#059669' ?>;
      }
      .bg-brand { background-color: var(--brand); }
      .text-brand { color: var(--brand); }
      .bg-accent { background-color: var(--accent); }
      .border-brand { border-color: var(--brand); }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased">

<?php if ($impersonated): ?>
    <?php /* FR-2.6: an admin must never forget whose account they are inside. */ ?>
    <div class="bg-amber-500 text-amber-950 px-4 py-2 text-sm font-medium text-center">
        Support access in progress — you are viewing this account as its owner. Actions are audited.
        <form method="post" action="<?= h(url('/admin/impersonation/end')) ?>" class="inline">
            <?= Csrf::field() ?>
            <button class="underline font-semibold ml-2">End now</button>
        </form>
    </div>
<?php endif; ?>

<?= $content ?>

<?php if ($tenant !== null && (int) ($tenant['hide_platform_credit'] ?? 0) === 0): ?>
    <footer class="max-w-5xl mx-auto px-4 py-6 text-center">
        <span class="text-xs text-slate-400">powered by Pilotage</span>
    </footer>
<?php endif; ?>

</body>
</html>
