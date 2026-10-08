<?php
/**
 * Chinese Astrology Engine
 *
 * Zodiac calculations, compatibility data, TCM element mappings.
 * All static data - no API calls needed.
 */

// --- Zodiac Calculation ---

function getZodiacAnimal(int $year): string {
    $animals = ['Rat', 'Ox', 'Tiger', 'Rabbit', 'Dragon', 'Snake',
                'Horse', 'Goat', 'Monkey', 'Rooster', 'Dog', 'Pig'];
    $index = ($year - 4) % 12;
    if ($index < 0) $index += 12;
    return $animals[$index];
}

function getElement(int $year): string {
    // Last digit: 0/1=Metal, 2/3=Water, 4/5=Wood, 6/7=Fire, 8/9=Earth
    $elements = ['Metal', 'Metal', 'Water', 'Water', 'Wood', 'Wood',
                 'Fire', 'Fire', 'Earth', 'Earth'];
    return $elements[$year % 10];
}

function getYinYang(int $year): string {
    return ($year % 2 === 0) ? 'Yang' : 'Yin';
}

function getHeavenlyStem(int $year): array {
    $stems = [
        ['name' => 'Geng',  'chinese' => '庚', 'element' => 'Metal', 'polarity' => 'Yang'],
        ['name' => 'Xin',   'chinese' => '辛', 'element' => 'Metal', 'polarity' => 'Yin'],
        ['name' => 'Ren',   'chinese' => '壬', 'element' => 'Water', 'polarity' => 'Yang'],
        ['name' => 'Gui',   'chinese' => '癸', 'element' => 'Water', 'polarity' => 'Yin'],
        ['name' => 'Jia',   'chinese' => '甲', 'element' => 'Wood',  'polarity' => 'Yang'],
        ['name' => 'Yi',    'chinese' => '乙', 'element' => 'Wood',  'polarity' => 'Yin'],
        ['name' => 'Bing',  'chinese' => '丙', 'element' => 'Fire',  'polarity' => 'Yang'],
        ['name' => 'Ding',  'chinese' => '丁', 'element' => 'Fire',  'polarity' => 'Yin'],
        ['name' => 'Wu',    'chinese' => '戊', 'element' => 'Earth', 'polarity' => 'Yang'],
        ['name' => 'Ji',    'chinese' => '己', 'element' => 'Earth', 'polarity' => 'Yin'],
    ];
    return $stems[$year % 10];
}

function getEarthlyBranch(int $year): array {
    $branches = [
        ['name' => 'Zi',   'chinese' => '子', 'animal' => 'Rat'],
        ['name' => 'Chou', 'chinese' => '丑', 'animal' => 'Ox'],
        ['name' => 'Yin',  'chinese' => '寅', 'animal' => 'Tiger'],
        ['name' => 'Mao',  'chinese' => '卯', 'animal' => 'Rabbit'],
        ['name' => 'Chen', 'chinese' => '辰', 'animal' => 'Dragon'],
        ['name' => 'Si',   'chinese' => '巳', 'animal' => 'Snake'],
        ['name' => 'Wu',   'chinese' => '午', 'animal' => 'Horse'],
        ['name' => 'Wei',  'chinese' => '未', 'animal' => 'Goat'],
        ['name' => 'Shen', 'chinese' => '申', 'animal' => 'Monkey'],
        ['name' => 'You',  'chinese' => '酉', 'animal' => 'Rooster'],
        ['name' => 'Xu',   'chinese' => '戌', 'animal' => 'Dog'],
        ['name' => 'Hai',  'chinese' => '亥', 'animal' => 'Pig'],
    ];
    $index = ($year - 4) % 12;
    if ($index < 0) $index += 12;
    return $branches[$index];
}

function getSexagenaryCycleName(int $year): string {
    $stem = getHeavenlyStem($year);
    $branch = getEarthlyBranch($year);
    return $stem['name'] . ' ' . $branch['name'] . ' (' . $stem['chinese'] . $branch['chinese'] . ')';
}

