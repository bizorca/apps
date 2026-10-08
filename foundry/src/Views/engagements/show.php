<?php
$title = h($engagement['title']) . ' — Bizorca Consulting';
ob_start();

$columnColors = [
    'violet'  => 'bg-violet-100 text-violet-700 border-violet-200',
    'blue'    => 'bg-blue-100 text-blue-700 border-blue-200',
    'amber'   => 'bg-amber-100 text-amber-700 border-amber-200',
    'orange'  => 'bg-orange-100 text-orange-700 border-orange-200',
    'emerald' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
    'slate'   => 'bg-slate-100 text-slate-700 border-slate-200',
];
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4 sticky top-0 z-40">
    <div class="max-w-full px-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <a href="<?= u('/dashboard') ?>" class="text-slate-500 hover:text-white transition-colors flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="text-white font-semibold text-sm truncate"><?= h($engagement['title']) ?></span>
            <?php
            $statusColors = [
                'onboarding' => 'bg-violet-500/20 text-violet-300',
                'active'     => 'bg-emerald-500/20 text-emerald-300',
                'paused'     => 'bg-amber-500/20 text-amber-300',
                'complete'   => 'bg-slate-500/20 text-slate-400',
            ];
            $sc = $statusColors[$engagement['status']] ?? 'bg-slate-500/20 text-slate-400';
            ?>
            <span class="flex-shrink-0 text-xs px-2 py-0.5 rounded-full <?= $sc ?>"><?= ucfirst($engagement['status']) ?></span>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <?php if ($scopeStats['added'] > 0): ?>
            <span class="text-xs bg-amber-500/20 text-amber-300 px-2 py-1 rounded-full">
                <?= $scopeStats['added'] ?> out-of-scope card<?= $scopeStats['added'] !== 1 ? 's' : '' ?>
            </span>
            <?php endif; ?>
            <a href="<?= u('/engagements/' . ($engagement['id']) . '/changes') ?>" class="text-xs text-slate-400 hover:text-white transition-colors">Change Requests</a>
            <form method="POST" action="<?= u('/logout') ?>" class="inline">
                <?= csrf_field() ?>
                <button class="text-slate-500 hover:text-white text-xs transition-colors">Sign out</button>
            </form>
        </div>
    </div>
</nav>

