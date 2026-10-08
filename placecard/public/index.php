<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';

if (isLoggedIn()) {
    header('Location: ' . PC_BASE . '/dashboard.php');
    exit;
}

$pageTitle = 'placecard — Social dining for strangers';
$pageDesc  = 'Group dinners at partner restaurants. No algorithm, no swiping, no agenda. Just show up and eat.';
?>
<?php include PC_ROOT . '/includes/head.php' ?>

<?php include PC_ROOT . '/includes/public-nav.php' ?>

<!-- Hero -->
<section class="min-h-screen flex items-center justify-center pt-16 px-6" style="background: linear-gradient(160deg, #FAF7F2 0%, #F2EAE0 100%);">
  <div class="max-w-4xl mx-auto text-center py-24">
    <div class="inline-flex items-center gap-2 bg-white border border-pc-border rounded-full px-4 py-1.5 text-sm text-pc-slate mb-8 shadow-card">
      <span>🍽️</span> Now in Chiang Mai &amp; Port Townsend
    </div>
    <h1 class="text-5xl md:text-7xl font-bold text-pc-charcoal leading-tight tracking-tight mb-6">
      Good food.<br>
      Interesting people.<br>
      <span class="text-pc-terracotta">No agenda.</span>
    </h1>
    <p class="text-xl text-pc-slate max-w-xl mx-auto mb-10 leading-relaxed">
      Group dinners at real restaurants with strangers who become, occasionally, friends. No algorithm. No matching quiz. No app to scroll. Just show up and eat.
    </p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
      <a href="<?= PC_BASE ?>/register.php" class="btn-primary text-base px-8 py-3.5 rounded-full font-semibold transition-colors inline-block shadow-sm">
        Find your table
      </a>
      <a href="<?= PC_BASE ?>/about.php" class="btn-ghost text-base px-8 py-3.5 rounded-full font-semibold transition-colors inline-block">
        How it works
      </a>
    </div>
    <p class="text-pc-slate text-sm mt-6">Free to join. No subscription. Restaurants cover the vibe.</p>
  </div>
</section>

<!-- How it works -->
<section class="py-24 px-6 bg-white">
  <div class="max-w-5xl mx-auto">
    <div class="text-center mb-16">
      <h2 class="text-3xl md:text-4xl font-bold text-pc-charcoal mb-4">Three steps to a better Tuesday</h2>
      <p class="text-pc-slate text-lg">We kept the math simple on purpose.</p>
    </div>
    <div class="grid md:grid-cols-3 gap-8">
      <div class="text-center px-6">
        <div class="w-16 h-16 rounded-2xl bg-pc-warm flex items-center justify-center mx-auto mb-5 text-3xl">🗓️</div>
        <h3 class="text-xl font-semibold text-pc-charcoal mb-3">Browse open dinners</h3>
        <p class="text-pc-slate leading-relaxed">See what's on in your city. Filter by date, restaurant, size. When something looks right, claim your seat.</p>
      </div>
      <div class="text-center px-6">
        <div class="w-16 h-16 rounded-2xl bg-pc-warm flex items-center justify-center mx-auto mb-5 text-3xl">✅</div>
        <h3 class="text-xl font-semibold text-pc-charcoal mb-3">RSVP. That's it.</h3>
        <p class="text-pc-slate leading-relaxed">No pre-dinner Slack thread, no coordinating a group, no cancellations at 5pm. RSVP once and show up.</p>
      </div>
      <div class="text-center px-6">
        <div class="w-16 h-16 rounded-2xl bg-pc-warm flex items-center justify-center mx-auto mb-5 text-3xl">🤝</div>
        <h3 class="text-xl font-semibold text-pc-charcoal mb-3">Meet people who eat</h3>
        <p class="text-pc-slate leading-relaxed">The table does the work. You bring an appetite and a story or two. Shared meals have been breaking ice for ten thousand years — we're just making the reservation easier.</p>
      </div>
    </div>
  </div>
</section>

