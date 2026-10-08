<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';

if (isLoggedIn()) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$yearInfo = getCurrentYearInfo();
$animals = getAllAnimals();
$elements = getAllElements();

$pageTitle = 'Discover Your Chinese Zodiac Path';
$fullWidth = true;
require AS_ROOT . '/templates/header.php';
?>

<!-- Hero Section -->
<section class="bg-gradient-to-br from-brand-900 via-brand-800 to-brand-700 text-white py-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-brand-200 text-sm font-medium uppercase tracking-widest mb-4"><?= h($yearInfo['year']) ?> &middot; Year of the <?= h($yearInfo['animal']) ?> &middot; <?= h($yearInfo['element']) ?> <?= h($yearInfo['yin_yang']) ?></p>
        <h1 class="text-4xl sm:text-5xl font-bold mb-6 leading-tight">Discover Your<br>Chinese Zodiac Path</h1>
        <p class="text-xl text-brand-100 mb-10 max-w-2xl mx-auto">Unlock ancient wisdom rooted in Traditional Chinese Medicine. Enter your birth details for a personalized Ba Zi (Four Pillars) astrology profile.</p>

        <!-- Birth Year Form -->
        <form action="<?= url('/profile.php') ?>" method="POST" class="max-w-lg mx-auto bg-white/10 backdrop-blur rounded-2xl p-6 sm:p-8 border border-white/20">
            <?= csrfField() ?>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="col-span-2">
                    <label for="birth_year" class="block text-sm font-medium text-brand-100 mb-1 text-left">Birth Year *</label>
                    <input type="number" name="birth_year" id="birth_year" required min="1920" max="<?= date('Y') ?>" placeholder="e.g. 1990"
                           class="w-full rounded-lg bg-white/20 border-white/30 text-white placeholder-brand-200 px-4 py-3 border focus:ring-2 focus:ring-white/50 focus:border-transparent">
                </div>
                <div>
                    <label for="birth_month" class="block text-sm font-medium text-brand-100 mb-1 text-left">Month</label>
                    <select name="birth_month" id="birth_month" class="w-full rounded-lg bg-white/20 border-white/30 text-white px-4 py-3 border focus:ring-2 focus:ring-white/50">
                        <option value="">Optional</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>"><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="birth_day" class="block text-sm font-medium text-brand-100 mb-1 text-left">Day</label>
                    <select name="birth_day" id="birth_day" class="w-full rounded-lg bg-white/20 border-white/30 text-white px-4 py-3 border focus:ring-2 focus:ring-white/50">
                        <option value="">Optional</option>
                        <?php for ($d = 1; $d <= 31; $d++): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="mb-6">
                <label for="birth_hour" class="block text-sm font-medium text-brand-100 mb-1 text-left">Birth Hour</label>
                <select name="birth_hour" id="birth_hour" class="w-full rounded-lg bg-white/20 border-white/30 text-white px-4 py-3 border focus:ring-2 focus:ring-white/50">
                    <option value="">Optional</option>
                    <option value="0">11pm - 1am (Rat hour)</option>
                    <option value="2">1am - 3am (Ox hour)</option>
                    <option value="4">3am - 5am (Tiger hour)</option>
                    <option value="6">5am - 7am (Rabbit hour)</option>
                    <option value="8">7am - 9am (Dragon hour)</option>
                    <option value="10">9am - 11am (Snake hour)</option>
                    <option value="12">11am - 1pm (Horse hour)</option>
                    <option value="14">1pm - 3pm (Goat hour)</option>
                    <option value="16">3pm - 5pm (Monkey hour)</option>
                    <option value="18">5pm - 7pm (Rooster hour)</option>
                    <option value="20">7pm - 9pm (Dog hour)</option>
                    <option value="22">9pm - 11pm (Pig hour)</option>
                </select>
            </div>
            <button type="submit" class="w-full bg-white text-brand-800 py-3 rounded-lg font-bold text-lg hover:bg-brand-50 transition shadow-lg">
                Reveal Your Zodiac Profile
            </button>
            <p class="text-brand-200 text-xs mt-3">Free &middot; No account required &middot; More details = deeper reading</p>
        </form>
    </div>
</section>

