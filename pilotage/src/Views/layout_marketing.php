<?php
/**
 * The public marketing site. Apex host only.
 *
 * Deliberately NOT layout.php. Three differences, each of which matters:
 *
 *   1. It is indexable. Every other layout in this project carries
 *      `noindex, nofollow`, because everything else is somebody's private
 *      client work. This is the one surface that exists to be found.
 *   2. There is no tenant, so there is no tenant branding. These pages are
 *      Pilotage's own, and the palette is fixed rather than read from a row.
 *   3. It has navigation a stranger can use. layout.php assumes a session.
 *
 * Typography is a system stack — no webfont. `font-src` in the CSP is
 * 'self' data:, and a marketing page is not worth widening a security header
 * or adding a build step for. Georgia at a large size does more for the page
 * than a downloaded grotesque would anyway.
 *
 * @var string $content
 * @var string $title
 * @var string|null $description  meta description; every page should set one
 * @var string|null $canonical    absolute URL, defaults to the current path
 * @var string|null $active       nav key to highlight
 */
use Bizorca\Pilotage\Core\Csp;
use Bizorca\Pilotage\Services\Entitlements;

$beta = Entitlements::inBeta();
$active = $active ?? '';
$description = $description ?? 'A shared workspace for business coaches, advisors and consultants, and the businesses they work with.';

$path = strtok((string) (pl_request_path()), '?') ?: '/';
$canonical = $canonical ?? app_url($path);

$nav = [
    'product' => ['/', 'Product'],
    'pricing' => ['/pricing', 'Pricing'],
    'about'   => ['/about', 'About'],
    'faq'     => ['/faq', 'FAQ'],
];
?>
<!doctype html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — Pilotage</title>
    <meta name="description" content="<?= h($description) ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Pilotage">
    <meta property="og:title" content="<?= h($title) ?>">
    <meta property="og:description" content="<?= h($description) ?>">
    <meta property="og:url" content="<?= h($canonical) ?>">
    <meta name="twitter:card" content="summary">

    <script <?= Csp::attr() ?> src="https://cdn.tailwindcss.com"></script>
    <script <?= Csp::attr() ?>>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans:    ['ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
              display: ['ui-serif', 'Georgia', 'Cambria', 'Times New Roman', 'serif'],
            },
            colors: {
              ink:  { DEFAULT: '#0b1220', soft: '#131c2e' },
              tide: { 50: '#eef7f4', 100: '#d3ebe4', 500: '#0f766e', 600: '#0d6259', 700: '#0a4f47' },
            },
          },
        },
      };
    </script>

    <style>
      /* The horizon rule under the hero. One gradient, no image request. */
      .hairline { background-image: linear-gradient(to right, transparent, rgba(15,118,110,.45), transparent); }
      /* <details> is the accordion. Alpine was removed from this project to
         drop 'unsafe-eval' from the CSP; do not reintroduce it for a marker. */
      summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="h-full bg-white text-ink font-sans antialiased">

<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded focus:bg-ink focus:px-4 focus:py-2 focus:text-sm focus:text-white">
    Skip to content
</a>

<header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-6 px-5 py-4">
        <a href="<?= h(app_url('/')) ?>" class="flex items-center gap-2.5">
            <?php /* The harbour pilot's mark: a hull on the waterline. */ ?>
            <svg viewBox="0 0 24 24" class="h-6 w-6 text-tide-500" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path d="M12 3v9" stroke-linecap="round"/>
                <path d="M4 12h16l-2.4 5.4a6 6 0 0 1-11.2 0L4 12Z" stroke-linejoin="round"/>
                <path d="M8.5 6.5h7" stroke-linecap="round"/>
            </svg>
            <span class="font-display text-xl tracking-tight">Pilotage</span>
        </a>

        <nav class="ml-auto hidden items-center gap-7 md:flex" aria-label="Main">
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= h(app_url($href)) ?>"
                   class="text-sm <?= $active === $key ? 'font-medium text-ink' : 'text-slate-600 hover:text-ink' ?>"
                   <?= $active === $key ? 'aria-current="page"' : '' ?>>
                    <?= h($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="ml-auto flex items-center gap-3 md:ml-0">
            <a href="<?= h(app_url('/signin')) ?>" class="hidden text-sm text-slate-600 hover:text-ink sm:inline">Sign in</a>
            <a href="<?= h(app_url('/signup')) ?>"
               class="rounded-md bg-ink px-3.5 py-2 text-sm font-medium text-white hover:bg-ink-soft">
                <?= $beta ? 'Start free' : 'Start a trial' ?>
            </a>
        </div>
    </div>

    <?php /* Mobile nav. No script: the links move to a second row rather than
             hiding behind a hamburger that would need one. "Sign in" is here
             too — it is dropped from the top bar at this width, and a returning
             client with no way back to their firm is the one thing this header
             must not do. */ ?>
    <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-1 border-t border-slate-100 px-5 py-2.5 md:hidden" aria-label="Main">
        <?php foreach ($nav as $key => [$href, $label]): ?>
            <a href="<?= h(app_url($href)) ?>"
               class="text-sm <?= $active === $key ? 'font-medium text-ink' : 'text-slate-600' ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= h(app_url('/signin')) ?>" class="text-sm text-slate-600 sm:hidden">Sign in</a>
    </nav>
</header>

<main id="main"><?= $content ?></main>

<footer class="mt-24 border-t border-slate-200 bg-slate-50">
    <div class="mx-auto max-w-6xl px-5 py-14">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <div class="flex items-center gap-2.5">
                    <svg viewBox="0 0 24 24" class="h-5 w-5 text-tide-500" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path d="M12 3v9" stroke-linecap="round"/>
                        <path d="M4 12h16l-2.4 5.4a6 6 0 0 1-11.2 0L4 12Z" stroke-linejoin="round"/>
                        <path d="M8.5 6.5h7" stroke-linecap="round"/>
                    </svg>
                    <span class="font-display text-lg">Pilotage</span>
                </div>
                <p class="mt-3 max-w-sm text-sm leading-relaxed text-slate-600">
                    A pilot boards the ship at the harbour mouth and guides it in. They do not
                    own the vessel and they do not sail it — they know the water. That is the job
                    this is built for.
                </p>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Product</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="<?= h(app_url('/')) ?>" class="text-slate-600 hover:text-ink">Overview</a></li>
                    <li><a href="<?= h(app_url('/pricing')) ?>" class="text-slate-600 hover:text-ink">Pricing</a></li>
                    <li><a href="<?= h(app_url('/faq')) ?>" class="text-slate-600 hover:text-ink">FAQ</a></li>
                    <li><a href="<?= h(app_url('/signup')) ?>" class="text-slate-600 hover:text-ink">Create a workspace</a></li>
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Company</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="<?= h(app_url('/about')) ?>" class="text-slate-600 hover:text-ink">About</a></li>
                    <li><a href="<?= h(app_url('/signin')) ?>" class="text-slate-600 hover:text-ink">Sign in</a></li>
                    <li><a href="mailto:hello@<?= h(base_domain()) ?>" class="text-slate-600 hover:text-ink">hello@<?= h(base_domain()) ?></a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-2 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; <?= date('Y') ?> Bizorca LLC. Pilotage is a product of Bizorca.</p>
            <p>Every firm gets its own address at <span class="font-mono"><?= h(base_domain() . PL_BASE) ?>/f/yourfirm</span></p>
        </div>
    </div>
</footer>

</body>
</html>