function calculateProfile(int $year, ?int $month = null, ?int $day = null, ?int $hour = null): array {
    $stem = getHeavenlyStem($year);
    $branch = getEarthlyBranch($year);

    $profile = [
        'birth_year' => $year,
        'birth_month' => $month,
        'birth_day' => $day,
        'birth_hour' => $hour,
        'zodiac_animal' => getZodiacAnimal($year),
        'zodiac_element' => getElement($year),
        'yin_yang' => getYinYang($year),
        'heavenly_stem' => $stem['name'] . ' (' . $stem['chinese'] . ')',
        'earthly_branch' => $branch['name'] . ' (' . $branch['chinese'] . ')',
        'sexagenary_name' => getSexagenaryCycleName($year),
    ];

    $profile['four_pillars'] = calculateFourPillars($year, $month, $day, $hour);

    return $profile;
}

// --- Animal Data ---

function getAnimalInfo(string $animal): array {
    $data = [
        'Rat' => [
            'chinese' => '鼠',
            'traits' => ['Quick-witted', 'Resourceful', 'Versatile', 'Kind', 'Intelligent'],
            'strengths' => 'Adaptable and clever with strong intuition and charm.',
            'challenges' => 'Can be overly cautious, critical, or prone to anxiety.',
            'fixed_element' => 'Water',
            'lucky_numbers' => [2, 3],
            'lucky_colors' => ['Blue', 'Gold', 'Green'],
            'lucky_direction' => 'Southeast',
        ],
        'Ox' => [
            'chinese' => '牛',
            'traits' => ['Diligent', 'Dependable', 'Strong', 'Determined', 'Honest'],
            'strengths' => 'Patient and hardworking with a quiet confidence.',
            'challenges' => 'Can be stubborn, rigid, or resistant to change.',
            'fixed_element' => 'Earth',
            'lucky_numbers' => [1, 4],
            'lucky_colors' => ['White', 'Yellow', 'Green'],
            'lucky_direction' => 'South',
        ],
        'Tiger' => [
            'chinese' => '虎',
            'traits' => ['Brave', 'Confident', 'Independent', 'Passionate', 'Protective'],
            'strengths' => 'Courageous natural leader with magnetic energy.',
            'challenges' => 'Can be impulsive, restless, or overly aggressive.',
            'fixed_element' => 'Wood',
            'lucky_numbers' => [1, 3, 4],
            'lucky_colors' => ['Blue', 'Gray', 'Orange'],
            'lucky_direction' => 'East',
        ],
        'Rabbit' => [
            'chinese' => '兔',
            'traits' => ['Gentle', 'Quiet', 'Graceful', 'Kind', 'Sensitive'],
            'strengths' => 'Elegant and diplomatic with refined taste.',
            'challenges' => 'Can be overly timid, superficial, or conflict-avoidant.',
            'fixed_element' => 'Wood',
            'lucky_numbers' => [3, 4, 6],
            'lucky_colors' => ['Red', 'Pink', 'Purple'],
            'lucky_direction' => 'East',
        ],
        'Dragon' => [
            'chinese' => '龙',
            'traits' => ['Charismatic', 'Ambitious', 'Powerful', 'Lucky', 'Creative'],
            'strengths' => 'Natural born leader with boundless energy and vision.',
            'challenges' => 'Can be arrogant, impatient, or overly demanding.',
            'fixed_element' => 'Earth',
            'lucky_numbers' => [1, 6, 7],
            'lucky_colors' => ['Gold', 'Silver', 'Gray'],
            'lucky_direction' => 'East',
        ],
        'Snake' => [
            'chinese' => '蛇',
            'traits' => ['Wise', 'Mysterious', 'Introspective', 'Intuitive', 'Strategic'],
            'strengths' => 'Deep thinker with sharp instincts and quiet wisdom.',
            'challenges' => 'Can be suspicious, secretive, or possessive.',
            'fixed_element' => 'Fire',
            'lucky_numbers' => [2, 8, 9],
            'lucky_colors' => ['Black', 'Red', 'Yellow'],
            'lucky_direction' => 'South',
        ],
        'Horse' => [
            'chinese' => '马',
            'traits' => ['Energetic', 'Independent', 'Freedom-loving', 'Enthusiastic', 'Warm'],
            'strengths' => 'Free-spirited and charismatic with infectious enthusiasm.',
            'challenges' => 'Can be impatient, self-centered, or commitment-averse.',
            'fixed_element' => 'Fire',
            'lucky_numbers' => [2, 3, 7],
            'lucky_colors' => ['Yellow', 'Brown', 'Purple'],
            'lucky_direction' => 'South',
        ],
        'Goat' => [
            'chinese' => '羊',
            'traits' => ['Gentle', 'Creative', 'Artistic', 'Sensitive', 'Cooperative'],
            'strengths' => 'Deeply creative with a compassionate and artistic soul.',
            'challenges' => 'Can be indecisive, pessimistic, or overly dependent.',
            'fixed_element' => 'Earth',
            'lucky_numbers' => [2, 7],
            'lucky_colors' => ['Brown', 'Red', 'Purple'],
            'lucky_direction' => 'South',
        ],
        'Monkey' => [
            'chinese' => '猴',
            'traits' => ['Playful', 'Clever', 'Curious', 'Adaptable', 'Witty'],
            'strengths' => 'Quick learner with sharp problem-solving skills and humor.',
            'challenges' => 'Can be mischievous, opportunistic, or lack follow-through.',
            'fixed_element' => 'Metal',
            'lucky_numbers' => [4, 9],
            'lucky_colors' => ['White', 'Blue', 'Gold'],
            'lucky_direction' => 'West',
        ],
        'Rooster' => [
            'chinese' => '鸡',
            'traits' => ['Honest', 'Observant', 'Practical', 'Hardworking', 'Courageous'],
            'strengths' => 'Detail-oriented and punctual with strong moral compass.',
            'challenges' => 'Can be critical, blunt, or overly perfectionistic.',
            'fixed_element' => 'Metal',
            'lucky_numbers' => [5, 7, 8],
            'lucky_colors' => ['Gold', 'Brown', 'Yellow'],
            'lucky_direction' => 'West',
        ],
        'Dog' => [
            'chinese' => '狗',
            'traits' => ['Loyal', 'Honest', 'Trustworthy', 'Protective', 'Sincere'],
            'strengths' => 'Faithful companion with strong sense of justice.',
            'challenges' => 'Can be anxious, pessimistic, or overly cautious.',
            'fixed_element' => 'Earth',
            'lucky_numbers' => [3, 4, 9],
            'lucky_colors' => ['Red', 'Green', 'Purple'],
            'lucky_direction' => 'East',
        ],
        'Pig' => [
            'chinese' => '猪',
            'traits' => ['Compassionate', 'Generous', 'Kind', 'Honest', 'Diligent'],
            'strengths' => 'Warm-hearted and genuine with great appreciation for life.',
            'challenges' => 'Can be naive, materialistic, or overly trusting.',
            'fixed_element' => 'Water',
            'lucky_numbers' => [2, 5, 8],
            'lucky_colors' => ['Yellow', 'Gray', 'Brown'],
            'lucky_direction' => 'Southeast',
        ],
    ];
    return $data[$animal] ?? [];
}

