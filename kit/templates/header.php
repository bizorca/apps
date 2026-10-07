<?php
/**
 * Page chrome. Expects optional $pageTitle, $client, $backLink, $bodyClass.
 */
declare(strict_types=1);

$__user     = $__user     ?? currentUser();
$pageTitle  = $pageTitle  ?? '';
$client     = $client     ?? null;
$bodyClass  = $bodyClass  ?? '';
$fullTitle  = $pageTitle !== '' ? $pageTitle . ' · ' . APP_NAME : APP_NAME . ' — ' . APP_TAGLINE;
$flashError = flash('error');
$flashOk    = flash('success');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($fullTitle) ?></title>
<meta name="description" content="<?= h(APP_DESC) ?>">
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= h(asset('/assets/tailwind.css')) ?>">
<style><?= paletteCss() ?></style>
<link rel="icon" href="<?= h(appFavicon()) ?>">
</head>
<body class="bg-paper text-ink font-sans antialiased <?= h($bodyClass) ?>">

<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50 focus:rounded focus:bg-primary focus:px-3 focus:py-2 focus:text-primary-ink">Skip to content</a>

<header class="no-print border-b border-hairline bg-surface">
  <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
    <a href="<?= h(url($__user ? '/dashboard.php' : '/')) ?>" class="flex items-center gap-2 font-semibold text-ink">
      <?= appMark('h-7 w-7 shrink-0 rounded') ?>
      <span class="whitespace-nowrap"><?= h(APP_NAME) ?></span>
    </a>
    <?php if ($__user): ?>
      <nav class="flex items-center gap-1 text-sm">
        <a href="<?= h(url('/dashboard.php')) ?>" class="rounded px-2 py-1 text-muted hover:bg-sunk hover:text-ink">Clients</a>
        <a href="<?= h(url('/reference.php')) ?>" class="rounded px-2 py-1 text-muted hover:bg-sunk hover:text-ink">Reference</a>
        <a href="<?= h(url('/report.php')) ?>" class="rounded px-2 py-1 text-muted hover:bg-sunk hover:text-ink">Activity report</a>
      </nav>
      <div class="ml-auto flex items-center gap-3 text-sm">
        <a href="/" class="text-muted underline decoration-hairline underline-offset-4 hover:text-ink">All tools</a>
        <a href="/account/settings.php" class="hidden text-muted hover:text-ink sm:inline"><?= h(userDisplayName($__user)) ?></a>
        <a href="/account/logout.php" class="text-muted underline decoration-hairline underline-offset-4 hover:text-ink">Sign out</a>
      </div>
    <?php else: ?>
      <div class="ml-auto text-sm">
        <a href="<?= h(kitLoginUrl()) ?>" class="btn-primary">Sign in</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($client): ?>
    <div class="border-t border-hairline bg-sunk">
      <div class="mx-auto flex max-w-6xl flex-wrap items-baseline gap-x-3 gap-y-1 px-4 py-2 text-sm">
        <a href="<?= h(url('/client.php?id=' . (int) $client['id'])) ?>" class="font-semibold text-ink hover:underline">
          <?= h($client['business_name']) ?>
        </a>
        <?php if ($client['owner_name'] !== ''): ?>
          <span class="text-muted"><?= h($client['owner_name']) ?></span>
        <?php endif; ?>
        <?php if ($client['business_type'] !== ''): ?>
          <span class="text-muted">· <?= h($client['business_type']) ?></span>
        <?php endif; ?>
        <?php if ($pageTitle !== ''): ?>
          <span class="text-muted">· <?= h($pageTitle) ?></span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</header>

<?php if ($flashError !== '' || $flashOk !== ''): ?>
  <div class="no-print mx-auto max-w-6xl px-4 pt-4">
    <?php if ($flashError !== ''): ?>
      <div role="alert" class="rounded-md border border-bad bg-bad-soft px-4 py-3 text-sm text-bad"><?= h($flashError) ?></div>
    <?php endif; ?>
    <?php if ($flashOk !== ''): ?>
      <div role="status" class="rounded-md border border-good bg-good-soft px-4 py-3 text-sm text-good"><?= h($flashOk) ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<main id="main" class="mx-auto max-w-6xl px-4 py-6">
