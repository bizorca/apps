<?php
require __DIR__ . '/_bootstrap.php';

$pageTitle = 'Features';
$fullWidth = true;
require AS_ROOT . '/templates/header.php';
?>

<!-- Hero -->
<div class="bg-gradient-to-br from-brand-600 to-brand-800 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-extrabold text-white tracking-tight">Features</h1>
        <p class="mt-4 text-xl text-brand-100 max-w-2xl mx-auto">Ancient wisdom meets modern technology. Explore everything included in your astrology experience.</p>
    </div>
</div>

<div class="bg-gray-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Free Features -->
        <div class="mb-16">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Free for Everyone</h2>
            <p class="text-gray-500 mb-8">No account required to get started. Create your profile in seconds.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <div class="text-3xl mb-3">&#x1F400;</div>
                    <h3 class="font-bold text-gray-900 mb-2">Zodiac Animal Profile</h3>
                    <p class="text-sm text-gray-600">Discover your Chinese zodiac animal based on your birth year. Learn your animal's personality traits, strengths, challenges, and natural tendencies rooted in thousands of years of tradition.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <div class="text-3xl mb-3">&#x1F525;</div>
                    <h3 class="font-bold text-gray-900 mb-2">Five Element Analysis</h3>
                    <p class="text-sm text-gray-600">Your birth year also reveals your dominant element &mdash; Wood, Fire, Earth, Metal, or Water. Each element shapes your personality, health tendencies, and life path in unique ways.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <div class="text-3xl mb-3">&#x2728;</div>
                    <h3 class="font-bold text-gray-900 mb-2">Basic Reading</h3>
                    <p class="text-sm text-gray-600">Get a personalized reading that combines your zodiac animal, element, and yin/yang energy into an insightful overview of your astrological profile.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <div class="text-3xl mb-3">&#x2764;&#xFE0F;</div>
                    <h3 class="font-bold text-gray-900 mb-2">Compatibility Checker</h3>
                    <p class="text-sm text-gray-600">Enter a partner's birth year to discover your zodiac compatibility. Based on the San He (triple harmony) and Liu He (six pairs) systems used for centuries in Chinese astrology.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <div class="text-3xl mb-3">&#x2726;</div>
                    <h3 class="font-bold text-gray-900 mb-2">Star Seed Lineage Quiz</h3>
                    <p class="text-sm text-gray-600">12 questions that identify which of 10 stellar lineages resonates most with your soul's nature &mdash; Pleiadian, Sirian, Arcturian, Andromedan, Lyran, and more. Includes a full resonance profile across all 10 types and an AI-generated reading of your specific lineage combination.</p>
                </div>
            </div>
        </div>

        <!-- Premium Features -->
        <div class="mb-16">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Premium</h2>
            <p class="text-gray-500 mb-8">The complete astrology experience with ongoing personalized insights. <a href="<?= url('/pricing.php') ?>" class="text-brand-600 hover:text-brand-700 font-medium">$8/month</a>, cancel anytime.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl p-6 border-2 border-brand-200 shadow-sm">
                    <div class="text-3xl mb-3">&#x1F4DC;</div>
                    <h3 class="font-bold text-gray-900 mb-2">Full Enhanced Reading</h3>
                    <p class="text-sm text-gray-600">A comprehensive, multi-section reading covering your personality, element analysis, TCM health insights, relationship patterns, career guidance, and life path. Generated using Chinese zodiac and Traditional Chinese Medicine principles.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border-2 border-brand-200 shadow-sm">
                    <div class="text-3xl mb-3">&#x1F319;</div>
                    <h3 class="font-bold text-gray-900 mb-2">Weekly Personalized Forecasts</h3>
                    <p class="text-sm text-gray-600">Every week, receive a new forecast tailored to your zodiac animal — Chinese and Western — with Star Seed resonance woven in if you've taken the quiz. Covers energy, relationships, health, and key days for the week ahead.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border-2 border-brand-200 shadow-sm">
                    <div class="text-3xl mb-3">&#x1F9D8;</div>
                    <h3 class="font-bold text-gray-900 mb-2">All 5 Guided Meditations</h3>
                    <p class="text-sm text-gray-600">Five element-specific guided meditations grounded in TCM organ health. Free users get their birth element meditation; Premium unlocks all five for a complete practice across Wood, Fire, Earth, Metal, and Water.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border-2 border-brand-200 shadow-sm">
                    <div class="text-3xl mb-3">&#x1F33F;</div>
                    <h3 class="font-bold text-gray-900 mb-2">TCM Health & Wellness</h3>
                    <p class="text-sm text-gray-600">Your reading includes Traditional Chinese Medicine insights: organ associations, dietary recommendations, seasonal wellness guidance, and body clock awareness based on your element.</p>
                </div>
            </div>
        </div>

        <!-- How It Works -->
        <div class="mb-16">
            <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">How It Works</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-brand-100 text-brand-700 rounded-full text-lg font-bold mb-4">1</div>
                    <h3 class="font-bold text-gray-900 mb-2">Enter Your Birth Year</h3>
                    <p class="text-sm text-gray-500">We calculate your zodiac animal, dominant element, and yin/yang energy using traditional Chinese astrology methods.</p>
                </div>
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-brand-100 text-brand-700 rounded-full text-lg font-bold mb-4">2</div>
                    <h3 class="font-bold text-gray-900 mb-2">Get Your Reading</h3>
                    <p class="text-sm text-gray-500">We generate a personalized reading combining zodiac wisdom with TCM health principles, tailored to your unique profile.</p>
                </div>
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-brand-100 text-brand-700 rounded-full text-lg font-bold mb-4">3</div>
                    <h3 class="font-bold text-gray-900 mb-2">Stay Connected</h3>
                    <p class="text-sm text-gray-500">Premium members receive weekly forecasts, guided meditations, and ongoing wellness insights aligned with the Chinese calendar.</p>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="text-center bg-white rounded-2xl p-10 border border-gray-200 shadow-sm">
            <h2 class="text-2xl font-bold text-gray-900 mb-3">Ready to Discover Your Path?</h2>
            <p class="text-gray-500 mb-6">Enter your birth year and receive your zodiac profile instantly. No account required.</p>
            <a href="<?= url('/') ?>" class="inline-block bg-brand-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-brand-700 transition">Get Started Free</a>
        </div>
    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
