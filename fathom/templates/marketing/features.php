<?php
/** Ported from resources/views (Blade). */
$__title = 'Features — Fathom';
$__meta = 'Everything Fathom includes: Kanban boards, cards with checklists and comments, team collaboration, magic link login, public sharing, business type templates, and more — all free.';
ob_start();
?>
<section class="max-w-4xl mx-auto px-6 pt-20 pb-14 text-center">
    <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight mb-5">
        Everything you need.<br class="hidden md:block"> <span class="text-indigo-600">Nothing you don't.</span>
    </h1>
    <p class="text-xl text-gray-500 max-w-xl mx-auto">
        Fathom is deliberately scoped. If you're managing clients and tracking work, it has what you need. If you're building rockets, it probably doesn't — and that's fine.
    </p>
</section>


<div class="max-w-5xl mx-auto px-6 space-y-24 pb-20">

    
    <div class="grid md:grid-cols-2 gap-12 items-center">
        <div>
            <div class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 text-xs font-bold px-3 py-1 rounded-full mb-4">Boards</div>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">One board per workflow. As many as you need.</h2>
            <p class="text-gray-500 leading-relaxed mb-4">
                Create boards for your client pipeline, active engagements, operations, content — whatever your business actually runs on. Columns are fully customizable. Move them left and right, rename them, give them WIP limits.
            </p>
            <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Unlimited boards</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Custom column names and order</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> WIP limits per column</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Archive boards when a season wraps</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Color-code boards for quick scanning</li>
            </ul>
        </div>
        <div class="bg-gray-50 rounded-2xl border border-gray-200 p-5">
            <div class="flex gap-3">
                <?php foreach (['New Leads' => 'bg-indigo-100 text-indigo-700', 'Discovery' => 'bg-yellow-100 text-yellow-700', 'Enrolled' => 'bg-emerald-100 text-emerald-700'] as $col => $style): ?>
                <div class="flex-1">
                    <div class="text-xs font-semibold <?= e($style) ?> px-2 py-1 rounded mb-2 text-center"><?= e($col) ?></div>
                    <div class="space-y-2">
                        <div class="bg-white rounded-lg border border-gray-200 p-2 text-xs text-gray-700 shadow-sm">Maria T.</div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2 text-xs text-gray-700 shadow-sm">Robert C.</div>
                        <?php if ($col === 'New Leads'): ?><div class="bg-white rounded-lg border border-gray-200 p-2 text-xs text-gray-700 shadow-sm">Priya N.</div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    
    <div class="grid md:grid-cols-2 gap-12 items-center">
        <div class="order-2 md:order-1 bg-gray-50 rounded-2xl border border-gray-200 p-5 space-y-3">
            <div class="text-sm font-semibold text-gray-900">Maria Torres — Career Change</div>
            <div class="flex gap-2">
                <span class="text-xs bg-violet-100 text-violet-700 px-2 py-0.5 rounded-full">Interview Prep</span>
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Resume</span>
            </div>
            <div class="text-xs text-gray-500 border-t border-gray-200 pt-3">
                <div class="font-medium text-gray-700 mb-1.5">Checklist (2 of 4)</div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2"><div class="w-3.5 h-3.5 rounded border-2 border-indigo-500 bg-indigo-500 flex items-center justify-center"><svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg></div><span class="line-through text-gray-400">Resume draft reviewed</span></div>
                    <div class="flex items-center gap-2"><div class="w-3.5 h-3.5 rounded border-2 border-indigo-500 bg-indigo-500 flex items-center justify-center"><svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg></div><span class="line-through text-gray-400">LinkedIn overhaul</span></div>
                    <div class="flex items-center gap-2"><div class="w-3.5 h-3.5 rounded border border-gray-300"></div><span>Mock interview #1</span></div>
                    <div class="flex items-center gap-2"><div class="w-3.5 h-3.5 rounded border border-gray-300"></div><span>Target company list</span></div>
                </div>
            </div>
        </div>
        <div class="order-1 md:order-2">
            <div class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 text-xs font-bold px-3 py-1 rounded-full mb-4">Cards</div>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Every card holds everything about that client or task.</h2>
            <p class="text-gray-500 leading-relaxed mb-4">
                Description, checklists, comments, due dates, assignees, tags, reactions, file links — it's all there when you open the card. No hunting across three different tools to reconstruct context before a call.
            </p>
            <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Checklists with progress tracking</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Comments and threaded discussion</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Tags and color labels</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Due dates</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Assignees, watchers, and pins</li>
            </ul>
        </div>
    </div>

    
    <div class="grid md:grid-cols-2 gap-12 items-center">
        <div>
            <div class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 text-xs font-bold px-3 py-1 rounded-full mb-4">Business type templates</div>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Walk in on day one with your boards already built.</h2>
            <p class="text-gray-500 leading-relaxed mb-4">
                Pick your business type during signup and Fathom pre-populates your account with boards, columns, and tags tailored to how that business actually operates. Financial coaches get a client pipeline and active clients board. Tax consultants get a returns tracker. Career coaches get an interview pipeline. All of it is fully editable — it's a starting point, not a straitjacket.
            </p>
            <div class="space-y-2 text-sm text-gray-600">
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Financial Coach</div>
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Tax Consultant</div>
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Career Coach</div>
                <div class="flex items-center gap-2 text-gray-400 italic">More types added regularly</div>
            </div>
        </div>
        <div class="bg-indigo-50 rounded-2xl border border-indigo-100 p-6 space-y-3">
            <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider mb-2">Financial Coach — pre-built</div>
            <?php foreach (['Client Pipeline' => 'New Leads → Discovery → Proposal Sent → Enrolled', 'Active Clients' => 'Onboarding → Building Plan → Plan in Action → Graduating', 'Operations' => 'Backlog → This Week → In Progress → Done'] as $board => $cols): ?>
            <div class="bg-white rounded-xl border border-indigo-100 p-3">
                <div class="font-semibold text-gray-800 text-sm mb-1"><?= e($board) ?></div>
                <div class="text-xs text-gray-400"><?= e($cols) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    
    <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-10 text-center">Everything else worth knowing about</h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ([
                ['icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z', 'title' => 'Team members', 'body' => 'Invite collaborators with a shareable link. Manage roles and remove access any time.'],
                ['icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z', 'title' => 'Magic link sign-in', 'body' => 'Email, click, in. No password to create, store, forget, or reset. Simple as it gets.'],
                ['icon' => 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244', 'title' => 'Public board sharing', 'body' => 'Share a live, read-only board view with clients or partners — no account required on their end.'],
                ['icon' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0', 'title' => 'Notifications', 'body' => 'Notified when something relevant happens. Not when everything happens. You can tune it.'],
                ['icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z', 'title' => 'Activity feed', 'body' => 'Every move, comment, and status change is logged. Pull up any card and see exactly what happened and when.'],
                ['icon' => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3', 'title' => 'Data export', 'body' => 'Export your boards and cards any time. Your data belongs to you. No ransom, no premium export tier.'],
            ] as $f): ?>
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-5">
                <div class="w-8 h-8 bg-white rounded-lg border border-gray-200 flex items-center justify-center mb-3 shadow-sm">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?= e($f['icon']) ?>"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 text-sm mb-1"><?= e($f['title']) ?></h3>
                <p class="text-xs text-gray-500 leading-relaxed"><?= e($f['body']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>


<section class="bg-gray-900 py-20 mt-10">
    <div class="max-w-2xl mx-auto px-6 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">All of it. Free.</h2>
        <p class="text-gray-400 text-lg mb-8">No feature gating. No seat limits. No expiring trial.</p>
        <a href="<?= e(route('signup')) ?>" class="inline-block bg-indigo-600 text-white font-bold px-8 py-3.5 rounded-xl text-base hover:bg-indigo-700 transition">
            Create your account
        </a>
    </div>
</section>
<?php fm_layout('marketing', (string) $__title, (string) ob_get_clean(), ['meta' => $__meta]); ?>