<!-- The 12 Zodiac Animals -->
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-gray-900 text-center mb-3">The 12 Chinese Zodiac Animals</h2>
        <p class="text-gray-600 text-center mb-12 max-w-2xl mx-auto">Each animal sign carries unique energies that shape personality, health, and destiny through a 12-year cycle.</p>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-4">
            <?php foreach ($animals as $animal):
                $info = getAnimalInfo($animal);
                $emoji = getAnimalEmoji($animal);
            ?>
            <div class="text-center p-4 rounded-xl hover:bg-brand-50 transition group cursor-default">
                <div class="text-4xl mb-2"><?= $emoji ?></div>
                <div class="font-semibold text-gray-900 group-hover:text-brand-700"><?= h($animal) ?></div>
                <div class="text-xs text-gray-500"><?= h($info['chinese']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- The Five Elements -->
<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-gray-900 text-center mb-3">The Five Elements of TCM</h2>
        <p class="text-gray-600 text-center mb-12 max-w-2xl mx-auto">Traditional Chinese Medicine maps five elements to organ systems, emotions, and seasons &mdash; revealing your body's unique blueprint.</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-6">
            <?php foreach ($elements as $element):
                $tcm = getElementTCM($element);
                $css = getElementCSS($element);
            ?>
            <div class="<?= $css['bg'] ?> <?= $css['border'] ?> border rounded-xl p-6 text-center">
                <div class="text-2xl font-bold <?= $css['text'] ?> mb-1"><?= h($element) ?></div>
                <div class="text-sm <?= $css['accent'] ?> mb-3"><?= h($tcm['season']) ?></div>
                <div class="text-sm text-gray-600 space-y-1">
                    <p><span class="font-medium">Organs:</span> <?= h(implode(' / ', $tcm['organs'])) ?></p>
                    <p><span class="font-medium">Emotion:</span> <?= h($tcm['emotion_positive']) ?></p>
                    <p><span class="font-medium">Taste:</span> <?= h($tcm['taste']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Star Seed Quiz Promo -->
<section class="py-16 bg-white border-t border-gray-100">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-br from-violet-50 via-rose-50 to-amber-50 border border-violet-200 rounded-2xl p-8 sm:p-12">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-widest text-violet-600 mb-3">New &mdash; Star Seed Lineage Quiz</p>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">Where does your soul come from?</h2>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Beyond the birth chart, there's a framework that asks a different kind of question entirely. Star seed lineages &mdash; Pleiadian, Sirian, Arcturian, Andromedan, and others &mdash; describe soul-level origin points: the cosmic environments that shaped your deepest instincts, gifts, and recurring life themes before this lifetime.
                </p>
                <p class="text-gray-600 leading-relaxed mb-6">
                    Take our 12-question quiz to identify your primary and secondary lineages from 10 traditions. You'll get a full resonance profile showing how strongly each one registers, plus an AI-generated reading specific to your combination.
                </p>
                <a href="<?= url('/starseed-quiz.php') ?>" class="inline-block bg-violet-700 text-white px-7 py-3 rounded-lg font-semibold hover:bg-violet-800 transition">
                    Take the Quiz &rarr;
                </a>
                <p class="text-xs text-gray-400 mt-3">Free &middot; about 2 minutes &middot; no account needed to start</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="py-16 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-gray-900 mb-12">How It Works</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <div class="w-12 h-12 bg-brand-100 text-brand-700 rounded-full flex items-center justify-center mx-auto mb-4 text-xl font-bold">1</div>
                <h3 class="font-semibold text-gray-900 mb-2">Enter Your Birth Year</h3>
                <p class="text-gray-600 text-sm">Share your birth details to calculate your zodiac animal, element, and yin/yang energy.</p>
            </div>
            <div>
                <div class="w-12 h-12 bg-brand-100 text-brand-700 rounded-full flex items-center justify-center mx-auto mb-4 text-xl font-bold">2</div>
                <h3 class="font-semibold text-gray-900 mb-2">Get Your Profile</h3>
                <p class="text-gray-600 text-sm">Receive an enhanced reading connecting your zodiac to TCM health, personality, and life path.</p>
            </div>
            <div>
                <div class="w-12 h-12 bg-brand-100 text-brand-700 rounded-full flex items-center justify-center mx-auto mb-4 text-xl font-bold">3</div>
                <h3 class="font-semibold text-gray-900 mb-2">Get Your Full Reading</h3>
                <p class="text-gray-600 text-sm">Create a free account for weekly forecasts, all 5 guided meditations, TCM health profile, and more &mdash; everything included, no subscription needed.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-16 bg-brand-600">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">Ready to Discover Your Path?</h2>
        <p class="text-brand-100 mb-8">Get your free zodiac profile in seconds. No account required to start.</p>
        <a href="#" onclick="document.getElementById('birth_year').focus(); window.scrollTo({top: 0, behavior: 'smooth'}); return false;"
           class="inline-block bg-white text-brand-700 px-8 py-3 rounded-lg font-bold text-lg hover:bg-brand-50 transition shadow-lg">
            Get Started Free
        </a>
    </div>
</section>

<?php require AS_ROOT . '/templates/footer.php'; ?>
