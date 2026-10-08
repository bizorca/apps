<?php
use Dispatch\Core\View;
use Dispatch\Core\Auth;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title ?? $_appName) ?> <?= isset($title) && $title !== $_appName ? '— ' . View::e($_appName) : '' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#eef2ff',100:'#e0e7ff',500:'#6366f1',600:'#4f46e5',700:'#4338ca',800:'#3730a3',900:'#312e81' }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .gradient-hero { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4c1d95 70%, #1e1b4b 100%); }
        .gradient-text { background: linear-gradient(135deg, #a5b4fc, #c4b5fd); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .card-hover { transition: transform 0.2s, box-shadow 0.2s; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
        .nav-blur { backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">

<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 nav-blur bg-white/90 border-b border-slate-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <a href="<?= $_base ?>/" class="flex items-center gap-2 group">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center shadow-sm group-hover:bg-indigo-700 transition-colors">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="font-bold text-slate-900 text-lg tracking-tight"><?= View::e($_appName) ?></span>
            </a>

            <!-- Center nav links (desktop) -->
            <div class="hidden md:flex items-center gap-1">
                <a href="<?= $_base ?>/about" class="px-3 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">About</a>
                <a href="<?= $_base ?>/features" class="px-3 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Features</a>
                <a href="<?= $_base ?>/pricing" class="px-3 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Pricing</a>
            </div>

            <!-- Right: auth (desktop) + hamburger (mobile) -->
            <div class="flex items-center gap-3">
                <div class="hidden md:flex items-center gap-3">
                    <?php if ($_auth): ?>
                        <a href="<?= $_base ?>/dashboard" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors">Dashboard</a>
                        <form method="POST" action="<?= $_base ?>/logout">
                            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                            <button type="submit" class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Sign out</button>
                        </form>
                    <?php else: ?>
                        <a href="<?= $_base ?>/login" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors">Log in</a>
                        <a href="<?= $_base ?>/register" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                            Get Started Free
                        </a>
                    <?php endif; ?>
                </div>
                <!-- Mobile hamburger -->
                <button onclick="document.getElementById('mobile-nav').classList.toggle('hidden')" class="md:hidden p-2 rounded-lg hover:bg-slate-100 text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile nav dropdown -->
    <div id="mobile-nav" class="hidden md:hidden border-t border-slate-200 bg-white">
        <div class="px-4 py-3 space-y-1">
            <a href="<?= $_base ?>/about" class="block px-3 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">About</a>
            <a href="<?= $_base ?>/features" class="block px-3 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Features</a>
            <a href="<?= $_base ?>/pricing" class="block px-3 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Pricing</a>
            <div class="pt-2 border-t border-slate-100 space-y-1">
                <?php if ($_auth): ?>
                    <a href="<?= $_base ?>/dashboard" class="block px-3 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Dashboard</a>
                    <form method="POST" action="<?= $_base ?>/logout">
                        <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                        <button type="submit" class="w-full text-left px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-lg transition-colors">Sign out</button>
                    </form>
                <?php else: ?>
                    <a href="<?= $_base ?>/login" class="block px-3 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Log in</a>
                    <a href="<?= $_base ?>/register" class="block px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Get Started Free</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Flash messages -->
<?php if (!empty($_flash)): ?>
<div class="fixed top-20 right-4 z-50 space-y-2 max-w-sm" id="flash-container">
    <?php foreach ($_flash as $type => $message): ?>
        <?php
        $classes = match($type) {
            'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
            'error'   => 'bg-red-50 border-red-200 text-red-800',
            'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
            default   => 'bg-blue-50 border-blue-200 text-blue-800',
        };
        $icon = match($type) {
            'success' => '✓',
            'error'   => '✕',
            'warning' => '⚠',
            default   => 'ℹ',
        };
        ?>
        <div class="flex items-start gap-3 px-4 py-3 rounded-xl border <?= $classes ?> shadow-md">
            <span class="font-bold text-sm mt-0.5"><?= $icon ?></span>
            <p class="text-sm font-medium"><?= View::e($message) ?></p>
        </div>
    <?php endforeach; ?>
</div>
<script>setTimeout(() => { const el = document.getElementById('flash-container'); if(el) el.style.opacity = '0'; el.style.transition = 'opacity 0.5s'; setTimeout(() => el?.remove(), 500); }, 4000);</script>
<?php endif; ?>

<!-- Main content -->
<main class="pt-16">
    <?= $content ?>
</main>

<!-- Footer -->
<footer class="bg-slate-900 text-slate-400 mt-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <span class="font-bold text-white text-lg"><?= View::e($_appName) ?></span>
                </div>
                <p class="text-slate-400 text-sm leading-relaxed max-w-sm">
                    Reduce the cognitive load of event promotion. Calculate submission deadlines, track venues, and coordinate your outreach — all in one place.
                </p>
            </div>
            <div>
                <h4 class="text-white font-semibold text-sm mb-4 uppercase tracking-wider">Product</h4>
                <ul class="space-y-2">
                    <li><a href="<?= $_base ?>/features" class="text-sm hover:text-white transition-colors">Features</a></li>
                    <li><a href="<?= $_base ?>/pricing" class="text-sm hover:text-white transition-colors">Pricing</a></li>
                    <li><a href="<?= $_base ?>/register" class="text-sm hover:text-white transition-colors">Sign Up Free</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-semibold text-sm mb-4 uppercase tracking-wider">Company</h4>
                <ul class="space-y-2">
                    <li><a href="<?= $_base ?>/about" class="text-sm hover:text-white transition-colors">About</a></li>
                    <li><a href="https://login.bizorca.com/terms" class="text-sm hover:text-white transition-colors" target="_blank" rel="noopener">Terms of Service</a></li>
                    <li><a href="https://login.bizorca.com/privacy" class="text-sm hover:text-white transition-colors" target="_blank" rel="noopener">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-800 mt-12 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm">&copy; <?= date('Y') ?> <?= View::e($_appName) ?>. Free and open for community use.</p>
            <p class="text-sm">
                <a href="https://login.bizorca.com/terms" class="hover:text-white transition-colors" target="_blank" rel="noopener">Terms</a>
                <span class="mx-2">·</span>
                <a href="https://login.bizorca.com/privacy" class="hover:text-white transition-colors" target="_blank" rel="noopener">Privacy</a>
            </p>
        </div>
    </div>
</footer>

</body>
</html>
