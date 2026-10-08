<?php
/**
 * Direct invite link handler: /team-join.php?code=XXXXX
 * Allows joining a team via a shareable URL.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/teams.php';
requireOnboarded();

$userId = getCurrentUserId();
$inviteCode = trim($_GET['code'] ?? '');

if (!$inviteCode) {
    setFlash('error', 'Missing invite code.');
    header('Location: ' . url('/teams.php'));
    exit;
}

$team = joinTeamByInvite($inviteCode, $userId);
if ($team) {
    setFlash('success', 'Joined team "' . $team['name'] . '"!');
    header('Location: ' . url('/team-detail.php') . '?slug=' . $team['slug']);
} else {
    setFlash('error', 'Invalid invite code.');
    header('Location: ' . url('/teams.php'));
}
exit;
