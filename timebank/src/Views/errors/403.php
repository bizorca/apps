<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied &mdash; <?= e($tenant['name'] ?? 'TimeBank') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gray-50 flex items-center justify-center font-sans antialiased">
    <div class="text-center px-4 py-16 max-w-md mx-auto">
        <div class="w-24 h-24 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <p class="text-6xl font-bold text-red-400 mb-2">403</p>
        <h1 class="text-2xl font-semibold text-gray-800 mb-3">Access denied</h1>
        <p class="text-gray-500 mb-8 text-sm leading-relaxed">
            You don't have permission to view this page. If you think that's a mistake, reach out to your timebank admin.
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