function getAnimalEmoji(string $animal): string {
    $map = [
        'Rat' => "\u{1F400}",
        'Ox' => "\u{1F402}",
        'Tiger' => "\u{1F405}",
        'Rabbit' => "\u{1F407}",
        'Dragon' => "\u{1F409}",
        'Snake' => "\u{1F40D}",
        'Horse' => "\u{1F40E}",
        'Goat' => "\u{1F410}",
        'Monkey' => "\u{1F412}",
        'Rooster' => "\u{1F413}",
        'Dog' => "\u{1F415}",
        'Pig' => "\u{1F416}",
    ];
    return $map[$animal] ?? '';
}

function getAnimalChinese(string $animal): string {
    $info = getAnimalInfo($animal);
    return $info['chinese'] ?? '';
}

// --- Compatibility ---

function getCompatibility(string $animal): array {
    $sanHe = [
        'Rat' => ['Dragon', 'Monkey'],
        'Ox' => ['Snake', 'Rooster'],
        'Tiger' => ['Horse', 'Dog'],
        'Rabbit' => ['Goat', 'Pig'],
        'Dragon' => ['Rat', 'Monkey'],
        'Snake' => ['Ox', 'Rooster'],
        'Horse' => ['Tiger', 'Dog'],
        'Goat' => ['Rabbit', 'Pig'],
        'Monkey' => ['Rat', 'Dragon'],
        'Rooster' => ['Ox', 'Snake'],
        'Dog' => ['Tiger', 'Horse'],
        'Pig' => ['Rabbit', 'Goat'],
    ];
    $liuHe = [
        'Rat' => 'Ox', 'Ox' => 'Rat',
        'Tiger' => 'Pig', 'Pig' => 'Tiger',
        'Rabbit' => 'Dog', 'Dog' => 'Rabbit',
        'Dragon' => 'Rooster', 'Rooster' => 'Dragon',
        'Snake' => 'Monkey', 'Monkey' => 'Snake',
        'Horse' => 'Goat', 'Goat' => 'Horse',
    ];
    $clashes = [
        'Rat' => 'Horse', 'Horse' => 'Rat',
        'Ox' => 'Goat', 'Goat' => 'Ox',
        'Tiger' => 'Monkey', 'Monkey' => 'Tiger',
        'Rabbit' => 'Rooster', 'Rooster' => 'Rabbit',
        'Dragon' => 'Dog', 'Dog' => 'Dragon',
        'Snake' => 'Pig', 'Pig' => 'Snake',
    ];

    return [
        'best_match' => $liuHe[$animal] ?? '',
        'harmonies' => $sanHe[$animal] ?? [],
        'clash' => $clashes[$animal] ?? '',
    ];
}

