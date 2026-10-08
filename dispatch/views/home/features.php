<?php $title = 'Features'; ?>

<section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-20">
            <span class="text-indigo-600 font-semibold text-sm uppercase tracking-wider">Everything You Need</span>
            <h1 class="mt-3 text-5xl font-bold text-slate-900 tracking-tight">Built for the whole promotion workflow</h1>
            <p class="mt-5 text-xl text-slate-500 max-w-3xl mx-auto">From event creation to flyer tracking, Dispatch handles every step of the local event promotion process.</p>
        </div>

        <!-- Feature: Schedule Engine -->
        <div class="grid lg:grid-cols-2 gap-16 items-center mb-28">
            <div>
                <div class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 text-sm font-semibold px-4 py-1.5 rounded-full mb-6">
                    <span>⚙️</span> Schedule Engine
                </div>
                <h2 class="text-3xl font-bold text-slate-900 mb-4 tracking-tight">Reverse-schedules every deadline automatically</h2>
                <p class="text-slate-500 leading-relaxed mb-6">Enter your event date, select your venues, and Dispatch does the math. Each venue's required lead time plus a built-in buffer day is subtracted from your event date to produce an exact submission deadline.</p>
                <ul class="space-y-3">
                    <?php
                    $items = ['Venue-specific lead time library', 'Configurable buffer days per venue', 'Visual timeline of upcoming tasks', 'Overdue detection and highlighting', 'Recurring event support (weekly, monthly)'];
                    foreach ($items as $item):
                    ?>
                    <li class="flex items-center gap-3 text-slate-600">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <?= $item ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="bg-slate-900 rounded-2xl p-6 font-mono text-sm">
                <div class="text-slate-400 mb-4">// Schedule calculation example</div>
                <div class="space-y-2">
                    <div><span class="text-purple-400">event_date</span>  <span class="text-slate-400">=</span> <span class="text-emerald-300">"2024-08-15"</span></div>
                    <div class="mt-4 text-slate-400">// KPTZ Events Calendar</div>
                    <div><span class="text-blue-400">lead_time</span>    <span class="text-slate-400">= 14 days</span></div>
                    <div><span class="text-blue-400">buffer</span>       <span class="text-slate-400">= 2 days</span></div>
                    <div class="text-yellow-300 font-bold">due_date = <span class="text-white">"2024-07-30"</span></div>
                    <div class="mt-4 text-slate-400">// Port Townsend Leader</div>
                    <div><span class="text-blue-400">lead_time</span>    <span class="text-slate-400">= 10 days</span></div>
                    <div><span class="text-blue-400">buffer</span>       <span class="text-slate-400">= 1 day</span></div>
                    <div class="text-yellow-300 font-bold">due_date = <span class="text-white">"2024-08-04"</span></div>
                </div>
            </div>
        </div>

        <!-- Feature: Venue Library -->
        <div class="grid lg:grid-cols-2 gap-16 items-center mb-28">
            <div class="order-2 lg:order-1">
                <div class="grid grid-cols-2 gap-3">
                    <?php
                    $venues = [
                        ['name' => 'KPTZ Events', 'type' => 'Radio', 'lead' => '14 days', 'color' => 'purple'],
                        ['name' => 'PT Leader', 'type' => 'Print', 'lead' => '10 days', 'color' => 'blue'],
                        ['name' => 'Facebook Events', 'type' => 'Social', 'lead' => '1 day', 'color' => 'indigo'],
                        ['name' => 'Eventbrite', 'type' => 'Calendar', 'lead' => '1 day', 'color' => 'orange'],
                        ['name' => 'Nextdoor', 'type' => 'Social', 'lead' => '1 day', 'color' => 'green'],
                        ['name' => 'Co-op Bulletin', 'type' => 'Physical', 'lead' => '3 days', 'color' => 'amber'],
                    ];
                    foreach ($venues as $v):
                    ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <p class="font-semibold text-slate-900 text-sm"><?= $v['name'] ?></p>
                        <p class="text-slate-400 text-xs mt-0.5"><?= $v['type'] ?> · <?= $v['lead'] ?> lead</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="order-1 lg:order-2">
                <div class="inline-flex items-center gap-2 bg-violet-50 text-violet-700 text-sm font-semibold px-4 py-1.5 rounded-full mb-6">
                    <span>🏛️</span> Venue Library
                </div>
                <h2 class="text-3xl font-bold text-slate-900 mb-4 tracking-tight">Curated, verified, and community-grown</h2>
                <p class="text-slate-500 leading-relaxed mb-6">The venue library is maintained by a SysOp who verifies submission portals, confirms actual lead times, and codifies formatting requirements into the logic engine. Users can suggest new venues through a structured submission form.</p>
                <ul class="space-y-3">
                    <?php
                    $items = ['Radio, print, digital, and physical venues', 'Verified submission URLs and contact info', 'Asset requirements (image specs, word counts)', 'Community-suggested venues with triage review', 'Instantly available to all users on approval'];
                    foreach ($items as $item):
                    ?>
                    <li class="flex items-center gap-3 text-slate-600">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <?= $item ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Feature: Flyer Tracker -->
        <div class="grid lg:grid-cols-2 gap-16 items-center mb-28">
            <div>
                <div class="inline-flex items-center gap-2 bg-amber-50 text-amber-700 text-sm font-semibold px-4 py-1.5 rounded-full mb-6">
                    <span>📌</span> Flyer Tracker
                </div>
                <h2 class="text-3xl font-bold text-slate-900 mb-4 tracking-tight">Know exactly where every flyer is</h2>
                <p class="text-slate-500 leading-relaxed mb-6">Log every physical posting location — bulletin boards, coffee shops, community centers — with the date posted, quantity, and notes. Track when flyers need to be removed or replaced.</p>
                <ul class="space-y-3">
                    <?php
                    $items = ['Location name and address', 'Posted and removal dates', 'Quantity tracking', 'Active / removed / unknown status', 'Link to specific campaign'];
                    foreach ($items as $item):
                    ?>
                    <li class="flex items-center gap-3 text-slate-600">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <?= $item ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="space-y-3">
                <?php
                $locations = [
                    ['name' => 'Port Townsend Food Co-op', 'addr' => '414 Kearney St', 'status' => 'Active', 'posted' => 'Jun 12', 'qty' => 5, 'color' => 'emerald'],
                    ['name' => 'Uptown Pub', 'addr' => '101 Quincy St', 'status' => 'Active', 'posted' => 'Jun 13', 'qty' => 3, 'color' => 'emerald'],
                    ['name' => 'Old Siren Coffee', 'addr' => 'Water St', 'status' => 'Removed', 'posted' => 'Jun 10', 'qty' => 2, 'color' => 'slate'],
                ];
                foreach ($locations as $loc):
                ?>
                <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="font-semibold text-slate-900 text-sm"><?= $loc['name'] ?></p>
                        <p class="text-slate-400 text-xs mt-0.5"><?= $loc['addr'] ?> · Posted <?= $loc['posted'] ?> · <?= $loc['qty'] ?> flyers</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-<?= $loc['color'] ?>-100 text-<?= $loc['color'] ?>-700"><?= $loc['status'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Feature: Security -->
        <div class="grid lg:grid-cols-2 gap-16 items-center mb-28">
            <div class="order-2 lg:order-1">
                <div class="grid grid-cols-2 gap-3">
                    <?php
                    $security = [
                        ['icon' => '🔑', 'label' => 'One account', 'desc' => 'One Bizorca Tools login across every tool on tools.bizorca.com'],
                        ['icon' => '🛡️', 'label' => 'CSRF Protection', 'desc' => 'Every form and state-change is token-verified'],
                        ['icon' => '🔒', 'label' => 'bcrypt passwords', 'desc' => 'Hashed with bcrypt — no plaintext ever stored'],
                        ['icon' => '📧', 'label' => 'Email verification', 'desc' => 'Accounts verified before access is granted'],
                        ['icon' => '👤', 'label' => 'Role-based access', 'desc' => 'User, admin, and sysop tiers with enforced gates'],
                        ['icon' => '🚪', 'label' => 'Ownership checks', 'desc' => 'Every resource is scoped to its owner on every request'],
                    ];
                    foreach ($security as $s):
                    ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <p class="font-semibold text-slate-900 text-sm"><?= $s['icon'] ?> <?= $s['label'] ?></p>
                        <p class="text-slate-400 text-xs mt-1 leading-snug"><?= $s['desc'] ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="order-1 lg:order-2">
                <div class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-700 text-sm font-semibold px-4 py-1.5 rounded-full mb-6">
                    <span>🔐</span> Security
                </div>
                <h2 class="text-3xl font-bold text-slate-900 mb-4 tracking-tight">Built with security in the foundation, not bolted on top</h2>
                <p class="text-slate-500 leading-relaxed mb-6">Dispatch uses your Bizorca Tools account — the same login for every tool on tools.bizorca.com — so you're never managing another password. CSRF tokens protect every POST request. Passwords are hashed with bcrypt. Every campaign and action item is scoped to its owner on every read and write.</p>
                <ul class="space-y-3">
                    <?php
                    $items = [
                        'One Bizorca Tools account — sign in once, use every tool',
                        'CSRF tokens on every state-changing request',
                        'Secure password reset with expiring one-time tokens',
                        'Role-based gates enforced server-side on every route',
                        'No third-party tracking or analytics scripts',
                    ];
                    foreach ($items as $item):
                    ?>
                    <li class="flex items-center gap-3 text-slate-600">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <?= $item ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- CTA -->
        <div class="text-center py-16 bg-slate-50 rounded-3xl border border-slate-200">
            <h2 class="text-3xl font-bold text-slate-900 mb-4">Ready to try it?</h2>
            <p class="text-slate-500 mb-8">Free forever. No credit card required.</p>
            <a href="<?= $_base ?>/register" class="inline-flex items-center px-8 py-4 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm">
                Get Started Free →
            </a>
        </div>
    </div>
</section>
