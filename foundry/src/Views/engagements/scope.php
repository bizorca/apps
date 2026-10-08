<?php
$title = 'Scope Document — ' . h($engagement['title']);
ob_start();
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4">
    <div class="max-w-3xl mx-auto flex items-center justify-between">
        <a href="<?= u('/engagements/' . ($engagement['id'])) ?>" class="text-slate-500 hover:text-white flex items-center gap-2 text-sm transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to Board
        </a>
        <span class="text-white font-semibold text-sm">Bizorca <span class="text-brand-500">Consulting</span></span>
    </div>
</nav>

<div class="min-h-screen bg-slate-50 py-12 px-6">
    <div class="max-w-3xl mx-auto">

        <div class="mb-8">
            <div class="text-brand-600 text-xs font-semibold uppercase tracking-widest mb-2">Scope Document</div>
            <h1 class="text-3xl font-bold text-slate-900 mb-2"><?= h($engagement['title']) ?></h1>
            <p class="text-slate-500">
                Scope locked <?= date('F j, Y', strtotime($engagement['scope_locked_at'])) ?>.
                <?php if ($engagement['client_accepted_scope_at']): ?>
                <span class="text-emerald-600 font-medium">You accepted this scope on <?= date('F j, Y', strtotime($engagement['client_accepted_scope_at'])) ?>.</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-8 mb-8">
            <h2 class="text-lg font-semibold text-slate-900 mb-6">Deliverables</h2>

            <?php if (empty($scopes)): ?>
            <p class="text-slate-400 text-sm">No scope items defined yet.</p>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($scopes as $i => $item): ?>
                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-7 h-7 rounded-full bg-brand-100 text-brand-700 text-xs font-bold flex items-center justify-center mt-0.5">
                        <?= $i + 1 ?>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-slate-900"><?= h($item['title']) ?></h3>
                            <?php if (!$item['is_original']): ?>
                            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Added via change request</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($item['description']): ?>
                        <p class="text-slate-500 text-sm mt-1 leading-relaxed"><?= h($item['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- What's NOT in scope -->
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 mb-8">
            <h3 class="font-semibold text-amber-900 mb-2">Important: what's not in scope</h3>
            <p class="text-amber-800 text-sm leading-relaxed">
                Any work outside the deliverables listed above requires a formal change request. This protects you — you always know exactly what you're getting and what you've already paid for. There are no surprise invoices. If you need something added, submit a change request through the system and it gets reviewed before any work starts.
            </p>
        </div>

        <!-- Accept button -->
        <?php if (!$engagement['client_accepted_scope_at']): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-6">
            <h3 class="font-semibold text-slate-900 mb-2">Ready to proceed?</h3>
            <p class="text-slate-500 text-sm mb-6">By accepting this scope document, you confirm that the deliverables listed above accurately represent the agreed-upon work for this engagement.</p>
            <form method="POST" action="<?= u('/engagements/' . ($engagement['id']) . '/scope/accept') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 text-white font-semibold px-8 py-3 rounded-xl transition-colors">
                    Accept Scope &amp; Activate Project Board
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 flex items-center gap-3">
            <svg class="w-6 h-6 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <div>
                <div class="font-semibold text-emerald-900">Scope accepted.</div>
                <div class="text-emerald-700 text-sm">Accepted on <?= date('F j, Y', strtotime($engagement['client_accepted_scope_at'])) ?></div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