<div class="bg-slate-900 min-h-screen">

    <!-- Scope not yet accepted banner -->
    <?php if ($engagement['scope_locked_at'] && !$engagement['client_accepted_scope_at']): ?>
    <div class="bg-amber-500 px-6 py-3 flex items-center justify-between gap-4">
        <p class="text-sm text-white font-medium">
            Your scope document is ready for review. Please accept it to activate your project board.
        </p>
        <a href="<?= u('/engagements/' . ($engagement['id']) . '/scope') ?>" class="bg-white text-amber-700 font-semibold text-sm px-4 py-1.5 rounded-lg flex-shrink-0 hover:bg-amber-50 transition-colors">
            Review Scope
        </a>
    </div>
    <?php endif; ?>

    <?php if ($board && $engagement['client_accepted_scope_at']): ?>

    <!-- Kanban Board -->
    <div class="p-6 overflow-x-auto" x-data="kanban()" x-init="init()">
        <div class="flex gap-5 min-w-max">
            <?php foreach ($columns as $column): ?>
            <?php
                $colorClass = $columnColors[$column['color']] ?? $columnColors['slate'];
                $colCards   = $cardsByColumn[$column['id']] ?? [];
            ?>
            <div class="w-72 flex-shrink-0">
                <!-- Column header -->
                <div class="flex items-center justify-between mb-3 px-1">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-slate-300"><?= h($column['name']) ?></span>
                        <span class="text-xs bg-slate-800 text-slate-500 rounded-full w-5 h-5 flex items-center justify-center">
                            <?= count($colCards) ?>
                        </span>
                    </div>
                </div>

                <!-- Cards -->
                <div class="space-y-3 min-h-[120px]"
                     id="column-<?= $column['id'] ?>">
                    <?php foreach ($colCards as $card): ?>
                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 hover:border-slate-500 transition-colors cursor-pointer group"
                         id="card-<?= $card['id'] ?>"
                         x-data="{ open: false }">

                        <!-- Card header -->
                        <div @click="open = !open" class="flex items-start justify-between gap-2">
                            <h4 class="text-sm font-medium text-slate-200 leading-snug"><?= h($card['title']) ?></h4>
                            <?php if (!$card['is_original_scope']): ?>
                            <span class="flex-shrink-0 text-xs bg-amber-500/20 text-amber-400 px-1.5 py-0.5 rounded">+scope</span>
                            <?php endif; ?>
                        </div>

                        <!-- Due date -->
                        <?php if ($card['due_at']): ?>
                        <div class="mt-2 text-xs text-slate-500">
                            Due <?= date('M j', strtotime($card['due_at'])) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Card body (expanded) -->
                        <div x-show="open" x-cloak class="mt-4 border-t border-slate-700 pt-4 space-y-4">

                            <?php if ($card['description']): ?>
                            <p class="text-xs text-slate-400 leading-relaxed"><?= nl2br(h($card['description'])) ?></p>
                            <?php endif; ?>

                            <?php
                            $steps    = \Bizorca\Consulting\Models\Card::steps($card['id']);
                            $comments = \Bizorca\Consulting\Models\Card::comments($card['id']);
                            ?>

                            <!-- Checklist -->
                            <?php if (!empty($steps)): ?>
                            <div>
                                <h5 class="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">
                                    Checklist &mdash; <?= count(array_filter($steps, fn($s) => $s['completed'])) ?>/<?= count($steps) ?>
                                </h5>
                                <?php
                                $completedSteps = count(array_filter($steps, fn($s) => $s['completed']));
                                $pct = count($steps) > 0 ? round($completedSteps / count($steps) * 100) : 0;
                                ?>
                                <div class="h-1 bg-slate-700 rounded-full mb-3">
                                    <div class="h-1 bg-brand-500 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                                </div>
                                <div class="space-y-2">
                                    <?php foreach ($steps as $step): ?>
                                    <form method="POST" action="<?= u('/cards/' . ($card['id']) . '/step/' . ($step['id'])) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="flex items-center gap-2 w-full text-left hover:bg-slate-700 rounded px-1 py-0.5 transition-colors">
                                            <span class="w-4 h-4 flex-shrink-0 rounded border <?= $step['completed'] ? 'bg-brand-500 border-brand-500' : 'border-slate-600' ?> flex items-center justify-center">
                                                <?php if ($step['completed']): ?>
                                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                <?php endif; ?>
                                            </span>
                                            <span class="text-xs text-slate-300 <?= $step['completed'] ? 'line-through text-slate-500' : '' ?>"><?= h($step['title']) ?></span>
                                        </button>
                                    </form>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Comments -->
                            <?php if (!empty($comments)): ?>
                            <div>
                                <h5 class="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Comments</h5>
                                <div class="space-y-3">
                                    <?php foreach ($comments as $comment): ?>
                                    <div class="text-xs">
                                        <span class="font-medium text-slate-300"><?= h($comment['first_name'] . ' ' . $comment['last_name']) ?></span>
                                        <span class="text-slate-600 ml-1"><?= time_ago($comment['created_at']) ?></span>
                                        <p class="text-slate-400 mt-1 leading-relaxed"><?= nl2br(h($comment['body'])) ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Add comment -->
                            <form method="POST" action="<?= u('/cards/' . ($card['id']) . '/comment') ?>" class="flex gap-2">
                                <?= csrf_field() ?>
                                <input type="text" name="body" placeholder="Add a comment..."
                                       class="flex-1 bg-slate-700 border border-slate-600 rounded-lg px-3 py-1.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                <button type="submit" class="bg-brand-500 hover:bg-brand-600 text-white text-xs px-3 py-1.5 rounded-lg transition-colors flex-shrink-0">Post</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($colCards)): ?>
                    <div class="text-xs text-slate-700 text-center py-6 border-2 border-dashed border-slate-800 rounded-xl">
                        No cards
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    function kanban() {
        return {
            init() {
                // Future: add drag-drop reorder (admin only)
            }
        }
    }
    </script>

    <?php else: ?>

    <!-- Board not yet created or scope not accepted -->
    <div class="flex items-center justify-center min-h-[60vh]">
        <div class="text-center max-w-sm">
            <div class="w-16 h-16 bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-300 mb-2">Project board coming soon.</h3>
            <p class="text-slate-500 text-sm">
                <?php if ($engagement['status'] === 'onboarding'): ?>
                Your project board will be set up once onboarding is complete and the scope is accepted.
                <?php else: ?>
                Your project board is being configured.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
