<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once PC_ROOT . '/includes/session.php';

requireAuth();

// If profile is already set up, skip to dashboard
$user = currentUser();
if (!empty($user['bio'])) {
    header('Location: ' . PC_BASE . '/dashboard.php');
    exit;
}

$pageTitle = 'Welcome to placecard';
?>
<?php include PC_ROOT . '/includes/head.php' ?>

<style>
  .slide { display: none; }
  .slide.active { display: flex; }
  .dot { transition: width 0.3s ease; }
</style>

<div class="min-h-screen flex flex-col" style="background: linear-gradient(160deg, #FAF7F2 0%, #F2EAE0 100%);">

  <!-- Progress dots -->
  <div class="flex justify-center gap-2 pt-14 pb-2">
    <div id="dot-0" class="dot h-2 rounded-full bg-pc-terracotta w-6"></div>
    <div id="dot-1" class="dot h-2 rounded-full bg-pc-border w-2"></div>
    <div id="dot-2" class="dot h-2 rounded-full bg-pc-border w-2"></div>
  </div>

  <!-- Slides -->
  <div class="flex-1 flex flex-col" id="slider">

    <!-- Slide 1 -->
    <div class="slide active flex-col items-center justify-center text-center px-8 py-16 flex-1" id="slide-0">
      <div class="w-20 h-20 rounded-3xl bg-pc-warm flex items-center justify-center mx-auto mb-8 shadow-card">
        <span class="text-4xl">🍴</span>
      </div>
      <h1 class="text-4xl font-bold text-pc-charcoal mb-4 leading-tight">Good food.<br>Interesting people.<br>No agenda.</h1>
      <p class="text-pc-slate text-lg leading-relaxed max-w-sm mx-auto">placecard connects you with a table of strangers at real restaurants. No algorithm, no swiping, no pre-dinner messaging thread.</p>
    </div>

    <!-- Slide 2 -->
    <div class="slide flex-col items-center justify-center text-center px-8 py-16 flex-1" id="slide-1">
      <div class="w-20 h-20 rounded-3xl bg-pc-warm flex items-center justify-center mx-auto mb-8 shadow-card">
        <span class="text-4xl">🗺️</span>
      </div>
      <h1 class="text-4xl font-bold text-pc-charcoal mb-4 leading-tight">Find your table<br>in the city.</h1>
      <p class="text-pc-slate text-lg leading-relaxed max-w-sm mx-auto">Browse open dinners in your city. Filter by date, restaurant, or how many spots are left. When something looks right — grab a seat.</p>
    </div>

    <!-- Slide 3 -->
    <div class="slide flex-col items-center justify-center text-center px-8 py-16 flex-1" id="slide-2">
      <div class="w-20 h-20 rounded-3xl bg-pc-warm flex items-center justify-center mx-auto mb-8 shadow-card">
        <span class="text-4xl">👥</span>
      </div>
      <h1 class="text-4xl font-bold text-pc-charcoal mb-4 leading-tight">Show up<br>and eat.</h1>
      <p class="text-pc-slate text-lg leading-relaxed max-w-sm mx-auto">No swiping, no matching quiz, no app to scroll. RSVP once. Show up at the restaurant. The table takes it from there.</p>
    </div>

  </div>

  <!-- Navigation -->
  <div class="px-8 pb-12 flex flex-col items-center gap-4 max-w-sm mx-auto w-full">
    <button id="nextBtn" onclick="nextSlide()" class="w-full btn-primary py-4 rounded-2xl font-semibold text-base transition-colors">
      Next
    </button>
    <button id="skipBtn" onclick="goToSetup()" class="text-pc-slate text-sm hover:text-pc-charcoal transition-colors">
      Skip
    </button>
  </div>

</div>

<script>
let current = 0;
const total = 3;

function updateDots() {
  for (let i = 0; i < total; i++) {
    const dot = document.getElementById('dot-' + i);
    if (i === current) {
      dot.className = 'dot h-2 rounded-full bg-pc-terracotta w-6';
    } else {
      dot.className = 'dot h-2 rounded-full bg-pc-border w-2';
    }
  }
}

function nextSlide() {
  document.getElementById('slide-' + current).classList.remove('active');
  current++;
  if (current >= total) {
    goToSetup();
    return;
  }
  document.getElementById('slide-' + current).classList.add('active');
  updateDots();

  const nextBtn = document.getElementById('nextBtn');
  const skipBtn = document.getElementById('skipBtn');
  if (current === total - 1) {
    nextBtn.textContent = 'Get started';
    skipBtn.style.display = 'none';
  }
}

function goToSetup() {
  window.location.href = '<?= PC_BASE ?>/setup.php';
}
</script>

</body>
</html>
