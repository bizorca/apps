<?php
/**
 * Page frame for the shared account pages: same look as the landing page
 * (Tailwind CDN, brand blue), with the signed-in state in the nav.
 */

declare(strict_types=1);

function tl_page_open(string $title): void
{
    $user = tl_user();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= tl_h($title) ?> — Bizorca Tools</title>
  <meta name="robots" content="noindex">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { colors: { brand: {
      50: '#f0f4ff', 100: '#e0eaff', 200: '#c3d4fe', 500: '#4f6ef7', 600: '#3b55e6', 700: '#2d42cc', 900: '#1a2566'
    } } } } }
  </script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
  <header class="border-b border-slate-100 bg-white">
    <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
      <a href="/" class="font-bold text-lg tracking-tight text-slate-900"><span class="text-brand-600">Bizorca</span> Tools</a>
      <nav class="flex items-center gap-5 text-sm">
        <?php if ($user): ?>
          <a href="/account/settings.php" class="text-slate-600 hover:text-brand-600"><?= tl_h($user['name']) ?></a>
          <a href="/account/logout.php" class="text-slate-600 hover:text-brand-600">Sign out</a>
        <?php else: ?>
          <a href="/account/login.php" class="text-slate-600 hover:text-brand-600">Sign in</a>
          <a href="/account/register.php" class="font-medium bg-brand-600 text-white px-4 py-2 rounded-lg hover:bg-brand-700">Create an account</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="flex-1 px-6 py-12">
    <?php
}

function tl_page_close(): void
{
    ?>
  </main>
  <footer class="border-t border-slate-100 bg-white">
    <div class="max-w-5xl mx-auto px-6 py-6 text-sm text-slate-500">
      <span class="font-semibold text-slate-700">Bizorca LLC</span> &mdash; Washington State
    </div>
  </footer>
</body>
</html>
    <?php
}

/** The narrow card every account form sits in. */
function tl_card_open(string $heading, string $intro = '', string $error = '', string $notice = ''): void
{
    ?>
    <div class="max-w-md mx-auto bg-white border border-slate-200 rounded-2xl p-6 sm:p-8">
      <h1 class="text-2xl font-bold text-slate-900 mb-2"><?= tl_h($heading) ?></h1>
      <?php if ($intro !== ''): ?><p class="text-sm text-slate-600 leading-relaxed mb-6"><?= $intro ?></p><?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="text-sm rounded-lg px-4 py-3 mb-5 bg-red-50 text-red-800 border border-red-200" role="alert"><?= tl_h($error) ?></div>
      <?php endif; ?>
      <?php if ($notice !== ''): ?>
        <div class="text-sm rounded-lg px-4 py-3 mb-5 bg-emerald-50 text-emerald-800 border border-emerald-200" role="status"><?= tl_h($notice) ?></div>
      <?php endif; ?>
    <?php
}

function tl_card_close(): void
{
    echo "    </div>\n";
}

/** A labelled input, styled like the landing page's request form. */
function tl_field(string $name, string $label, string $type = 'text', string $value = '', string $autocomplete = '', bool $required = true): string
{
    return sprintf(
        '<div><label for="f-%1$s" class="block text-sm font-medium text-slate-700 mb-1">%2$s</label>'
        . '<input id="f-%1$s" name="%1$s" type="%3$s" value="%4$s"%5$s%6$s '
        . 'class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"></div>',
        tl_h($name), tl_h($label), tl_h($type), tl_h($value),
        $autocomplete !== '' ? ' autocomplete="' . tl_h($autocomplete) . '"' : '',
        $required ? ' required' : ''
    );
}

const TL_BUTTON = 'w-full bg-brand-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-brand-700 transition-colors';