function getCompatibilityBetween(string $animal1, string $animal2): array {
    $compat = getCompatibility($animal1);

    if ($compat['best_match'] === $animal2) {
        return ['level' => 'excellent', 'label' => 'Secret Friend (Liu He)', 'description' => 'One of the most harmonious pairings. You complement each other naturally and share deep mutual understanding.'];
    }
    if (in_array($animal2, $compat['harmonies'])) {
        return ['level' => 'great', 'label' => 'Triple Harmony (San He)', 'description' => 'A naturally supportive pairing with shared values and complementary strengths.'];
    }
    if ($compat['clash'] === $animal2) {
        return ['level' => 'challenging', 'label' => 'Zodiac Clash', 'description' => 'Opposite energies that can create tension but also powerful attraction. Requires understanding and compromise.'];
    }
    return ['level' => 'neutral', 'label' => 'Neutral Pairing', 'description' => 'Neither strongly harmonious nor conflicting. Success depends on individual effort and communication.'];
}

// --- TCM Element Mappings ---

function getElementTCM(string $element): array {
    $map = [
        'Wood' => [
            'organs' => ['Liver', 'Gallbladder'],
            'emotion_negative' => 'Anger / Frustration',
            'emotion_positive' => 'Kindness / Generosity',
            'season' => 'Spring',
            'color' => 'Green',
            'taste' => 'Sour',
            'direction' => 'East',
            'body_parts' => ['Eyes', 'Tendons', 'Nails'],
            'foods' => ['Leafy greens', 'Sprouts', 'Citrus', 'Green tea'],
            'health_focus' => 'Flexibility, detoxification, and emotional flow. Support liver function through gentle movement and sour foods.',
        ],
        'Fire' => [
            'organs' => ['Heart', 'Small Intestine'],
            'emotion_negative' => 'Overexcitement / Anxiety',
            'emotion_positive' => 'Joy / Compassion',
            'season' => 'Summer',
            'color' => 'Red',
            'taste' => 'Bitter',
            'direction' => 'South',
            'body_parts' => ['Tongue', 'Blood vessels', 'Complexion'],
            'foods' => ['Bitter greens', 'Red beans', 'Watermelon', 'Green tea'],
            'health_focus' => 'Heart health, circulation, and emotional balance. Calm the mind through meditation and bitter-flavored foods.',
        ],
        'Earth' => [
            'organs' => ['Spleen', 'Stomach'],
            'emotion_negative' => 'Worry / Overthinking',
            'emotion_positive' => 'Trust / Stability',
            'season' => 'Late Summer',
            'color' => 'Yellow',
            'taste' => 'Sweet',
            'direction' => 'Center',
            'body_parts' => ['Mouth', 'Muscles', 'Flesh'],
            'foods' => ['Root vegetables', 'Warm soups', 'Whole grains', 'Sweet potato'],
            'health_focus' => 'Digestive strength and grounding. Support the spleen with warm, cooked foods and regular meal times.',
        ],
        'Metal' => [
            'organs' => ['Lungs', 'Large Intestine'],
            'emotion_negative' => 'Grief / Sadness',
            'emotion_positive' => 'Courage / Righteousness',
            'season' => 'Autumn',
            'color' => 'White',
            'taste' => 'Pungent',
            'direction' => 'West',
            'body_parts' => ['Nose', 'Skin', 'Body hair'],
            'foods' => ['Pears', 'Radish', 'Garlic', 'Ginger', 'White rice'],
            'health_focus' => 'Respiratory health and letting go. Strengthen lungs through deep breathing exercises and pungent foods.',
        ],
        'Water' => [
            'organs' => ['Kidneys', 'Bladder'],
            'emotion_negative' => 'Fear / Insecurity',
            'emotion_positive' => 'Wisdom / Willpower',
            'season' => 'Winter',
            'color' => 'Black / Blue',
            'taste' => 'Salty',
            'direction' => 'North',
            'body_parts' => ['Ears', 'Bones', 'Hair on head'],
            'foods' => ['Black beans', 'Seaweed', 'Walnuts', 'Bone broth'],
            'health_focus' => 'Kidney vitality, rest, and conservation of energy. Nourish kidneys with warming foods and adequate sleep.',
        ],
    ];
    return $map[$element] ?? [];
}

