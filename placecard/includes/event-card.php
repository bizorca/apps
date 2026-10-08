<?php
// Variables expected from parent:
// $event    — event array
// $isRsvpd  — bool
// $isHosted — bool
// $spotsLeft — int
// $isFull    — bool
// $dateStr   — formatted date
// $timeStr   — formatted time
// $token     — JWT
?>
<div class="bg-white rounded-2xl border border-pc-border shadow-card overflow-hidden">
  <a href="<?= PC_BASE ?>/event.php?id=<?= htmlspecialchars($event['id']) ?>" class="block p-5 hover:bg-pc-cream/50 transition-colors">
    <div class="flex justify-between items-start mb-3">
      <div class="flex-1 min-w-0">
        <h3 class="font-semibold text-pc-charcoal truncate"><?= htmlspecialchars($event['restaurant_name'] ?? '') ?></h3>
        <p class="text-pc-slate text-xs mt-0.5"><?= htmlspecialchars($event['restaurant_neighborhood'] ?? '') ?></p>
      </div>
      <div class="ml-3 flex-shrink-0">
        <?php if ($isRsvpd || $isHosted): ?>
          <span class="bg-pc-sage/15 text-pc-sage text-xs px-2.5 py-1 rounded-full font-medium flex items-center gap-1">
            ✓ <?= $isHosted ? 'Hosting' : "You're going" ?>
          </span>
        <?php elseif ($isFull): ?>
          <span class="bg-pc-border/60 text-pc-slate text-xs px-2.5 py-1 rounded-full font-medium">Full</span>
        <?php else: ?>
          <span class="bg-pc-warm text-pc-terracotta text-xs px-2.5 py-1 rounded-full font-medium">
            <?= $spotsLeft ?> spot<?= $spotsLeft !== 1 ? 's' : '' ?> left
          </span>
        <?php endif ?>
      </div>
    </div>

    <div class="border-t border-pc-border/50 pt-3 grid grid-cols-3 gap-2 text-xs text-pc-slate">
      <div class="flex items-center gap-1.5"><span>📅</span><?= $dateStr ?></div>
      <div class="flex items-center gap-1.5"><span>🕐</span><?= $timeStr ?></div>
      <div class="flex items-center gap-1.5"><span>👤</span><?= htmlspecialchars($event['host_first_name'] ?? '') ?></div>
    </div>

    <?php if (!empty($event['notes'])): ?>
      <p class="text-pc-slate text-xs mt-3 leading-relaxed line-clamp-2 italic">"<?= htmlspecialchars($event['notes']) ?>"</p>
    <?php endif ?>
  </a>
</div>
