<?php $title = 'Every Venue. Every Deadline. One Dashboard.'; ?>

<!-- Hero Section -->
<section class="gradient-hero min-h-screen flex items-center relative overflow-hidden" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 35%, #4c1d95 65%, #1e1b4b 100%);">
    <!-- Background decorative elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-violet-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-violet-600/5 rounded-full blur-3xl"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 relative">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 bg-white/10 text-white/80 text-sm font-medium px-4 py-1.5 rounded-full mb-8 border border-white/10">
                    <span class="w-2 h-2 bg-emerald-400 rounded-full"></span>
                    Free for community use
                </div>

                <h1 class="text-5xl sm:text-6xl font-bold text-white leading-tight tracking-tight mb-6">
                    Every venue.<br>
                    Every deadline.<br>
                    <span style="background: linear-gradient(135deg, #a5b4fc, #c4b5fd); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">One dashboard.</span>
                </h1>

                <p class="text-lg text-white/70 leading-relaxed mb-10 max-w-lg">
                    Stop juggling spreadsheets and forgotten deadlines. Dispatch calculates every submission timeline, surfaces today's tasks, and keeps your event promotions on track — automatically.
                </p>

                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="<?= $_base ?>/register" class="inline-flex items-center justify-center px-8 py-4 bg-white text-indigo-700 font-bold rounded-xl hover:bg-indigo-50 transition-all shadow-lg shadow-indigo-900/30 text-base">
                        Get Started Free
                        <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                    <a href="<?= $_base ?>/features" class="inline-flex items-center justify-center px-8 py-4 bg-white/10 text-white font-semibold rounded-xl hover:bg-white/20 transition-all border border-white/20 text-base">
                        See How It Works
                    </a>
                </div>
            </div>

            <!-- Dashboard mockup -->
            <div class="hidden lg:block">
                <div class="bg-white/10 backdrop-blur rounded-2xl border border-white/20 p-1 shadow-2xl shadow-indigo-900/50">
                    <div class="bg-slate-900 rounded-xl overflow-hidden">
                        <!-- Fake browser bar -->
                        <div class="bg-slate-800 px-4 py-3 flex items-center gap-2">
                            <div class="w-3 h-3 bg-red-400 rounded-full"></div>
                            <div class="w-3 h-3 bg-amber-400 rounded-full"></div>
                            <div class="w-3 h-3 bg-emerald-400 rounded-full"></div>
                            <div class="flex-1 mx-4 bg-slate-700 rounded-md h-5 text-xs text-slate-400 flex items-center px-3">dispatch.local/dashboard</div>
                        </div>
                        <!-- Fake dashboard -->
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-3 gap-3">
                                <div class="bg-red-500/20 rounded-xl p-3 text-center">
                                    <div class="text-red-300 text-2xl font-bold">2</div>
                                    <div class="text-red-400 text-xs mt-0.5">Overdue</div>
                                </div>
                                <div class="bg-amber-500/20 rounded-xl p-3 text-center">
                                    <div class="text-amber-300 text-2xl font-bold">3</div>
                                    <div class="text-amber-400 text-xs mt-0.5">Due Today</div>
                                </div>
                                <div class="bg-indigo-500/20 rounded-xl p-3 text-center">
                                    <div class="text-indigo-300 text-2xl font-bold">7</div>
                                    <div class="text-indigo-400 text-xs mt-0.5">This Week</div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <?php
                                $mockItems = [
                                    ['venue' => 'KPTZ Events Calendar', 'event' => 'Summer Solstice Market', 'due' => 'Due Today', 'color' => 'amber'],
                                    ['venue' => 'Port Townsend Leader', 'event' => 'Summer Solstice Market', 'due' => 'In 2 days', 'color' => 'blue'],
                                    ['venue' => 'Facebook Events', 'event' => 'Friday Farmers Market', 'due' => 'In 4 days', 'color' => 'blue'],
                                ];
                                foreach ($mockItems as $mock):
                                ?>
                                <div class="bg-white/5 rounded-lg px-4 py-3 flex items-center justify-between">
                                    <div>
                                        <p class="text-white text-xs font-medium"><?= $mock['venue'] ?></p>
                                        <p class="text-slate-400 text-xs"><?= $mock['event'] ?></p>
                                    </div>
                                    <span class="text-xs font-semibold text-<?= $mock['color'] ?>-300 bg-<?= $mock['color'] ?>-500/20 px-2 py-1 rounded-full"><?= $mock['due'] ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Social proof / trust bar -->
<section class="bg-white border-b border-slate-200 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-slate-500 text-sm font-medium uppercase tracking-wider mb-8">Designed for community organizers, solo practitioners, and small businesses</p>
        <div class="flex flex-wrap justify-center gap-x-12 gap-y-6">
            <?php
            $outlets = ['Radio Stations', 'Print Newspapers', 'Community Calendars', 'Social Media', 'Physical Bulletin Boards', 'Email Newsletters'];
            foreach ($outlets as $outlet):
            ?>
            <div class="flex items-center gap-2 text-slate-600">
                <svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm font-medium"><?= $outlet ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Features section -->