<!-- Cities -->
<section class="py-24 px-6 bg-pc-cream">
  <div class="max-w-5xl mx-auto">
    <div class="text-center mb-16">
      <h2 class="text-3xl md:text-4xl font-bold text-pc-charcoal mb-4">Two cities. More coming.</h2>
      <p class="text-pc-slate text-lg">We started small on purpose. Quality over volume.</p>
    </div>
    <div class="grid md:grid-cols-2 gap-6">
      <div class="bg-white rounded-2xl p-8 shadow-card border border-pc-border">
        <div class="text-4xl mb-4">🇹🇭</div>
        <h3 class="text-2xl font-bold text-pc-charcoal mb-2">Chiang Mai</h3>
        <p class="text-pc-slate text-sm mb-1">Thailand</p>
        <p class="text-pc-charcoal mt-4 leading-relaxed">Northern Thai culture meets world-class food. From Lanna-era khao soi to a chef's table in Nimman, the restaurants here can carry a conversation on their own. We've picked five worth sitting at with a stranger.</p>
        <div class="mt-6 flex flex-wrap gap-2">
          <span class="bg-pc-warm text-pc-terracotta text-xs px-3 py-1 rounded-full font-medium">Huen Phen</span>
          <span class="bg-pc-warm text-pc-terracotta text-xs px-3 py-1 rounded-full font-medium">The House by Ginger</span>
          <span class="bg-pc-warm text-pc-terracotta text-xs px-3 py-1 rounded-full font-medium">Blackitch Artisan</span>
          <span class="bg-pc-warm text-pc-terracotta text-xs px-3 py-1 rounded-full font-medium">+ 2 more</span>
        </div>
      </div>
      <div class="bg-white rounded-2xl p-8 shadow-card border border-pc-border">
        <div class="text-4xl mb-4">🇺🇸</div>
        <h3 class="text-2xl font-bold text-pc-charcoal mb-2">Port Townsend</h3>
        <p class="text-pc-slate text-sm mb-1">Washington, USA</p>
        <p class="text-pc-charcoal mt-4 leading-relaxed">Victorian seaport with a serious local food scene. Puget Sound seafood, Pacific Northwest produce, and the kind of town where the restaurant owner sits at the bar. Small enough to feel like yours. Big enough to keep surprising you.</p>
        <div class="mt-6 flex flex-wrap gap-2">
          <span class="bg-pc-warm text-pc-terracotta text-xs px-3 py-1 rounded-full font-medium">Fountain Cafe</span>
          <span class="bg-pc-warm text-pc-terracotta text-xs px-3 py-1 rounded-full font-medium">Finistère</span>
          <span class="bg-pc-warm text-pc-terracotta text-xs px-3 py-1 rounded-full font-medium">Silverwater Cafe</span>
          <span class="bg-pc-warm text-pc-terracotta text-xs px-3 py-1 rounded-full font-medium">+ 2 more</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Host CTA -->
<section class="py-24 px-6 bg-white">
  <div class="max-w-3xl mx-auto text-center">
    <div class="w-16 h-16 rounded-2xl bg-pc-warm flex items-center justify-center mx-auto mb-6 text-3xl">👋</div>
    <h2 class="text-3xl md:text-4xl font-bold text-pc-charcoal mb-4">Host a dinner</h2>
    <p class="text-pc-slate text-lg leading-relaxed mb-8">Pick a restaurant. Set a time. Open it up. Somebody will show up. They usually do. No experience required — just a curiosity about who else is out there eating.</p>
    <a href="<?= PC_BASE ?>/register.php" class="btn-primary text-base px-8 py-3.5 rounded-full font-semibold transition-colors inline-block">
      Start hosting
    </a>
  </div>
</section>

<!-- Privacy / trust -->
<section class="py-20 px-6 bg-pc-warm">
  <div class="max-w-4xl mx-auto grid md:grid-cols-3 gap-8">
    <div class="flex gap-4">
      <span class="text-pc-sage text-2xl flex-shrink-0 mt-1">🔒</span>
      <div>
        <h4 class="font-semibold text-pc-charcoal mb-1">Private until you RSVP</h4>
        <p class="text-pc-slate text-sm leading-relaxed">Attendee identities are kept private until the dinner. First names only at the table.</p>
      </div>
    </div>
    <div class="flex gap-4">
      <span class="text-pc-sage text-2xl flex-shrink-0 mt-1">🤷</span>
      <div>
        <h4 class="font-semibold text-pc-charcoal mb-1">No algorithm</h4>
        <p class="text-pc-slate text-sm leading-relaxed">We don't sort by compatibility score or shared interests. Serendipity is the feature.</p>
      </div>
    </div>
    <div class="flex gap-4">
      <span class="text-pc-sage text-2xl flex-shrink-0 mt-1">🚫</span>
      <div>
        <h4 class="font-semibold text-pc-charcoal mb-1">No subscription</h4>
        <p class="text-pc-slate text-sm leading-relaxed">placecard is free to join. We work with restaurants, not subscriptions.</p>
      </div>
    </div>
  </div>
</section>

<!-- Final CTA -->
<section class="py-24 px-6" style="background: linear-gradient(160deg, #1C1C1E 0%, #2d2d2f 100%);">
  <div class="max-w-2xl mx-auto text-center">
    <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Ready to sit down?</h2>
    <p class="text-gray-400 text-lg mb-8">Find a dinner near you, or host one of your own.</p>
    <a href="<?= PC_BASE ?>/register.php" class="btn-primary text-base px-8 py-3.5 rounded-full font-semibold transition-colors inline-block">
      Join placecard
    </a>
  </div>
</section>

<!-- Footer -->
<footer class="bg-white border-t border-pc-border py-10 px-6">
  <div class="max-w-5xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
    <span class="text-pc-charcoal font-semibold">placecard</span>
    <div class="flex gap-6 text-sm text-pc-slate">
      <a href="<?= PC_BASE ?>/about.php" class="hover:text-pc-charcoal transition-colors">About</a>
      <a href="<?= PC_BASE ?>/features.php" class="hover:text-pc-charcoal transition-colors">How it works</a>
      <a href="<?= PC_BASE ?>/login.php" class="hover:text-pc-charcoal transition-colors">Sign in</a>
    </div>
    <span class="text-pc-slate text-sm">© <?= date('Y') ?> placecard</span>
  </div>
</footer>

</body>
</html>