function getElementColor(string $element): string {
    $colors = [
        'Wood' => 'green',
        'Fire' => 'red',
        'Earth' => 'amber',
        'Metal' => 'gray',
        'Water' => 'blue',
    ];
    return $colors[$element] ?? 'gray';
}

function getElementCSS(string $element): array {
    $css = [
        'Wood'  => ['bg' => 'bg-green-50', 'border' => 'border-green-200', 'text' => 'text-green-800', 'badge_bg' => 'bg-green-100', 'accent' => 'text-green-600'],
        'Fire'  => ['bg' => 'bg-red-50', 'border' => 'border-red-200', 'text' => 'text-red-800', 'badge_bg' => 'bg-red-100', 'accent' => 'text-red-600'],
        'Earth' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-800', 'badge_bg' => 'bg-amber-100', 'accent' => 'text-amber-600'],
        'Metal' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800', 'badge_bg' => 'bg-gray-100', 'accent' => 'text-gray-600'],
        'Water' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'text' => 'text-blue-800', 'badge_bg' => 'bg-blue-100', 'accent' => 'text-blue-600'],
    ];
    return $css[$element] ?? $css['Earth'];
}

// --- Generating & Controlling Cycles ---

function getGeneratingElement(string $element): string {
    $cycle = ['Wood' => 'Fire', 'Fire' => 'Earth', 'Earth' => 'Metal', 'Metal' => 'Water', 'Water' => 'Wood'];
    return $cycle[$element] ?? '';
}

function getControllingElement(string $element): string {
    $cycle = ['Wood' => 'Earth', 'Fire' => 'Metal', 'Earth' => 'Water', 'Metal' => 'Wood', 'Water' => 'Fire'];
    return $cycle[$element] ?? '';
}

function getGeneratedByElement(string $element): string {
    $cycle = ['Wood' => 'Water', 'Fire' => 'Wood', 'Earth' => 'Fire', 'Metal' => 'Earth', 'Water' => 'Metal'];
    return $cycle[$element] ?? '';
}

// --- Chinese Body Clock ---

function getBodyClockHour(int $hour): array {
    $clock = [
        ['start' => 23, 'end' => 1,  'animal' => 'Rat',     'organ' => 'Gallbladder', 'focus' => 'Deep sleep and detoxification'],
        ['start' => 1,  'end' => 3,  'animal' => 'Ox',      'organ' => 'Liver',       'focus' => 'Liver repair and blood cleansing'],
        ['start' => 3,  'end' => 5,  'animal' => 'Tiger',    'organ' => 'Lungs',       'focus' => 'Deep breathing and oxygenation'],
        ['start' => 5,  'end' => 7,  'animal' => 'Rabbit',   'organ' => 'Large Intestine', 'focus' => 'Elimination and morning routine'],
        ['start' => 7,  'end' => 9,  'animal' => 'Dragon',   'organ' => 'Stomach',     'focus' => 'Breakfast and nourishment'],
        ['start' => 9,  'end' => 11, 'animal' => 'Snake',    'organ' => 'Spleen',      'focus' => 'Mental clarity and digestion'],
        ['start' => 11, 'end' => 13, 'animal' => 'Horse',    'organ' => 'Heart',       'focus' => 'Peak circulation and lunch'],
        ['start' => 13, 'end' => 15, 'animal' => 'Goat',     'organ' => 'Small Intestine', 'focus' => 'Nutrient absorption'],
        ['start' => 15, 'end' => 17, 'animal' => 'Monkey',   'organ' => 'Bladder',     'focus' => 'Hydration and energy storage'],
        ['start' => 17, 'end' => 19, 'animal' => 'Rooster',  'organ' => 'Kidney',      'focus' => 'Kidney energy and rest preparation'],
        ['start' => 19, 'end' => 21, 'animal' => 'Dog',      'organ' => 'Pericardium', 'focus' => 'Emotional balance and relaxation'],
        ['start' => 21, 'end' => 23, 'animal' => 'Pig',      'organ' => 'Triple Burner','focus' => 'Metabolism and sleep preparation'],
    ];

    foreach ($clock as $period) {
        if ($period['start'] > $period['end']) {
            // Wraps around midnight (23-1)
            if ($hour >= $period['start'] || $hour < $period['end']) return $period;
        } else {
            if ($hour >= $period['start'] && $hour < $period['end']) return $period;
        }
    }
    return $clock[0];
}

