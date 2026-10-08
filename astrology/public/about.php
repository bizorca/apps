<?php
require __DIR__ . '/_bootstrap.php';

$pageTitle = 'About';
$fullWidth = true;
require AS_ROOT . '/templates/header.php';
?>

<!-- Hero -->
<div class="bg-gradient-to-br from-brand-600 to-brand-800 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-extrabold text-white tracking-tight">About</h1>
        <p class="mt-4 text-xl text-brand-100 max-w-2xl mx-auto">Traditional Chinese astrology meets modern insight.</p>
    </div>
</div>

<div class="bg-gray-50 py-16">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Mission -->
        <div class="bg-white rounded-xl p-8 border border-gray-200 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Our Mission</h2>
            <p class="text-gray-600 leading-relaxed mb-4">
                Chinese astrology is one of the oldest continuous astrological traditions in the world, with roots stretching back over 2,000 years. Unlike Western astrology's focus on planetary positions, the Chinese system is built on cycles of time, the five elements of nature, and the deep health wisdom of Traditional Chinese Medicine.
            </p>
            <p class="text-gray-600 leading-relaxed mb-4">
                We created this app to make that wisdom accessible. By combining traditional zodiac calculations with modern interpretation, we offer personalized readings that go beyond generic horoscopes &mdash; connecting your birth data to personality insights, health guidance, and weekly forecasts grounded in authentic principles.
            </p>
            <p class="text-gray-600 leading-relaxed">
                Whether you're new to Chinese astrology or have followed the zodiac for years, our goal is to provide genuine value: self-understanding, wellness awareness, and a connection to one of humanity's oldest frameworks for making sense of life's patterns.
            </p>
        </div>

        <!-- The System -->
        <div class="bg-white rounded-xl p-8 border border-gray-200 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">The Chinese Zodiac System</h2>

            <div class="space-y-6">
                <div>
                    <h3 class="font-semibold text-gray-900 mb-2">12 Zodiac Animals</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        The Chinese zodiac follows a 12-year cycle, with each year governed by a different animal: Rat, Ox, Tiger, Rabbit, Dragon, Snake, Horse, Goat, Monkey, Rooster, Dog, and Pig. Your birth year animal shapes your core personality traits, natural strengths, and life challenges.
                    </p>
                </div>

                <div>
                    <h3 class="font-semibold text-gray-900 mb-2">Five Elements (Wu Xing)</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        Layered on top of the animal cycle is the five-element system: Wood, Fire, Earth, Metal, and Water. Each element corresponds to specific organs, emotions, seasons, colors, and foods in Traditional Chinese Medicine. Your birth element adds depth and nuance to your zodiac animal profile.
                    </p>
                </div>

                <div>
                    <h3 class="font-semibold text-gray-900 mb-2">Yin &amp; Yang</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        Every year carries either yin (receptive, introspective) or yang (active, expressive) energy. This polarity further refines your profile and influences how your animal and element traits manifest in your life.
                    </p>
                </div>

                <div>
                    <h3 class="font-semibold text-gray-900 mb-2">Heavenly Stems &amp; Earthly Branches</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        The full Chinese calendar system uses 10 Heavenly Stems and 12 Earthly Branches in a 60-year cycle (the Sexagenary Cycle). This creates 60 unique year types, meaning two people born in the same animal year but 12 years apart will have different stem-branch combinations and element profiles.
                    </p>
                </div>
            </div>
        </div>

        <!-- TCM Connection -->
        <div class="bg-white rounded-xl p-8 border border-gray-200 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Traditional Chinese Medicine</h2>
            <p class="text-gray-600 leading-relaxed mb-4">
                Our readings integrate Traditional Chinese Medicine (TCM) principles. Each of the five elements maps to specific organ systems, emotional patterns, and wellness practices:
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 mt-4">
                <div class="bg-green-50 rounded-lg p-3 text-center">
                    <div class="font-bold text-green-800 text-sm">Wood</div>
                    <div class="text-xs text-green-600 mt-1">Liver &middot; Spring</div>
                </div>
                <div class="bg-red-50 rounded-lg p-3 text-center">
                    <div class="font-bold text-red-800 text-sm">Fire</div>
                    <div class="text-xs text-red-600 mt-1">Heart &middot; Summer</div>
                </div>
                <div class="bg-amber-50 rounded-lg p-3 text-center">
                    <div class="font-bold text-amber-800 text-sm">Earth</div>
                    <div class="text-xs text-amber-600 mt-1">Spleen &middot; Late Summer</div>
                </div>
                <div class="bg-gray-100 rounded-lg p-3 text-center">
                    <div class="font-bold text-gray-800 text-sm">Metal</div>
                    <div class="text-xs text-gray-600 mt-1">Lung &middot; Autumn</div>
                </div>
                <div class="bg-blue-50 rounded-lg p-3 text-center">
                    <div class="font-bold text-blue-800 text-sm">Water</div>
                    <div class="text-xs text-blue-600 mt-1">Kidney &middot; Winter</div>
                </div>
            </div>
            <p class="text-gray-600 leading-relaxed mt-4">
                Our guided meditations are specifically designed around these organ-element relationships, offering practices that support your body's natural balance according to TCM principles.
            </p>
        </div>

        <!-- How Readings Work -->
        <div class="bg-white rounded-xl p-8 border border-gray-200 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Modern Technology, Ancient Wisdom</h2>
            <p class="text-gray-600 leading-relaxed mb-4">
                Readings and forecasts are generated using Chinese zodiac principles and TCM wisdom. Each reading is personalized to your specific birth data &mdash; your zodiac animal, element, yin/yang polarity, and heavenly stem/earthly branch combination.
            </p>
            <p class="text-gray-600 leading-relaxed">
                Weekly forecasts incorporate the energy of each week's ruling animal and element, creating relevant, timely insights rather than generic predictions. Our meditations are hand-crafted by practitioners to ensure quality and authenticity.
            </p>
        </div>

        <!-- Jillian Ribbons -->
        <div class="bg-white rounded-xl border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gradient-to-r from-brand-50 to-amber-50 px-8 pt-8 pb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-1">Personal Consultations with Jillian Ribbons</h2>
                <p class="text-sm text-gray-500">East Asian Medicine Practitioner &amp; Osteopathic Manual Therapist</p>
            </div>
            <div class="px-8 pb-8 pt-4">
                <p class="text-gray-600 leading-relaxed mb-4">
                    For those who want to go deeper than digital readings, we partner with Jillian Ribbons &mdash; an East Asian Medical practitioner and osteopathic manual therapist based in Port Ludlow, Washington. Jillian offers remote sessions that bring the principles behind this app to life through one-on-one guidance.
                </p>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Jillian's approach is rooted in holistic assessment. Rather than treating isolated symptoms, she evaluates the whole person &mdash; body, mind, and energetic balance. As she describes it: &ldquo;If the body is a tree in a forest, then Western medicine is skilled at finding the smallest microbe on the tree. The holistic practitioner will look at the whole structure of leaves, branches, and roots.&rdquo;
                </p>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Her practice integrates multiple modalities including traditional acupuncture and pulse diagnosis, osteopathic evaluation (visceral, vascular, and cranial-sacral), Chinese herbal formulas, sound healing with tuning forks and singing bowls, neurological integration, frequency-specific micro-current therapy, and guided meditations drawing from ancestral and spiritual traditions.
                </p>
                <p class="text-gray-600 leading-relaxed mb-6">
                    Jillian specializes in addressing root causes &mdash; including childhood trauma, ancestral patterning, and energetic disturbances &mdash; rather than treating symptoms in isolation. Her remote sessions make this deep, personalized work accessible regardless of location.
                </p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="https://joypointacupuncture.fullslate.com/services/413" target="_blank" rel="noopener" class="bg-brand-600 text-white px-6 py-2.5 rounded-lg hover:bg-brand-700 font-medium text-sm text-center transition">Book a Remote Session</a>
                    <a href="https://www.sacredflowhealingarts.com/services" target="_blank" rel="noopener" class="border border-brand-300 text-brand-700 px-6 py-2.5 rounded-lg hover:bg-brand-50 font-medium text-sm text-center transition">Learn More About Jillian</a>
                </div>
            </div>
        </div>

        <!-- Star Seed Lineages -->
        <div class="bg-white rounded-xl p-8 border border-gray-200 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Star Seed Lineages</h2>
            <p class="text-gray-600 leading-relaxed mb-4">
                Chinese and Western astrology both work from the same basic premise: the circumstances of your birth reveal something true about your nature. Star seed lineage theory extends that premise outward &mdash; asking not just <em>when</em> you were born, but <em>where</em> your soul originates from at a deeper level.
            </p>
            <p class="text-gray-600 leading-relaxed mb-4">
                The framework has roots in metaphysical traditions going back decades, but it's been refined through channels like Barbara Marciniak's Pleiadian transmissions, Dolores Cannon's hypnotic regression research, and the broader New Age synthesis of the 1980s and 90s. Ten lineages are consistently recognized across those traditions: Pleiadian, Sirian, Arcturian, Andromedan, Lyran, Orion, Mintakan, Hadarian, Alpha Centaurian, and Draconian. Each carries distinct personality patterns, spiritual gifts, life challenges, and a specific mission frequency that shows up in how a person moves through the world.
            </p>
            <p class="text-gray-600 leading-relaxed mb-4">
                Most people carry a primary lineage and a secondary one &mdash; and the combination matters as much as the individual types. A Pleiadian-Sirian reads differently than a Pleiadian-Mintakan, even though both lead with love. Our quiz identifies your top two lineages and generates a reading specific to that pairing, including how it intersects with your Chinese and Western astrological signatures where relevant.
            </p>
            <p class="text-gray-600 leading-relaxed">
                We treat this framework the same way we treat the rest of this app: seriously, specifically, and without watering it down into vague feel-good language. Whether you find it literally true, metaphorically useful, or just a surprisingly accurate mirror &mdash; that's between you and the results.
            </p>
        </div>

        <!-- CTA -->
        <div class="text-center">
            <a href="<?= url('/') ?>" class="inline-block bg-brand-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-brand-700 transition">Discover Your Zodiac Profile</a>
        </div>
    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
