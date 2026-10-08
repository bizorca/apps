<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'placecard') ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDesc ?? 'Social dining for strangers in cities. Group dinners at partner restaurants. No algorithm — just show up and eat.') ?>">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        'pc-cream':      '#FAF7F2',
        'pc-terracotta': '#C4704A',
        'pc-charcoal':   '#1C1C1E',
        'pc-slate':      '#6B6B6B',
        'pc-sage':       '#4A7A5A',
        'pc-warm':       '#F2EAE0',
        'pc-border':     '#E5DDD4',
      },
      fontFamily: {
        sans: ['-apple-system', 'BlinkMacSystemFont', '"SF Pro Display"', '"Segoe UI"', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        'card': '0 1px 4px 0 rgba(28,28,30,0.08), 0 0 0 1px rgba(229,221,212,0.6)',
      }
    }
  }
}
</script>
<style>
  body { background-color: #FAF7F2; color: #1C1C1E; }
  .chip-active { background-color: #C4704A; color: #fff; border-color: #C4704A; }
  .chip-inactive { background-color: #fff; color: #1C1C1E; border-color: #E5DDD4; }
  .pref-active { background-color: #FAF7F2; border-color: #C4704A; color: #C4704A; }
  .pref-inactive { background-color: #fff; border-color: #E5DDD4; color: #1C1C1E; }
  input:focus, textarea:focus, select:focus { outline: none; border-color: #C4704A; box-shadow: 0 0 0 3px rgba(196,112,74,0.15); }
  .tab-active { color: #C4704A; border-bottom-color: #C4704A; }
  .tab-inactive { color: #6B6B6B; border-bottom-color: transparent; }
  .btn-primary { background-color: #C4704A; color: #fff; }
  .btn-primary:hover { background-color: #b3603c; }
  .btn-secondary { background-color: #fff; color: #C4704A; border: 1.5px solid #C4704A; }
  .btn-secondary:hover { background-color: #FAF7F2; }
  .btn-ghost { background-color: transparent; color: #6B6B6B; border: 1.5px solid #E5DDD4; }
  .btn-ghost:hover { background-color: #F2EAE0; }
  @media (max-width: 768px) { .sidebar { display: none; } }
</style>
</head>
<body class="min-h-screen font-sans antialiased">
