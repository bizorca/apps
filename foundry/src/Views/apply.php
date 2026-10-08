<?php
$title = 'Apply — Bizorca Foundry';
ob_start();
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4">
    <div class="max-w-4xl mx-auto flex items-center justify-between">
        <a href="<?= u('/') ?>" class="text-white font-semibold text-sm">Bizorca <span class="text-brand-500">Foundry</span></a>
    </div>
</nav>

<div class="min-h-screen bg-slate-50 py-16 px-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-10">
            <a href="<?= u('/') ?>" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back</a>
            <h1 class="text-4xl font-bold text-slate-900 mt-4 mb-2">Apply for an Engagement</h1>
            <p class="text-slate-500 text-lg">Ten minutes. I read every one personally.</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-5 py-4 mb-8 text-sm">
            Please fix the errors below before submitting.
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= u('/apply') ?>" class="space-y-8 bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
            <?= csrf_field() ?>

            <!-- Section 1: About You -->
            <div>
                <h2 class="text-lg font-semibold text-slate-900 mb-4 pb-2 border-b border-slate-100">About You</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">First Name <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" value="<?= h($old['first_name'] ?? '') ?>"
                               class="w-full border <?= isset($errors['first_name']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <?php if (isset($errors['first_name'])): ?>
                        <p class="text-red-500 text-xs mt-1"><?= h($errors['first_name']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" name="last_name" value="<?= h($old['last_name'] ?? '') ?>"
                               class="w-full border <?= isset($errors['last_name']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <?php if (isset($errors['last_name'])): ?>
                        <p class="text-red-500 text-xs mt-1"><?= h($errors['last_name']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="<?= h($old['email'] ?? '') ?>"
                           class="w-full border <?= isset($errors['email']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <?php if (isset($errors['email'])): ?>
                    <p class="text-red-500 text-xs mt-1"><?= h($errors['email']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section 2: Your Business -->
            <div>
                <h2 class="text-lg font-semibold text-slate-900 mb-4 pb-2 border-b border-slate-100">Your Business</h2>
                <div class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Company Name <span class="text-red-500">*</span></label>
                            <input type="text" name="company_name" value="<?= h($old['company_name'] ?? '') ?>"
                                   class="w-full border <?= isset($errors['company_name']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <?php if (isset($errors['company_name'])): ?>
                            <p class="text-red-500 text-xs mt-1"><?= h($errors['company_name']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Website</label>
                            <input type="url" name="website" value="<?= h($old['website'] ?? '') ?>" placeholder="https://"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Annual Revenue <span class="text-red-500">*</span></label>
                            <select name="revenue_range" class="w-full border <?= isset($errors['revenue_range']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="">Select...</option>
                                <?php foreach (\Bizorca\Consulting\Models\Application::OPTIONS['revenue_range'] as $r): ?>
                                <option value="<?= h($r) ?>" <?= ($old['revenue_range'] ?? '') === $r ? 'selected' : '' ?>><?= h($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['revenue_range'])): ?>
                            <p class="text-red-500 text-xs mt-1"><?= h($errors['revenue_range']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Team Size <span class="text-red-500">*</span></label>
                            <select name="employee_count" class="w-full border <?= isset($errors['employee_count']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="">Select...</option>
                                <?php foreach (\Bizorca\Consulting\Models\Application::OPTIONS['employee_count'] as $e): ?>
                                <option value="<?= h($e) ?>" <?= ($old['employee_count'] ?? '') === $e ? 'selected' : '' ?>><?= h($e) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['employee_count'])): ?>
                            <p class="text-red-500 text-xs mt-1"><?= h($errors['employee_count']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: The Real Stuff -->
            <div>
                <h2 class="text-lg font-semibold text-slate-900 mb-4 pb-2 border-b border-slate-100">The Real Stuff</h2>
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Where do your current operating procedures live? <span class="text-red-500">*</span></label>
                        <select name="proc_location" class="w-full border <?= isset($errors['proc_location']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">Select...</option>
                            <?php foreach (\Bizorca\Consulting\Models\Application::OPTIONS['proc_location'] as $p): ?>
                            <option value="<?= h($p) ?>" <?= ($old['proc_location'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['proc_location'])): ?>
                        <p class="text-red-500 text-xs mt-1"><?= h($errors['proc_location']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            If you stepped away completely unplugged for two straight weeks, what breaks first? <span class="text-red-500">*</span>
                        </label>
                        <p class="text-xs text-slate-400 mb-2">Be honest. Does client delivery stop? Does lead flow die? Does payroll get missed?</p>
                        <textarea name="two_weeks" rows="3"
                                  class="w-full border <?= isset($errors['two_weeks']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"><?= h($old['two_weeks'] ?? '') ?></textarea>
                        <?php if (isset($errors['two_weeks'])): ?>
                        <p class="text-red-500 text-xs mt-1"><?= h($errors['two_weeks']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            What is the primary operational bottleneck keeping you trapped in the day-to-day? <span class="text-red-500">*</span>
                        </label>
                        <p class="text-xs text-slate-400 mb-2">Be specific. "I need to grow" is a marketing problem. "I am personally reviewing every deliverable before it goes out" is a systems problem.</p>
                        <textarea name="core_problem" rows="4"
                                  class="w-full border <?= isset($errors['core_problem']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"><?= h($old['core_problem'] ?? '') ?></textarea>
                        <?php if (isset($errors['core_problem'])): ?>
                        <p class="text-red-500 text-xs mt-1"><?= h($errors['core_problem']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            If this engagement is a total success, what specific tasks or roles will you permanently stop doing? <span class="text-red-500">*</span>
                        </label>
                        <p class="text-xs text-slate-400 mb-2">We measure success by the capacity we return to you, not just the revenue we add.</p>
                        <textarea name="desired_outcome" rows="4"
                                  class="w-full border <?= isset($errors['desired_outcome']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"><?= h($old['desired_outcome'] ?? '') ?></textarea>
                        <?php if (isset($errors['desired_outcome'])): ?>
                        <p class="text-red-500 text-xs mt-1"><?= h($errors['desired_outcome']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section 4: Logistics -->
            <div>
                <h2 class="text-lg font-semibold text-slate-900 mb-4 pb-2 border-b border-slate-100">Logistics</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">When are you prepared to begin? <span class="text-red-500">*</span></label>
                        <select name="timeline" class="w-full border <?= isset($errors['timeline']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">Select...</option>
                            <?php foreach (\Bizorca\Consulting\Models\Application::OPTIONS['timeline'] as $t): ?>
                            <option value="<?= h($t) ?>" <?= ($old['timeline'] ?? '') === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['timeline'])): ?>
                        <p class="text-red-500 text-xs mt-1"><?= h($errors['timeline']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Investment Budget Range <span class="text-red-500">*</span></label>
                        <select name="budget_range" class="w-full border <?= isset($errors['budget_range']) ? 'border-red-400' : 'border-slate-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">Select...</option>
                            <?php foreach (\Bizorca\Consulting\Models\Application::OPTIONS['budget_range'] as $b): ?>
                            <option value="<?= h($b) ?>" <?= ($old['budget_range'] ?? '') === $b ? 'selected' : '' ?>><?= h($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['budget_range'])): ?>
                        <p class="text-red-500 text-xs mt-1"><?= h($errors['budget_range']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">How did you navigate here?</label>
                    <input type="text" name="referral_source" value="<?= h($old['referral_source'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Anything else we should know before we review?</label>
                    <textarea name="notes" rows="3"
                              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"><?= h($old['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-4 rounded-xl text-lg transition-colors">
                    Submit Application
                </button>
                <p class="text-center text-xs text-slate-400 mt-3">This application does not obligate you in any way. I'll respond personally within a few business days.</p>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
