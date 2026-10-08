<?php
$appUrl = TM_BASE . '/';
$tb = $stats ?? [];
$banks = $timebanks ?? [];
?>

<!-- =====================================================================
     NAVIGATION
     ===================================================================== -->
<nav class="fixed top-0 inset-x-0 z-50 bg-white/90 backdrop-blur border-b border-gray-100" x-data="{ open: false }">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
        <a href="<?= $appUrl ?>" class="flex items-center gap-2 font-bold text-xl text-primary-700">
            <svg class="w-7 h-7 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
            </svg>
            TimeBank
        </a>
        <div class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
            <a href="#features" class="hover:text-primary-700 transition-colors">Features</a>
            <a href="#how-it-works" class="hover:text-primary-700 transition-colors">How It Works</a>
            <a href="#timebanks" class="hover:text-primary-700 transition-colors">Find a TimeBank</a>
        </div>
        <div class="hidden md:flex items-center gap-3">
            <a href="mailto:hello@bizorca.com" class="text-sm font-medium text-gray-600 hover:text-primary-700 transition-colors">Start a TimeBank</a>
            <span class="text-gray-300">|</span>
            <a href="#timebanks" class="text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 px-4 py-2 rounded-lg transition-colors">Join a Community</a>
        </div>
        <!-- Mobile menu button -->
        <button @click="open = !open" class="md:hidden p-2 rounded-md text-gray-600 hover:bg-gray-100">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <!-- Mobile menu -->
    <div x-show="open" x-cloak class="md:hidden border-t border-gray-100 bg-white px-4 py-4 space-y-3 text-sm font-medium">
        <a href="#features" @click="open=false" class="block text-gray-700 hover:text-primary-700">Features</a>
        <a href="#how-it-works" @click="open=false" class="block text-gray-700 hover:text-primary-700">How It Works</a>
        <a href="#timebanks" @click="open=false" class="block text-gray-700 hover:text-primary-700">Find a TimeBank</a>
        <a href="mailto:hello@bizorca.com" class="block text-primary-700 font-semibold">Start a TimeBank →</a>
    </div>
</nav>

<!-- =====================================================================
     HERO
     ===================================================================== -->
<section class="pt-32 pb-24 bg-gradient-to-br from-primary-50 via-white to-orange-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 text-center">
        <div class="inline-flex items-center gap-2 bg-primary-100 text-primary-800 text-xs font-semibold px-3 py-1.5 rounded-full mb-6">
            <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
            Free &amp; open for every community
        </div>
        <h1 class="text-5xl sm:text-6xl font-extrabold text-gray-900 leading-tight mb-6">
            Your time is worth<br>
            <span class="text-primary-600">exactly one hour.</span>
        </h1>
        <p class="text-xl text-gray-500 max-w-2xl mx-auto mb-10 leading-relaxed">
            TimeBank connects neighbors who exchange skills and services — no money changes hands. An hour of help is an hour of help, whether you're a surgeon or a dog walker.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="#timebanks" class="inline-flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold px-8 py-4 rounded-xl text-lg transition-colors shadow-lg shadow-primary-200">
                Find Your TimeBank
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
            <a href="mailto:hello@bizorca.com" class="inline-flex items-center justify-center gap-2 bg-white hover:bg-gray-50 text-gray-800 font-semibold px-8 py-4 rounded-xl text-lg border border-gray-200 transition-colors">
                Start a TimeBank
            </a>
        </div>
    </div>
</section>

<!-- =====================================================================
     STATS BAR
     ===================================================================== -->
<?php if (!empty($tb['total_members'])): ?>
<section class="bg-primary-700 text-white py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
        <div>
            <div class="text-4xl font-extrabold"><?= number_format((int)$tb['total_timebanks']) ?></div>
            <div class="text-primary-200 text-sm mt-1 font-medium">Active Communities</div>
        </div>
        <div>
            <div class="text-4xl font-extrabold"><?= number_format((int)$tb['total_members']) ?></div>
            <div class="text-primary-200 text-sm mt-1 font-medium">Members</div>
        </div>
        <div>
            <div class="text-4xl font-extrabold"><?= number_format((float)$tb['total_hours'], 0) ?></div>
            <div class="text-primary-200 text-sm mt-1 font-medium">Hours Exchanged</div>
        </div>
        <div>
            <div class="text-4xl font-extrabold"><?= number_format((int)$tb['total_offers']) ?></div>
            <div class="text-primary-200 text-sm mt-1 font-medium">Active Offers</div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================================
     HOW IT WORKS
     ===================================================================== -->
