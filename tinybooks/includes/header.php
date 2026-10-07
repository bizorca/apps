<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title ?? APP_NAME) ?> &mdash; <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#f0f4ff',
                            100: '#e0e9ff',
                            500: '#4f6ef7',
                            600: '#3b55e6',
                            700: '#2d44cc',
                            900: '#1a2880',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none; }
        .sidebar-link { @apply flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors; }
        .sidebar-link.active { @apply bg-brand-50 text-brand-700; }
    </style>
</head>
<body class="h-full">