<section class="py-24 bg-white" id="features">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-indigo-600 font-semibold text-sm uppercase tracking-wider">How It Works</span>
            <h2 class="mt-3 text-4xl font-bold text-slate-900 tracking-tight">The promotion workflow, automated</h2>
            <p class="mt-4 text-lg text-slate-500 max-w-2xl mx-auto">Stop tracking lead times in your head. Dispatch does the math and shows you exactly what to do, today.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <?php
            $features = [
                [
                    'icon' => '📅',
                    'title' => 'Smart Deadline Calculator',
                    'desc' => 'Enter your event date and select venues. Dispatch automatically subtracts each venue\'s required lead time plus a buffer — giving you an exact action plan.',
                    'color' => 'indigo',
                ],
                [
                    'icon' => '📍',
                    'title' => 'Curated Venue Library',
                    'desc' => 'A growing library of local publicity outlets — radio calendars, newspapers, social platforms, community boards — with submission requirements built in.',
                    'color' => 'violet',
                ],
                [
                    'icon' => '✅',
                    'title' => 'Daily Action Dashboard',
                    'desc' => 'Your dashboard surfaces only what needs attention today. Each task includes the specific formatting requirements and a direct link to the submission portal.',
                    'color' => 'emerald',
                ],
                [
                    'icon' => '📌',
                    'title' => 'Flyer Location Tracker',
                    'desc' => 'Log every physical bulletin board where you\'ve posted flyers — with addresses, posting dates, and removal reminders.',
                    'color' => 'amber',
                ],
                [
                    'icon' => '🔁',
                    'title' => 'Recurring Events',
                    'desc' => 'For weekly markets, monthly meetups, or any recurring event: Dispatch generates the full series of action items automatically.',
                    'color' => 'blue',
                ],
                [
                    'icon' => '📬',
                    'title' => 'Digest Notifications',
                    'desc' => 'One daily or weekly email summarizes all upcoming deadlines. No notification fatigue — just a clean, actionable summary when you need it.',
                    'color' => 'pink',
                ],
            ];
            foreach ($features as $f):
            ?>
            <div class="group p-8 rounded-2xl bg-slate-50 border border-slate-100 hover:border-indigo-100 hover:bg-indigo-50/30 transition-all card-hover">
                <div class="text-4xl mb-5"><?= $f['icon'] ?></div>
                <h3 class="text-lg font-bold text-slate-900 mb-3"><?= $f['title'] ?></h3>
                <p class="text-slate-500 leading-relaxed text-sm"><?= $f['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How it works steps -->
<section class="py-24 bg-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-indigo-600 font-semibold text-sm uppercase tracking-wider">The Process</span>
            <h2 class="mt-3 text-4xl font-bold text-slate-900 tracking-tight">Up and running in minutes</h2>
        </div>

        <div class="space-y-8">
            <?php
            $steps = [
                ['num' => '01', 'title' => 'Create a Campaign', 'desc' => 'Enter your event name, date, location, and a base description. That\'s all the information the engine needs.'],
                ['num' => '02', 'title' => 'Choose Your Venues', 'desc' => 'Browse the venue library and toggle on the outlets you want to reach. Each has verified lead times and submission requirements.'],
                ['num' => '03', 'title' => 'Receive Your Action Plan', 'desc' => 'Dispatch instantly calculates every submission deadline, accounting for lead times and built-in buffer days.'],
                ['num' => '04', 'title' => 'Work the Dashboard', 'desc' => 'Every morning, your dashboard shows exactly what\'s due. Click, submit, mark done. That\'s your whole workflow.'],
            ];
            foreach ($steps as $i => $step):
            ?>
            <div class="flex gap-6 items-start">
                <div class="flex-shrink-0 w-12 h-12 bg-indigo-600 text-white font-bold text-lg rounded-2xl flex items-center justify-center shadow-sm">
                    <?= $step['num'] ?>
                </div>
                <div class="pt-2">
                    <h3 class="text-lg font-bold text-slate-900 mb-2"><?= $step['title'] ?></h3>
                    <p class="text-slate-500 leading-relaxed"><?= $step['desc'] ?></p>
                </div>
            </div>
            <?php if ($i < count($steps) - 1): ?>
            <div class="ml-6 w-px h-4 bg-slate-300"></div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA section -->
<section class="py-24" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4c1d95 100%);">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <h2 class="text-4xl font-bold text-white mb-4 tracking-tight">Ready to simplify your promotions?</h2>
        <p class="text-white/70 text-lg mb-10">Sign up free. No credit card required.</p>
        <a href="<?= $_base ?>/register" class="inline-flex items-center px-10 py-4 bg-white text-indigo-700 font-bold rounded-xl text-lg hover:bg-indigo-50 transition-all shadow-lg">
            Create Your Free Account
            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
        </a>
    </div>
</section>