// --- Current Year Context ---

function getCurrentYearInfo(): array {
    $year = (int)date('Y');
    return [
        'year' => $year,
        'animal' => getZodiacAnimal($year),
        'element' => getElement($year),
        'yin_yang' => getYinYang($year),
        'sexagenary' => getSexagenaryCycleName($year),
    ];
}

// --- All 12 Animals List ---

function getAllAnimals(): array {
    return ['Rat', 'Ox', 'Tiger', 'Rabbit', 'Dragon', 'Snake',
            'Horse', 'Goat', 'Monkey', 'Rooster', 'Dog', 'Pig'];
}

function getAllElements(): array {
    return ['Wood', 'Fire', 'Earth', 'Metal', 'Water'];
}

// --- Ba Zi (Four Pillars) Calculations ---

function getStemByIndex(int $index): array {
    $stems = [
        ['name' => 'Jia',  'chinese' => '甲', 'element' => 'Wood',  'polarity' => 'Yang'],
        ['name' => 'Yi',   'chinese' => '乙', 'element' => 'Wood',  'polarity' => 'Yin'],
        ['name' => 'Bing', 'chinese' => '丙', 'element' => 'Fire',  'polarity' => 'Yang'],
        ['name' => 'Ding', 'chinese' => '丁', 'element' => 'Fire',  'polarity' => 'Yin'],
        ['name' => 'Wu',   'chinese' => '戊', 'element' => 'Earth', 'polarity' => 'Yang'],
        ['name' => 'Ji',   'chinese' => '己', 'element' => 'Earth', 'polarity' => 'Yin'],
        ['name' => 'Geng', 'chinese' => '庚', 'element' => 'Metal', 'polarity' => 'Yang'],
        ['name' => 'Xin',  'chinese' => '辛', 'element' => 'Metal', 'polarity' => 'Yin'],
        ['name' => 'Ren',  'chinese' => '壬', 'element' => 'Water', 'polarity' => 'Yang'],
        ['name' => 'Gui',  'chinese' => '癸', 'element' => 'Water', 'polarity' => 'Yin'],
    ];
    $i = $index % 10;
    if ($i < 0) $i += 10;
    return array_merge($stems[$i], ['index' => $i]);
}

function getBranchByIndex(int $index): array {
    $branches = [
        ['name' => 'Zi',   'chinese' => '子', 'animal' => 'Rat',     'element' => 'Water'],
        ['name' => 'Chou', 'chinese' => '丑', 'animal' => 'Ox',      'element' => 'Earth'],
        ['name' => 'Yin',  'chinese' => '寅', 'animal' => 'Tiger',   'element' => 'Wood'],
        ['name' => 'Mao',  'chinese' => '卯', 'animal' => 'Rabbit',  'element' => 'Wood'],
        ['name' => 'Chen', 'chinese' => '辰', 'animal' => 'Dragon',  'element' => 'Earth'],
        ['name' => 'Si',   'chinese' => '巳', 'animal' => 'Snake',   'element' => 'Fire'],
        ['name' => 'Wu',   'chinese' => '午', 'animal' => 'Horse',   'element' => 'Fire'],
        ['name' => 'Wei',  'chinese' => '未', 'animal' => 'Goat',    'element' => 'Earth'],
        ['name' => 'Shen', 'chinese' => '申', 'animal' => 'Monkey',  'element' => 'Metal'],
        ['name' => 'You',  'chinese' => '酉', 'animal' => 'Rooster', 'element' => 'Metal'],
        ['name' => 'Xu',   'chinese' => '戌', 'animal' => 'Dog',     'element' => 'Earth'],
        ['name' => 'Hai',  'chinese' => '亥', 'animal' => 'Pig',     'element' => 'Water'],
    ];
    $i = $index % 12;
    if ($i < 0) $i += 12;
    return array_merge($branches[$i], ['index' => $i]);
}

