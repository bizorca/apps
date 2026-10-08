<?php
$title = 'Application Received — Bizorca Consulting';
ob_start();
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4">
    <div class="max-w-4xl mx-auto">
        <a href="<?= u('/') ?>" class="text-white font-semibold text-sm">Bizorca <span class="text-brand-500">Consulting</span></a>
    </div>
</nav>

<div class="min-h-screen bg-slate-50 flex items-center justify-center px-6">
    <div class="max-w-lg text-center">
        <div class="w-16 h-16 bg-brand-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-3xl font-bold text-slate-900 mb-4">Application received.</h1>
        <p class="text-lg text-slate-600 mb-4">I read every application personally. You'll hear back within a few business days &mdash; not a form letter, an actual response.</p>
        <p class="text-slate-500 mb-8">If we're a fit, I'll reach out to schedule a discovery call. If not, I'll tell you why &mdash; and point you toward something that might be a better match.</p>
        <a href="<?= u('/') ?>" class="text-brand-600 hover:text-brand-700 font-medium">&larr; Back to the site</a>
    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