<section id="how-it-works" class="py-24 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-extrabold text-gray-900 mb-4">How TimeBanking Works</h2>
            <p class="text-lg text-gray-500 max-w-xl mx-auto">Simple by design. Every member starts with a balance of zero and builds community by giving.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-10">
            <div class="text-center">
                <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <svg class="w-8 h-8 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-3">1. Join Your Community</h3>
                <p class="text-gray-500 leading-relaxed">Find a timebank near you, create your profile, and list the skills you can offer — cooking, repairs, tutoring, rides, gardening, or anything else.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-accent-50 rounded-2xl flex items-center justify-center mx-auto mb-5" style="background-color: #fff7ed;">
                    <svg class="w-8 h-8 text-accent-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-3">2. Give &amp; Receive</h3>
                <p class="text-gray-500 leading-relaxed">Help a neighbor with their garden — you earn one time credit per hour. Use those credits to get help with something you need. Simple.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-3">3. Build Community Wealth</h3>
                <p class="text-gray-500 leading-relaxed">Every exchange strengthens relationships and builds real community capital — the kind no bank can print and no recession can inflate away.</p>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================================
     FEATURES
     ===================================================================== -->
<section id="features" class="py-24 bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-extrabold text-gray-900 mb-4">Everything a Community Needs</h2>
            <p class="text-lg text-gray-500 max-w-xl mx-auto">Built for the organizers running the show and the members exchanging skills every day.</p>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">

            <?php
            $features = [
                ['icon' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z', 'title' => 'Member Profiles', 'desc' => 'Rich profiles with bio, skills, service categories, avatar, and endorsements from fellow members.', 'color' => 'primary'],
                ['icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Time Tracking', 'desc' => 'Record one-to-one exchanges, classes with many receivers, or group projects — in 15-minute increments.', 'color' => 'accent'],
                ['icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z', 'title' => 'Offers &amp; Requests', 'desc' => 'Post what you can give and what you need. Browse by category or search across the whole community.', 'color' => 'green'],
                ['icon' => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75', 'title' => 'Messaging', 'desc' => 'Direct messages, group threads, and community announcements — all in one inbox.', 'color' => 'blue'],
                ['icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197', 'title' => 'Groups', 'desc' => 'Neighborhood clusters, interest groups, or project teams — each with their own message thread.', 'color' => 'purple'],
                ['icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z', 'title' => 'Admin Reports', 'desc' => '30+ built-in reports: member balances, hours by category, inactive members, new sign-ups, and more.', 'color' => 'primary'],
                ['icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z', 'title' => 'Multi-Tenant', 'desc' => 'Run dozens of independent timebanks on one platform. Each community gets its own URL, members, and settings.', 'color' => 'orange'],
                ['icon' => 'M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.98l7.5-4.04a2.25 2.25 0 012.134 0l7.5 4.04a2.25 2.25 0 011.183 1.98V19.5z', 'title' => 'Email Notifications', 'desc' => 'Weekly digests, transaction confirmations, new messages, and donation reminders — all customizable per community.', 'color' => 'teal'],
                ['icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z', 'title' => 'Donations &amp; Payments', 'desc' => 'Accept membership donations via PayPal or Stripe. Track payments, forgive balances, and run annual drives.', 'color' => 'green'],
            ];

            $colorMap = [
                'primary' => ['bg' => 'bg-primary-50',  'icon' => 'text-primary-600'],
                'accent'  => ['bg' => 'bg-orange-50',   'icon' => 'text-orange-500'],
                'green'   => ['bg' => 'bg-green-50',    'icon' => 'text-green-600'],
                'blue'    => ['bg' => 'bg-blue-50',     'icon' => 'text-blue-600'],
                'purple'  => ['bg' => 'bg-purple-50',   'icon' => 'text-purple-600'],
                'orange'  => ['bg' => 'bg-orange-50',   'icon' => 'text-orange-600'],
                'teal'    => ['bg' => 'bg-teal-50',     'icon' => 'text-teal-600'],
            ];

            foreach ($features as $f):
                $c = $colorMap[$f['color']] ?? $colorMap['primary'];
            ?>
            <div class="bg-white rounded-2xl p-6 border border-gray-100 hover:border-primary-200 hover:shadow-md transition-all duration-200">
                <div class="w-12 h-12 <?= $c['bg'] ?> rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 <?= $c['icon'] ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $f['icon'] ?>"/>
                    </svg>
                </div>
                <h3 class="font-bold text-gray-800 mb-2"><?= $f['title'] ?></h3>
                <p class="text-sm text-gray-500 leading-relaxed"><?= $f['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- =====================================================================
     TIMEBANK DIRECTORY
     ===================================================================== -->
<section id="timebanks" class="py-24 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-extrabold text-gray-900 mb-4">Active Communities</h2>
            <p class="text-lg text-gray-500 max-w-xl mx-auto">Find a timebank in your area and start exchanging skills today.</p>
        </div>

        <?php if (empty($banks)): ?>
        <div class="text-center py-16 bg-gray-50 rounded-2xl">
            <div class="text-5xl mb-4">🌱</div>
            <h3 class="text-xl font-bold text-gray-700 mb-2">No communities yet</h3>
            <p class="text-gray-500 mb-6">Be the first to start a timebank in your neighborhood.</p>
            <a href="mailto:hello@bizorca.com" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-3 rounded-xl transition-colors">
                Get Started →
            </a>
        </div>
        <?php else: ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($banks as $bank): ?>
            <a href="<?= e(community_url($bank['subdomain'])) ?>"
               class="group bg-white border border-gray-200 rounded-2xl p-6 hover:border-primary-300 hover:shadow-lg transition-all duration-200">
                <div class="flex items-start gap-4 mb-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                        <?= strtoupper(mb_substr(e($bank['name']), 0, 1)) ?>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-bold text-gray-800 group-hover:text-primary-700 transition-colors truncate"><?= e($bank['name']) ?></h3>
                        <p class="text-xs text-gray-400 font-mono"><?= e($bank['subdomain']) ?>.timebank.bizorca.com</p>
                    </div>
                </div>
                <?php if (!empty($bank['tagline'])): ?>
                <p class="text-sm text-gray-500 mb-4 leading-relaxed"><?= e(truncate($bank['tagline'], 80)) ?></p>
                <?php endif; ?>
                <div class="flex items-center gap-4 text-xs text-gray-400 border-t border-gray-50 pt-4">
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                        <?= number_format((int)$bank['member_count']) ?> members
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/></svg>
                        <?= number_format((float)$bank['hours_exchanged'], 1) ?> hrs exchanged
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- =====================================================================
     CTA
     ===================================================================== -->
<section class="py-24 bg-gradient-to-br from-primary-700 to-primary-900 text-white text-center">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <h2 class="text-4xl font-extrabold mb-5">Ready to start your own TimeBank?</h2>
        <p class="text-primary-200 text-lg mb-10 leading-relaxed">
            The platform is free. Setup takes minutes. We handle the software — you build the community.
        </p>
        <a href="mailto:hello@bizorca.com?subject=Start a TimeBank" class="inline-flex items-center gap-2 bg-white text-primary-700 hover:bg-primary-50 font-bold px-10 py-4 rounded-xl text-lg transition-colors shadow-xl">
            Contact Us to Get Started
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
    </div>
</section>

<!-- =====================================================================
     FOOTER
     ===================================================================== -->
<footer class="bg-gray-900 text-gray-400 py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-2 text-white font-bold text-lg">
            <svg class="w-5 h-5 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
            </svg>
            TimeBank
        </div>
        <p class="text-sm text-center">
            Built by <a href="https://bizorca.com" class="text-primary-400 hover:text-primary-300 transition-colors">Bizorca</a>.
            Free for all communities. &copy; <?= date('Y') ?>
        </p>
        <a href="mailto:hello@bizorca.com" class="text-sm hover:text-white transition-colors">hello@bizorca.com</a>
    </div>
</footer>
