<?php
/**
 * Settings. Secrets come from the shared private_html/.env.php via tl_env(),
 * under AF_ keys; the database is the shared tools database (af_ tables).
 */

return [
    'env'   => (string) tl_env('AF_ENV', 'production'),
    'debug' => tl_env('AF_ENV', 'production') === 'local',

    'db' => [
        'host' => (string) tl_env('DB_HOST', 'localhost'),
        'name' => (string) tl_env('DB_NAME', ''),
        'user' => (string) tl_env('DB_USER', ''),
        'pass' => (string) tl_env('DB_PASS', ''),
        // The data came from SiteGround, whose MySQL ran in America/Chicago, so
        // every DATETIME written by NOW() is Chicago wall time. Database keeps
        // the session in that zone so old and new rows compare the same way.
        'timezone' => (string) tl_env('AF_DB_TIMEZONE', 'America/Chicago'),
    ],

    'worker' => [
        'token'          => (string) tl_env('AF_WORKER_TOKEN', ''),
        'lease_seconds'  => 900,
        'max_attempts'   => 3,
    ],

    // Prose. Claude writes the posts; see Services\Anthropic for why.
    // This key should be budget-capped — it lives on the server, so a breach
    // should cost a cap rather than an open tab.
    'anthropic' => [
        'key'   => (string) tl_env('AF_ANTHROPIC_API_KEY', ''),
        'model' => (string) tl_env('AF_ANTHROPIC_MODEL', 'claude-opus-5'),
    ],

    // Images only — Claude does not generate images. Same budget-cap reasoning.
    'gemini' => [
        'key'          => (string) tl_env('AF_GEMINI_API_KEY', ''),
        'text_model'   => (string) tl_env('AF_GEMINI_TEXT_MODEL', 'gemini-3.1-pro-preview'),
        'image_model'  => (string) tl_env('AF_GEMINI_IMAGE_MODEL', 'gemini-3-pro-image'),
    ],

    // PNG and JPEG only. WebP is never used anywhere in this system.
    'images' => [
        'substack_width' => 1456,
        'web_max_edge'   => 1600,
        'jpeg_quality'   => 88,
        'allowed'        => ['png', 'jpg'],
    ],
];
