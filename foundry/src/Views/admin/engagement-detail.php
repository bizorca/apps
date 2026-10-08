<?php
$title = h($engagement['title']) . ' — Bizorca Admin';
ob_start();

$columnColors = [
    'violet'  => ['header' => 'text-violet-600', 'bg' => 'bg-violet-50 border-violet-200'],
    'blue'    => ['header' => 'text-blue-600',   'bg' => 'bg-blue-50 border-blue-200'],
    'amber'   => ['header' => 'text-amber-600',  'bg' => 'bg-amber-50 border-amber-200'],
    'orange'  => ['header' => 'text-orange-600', 'bg' => 'bg-orange-50 border-orange-200'],
    'emerald' => ['header' => 'text-emerald-600','bg' => 'bg-emerald-50 border-emerald-200'],
    'slate'   => ['header' => 'text-slate-600',  'bg' => 'bg-slate-50 border-slate-200'],
];
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4 sticky top-0 z-40">
    <div class="max-w-full px-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="<?= u('/admin/engagements') ?>" class="text-slate-500 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="text-white font-semibold text-sm truncate"><?= h($engagement['title']) ?></span>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <?php if ($scopeStats['added'] > 0): ?>
            <span class="text-xs bg-amber-500/20 text-amber-400 px-2 py-0.5 rounded-full">
                <?= $scopeStats['added'] ?> out-of-scope card<?= $scopeStats['added'] != 1 ? 's' : '' ?>
            </span>
            <?php endif; ?>
            <a href="<?= u('/admin') ?>" class="text-slate-400 hover:text-white text-xs transition-colors">Admin</a>
        </div>
    </div>
</nav>

