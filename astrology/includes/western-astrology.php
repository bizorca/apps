<?php
/**
 * Western Astrology Engine
 *
 * Sun sign calculations, sign data, elements, modalities, and compatibility.
 * Requires birth month and day at minimum to determine sun sign.
 */

// --- Sun Sign Calculation ---

function getWesternSign(int $month, int $day): string {
    $md = $month * 100 + $day;
    if ($md >= 321 && $md <= 419) return 'Aries';
    if ($md >= 420 && $md <= 520) return 'Taurus';
    if ($md >= 521 && $md <= 620) return 'Gemini';
    if ($md >= 621 && $md <= 722) return 'Cancer';
    if ($md >= 723 && $md <= 822) return 'Leo';
    if ($md >= 823 && $md <= 922) return 'Virgo';
    if ($md >= 923 && $md <= 1022) return 'Libra';
    if ($md >= 1023 && $md <= 1121) return 'Scorpio';
    if ($md >= 1122 && $md <= 1221) return 'Sagittarius';
    if ($md >= 1222 || $md <= 119) return 'Capricorn';
    if ($md >= 120 && $md <= 218) return 'Aquarius';
    return 'Pisces'; // Feb 19 – Mar 20
}

function getWesternElement(string $sign): string {
    $elements = [
        'Aries' => 'Fire', 'Leo' => 'Fire', 'Sagittarius' => 'Fire',
        'Taurus' => 'Earth', 'Virgo' => 'Earth', 'Capricorn' => 'Earth',
        'Gemini' => 'Air', 'Libra' => 'Air', 'Aquarius' => 'Air',
        'Cancer' => 'Water', 'Scorpio' => 'Water', 'Pisces' => 'Water',
    ];
    return $elements[$sign] ?? '';
}

function getWesternModality(string $sign): string {
    $modalities = [
        'Aries' => 'Cardinal', 'Cancer' => 'Cardinal', 'Libra' => 'Cardinal', 'Capricorn' => 'Cardinal',
        'Taurus' => 'Fixed', 'Leo' => 'Fixed', 'Scorpio' => 'Fixed', 'Aquarius' => 'Fixed',
        'Gemini' => 'Mutable', 'Virgo' => 'Mutable', 'Sagittarius' => 'Mutable', 'Pisces' => 'Mutable',
    ];
    return $modalities[$sign] ?? '';
}

function getWesternSignGlyph(string $sign): string {
    $glyphs = [
        'Aries' => '♈', 'Taurus' => '♉', 'Gemini' => '♊', 'Cancer' => '♋',
        'Leo' => '♌', 'Virgo' => '♍', 'Libra' => '♎', 'Scorpio' => '♏',
        'Sagittarius' => '♐', 'Capricorn' => '♑', 'Aquarius' => '♒', 'Pisces' => '♓',
    ];
    return $glyphs[$sign] ?? '';
}

// --- Sign Data ---