function getLiChunDate(int $year): string {
    // Li Chun (Start of Spring) dates. Most years fall on Feb 4.
    // Years where Li Chun falls on Feb 3 or Feb 5.
    $feb3 = [1925,1928,1932,1936,1940,1943,1947,1951,1955,1959,
             1963,1967,1971,1975,1979,1983,1987,1991,1995,1999,
             2003,2007,2011,2015,2019,2023,2027,2031,2035,2039,2043,2047];
    $feb5 = [1920,1924,1939,1943,1958,1962,1977,1981,1996,2000,
             2015,2019,2034,2038];

    if (in_array($year, $feb5)) {
        return $year . '-02-05';
    }
    if (in_array($year, $feb3)) {
        return $year . '-02-03';
    }
    return $year . '-02-04';
}

function getAdjustedBaZiYear(int $year, ?int $month = null, ?int $day = null): int {
    if ($month === null || $day === null) {
        return $year;
    }
    $liChun = getLiChunDate($year);
    $birthDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
    if ($birthDate < $liChun) {
        return $year - 1;
    }
    return $year;
}

function getSolarMonth(int $month, int $day): int {
    // Solar term boundaries (Jie Qi) for Chinese months.
    // Each Chinese month starts at a specific solar term date.
    $md = $month * 100 + $day;

    if ($md >= 204 && $md < 306)  return 1;  // Yin/Tiger month
    if ($md >= 306 && $md < 405)  return 2;  // Mao/Rabbit month
    if ($md >= 405 && $md < 506)  return 3;  // Chen/Dragon month
    if ($md >= 506 && $md < 606)  return 4;  // Si/Snake month
    if ($md >= 606 && $md < 707)  return 5;  // Wu/Horse month
    if ($md >= 707 && $md < 808)  return 6;  // Wei/Goat month
    if ($md >= 808 && $md < 908)  return 7;  // Shen/Monkey month
    if ($md >= 908 && $md < 1008) return 8;  // You/Rooster month
    if ($md >= 1008 && $md < 1107) return 9;  // Xu/Dog month
    if ($md >= 1107 && $md < 1207) return 10; // Hai/Pig month
    if ($md >= 1207 || $md < 106) return 11;  // Zi/Rat month
    return 12; // Chou/Ox month (Jan 6 - Feb 3)
}

function getJulianDayNumber(int $year, int $month, int $day): int {
    $a = intdiv(14 - $month, 12);
    $y = $year + 4800 - $a;
    $m = $month + 12 * $a - 3;
    return $day + intdiv(153 * $m + 2, 5) + 365 * $y
         + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
}

function getHourBranchIndex(int $hour): int {
    return intdiv($hour, 2);
}

function calculateYearPillar(int $baZiYear): array {
    $stemIndex = ($baZiYear % 10 + 6) % 10;
    $branchIndex = ($baZiYear - 4) % 12;
    if ($branchIndex < 0) $branchIndex += 12;

    return [
        'stem' => getStemByIndex($stemIndex),
        'branch' => getBranchByIndex($branchIndex),
        'label' => 'Year Pillar',
        'chinese_label' => '年柱',
        'metaphor' => 'Root',
        'metaphor_chinese' => '根基',
        'meaning' => 'Ancestry, family background, early life',
    ];
}

function calculateMonthPillar(int $yearStemIndex, int $solarMonth): array {
    $branchIndex = ($solarMonth + 1) % 12;
    $monthStemStart = (($yearStemIndex % 5) * 2 + 2) % 10;
    $stemIndex = ($monthStemStart + $solarMonth - 1) % 10;

    return [
        'stem' => getStemByIndex($stemIndex),
        'branch' => getBranchByIndex($branchIndex),
        'label' => 'Month Pillar',
        'chinese_label' => '月柱',
        'metaphor' => 'Trunk',
        'metaphor_chinese' => '苗',
        'meaning' => 'Parents, growth period, career',
    ];
}

