<?php
$title = 'Dashboard — Bizorca Consulting';
ob_start();
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4">
    <div class="max-w-5xl mx-auto flex items-center justify-between">
        <a href="<?= u('/') ?>" class="text-white font-semibold text-sm">Bizorca <span class="text-brand-500">Consulting</span></a>
        <div class="flex items-center gap-4 text-sm">
            <span class="text-slate-400"><?= h($user['first_name'] . ' ' . $user['last_name']) ?></span>
            <form method="POST" action="<?= u('/logout') ?>" class="inline">
                <?= csrf_field() ?>
                <button class="text-slate-500 hover:text-white transition-colors">Sign out</button>
            </form>
        </div>
    </div>
</nav>

<div class="min-h-screen bg-slate-50">
    <div class="max-w-5xl mx-auto px-6 py-12">
        <h1 class="text-3xl font-bold text-slate-900 mb-2">Welcome back, <?= h($user['first_name']) ?>.</h1>

        <?php if (empty($engagements)): ?>
        <!-- No engagement yet -->
        <div class="mt-10">
            <?php if ($application): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center max-w-lg mx-auto">
                <div class="w-12 h-12 bg-brand-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-slate-900 mb-2">Application under review.</h2>
                <p class="text-slate-500">Your application was submitted <?= time_ago($application['created_at']) ?>. I'll be in touch shortly.</p>
            </div>
            <?php else: ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center max-w-lg mx-auto">
                <h2 class="text-xl font-semibold text-slate-900 mb-2">No active engagement.</h2>
                <p class="text-slate-500 mb-6">Ready to build the franchise prototype for your business?</p>
                <a href="<?= u('/apply') ?>" class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold px-6 py-3 rounded-lg transition-colors">
                    Apply for an Engagement
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>

        <!-- Active engagements -->
        <p class="text-slate-500 mb-8">Your active engagements.</p>
        <div class="space-y-4">
            <?php foreach ($engagements as $engagement): ?>
            <?php
                $statusColors = [
                    'onboarding' => 'bg-violet-100 text-violet-700',
                    'active'     => 'bg-emerald-100 text-emerald-700',
                    'paused'     => 'bg-amber-100 text-amber-700',
                    'complete'   => 'bg-slate-100 text-slate-600',
                ];
                $statusColor = $statusColors[$engagement['status']] ?? 'bg-slate-100 text-slate-600';
            ?>
            <a href="<?= u('/engagements/' . ($engagement['id'])) ?>" class="block bg-white border border-slate-200 rounded-2xl p-6 hover:border-brand-300 hover:shadow-sm transition-all">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-slate-900 text-lg mb-1"><?= h($engagement['title']) ?></h3>
                        <?php if ($engagement['description']): ?>
                        <p class="text-slate-500 text-sm"><?= h($engagement['description']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-slate-400 mt-2">Started <?= time_ago($engagement['created_at']) ?></p>
                    </div>
                    <span class="flex-shrink-0 text-xs font-medium px-2.5 py-1 rounded-full <?= $statusColor ?>">
                        <?= ucfirst($engagement['status']) ?>
                    </span>
                </div>

                <?php if ($engagement['status'] === 'onboarding' && !$engagement['scope_locked_at']): ?>
                <div class="mt-4 p-3 bg-violet-50 rounded-lg text-sm text-violet-700">
                    Onboarding in progress — scope document coming soon.
                </div>
                <?php elseif ($engagement['scope_locked_at'] && !$engagement['client_accepted_scope_at']): ?>
                <div class="mt-4 p-3 bg-amber-50 rounded-lg text-sm text-amber-700 flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    Action required: review and accept the scope document
                </div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