function getWesternSignInfo(string $sign): array {
    $data = [
        'Aries' => [
            'symbol' => 'Ram',
            'glyph' => '♈',
            'element' => 'Fire',
            'modality' => 'Cardinal',
            'ruling_planet' => 'Mars',
            'traits' => ['Bold', 'Ambitious', 'Pioneering', 'Energetic', 'Impulsive'],
            'strengths' => 'Natural-born leader with boundless energy and an unstoppable drive to initiate new things.',
            'challenges' => 'Impatience and impulsiveness can lead to unfinished projects and avoidable conflict.',
            'dates' => 'March 21 – April 19',
            'lucky_numbers' => [1, 9],
            'lucky_colors' => ['Red', 'Scarlet', 'Carmine'],
            'body_part' => 'Head, face',
            'keywords' => ['Initiative', 'Courage', 'Action'],
        ],
        'Taurus' => [
            'symbol' => 'Bull',
            'glyph' => '♉',
            'element' => 'Earth',
            'modality' => 'Fixed',
            'ruling_planet' => 'Venus',
            'traits' => ['Patient', 'Reliable', 'Sensual', 'Stubborn', 'Practical'],
            'strengths' => 'Dependable and grounded with a deep appreciation for beauty, comfort, and the finer things.',
            'challenges' => 'Stubbornness and resistance to change can create stagnation in relationships and career.',
            'dates' => 'April 20 – May 20',
            'lucky_numbers' => [2, 6],
            'lucky_colors' => ['Green', 'Pink', 'White'],
            'body_part' => 'Neck, throat',
            'keywords' => ['Stability', 'Sensuality', 'Persistence'],
        ],
        'Gemini' => [
            'symbol' => 'Twins',
            'glyph' => '♊',
            'element' => 'Air',
            'modality' => 'Mutable',
            'ruling_planet' => 'Mercury',
            'traits' => ['Curious', 'Witty', 'Adaptable', 'Expressive', 'Inconsistent'],
            'strengths' => 'Quick-witted and socially gifted, able to master any subject and connect across every social circle.',
            'challenges' => 'Restlessness and inconsistency make focus and follow-through a persistent challenge.',
            'dates' => 'May 21 – June 20',
            'lucky_numbers' => [3, 5],
            'lucky_colors' => ['Yellow', 'Light Blue', 'Silver'],
            'body_part' => 'Arms, lungs, nervous system',
            'keywords' => ['Communication', 'Duality', 'Curiosity'],
        ],
        'Cancer' => [
            'symbol' => 'Crab',
            'glyph' => '♋',
            'element' => 'Water',
            'modality' => 'Cardinal',
            'ruling_planet' => 'Moon',
            'traits' => ['Nurturing', 'Intuitive', 'Loyal', 'Emotional', 'Protective'],
            'strengths' => 'Deeply empathetic and fiercely protective of those they love, with powerful intuitive gifts.',
            'challenges' => 'Moodiness and a tendency to retreat into emotional shells can make intimacy complicated.',
            'dates' => 'June 21 – July 22',
            'lucky_numbers' => [2, 7],
            'lucky_colors' => ['Silver', 'White', 'Sea Green'],
            'body_part' => 'Chest, stomach',
            'keywords' => ['Nurture', 'Intuition', 'Home'],
        ],
        'Leo' => [
            'symbol' => 'Lion',
            'glyph' => '♌',
            'element' => 'Fire',
            'modality' => 'Fixed',
            'ruling_planet' => 'Sun',
            'traits' => ['Charismatic', 'Generous', 'Creative', 'Dramatic', 'Proud'],
            'strengths' => 'Magnetic presence and an enormous heart — Leos light up every room they walk into.',
            'challenges' => 'Pride and a need for constant recognition can tip into ego and inflexibility.',
            'dates' => 'July 23 – August 22',
            'lucky_numbers' => [1, 4],
            'lucky_colors' => ['Gold', 'Orange', 'Royal Purple'],
            'body_part' => 'Heart, spine',
            'keywords' => ['Leadership', 'Creativity', 'Vitality'],
        ],
        'Virgo' => [
            'symbol' => 'Maiden',
            'glyph' => '♍',
            'element' => 'Earth',
            'modality' => 'Mutable',
            'ruling_planet' => 'Mercury',
            'traits' => ['Analytical', 'Practical', 'Precise', 'Helpful', 'Critical'],
            'strengths' => 'Masterful at analysis and refinement, with an eye for detail that borders on supernatural.',
            'challenges' => 'Perfectionism and self-criticism can lead to anxiety and difficulty accepting imperfection in others.',
            'dates' => 'August 23 – September 22',
            'lucky_numbers' => [5, 6],
            'lucky_colors' => ['Navy Blue', 'Grey', 'Brown'],
            'body_part' => 'Digestive system, abdomen',
            'keywords' => ['Analysis', 'Service', 'Precision'],
        ],
        'Libra' => [
            'symbol' => 'Scales',
            'glyph' => '♎',
            'element' => 'Air',
            'modality' => 'Cardinal',
            'ruling_planet' => 'Venus',
            'traits' => ['Diplomatic', 'Fair-minded', 'Social', 'Charming', 'Indecisive'],
            'strengths' => 'Natural peacemaker with refined taste and an innate talent for balancing competing perspectives.',
            'challenges' => 'Indecision and people-pleasing can undermine authentic self-expression and personal needs.',
            'dates' => 'September 23 – October 22',
            'lucky_numbers' => [4, 6],
            'lucky_colors' => ['Pink', 'Light Blue', 'Ivory'],
            'body_part' => 'Kidneys, lower back',
            'keywords' => ['Balance', 'Harmony', 'Partnership'],
        ],
        'Scorpio' => [
            'symbol' => 'Scorpion',
            'glyph' => '♏',
            'element' => 'Water',
            'modality' => 'Fixed',
            'ruling_planet' => 'Pluto',
            'traits' => ['Intense', 'Strategic', 'Magnetic', 'Secretive', 'Transformative'],
            'strengths' => 'Unmatched depth of perception and emotional intensity, with the will to transform any situation.',
            'challenges' => 'Jealousy, possessiveness, and difficulty trusting can create cycles of control and isolation.',
            'dates' => 'October 23 – November 21',
            'lucky_numbers' => [8, 11],
            'lucky_colors' => ['Deep Red', 'Maroon', 'Black'],
            'body_part' => 'Reproductive organs, pelvis',
            'keywords' => ['Transformation', 'Power', 'Mystery'],
        ],
        'Sagittarius' => [
            'symbol' => 'Archer',
            'glyph' => '♐',
            'element' => 'Fire',
            'modality' => 'Mutable',
            'ruling_planet' => 'Jupiter',
            'traits' => ['Adventurous', 'Optimistic', 'Philosophical', 'Honest', 'Restless'],
            'strengths' => 'Boundless optimism and a love of freedom make Sagittarius the eternal seeker of truth and experience.',
            'challenges' => 'Bluntness and a fear of commitment can leave important relationships and projects unfinished.',
            'dates' => 'November 22 – December 21',
            'lucky_numbers' => [3, 9],
            'lucky_colors' => ['Purple', 'Maroon', 'Navy Blue'],
            'body_part' => 'Hips, thighs',
            'keywords' => ['Exploration', 'Freedom', 'Wisdom'],
        ],
        'Capricorn' => [
            'symbol' => 'Sea-Goat',
            'glyph' => '♑',
            'element' => 'Earth',
            'modality' => 'Cardinal',
            'ruling_planet' => 'Saturn',
            'traits' => ['Disciplined', 'Ambitious', 'Responsible', 'Patient', 'Reserved'],
            'strengths' => 'Tireless work ethic and long-game thinking — Capricorn is the architect of sustained achievement.',
            'challenges' => 'Workaholism and emotional guardedness can leave personal relationships feeling like an afterthought.',
            'dates' => 'December 22 – January 19',
            'lucky_numbers' => [6, 8],
            'lucky_colors' => ['Brown', 'Black', 'Dark Green'],
            'body_part' => 'Knees, skeletal system',
            'keywords' => ['Ambition', 'Discipline', 'Structure'],
        ],
        'Aquarius' => [
            'symbol' => 'Water Bearer',
            'glyph' => '♒',
            'element' => 'Air',
            'modality' => 'Fixed',
            'ruling_planet' => 'Uranus',
            'traits' => ['Independent', 'Humanitarian', 'Innovative', 'Eccentric', 'Detached'],
            'strengths' => 'Visionary intellect and genuine humanitarian spirit — Aquarius sees the future everyone else is still catching up to.',
            'challenges' => 'Emotional detachment and contrarianism for its own sake can strain close personal bonds.',
            'dates' => 'January 20 – February 18',
            'lucky_numbers' => [4, 7],
            'lucky_colors' => ['Electric Blue', 'Silver', 'Turquoise'],
            'body_part' => 'Ankles, circulatory system',
            'keywords' => ['Innovation', 'Humanity', 'Rebellion'],
        ],
        'Pisces' => [
            'symbol' => 'Fish',
            'glyph' => '♓',
            'element' => 'Water',
            'modality' => 'Mutable',
            'ruling_planet' => 'Neptune',
            'traits' => ['Compassionate', 'Artistic', 'Intuitive', 'Dreamy', 'Escapist'],
            'strengths' => 'Extraordinary empathy and creative imagination — Pisces lives on the boundary between the seen and unseen.',
            'challenges' => 'Boundary issues and escapism can leave Pisces vulnerable to confusion and avoidance.',
            'dates' => 'February 19 – March 20',
            'lucky_numbers' => [3, 9],
            'lucky_colors' => ['Sea Green', 'Lilac', 'Silver'],
            'body_part' => 'Feet, lymphatic system',
            'keywords' => ['Compassion', 'Imagination', 'Transcendence'],
        ],
    ];
    return $data[$sign] ?? [];
}

