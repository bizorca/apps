<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';

$pageTitle  = 'About — placecard';
$pageDesc   = 'Why we built placecard, and how it works.';
$activePage = 'about';
?>
<?php include PC_ROOT . '/includes/head.php' ?>
<?php include PC_ROOT . '/includes/public-nav.php' ?>

<div class="pt-16">

  <!-- Header -->
  <section class="py-20 px-6 bg-pc-cream border-b border-pc-border">
    <div class="max-w-3xl mx-auto">
      <h1 class="text-4xl md:text-5xl font-bold text-pc-charcoal mb-4 leading-tight">The table is the point.</h1>
      <p class="text-xl text-pc-slate leading-relaxed">Most social apps try to replace the awkward part. We kept it.</p>
    </div>
  </section>

  <!-- Main content -->
  <section class="py-16 px-6 bg-white">
    <div class="max-w-3xl mx-auto prose-like">

      <div class="mb-12">
        <h2 class="text-2xl font-bold text-pc-charcoal mb-4">Why we built this</h2>
        <p class="text-pc-charcoal leading-relaxed mb-4 text-lg">
          Eating alone is fine. Eating with the same three friends every week is also fine. But there's a version of city life where you end up at a table with six people you've never met, somewhere between the appetizers and the second round of drinks, and you realize that this — whatever this is — was the missing thing.
        </p>
        <p class="text-pc-charcoal leading-relaxed mb-4">
          We built placecard because that version of city life isn't that hard to arrange. It just requires a little coordination and a willingness to sit down with strangers. Most restaurant reservations are already strangers eating near each other — we just made it intentional.
        </p>
        <p class="text-pc-charcoal leading-relaxed">
          No compatibility scoring. No shared-interest matching. No pre-dinner messaging thread. The restaurant does most of the social heavy lifting already — good food gives everyone something to talk about, and a structured shared experience levels the field.
        </p>
      </div>

      <div class="mb-12 bg-pc-warm rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-pc-charcoal mb-4">What actually happens</h2>
        <p class="text-pc-charcoal leading-relaxed mb-4">
          Someone hosts a dinner. They pick a restaurant from our curated list, set a date and time, choose how many seats to open up (usually 4–6 people, host included), and post it. That's all hosting requires.
        </p>
        <p class="text-pc-charcoal leading-relaxed mb-4">
          Other people see the dinner and RSVP. They don't know who else is coming until they show up — that's by design. We've found that advance knowledge of the guest list creates a kind of pre-screening instinct that makes the thing worse before it starts.
        </p>
        <p class="text-pc-charcoal leading-relaxed">
          Everyone shows up. Everyone eats. Some people leave with a new contact in their phone. Some leave with a dinner companion for the next six months. Most leave having had a better Tuesday than they would have otherwise.
        </p>
      </div>

      <div class="mb-12">
        <h2 class="text-2xl font-bold text-pc-charcoal mb-4">The restaurants</h2>
        <p class="text-pc-charcoal leading-relaxed mb-4">
          We work with restaurants directly. Not every restaurant is a good social dining venue — some are too loud, some too intimate, some just don't have the right energy for a table of strangers finding their footing. We pick carefully.
        </p>
        <p class="text-pc-charcoal leading-relaxed">
          In Chiang Mai, that means places like Huen Phen in the Old City, where the Northern Thai food is reason enough to show up, and Blackitch Artisan Kitchen in Nimman, which runs a chef's table concept that practically hosts itself. In Port Townsend, it means the kind of places where the owner knows the fishing boats by name.
        </p>
      </div>

      <div class="mb-12">
        <h2 class="text-2xl font-bold text-pc-charcoal mb-4">Privacy, briefly</h2>
        <p class="text-pc-charcoal leading-relaxed mb-4">
          Your last name is never shown publicly. Other diners see your first name and your bio — that's it. Before you RSVP to a dinner, you won't see who else is attending. After you RSVP, you'll see first names only.
        </p>
        <p class="text-pc-charcoal leading-relaxed">
          You can choose whether to show your exact age or just a range. You can choose whether your last name ever appears. Both of these default to private.
        </p>
      </div>

    </div>
  </section>

  <!-- Cities detail -->
  <section class="py-16 px-6 bg-pc-cream">
    <div class="max-w-5xl mx-auto">
      <h2 class="text-3xl font-bold text-pc-charcoal mb-2 text-center">Where we are</h2>
      <p class="text-pc-slate text-center mb-12">Two cities for now. We'd rather do two things well than ten things badly.</p>
      <div class="grid md:grid-cols-2 gap-8">
        <div class="bg-white rounded-2xl overflow-hidden shadow-card border border-pc-border">
          <div class="h-3 bg-pc-terracotta"></div>
          <div class="p-8">
            <div class="text-5xl mb-3">🇹🇭</div>
            <h3 class="text-2xl font-bold text-pc-charcoal mb-1">Chiang Mai, Thailand</h3>
            <p class="text-pc-slate text-sm mb-4">Northern Thailand's cultural capital</p>
            <p class="text-pc-charcoal leading-relaxed text-sm">A city serious enough about food that it has its own regional cuisine — Lanna cooking, distinct from Bangkok, built around khao soi and nam prik and slow-braised pork that's been going since 6am. It's also, quietly, one of the best dining cities in Southeast Asia. We've picked five restaurants worth sitting at with a stranger.</p>
          </div>
        </div>
        <div class="bg-white rounded-2xl overflow-hidden shadow-card border border-pc-border">
          <div class="h-3 bg-pc-sage"></div>
          <div class="p-8">
            <div class="text-5xl mb-3">🇺🇸</div>
            <h3 class="text-2xl font-bold text-pc-charcoal mb-1">Port Townsend, WA</h3>
            <p class="text-pc-slate text-sm mb-4">Victorian seaport, Pacific Northwest</p>
            <p class="text-pc-charcoal leading-relaxed text-sm">Small enough that the restaurant owner knows your name by the second visit. Big enough that the food scene keeps surprising you — Puget Sound oysters, Pacific salmon, the kind of wine list put together by someone who actually cares. The town has more character per block than cities twenty times its size.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="py-20 px-6 bg-white">
    <div class="max-w-xl mx-auto text-center">
      <h2 class="text-2xl font-bold text-pc-charcoal mb-4">Sounds good. What now?</h2>
      <p class="text-pc-slate mb-8">Join and browse open dinners in your city. Or host your own — it takes about three minutes.</p>
      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <a href="<?= PC_BASE ?>/register.php" class="btn-primary px-7 py-3 rounded-full font-semibold transition-colors inline-block">Join placecard</a>
        <a href="<?= PC_BASE ?>/features.php" class="btn-ghost px-7 py-3 rounded-full font-semibold transition-colors inline-block">See all features</a>
      </div>
    </div>
  </section>

</div>

<!-- Footer -->
<footer class="bg-white border-t border-pc-border py-10 px-6">
  <div class="max-w-5xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
    <span class="text-pc-charcoal font-semibold">placecard</span>
    <div class="flex gap-6 text-sm text-pc-slate">
      <a href="<?= PC_BASE ?>/about.php" class="hover:text-pc-charcoal transition-colors font-medium text-pc-charcoal">About</a>
      <a href="<?= PC_BASE ?>/features.php" class="hover:text-pc-charcoal transition-colors">How it works</a>
      <a href="<?= PC_BASE ?>/login.php" class="hover:text-pc-charcoal transition-colors">Sign in</a>
    </div>
    <span class="text-pc-slate text-sm">© <?= date('Y') ?> placecard</span>
  </div>
</footer>

</body>
</html>