function calculateDayPillar(int $year, int $month, int $day): array {
    $jdn = getJulianDayNumber($year, $month, $day);
    $stemIndex = ($jdn - 1) % 10;
    if ($stemIndex < 0) $stemIndex += 10;
    $branchIndex = ($jdn + 1) % 12;
    if ($branchIndex < 0) $branchIndex += 12;

    return [
        'stem' => getStemByIndex($stemIndex),
        'branch' => getBranchByIndex($branchIndex),
        'label' => 'Day Pillar',
        'chinese_label' => '日柱',
        'metaphor' => 'Flower',
        'metaphor_chinese' => '花',
        'meaning' => 'Self, spouse, core identity',
    ];
}

function calculateHourPillar(int $dayStemIndex, int $hourBranchIndex): array {
    $hourStemStart = (($dayStemIndex % 5) * 2) % 10;
    $stemIndex = ($hourStemStart + $hourBranchIndex) % 10;

    return [
        'stem' => getStemByIndex($stemIndex),
        'branch' => getBranchByIndex($hourBranchIndex),
        'label' => 'Hour Pillar',
        'chinese_label' => '时柱',
        'metaphor' => 'Fruit',
        'metaphor_chinese' => '果',
        'meaning' => 'Children, legacy, later life',
    ];
}

function calculateElementBalance(array $pillars): array {
    $counts = ['Wood' => 0, 'Fire' => 0, 'Earth' => 0, 'Metal' => 0, 'Water' => 0];

    foreach ($pillars as $pillar) {
        if (isset($pillar['stem']['element'])) {
            $counts[$pillar['stem']['element']]++;
        }
        if (isset($pillar['branch']['element'])) {
            $counts[$pillar['branch']['element']]++;
        }
    }

    $maxCount = max($counts);
    $dominant = array_keys(array_filter($counts, fn($c) => $c === $maxCount));
    $missing = array_keys(array_filter($counts, fn($c) => $c === 0));

    return [
        'counts' => $counts,
        'total' => array_sum($counts),
        'dominant' => $dominant,
        'missing' => $missing,
    ];
}

function calculateFourPillars(int $year, ?int $month = null, ?int $day = null, ?int $hour = null): array {
    $result = [
        'pillar_count' => 0,
        'year_pillar' => null,
        'month_pillar' => null,
        'day_pillar' => null,
        'hour_pillar' => null,
        'day_master' => null,
        'element_balance' => null,
        'li_chun_adjusted' => false,
        'li_chun_note' => null,
    ];

    // Li Chun adjustment
    $baZiYear = getAdjustedBaZiYear($year, $month, $day);
    $result['li_chun_adjusted'] = ($baZiYear !== $year);
    if ($result['li_chun_adjusted']) {
        $result['li_chun_note'] = "Born before Li Chun (Start of Spring); Ba Zi year is $baZiYear.";
    }

    // Year Pillar (always available)
    $yearStemIndex = ($baZiYear % 10 + 6) % 10;
    $result['year_pillar'] = calculateYearPillar($baZiYear);
    $result['pillar_count'] = 1;
    $pillars = [$result['year_pillar']];

    // Month Pillar (needs at least month)
    if ($month !== null) {
        $solarDay = $day ?? 15;
        $solarMonth = getSolarMonth($month, $solarDay);
        $result['month_pillar'] = calculateMonthPillar($yearStemIndex, $solarMonth);
        if ($day === null) {
            $result['month_pillar']['approximate'] = true;
        }
        $result['pillar_count'] = 2;
        $pillars[] = $result['month_pillar'];
    }

    // Day Pillar (needs year + month + day)
    if ($month !== null && $day !== null) {
        $result['day_pillar'] = calculateDayPillar($year, $month, $day);
        $result['pillar_count'] = 3;
        $pillars[] = $result['day_pillar'];
        $result['day_master'] = $result['day_pillar']['stem'];
    }

    // Hour Pillar (needs day pillar + hour)
    if ($result['day_pillar'] !== null && $hour !== null) {
        $hourBranchIndex = getHourBranchIndex($hour);
        $dayStemIndex = $result['day_pillar']['stem']['index'];
        $result['hour_pillar'] = calculateHourPillar($dayStemIndex, $hourBranchIndex);
        $result['pillar_count'] = 4;
        $pillars[] = $result['hour_pillar'];
    }

    // Element Balance
    $result['element_balance'] = calculateElementBalance($pillars);

    return $result;
}