// --- Compatibility ---

function getWesternCompatibility(string $sign): array {
    $allSigns = getAllWesternSigns();
    $idx = array_search($sign, $allSigns);

    return [
        'best_matches' => [
            $allSigns[($idx + 4) % 12],
            $allSigns[($idx + 8) % 12],
        ],
        'good_matches' => [
            $allSigns[($idx + 2) % 12],
            $allSigns[($idx + 10) % 12],
        ],
        'opposition' => $allSigns[($idx + 6) % 12],
        'challenging' => [
            $allSigns[($idx + 3) % 12],
            $allSigns[($idx + 9) % 12],
        ],
        'element' => getWesternElement($sign),
    ];
}

function getWesternCompatibilityBetween(string $sign1, string $sign2): array {
    $allSigns = getAllWesternSigns();
    $idx1 = array_search($sign1, $allSigns);
    $idx2 = array_search($sign2, $allSigns);
    $diff = abs($idx1 - $idx2);
    if ($diff > 6) $diff = 12 - $diff;

    $elem1 = getWesternElement($sign1);
    $elem2 = getWesternElement($sign2);

    if ($elem1 === $elem2) {
        return ['level' => 'excellent', 'label' => 'Same Element (' . $elem1 . ')', 'description' => "Two {$elem1} signs with natural chemistry, mutual understanding, and deeply compatible drives."];
    }
    if ($diff === 6) {
        return ['level' => 'intense', 'label' => 'Opposition (180°)', 'description' => 'Opposites that complete each other — powerful magnetic attraction paired with an equal measure of tension. High-stakes but transformative.'];
    }
    if ($diff === 4 || $diff === 8) {
        return ['level' => 'great', 'label' => 'Trine (120°)', 'description' => 'One of the most naturally harmonious aspects. Shared values and complementary strengths create an easy, flowing connection.'];
    }
    if ($diff === 2 || $diff === 10) {
        return ['level' => 'good', 'label' => 'Sextile (60°)', 'description' => 'A friendly and supportive pairing with good opportunities for genuine connection and mutual growth.'];
    }
    if ($diff === 3 || $diff === 9) {
        return ['level' => 'challenging', 'label' => 'Square (90°)', 'description' => 'Friction and tension, but not without sparks. Squares push both people to grow — if they can resist the urge to clash.'];
    }
    return ['level' => 'neutral', 'label' => 'Neutral', 'description' => 'Neither strongly harmonious nor conflicting. Success depends on shared values and individual effort.'];
}

// --- CSS Helpers ---

function getWesternElementCSS(string $element): array {
    $css = [
        'Fire'  => ['bg' => 'bg-orange-50', 'border' => 'border-orange-200', 'text' => 'text-orange-800', 'badge_bg' => 'bg-orange-100', 'accent' => 'text-orange-600'],
        'Earth' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-800', 'badge_bg' => 'bg-emerald-100', 'accent' => 'text-emerald-600'],
        'Air'   => ['bg' => 'bg-sky-50', 'border' => 'border-sky-200', 'text' => 'text-sky-800', 'badge_bg' => 'bg-sky-100', 'accent' => 'text-sky-600'],
        'Water' => ['bg' => 'bg-indigo-50', 'border' => 'border-indigo-200', 'text' => 'text-indigo-800', 'badge_bg' => 'bg-indigo-100', 'accent' => 'text-indigo-600'],
    ];
    return $css[$element] ?? $css['Air'];
}

// --- All Signs List ---

function getAllWesternSigns(): array {
    return ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
            'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];
}
