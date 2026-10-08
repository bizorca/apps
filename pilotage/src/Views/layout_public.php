<?php
/**
 * Layout for unauthenticated, publicly-indexable pages — currently just the
 * enquiry form.
 *
 * Distinct from layout.php in two ways that matter: no app chrome (a stranger
 * has no session and nothing to navigate), and no `noindex` — this is the one
 * surface a firm actually wants found.
 *
 * @var string $content @var string $title @var array $tenant
 */
$brand = $tenant['primary_color'] ?? null;
$hex = static fn (?string $v): ?string => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : null;
$brand = $hex($brand);
$logo = $tenant['logo_url'] ?? null;
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — <?= h($tenant['name']) ?></title>
    <script <?= \Bizorca\Pilotage\Core\Csp::attr() ?> src="https://cdn.tailwindcss.com"></script>
    <style>:root { --brand: <?= $brand ?? '#0f172a' ?>; } .bg-brand{background-color:var(--brand)} .text-brand{color:var(--brand)}</style>
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased">
  <div class="max-w-xl mx-auto px-4 py-10">
    <div class="mb-8 text-center">
      <?php if (is_string($logo) && str_starts_with($logo, 'https://')): ?>
        <img src="<?= h($logo) ?>" alt="<?= h($tenant['name']) ?>" class="h-8 w-auto max-w-[12rem] object-contain mx-auto">
      <?php else: ?>
        <div class="text-lg font-semibold tracking-tight text-brand"><?= h($tenant['name']) ?></div>
      <?php endif; ?>
    </div>
    <?= $content ?>
    <p class="text-center text-xs text-slate-400 mt-8">powered by Pilotage</p>
  </div>
</body>
</html>