<div class="min-h-screen bg-slate-100">
    <div class="flex h-[calc(100vh-57px)]">

        <!-- Sidebar -->
        <div class="w-80 flex-shrink-0 bg-white border-r border-slate-200 overflow-y-auto">
            <div class="p-5 space-y-6">

                <!-- Client info -->
                <div>
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Client</div>
                    <div class="text-sm font-medium text-slate-900"><?= h($engagement['first_name'] . ' ' . $engagement['last_name']) ?></div>
                    <div class="text-xs text-slate-500"><?= h($engagement['email']) ?></div>
                    <p class="text-xs text-slate-400 mt-2 leading-relaxed">Signs in with a Bizorca Tools account at this address. If they applied without one, accepting created it with no password: send them to <span class="font-mono">tools.bizorca.com/account/forgot.php</span> to set one.</p>
                </div>

                <!-- Status -->
                <div>
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Status</div>
                    <?php
                    $statusColors = [
                        'onboarding' => 'bg-violet-100 text-violet-700',
                        'active'     => 'bg-emerald-100 text-emerald-700',
                        'paused'     => 'bg-amber-100 text-amber-700',
                        'complete'   => 'bg-slate-100 text-slate-600',
                    ];
                    $sc = $statusColors[$engagement['status']] ?? 'bg-slate-100 text-slate-600';
                    ?>
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full <?= $sc ?>"><?= ucfirst($engagement['status']) ?></span>
                </div>

                <!-- Onboarding steps -->
                <?php if (!empty($steps)): ?>
                <div>
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Onboarding</div>
                    <div class="space-y-2">
                        <?php foreach ($steps as $step): ?>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-4 h-4 rounded-full flex-shrink-0 flex items-center justify-center <?= $step['completed_at'] ? 'bg-emerald-500' : 'bg-slate-200' ?>">
                                <?php if ($step['completed_at']): ?>
                                <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <?php endif; ?>
                            </span>
                            <span class="<?= $step['completed_at'] ? 'text-slate-500 line-through' : 'text-slate-700' ?>"><?= h($step['label']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Scope management -->
                <div>
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Scope Document</div>

                    <?php if ($engagement['scope_locked_at']): ?>
                    <div class="text-xs text-emerald-600 mb-2">Locked <?= date('M j, Y', strtotime($engagement['scope_locked_at'])) ?></div>
                    <?php if ($engagement['client_accepted_scope_at']): ?>
                    <div class="text-xs text-emerald-600 mb-3">Client accepted <?= date('M j, Y', strtotime($engagement['client_accepted_scope_at'])) ?></div>
                    <?php else: ?>
                    <div class="text-xs text-amber-600 mb-3">Awaiting client acceptance</div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!$engagement['scope_locked_at']): ?>
                    <!-- Scope editor -->
                    <form method="POST" action="<?= u('/admin/engagements/' . ($engagement['id']) . '/scope') ?>" x-data="scopeEditor()">
                        <?= csrf_field() ?>
                        <div id="scope-items" class="space-y-2 mb-3">
                            <?php if (!empty($scopes)): ?>
                            <?php foreach ($scopes as $item): ?>
                            <div class="scope-item flex gap-2">
                                <div class="flex-1 space-y-1">
                                    <input type="text" name="scope_title[]" value="<?= h($item['title']) ?>"
                                           placeholder="Deliverable title"
                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500">
                                    <input type="text" name="scope_description[]" value="<?= h($item['description'] ?? '') ?>"
                                           placeholder="Description (optional)"
                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs text-slate-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="scope-item flex gap-2">
                                <div class="flex-1 space-y-1">
                                    <input type="text" name="scope_title[]" placeholder="Deliverable title"
                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500">
                                    <input type="text" name="scope_description[]" placeholder="Description (optional)"
                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs text-slate-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <button type="button" onclick="addScopeItem()" class="text-xs text-brand-600 hover:text-brand-700 mb-3 block">+ Add item</button>
                        <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white text-xs font-medium py-2 rounded-lg mb-2 transition-colors">Save Scope</button>
                    </form>

                    <form method="POST" action="<?= u('/admin/engagements/' . ($engagement['id']) . '/lock-scope') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="w-full bg-brand-500 hover:bg-brand-600 text-white text-xs font-medium py-2 rounded-lg transition-colors"
                                onclick="return confirm('Lock the scope? The client will be asked to review and accept it.')">
                            Lock &amp; Send to Client
                        </button>
                    </form>

                    <script>
                    function addScopeItem() {
                        const container = document.getElementById('scope-items');
                        const div = document.createElement('div');
                        div.className = 'scope-item flex gap-2';
                        div.innerHTML = `<div class="flex-1 space-y-1">
                            <input type="text" name="scope_title[]" placeholder="Deliverable title" class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500">
                            <input type="text" name="scope_description[]" placeholder="Description (optional)" class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs text-slate-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        </div>`;
                        container.appendChild(div);
                    }
                    </script>
                    <?php endif; ?>
                </div>

                <!-- Pending change requests -->
                <?php $pendingCRs = array_filter($changes, fn($c) => $c['status'] === 'pending'); ?>
                <?php if (!empty($pendingCRs)): ?>
                <div>
                    <div class="text-xs font-semibold text-amber-600 uppercase tracking-wide mb-3">Pending Changes (<?= count($pendingCRs) ?>)</div>
                    <?php foreach ($pendingCRs as $cr): ?>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-2">
                        <div class="font-medium text-slate-900 text-xs mb-1"><?= h($cr['title']) ?></div>
                        <p class="text-xs text-slate-600 mb-3"><?= h(mb_substr($cr['description'], 0, 100)) ?><?= mb_strlen($cr['description']) > 100 ? '…' : '' ?></p>
                        <div class="flex gap-2">
                            <form method="POST" action="<?= u('/admin/changes/' . ($cr['id']) . '/approve') ?>" class="flex-1">
                                <?= csrf_field() ?>
                                <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs py-1.5 rounded transition-colors">Approve</button>
                            </form>
                            <form method="POST" action="<?= u('/admin/changes/' . ($cr['id']) . '/decline') ?>" class="flex-1">
                                <?= csrf_field() ?>
                                <button class="w-full bg-red-100 hover:bg-red-200 text-red-700 text-xs py-1.5 rounded transition-colors">Decline</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Main: Kanban board -->
        <div class="flex-1 overflow-x-auto p-6">

            <?php if (!$board): ?>
            <!-- Create board -->
            <div class="flex items-center justify-center h-full">
                <div class="text-center">
                    <h3 class="font-semibold text-slate-700 mb-4">No project board yet.</h3>
                    <form method="POST" action="<?= u('/admin/engagements/' . ($engagement['id']) . '/board') ?>" class="flex gap-2 justify-center">
                        <?= csrf_field() ?>
                        <input type="text" name="name" placeholder="Board name" value="<?= h($engagement['title']) ?> — Project Board"
                               class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-72 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <button type="submit" class="bg-brand-500 hover:bg-brand-600 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">Create Board</button>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- Kanban board -->
            <div class="flex gap-5 min-w-max" id="kanban-board">
                <?php foreach ($columns as $column): ?>
                <?php
                $cc       = $columnColors[$column['color']] ?? $columnColors['slate'];
                $colCards = $cardsByColumn[$column['id']] ?? [];
                ?>
                <div class="w-72 flex-shrink-0" data-column-id="<?= $column['id'] ?>">
                    <!-- Column header -->
                    <div class="flex items-center justify-between mb-3 px-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-slate-700 <?= $cc['header'] ?>"><?= h($column['name']) ?></span>
                            <span class="text-xs bg-slate-200 text-slate-600 rounded-full w-5 h-5 flex items-center justify-center">
                                <?= count($colCards) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Cards -->
                    <div class="space-y-3 min-h-[100px]" id="col-<?= $column['id'] ?>" data-col="<?= $column['id'] ?>">
                        <?php foreach ($colCards as $card): ?>
                        <div class="bg-white border border-slate-200 rounded-xl p-4 hover:shadow-sm transition-shadow cursor-grab group"
                             id="card-<?= $card['id'] ?>"
                             data-card-id="<?= $card['id'] ?>"
                             x-data="{ open: false }">

                            <div @click="open = !open" class="flex items-start justify-between gap-2">
                                <h4 class="text-sm font-medium text-slate-800 leading-snug"><?= h($card['title']) ?></h4>
                                <?php if (!$card['is_original_scope']): ?>
                                <span class="flex-shrink-0 text-xs bg-amber-100 text-amber-600 px-1.5 py-0.5 rounded">+scope</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($card['due_at']): ?>
                            <div class="text-xs text-slate-400 mt-1">Due <?= date('M j', strtotime($card['due_at'])) ?></div>
                            <?php endif; ?>

                            <!-- Expanded card -->
                            <div x-show="open" x-cloak class="mt-4 pt-4 border-t border-slate-100 space-y-3">

                                <?php if ($card['description']): ?>
                                <p class="text-xs text-slate-600 leading-relaxed"><?= nl2br(h($card['description'])) ?></p>
                                <?php endif; ?>

                                <?php
                                $steps    = \Bizorca\Consulting\Models\Card::steps($card['id']);
                                $comments = \Bizorca\Consulting\Models\Card::comments($card['id']);
                                ?>

                                <?php if (!empty($steps)): ?>
                                <div>
                                    <div class="text-xs font-medium text-slate-400 mb-1"><?= count(array_filter($steps, fn($s) => $s['completed'])) ?>/<?= count($steps) ?> steps</div>
                                    <?php foreach ($steps as $step): ?>
                                    <div class="flex items-center gap-2 text-xs py-0.5 <?= $step['completed'] ? 'text-slate-400 line-through' : 'text-slate-700' ?>">
                                        <span class="w-3.5 h-3.5 rounded border <?= $step['completed'] ? 'bg-brand-500 border-brand-500' : 'border-slate-300' ?> flex-shrink-0"></span>
                                        <?= h($step['title']) ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($comments)): ?>
                                <div class="text-xs text-slate-400"><?= count($comments) ?> comment<?= count($comments) != 1 ? 's' : '' ?></div>
                                <?php endif; ?>

                                <!-- Move card -->
                                <form method="POST" action="<?= u('/admin/cards/' . ($card['id']) . '/move') ?>" class="flex gap-2">
                                    <?= csrf_field() ?>
                                    <select name="column_id" class="flex-1 border border-slate-200 rounded px-2 py-1 text-xs focus:outline-none">
                                        <?php foreach ($columns as $col): ?>
                                        <option value="<?= $col['id'] ?>" <?= $col['id'] == $card['column_id'] ? 'selected' : '' ?>><?= h($col['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white text-xs px-2 rounded transition-colors">Move</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($colCards)): ?>
                        <div class="text-xs text-slate-400 text-center py-4 border-2 border-dashed border-slate-200 rounded-xl">Empty</div>
                        <?php endif; ?>
                    </div>

                    <!-- Add card to column -->
                    <div class="mt-3" x-data="{ open: false }">
                        <button @click="open = !open" class="w-full text-left text-xs text-slate-400 hover:text-slate-600 px-1 flex items-center gap-1 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add card
                        </button>
                        <form method="POST" action="<?= u('/admin/cards') ?>" x-show="open" x-cloak class="mt-2 bg-white border border-slate-200 rounded-xl p-3 space-y-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="board_id" value="<?= $board['id'] ?>">
                            <input type="hidden" name="column_id" value="<?= $column['id'] ?>">
                            <input type="text" name="title" placeholder="Card title" required autofocus
                                   class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500">
                            <textarea name="description" rows="2" placeholder="Description (optional)"
                                      class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs resize-none focus:outline-none focus:ring-1 focus:ring-brand-500"></textarea>
                            <input type="date" name="due_at"
                                   class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500">
                            <div class="flex gap-2">
                                <button type="submit" class="flex-1 bg-brand-500 hover:bg-brand-600 text-white text-xs py-1.5 rounded transition-colors">Add</button>
                                <button type="button" @click="open = false" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs py-1.5 rounded transition-colors">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
