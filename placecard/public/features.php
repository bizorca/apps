<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';

$pageTitle  = 'How it works — placecard';
$pageDesc   = 'Browse dinners, RSVP, meet strangers. Here\'s how placecard works.';
$activePage = 'features';
?>
<?php include PC_ROOT . '/includes/head.php' ?>
<?php include PC_ROOT . '/includes/public-nav.php' ?>

<div class="pt-16">

  <!-- Header -->
  <section class="py-20 px-6 bg-pc-cream border-b border-pc-border">
    <div class="max-w-3xl mx-auto text-center">
      <h1 class="text-4xl md:text-5xl font-bold text-pc-charcoal mb-4">How placecard works</h1>
      <p class="text-xl text-pc-slate">Everything you need to know before you show up.</p>
    </div>
  </section>

  <!-- For diners -->
  <section class="py-20 px-6 bg-white">
    <div class="max-w-5xl mx-auto">
      <div class="grid md:grid-cols-2 gap-12 items-center mb-20">
        <div>
          <span class="text-pc-terracotta text-sm font-semibold uppercase tracking-widest">For diners</span>
          <h2 class="text-3xl font-bold text-pc-charcoal mt-2 mb-4">Browse. RSVP. Show up.</h2>
          <p class="text-pc-charcoal leading-relaxed mb-4">Open dinners are listed by city, date, and restaurant. Each listing shows the restaurant, the date and time, how many spots are left, and a brief note from the host. That's it — no bios, no photos, no pre-game social networking.</p>
          <p class="text-pc-charcoal leading-relaxed">When something looks right, hit RSVP. Your spot is confirmed. Show up at the restaurant at the listed time and introduce yourself. The table takes it from there.</p>
        </div>
        <div class="bg-pc-warm rounded-2xl p-6 space-y-3">
          <div class="bg-white rounded-xl p-5 border border-pc-border shadow-card">
            <div class="flex justify-between items-start mb-3">
              <div>
                <h4 class="font-semibold text-pc-charcoal">Huen Phen</h4>
                <p class="text-pc-slate text-xs">Phra Sing, Old City</p>
              </div>
              <span class="bg-pc-warm text-pc-terracotta text-xs px-2.5 py-1 rounded-full font-medium">3 spots left</span>
            </div>
            <div class="text-pc-slate text-sm space-y-1">
              <div class="flex items-center gap-2"><span>📅</span> Friday, Mar 28</div>
              <div class="flex items-center gap-2"><span>🕖</span> 7:00 PM</div>
              <div class="flex items-center gap-2"><span>👤</span> Hosted by Maya</div>
            </div>
          </div>
          <div class="bg-white rounded-xl p-5 border border-pc-border shadow-card">
            <div class="flex justify-between items-start mb-3">
              <div>
                <h4 class="font-semibold text-pc-charcoal">Blackitch Artisan Kitchen</h4>
                <p class="text-pc-slate text-xs">Nimman</p>
              </div>
              <span class="bg-pc-warm text-pc-terracotta text-xs px-2.5 py-1 rounded-full font-medium">2 spots left</span>
            </div>
            <div class="text-pc-slate text-sm space-y-1">
              <div class="flex items-center gap-2"><span>📅</span> Saturday, Mar 29</div>
              <div class="flex items-center gap-2"><span>🕗</span> 7:30 PM</div>
              <div class="flex items-center gap-2"><span>👤</span> Hosted by James</div>
            </div>
          </div>
        </div>
      </div>

      <!-- For hosts -->
      <div class="grid md:grid-cols-2 gap-12 items-center">
        <div class="order-2 md:order-1 bg-pc-cream rounded-2xl p-6">
          <h4 class="text-sm font-semibold text-pc-slate uppercase tracking-widest mb-4">Create a dinner</h4>
          <div class="space-y-4">
            <div class="bg-white rounded-xl p-4 border border-pc-border">
              <p class="text-xs text-pc-slate mb-1">Restaurant</p>
              <p class="font-medium text-pc-charcoal">The House by Ginger</p>
              <p class="text-xs text-pc-slate">Old City · $$</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="bg-white rounded-xl p-4 border border-pc-border">
                <p class="text-xs text-pc-slate mb-1">Date</p>
                <p class="font-medium text-pc-charcoal text-sm">Apr 5, 2026</p>
              </div>
              <div class="bg-white rounded-xl p-4 border border-pc-border">
                <p class="text-xs text-pc-slate mb-1">Time</p>
                <p class="font-medium text-pc-charcoal text-sm">7:00 PM</p>
              </div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-pc-border">
              <p class="text-xs text-pc-slate mb-2">Party size</p>
              <div class="flex gap-2">
                <?php foreach ([4,5,6,8] as $n): ?>
                  <span class="w-9 h-9 rounded-lg flex items-center justify-center text-sm border <?= $n === 5 ? 'bg-pc-terracotta text-white border-pc-terracotta font-semibold' : 'border-pc-border text-pc-slate' ?>">
                    <?= $n ?>
                  </span>
                <?php endforeach ?>
              </div>
            </div>
            <button class="w-full btn-primary py-3 rounded-xl font-semibold text-sm transition-colors">Create dinner</button>
          </div>
        </div>
        <div class="order-1 md:order-2">
          <span class="text-pc-terracotta text-sm font-semibold uppercase tracking-widest">For hosts</span>
          <h2 class="text-3xl font-bold text-pc-charcoal mt-2 mb-4">Pick a restaurant. Set a time. Done.</h2>
          <p class="text-pc-charcoal leading-relaxed mb-4">Creating a dinner takes about two minutes. Choose a restaurant from our vetted list, pick a date and time, decide how many people you want at the table (you're always seat one), add an optional note, and post it.</p>
          <p class="text-pc-charcoal leading-relaxed">You don't need hosting experience. You don't need to plan conversation topics. Restaurants that are good for this kind of thing already have the atmosphere — you're just the one who made the reservation.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Feature grid -->
  <section class="py-20 px-6 bg-pc-cream">
    <div class="max-w-5xl mx-auto">
      <h2 class="text-3xl font-bold text-pc-charcoal text-center mb-12">Everything on the menu</h2>
      <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-5">
        <?php
        $features = [
          ['🌆', 'City-based browsing',       'Browse dinners filtered by city. No need to set a radius or configure location permissions.'],
          ['🔒', 'Privacy by default',         'Last names never shown publicly. Attendee identities stay private until you\'ve RSVPd.'],
          ['📅', 'Real-time availability',     'Spot counts update in real time. If a dinner fills while you\'re deciding, you\'ll know.'],
          ['✍️', 'Host notes',                 'Hosts can add a brief note — a heads-up about the vibe, a seating detail, whatever matters.'],
          ['🚫', 'No algorithm',               'We don\'t sort, score, or match. Dinners are listed chronologically. First come, first seated.'],
          ['👤', 'Simple profiles',            'Bio, interests, and dining preferences — enough to know you\'re eating with a person, not a bot.'],
          ['🍽️', 'Curated restaurants',       'Every restaurant on placecard is vetted for social dining. Not every restaurant works for this. We picked the ones that do.'],
          ['📱', 'Works on any device',        'No app required. The web app works on your phone, tablet, or desktop — wherever you happen to be when you realize Tuesday is free.'],
          ['🆓', 'Free to use',               'No subscription, no per-dinner fee. Join, RSVP, host. We handle the rest.'],
        ];
        foreach ($features as [$icon, $title, $desc]):
        ?>
        <div class="bg-white rounded-2xl p-6 border border-pc-border">
          <div class="text-2xl mb-3"><?= $icon ?></div>
          <h3 class="font-semibold text-pc-charcoal mb-2"><?= $title ?></h3>
          <p class="text-pc-slate text-sm leading-relaxed"><?= $desc ?></p>
        </div>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="py-20 px-6 bg-white">
    <div class="max-w-3xl mx-auto">
      <h2 class="text-3xl font-bold text-pc-charcoal mb-12 text-center">Questions people ask</h2>
      <div class="space-y-6">
        <?php
        $faqs = [
          ['Do I pay at the restaurant separately or as a group?', 'However you and your table work it out. We don\'t handle payment — the restaurant runs the check normally. Some tables split, some people pay for a round of drinks, some just handle their own. It\'s dinner, not a financial transaction.'],
          ['What if I RSVP and then can\'t make it?', 'Cancel your RSVP from your dashboard before the dinner. It frees up the spot for someone else. If you\'re the host and need to cancel the whole dinner, you can do that too — we\'d just ask you not to do it the day of.'],
          ['Can I RSVP without a complete profile?', 'No — we require a bio before you can RSVP or host. It\'s not onerous: a sentence or two about who you are. It\'s enough for the host to know there\'s a real person on the other end of that RSVP.'],
          ['Is placecard a dating app?', 'No. It\'s a dining app. People meet people at dinners — that\'s the point — but the structure is explicitly a group dinner, not a two-person setup. The group format changes the dynamic entirely.'],
          ['Who can host a dinner?', 'Anyone with a complete profile. You don\'t need to be a local, a foodie, or particularly gregarious. You just need to be willing to show up first and introduce yourself to whoever arrives.'],
        ];
        foreach ($faqs as [$q, $a]):
        ?>
        <div class="border-b border-pc-border pb-6">
          <h3 class="font-semibold text-pc-charcoal mb-2"><?= htmlspecialchars($q) ?></h3>
          <p class="text-pc-slate leading-relaxed"><?= htmlspecialchars($a) ?></p>
        </div>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="py-20 px-6 bg-pc-warm">
    <div class="max-w-xl mx-auto text-center">
      <h2 class="text-2xl font-bold text-pc-charcoal mb-4">Ready to try it?</h2>
      <p class="text-pc-slate mb-8">Setup takes three minutes. Dinners take as long as they need to.</p>
      <a href="<?= PC_BASE ?>/register.php" class="btn-primary px-8 py-3.5 rounded-full font-semibold transition-colors inline-block">Create an account</a>
    </div>
  </section>

</div>

<!-- Footer -->
<footer class="bg-white border-t border-pc-border py-10 px-6">
  <div class="max-w-5xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
    <span class="text-pc-charcoal font-semibold">placecard</span>
    <div class="flex gap-6 text-sm text-pc-slate">
      <a href="<?= PC_BASE ?>/about.php" class="hover:text-pc-charcoal transition-colors">About</a>
      <a href="<?= PC_BASE ?>/features.php" class="hover:text-pc-charcoal transition-colors font-medium text-pc-charcoal">How it works</a>
      <a href="<?= PC_BASE ?>/login.php" class="hover:text-pc-charcoal transition-colors">Sign in</a>
    </div>
    <span class="text-pc-slate text-sm">© <?= date('Y') ?> placecard</span>
  </div>
</footer>

</body>
</html>
