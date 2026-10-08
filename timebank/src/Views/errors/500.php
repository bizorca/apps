<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Something Went Wrong &mdash; <?= e($tenant['name'] ?? 'TimeBank') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gray-50 flex items-center justify-center font-sans antialiased">
    <div class="text-center px-4 py-16 max-w-md mx-auto">
        <div class="w-24 h-24 rounded-full bg-amber-50 flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <p class="text-6xl font-bold text-amber-400 mb-2">500</p>
        <h1 class="text-2xl font-semibold text-gray-800 mb-3">Something went wrong</h1>
        <p class="text-gray-500 mb-8 text-sm leading-relaxed">
            An unexpected error occurred on our end. The problem has been logged. Try again in a moment, or head back to the dashboard.
        </p>
        <a href="<?= !empty($tenant) ? url('/dashboard') : TM_BASE . '/' ?>"
           class="inline-flex items-center gap-2 px-6 py-3 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Back to Dashboard
        </a>
    </div>
</body>
</html>
