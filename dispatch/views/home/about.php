<?php $title = 'About'; ?>

<section class="py-24 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-indigo-600 font-semibold text-sm uppercase tracking-wider">Our Story</span>
            <h1 class="mt-3 text-5xl font-bold text-slate-900 tracking-tight">About Dispatch</h1>
        </div>

        <div class="prose prose-lg max-w-none">
            <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-8 mb-12">
                <p class="text-indigo-900 text-xl font-medium leading-relaxed">
                    Dispatch was built to solve a specific, stubborn problem: the cognitive load of promoting events across many different publicity channels with different requirements, deadlines, and formats.
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-12 mb-16">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 mb-4">The Problem</h2>
                    <p class="text-slate-600 leading-relaxed mb-4">
                        Solo practitioners, community organizers, and small business owners consistently identify the same challenge: they know <em>what</em> needs to be done for event promotion, but keeping track of <em>when</em> to do each thing — across a dozen different venues with different lead times — is an enormous cognitive burden.
                    </p>
                    <p class="text-slate-600 leading-relaxed">
                        This isn't a skill problem. It's a systems problem. The radio station needs 14 days. The newspaper needs 10. The online calendar needs 1. Keeping that mental model active while also running a business is friction that eats time and causes missed opportunities.
                    </p>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 mb-4">Our Approach</h2>
                    <p class="text-slate-600 leading-relaxed mb-4">
                        Dispatch focuses on <strong>workflow calculation</strong>, not auto-publishing. The goal isn't to post on your behalf — it's to eliminate the cognitive overhead of knowing what to do and when to do it.
                    </p>
                    <p class="text-slate-600 leading-relaxed">
                        By building a library of verified venue requirements and running reverse-scheduling calculations, Dispatch converts a chaotic mental model into a clean, daily action list that anyone can execute in minutes.
                    </p>
                </div>
            </div>

            <div class="bg-slate-900 rounded-2xl p-8 mb-12">
                <h2 class="text-2xl font-bold text-white mb-6">Design Principles</h2>
                <div class="grid md:grid-cols-2 gap-6">
                    <?php
                    $principles = [
                        ['title' => 'Reduce cognitive load', 'desc' => 'Every feature decision asks: does this make the user think less?'],
                        ['title' => 'Verified data, always', 'desc' => 'The SysOp controls the venue library. No user can corrupt the shared dataset.'],
                        ['title' => 'Workflow, not automation', 'desc' => 'We tell you exactly what to do and where to go. You do the submitting.'],
                        ['title' => 'Single daily digest', 'desc' => 'One email per day, max. We respect your attention.'],
                    ];
                    foreach ($principles as $p):
                    ?>
                    <div class="flex gap-3">
                        <div class="w-5 h-5 bg-indigo-500 rounded-full flex-shrink-0 mt-0.5 flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-white font-semibold text-sm"><?= $p['title'] ?></h4>
                            <p class="text-slate-400 text-sm mt-1"><?= $p['desc'] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="text-center">
            <a href="<?= $_base ?>/register" class="inline-flex items-center px-8 py-4 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm">
                Get Started Free
            </a>
        </div>
    </div>
</section>
