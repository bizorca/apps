<?php
/**
 * Join a workspace from an invite link (signup/join.blade.php). Expects
 * $account, $code, $shared (tools user or null), $member (FmUser or null).
 */
$here = route('join', $code);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join <?= e($account->name) ?> — Fathom</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full flex items-center justify-center py-12">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-indigo-600">Fathom</h1>
            <p class="mt-2 text-gray-500 text-sm">Kanban, the way it should work.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            <?php if ($errors->any()): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors->all() as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <h2 class="text-lg font-semibold text-gray-900 mb-1">Join <?= e($account->name) ?></h2>
            <p class="text-sm text-gray-400 mb-6">You've been invited to join this workspace on Fathom.</p>

            <?php if (!$shared): ?>
                <p class="text-sm text-gray-600 mb-4">Fathom uses your Bizorca Tools account. Create one, or sign in, and you'll come straight back here.</p>
                <div class="space-y-3">
                    <a href="<?= e('/account/register.php?next=' . rawurlencode($here)) ?>"
                       class="block text-center w-full bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-indigo-700 transition">Create an account</a>
                    <a href="<?= e('/account/login.php?next=' . rawurlencode($here)) ?>"
                       class="block text-center w-full bg-white border border-gray-300 text-gray-700 rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-50 transition">Sign in</a>
                </div>
            <?php elseif ($member): ?>
                <p class="text-sm text-gray-600">You're already a member of <strong><?= e($member->account?->name) ?></strong>. Fathom keeps one workspace per person.</p>
                <a href="<?= e(route('boards.index')) ?>" class="mt-4 inline-block text-sm text-indigo-600 hover:underline">Go to your boards</a>
            <?php else: ?>
                <form method="POST" action="<?= e(route('join.store', $code)) ?>">
                    <?= csrf_field() ?>
                    <p class="text-sm text-gray-600 mb-4">You'll join as <strong><?= e($shared['name']) ?></strong> (<?= e($shared['email']) ?>).</p>
                    <button type="submit"
                            class="w-full bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-indigo-700 transition">
                        Join workspace
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
