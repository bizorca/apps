<?php
/**
 * Where the shared /account sign-in and sign-up pages send people back to.
 *
 * Claims what the visitor did before having an account, as the original's
 * login and register pages did: a star seed quiz taken anonymously (then shows
 * its results) and an anonymous Ba Zi profile (which becomes their primary).
 * Then lands on ?redirect=, which must be a path inside this tool.
 */
require __DIR__ . '/_bootstrap.php';
requireLogin();

$userId = (int) getCurrentUserId();
claimPendingProfile($userId);

if (claimPendingStarseed($userId)) {
    setFlash('success', 'Welcome! Your star seed lineage is revealed below.');
    header('Location: ' . url('/starseed-quiz.php'));
    exit;
}

$redirect = (string) ($_GET['redirect'] ?? '');
$safe = $redirect !== ''
    && str_starts_with($redirect, BASE_PATH . '/')
    && !str_contains($redirect, '\\')
    && !preg_match('/[\x00-\x1F\x7F]/', $redirect)
    && parse_url($redirect, PHP_URL_HOST) === null;

header('Location: ' . ($safe ? $redirect : url('/dashboard.php')));
exit;
