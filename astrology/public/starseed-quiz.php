<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/claude-api.php';

// Quiz-first funnel: anonymous visitors can take the quiz; results are held in
// the session and revealed after they register or log in (see claimPendingStarseed).
$user = getCurrentUser();
$db = getDB();
$pendingResult = $_SESSION['as_pending_starseed'] ?? null;

// ─── Star Seed Data ───────────────────────────────────────────────────────────

$starseeds = [
    'pleiadian' => [
        'name' => 'Pleiadian',
        'star_system' => 'The Pleiades (M45)',
        'distance_ly' => 444,
        'constellation' => 'Taurus',
        'color' => 'rose',
        'color_hex' => '#f9a8d4',
        'bg_class' => 'bg-rose-50',
        'border_class' => 'border-rose-200',
        'text_class' => 'text-rose-700',
        'badge_class' => 'bg-rose-100 text-rose-700',
        'glyph' => '✦',
        'core_mission' => 'To anchor unconditional love and heal emotional wounds at a collective level',
        'tagline' => 'Keeper of the Heart Flame',
        'traits' => ['deeply empathic', 'nurturing', 'gentle', 'peacemaker', 'highly sensitive', 'aesthetically attuned', 'drawn to healing'],
        'gifts' => ['natural healer', 'emotional intelligence as spiritual tool', 'heart chakra activation', 'channeling loving frequency', 'intuition in relationships'],
        'challenges' => ['difficulty with confrontation', 'absorbing others\' pain', 'people-pleasing', 'persistent homesickness', 'physical sensitivity'],
        'life_lesson' => 'Learning that your own needs matter as much as everyone else\'s',
        'description' => 'Pleiadians carry the oldest love frequency in this galaxy. They are the healers, the caretakers, the ones who feel everything before anyone else in the room does. Their challenge — and their liberation — is learning to hold that open heart without losing themselves inside it.',
    ],
    'sirian' => [
        'name' => 'Sirian',
        'star_system' => 'Sirius (Sirius A/B binary)',
        'distance_ly' => 8.6,
        'constellation' => 'Canis Major',
        'color' => 'blue',
        'color_hex' => '#93c5fd',
        'bg_class' => 'bg-blue-50',
        'border_class' => 'border-blue-200',
        'text_class' => 'text-blue-700',
        'badge_class' => 'bg-blue-100 text-blue-700',
        'glyph' => '★',
        'core_mission' => 'To preserve, transmit, and embody sacred knowledge',
        'tagline' => 'Guardian of Ancient Wisdom',
        'traits' => ['mission-oriented', 'deeply loyal', 'practical spiritualist', 'reserved', 'honorable', 'connected to nature and water', 'drawn to ancient systems'],
        'gifts' => ['Akashic records access', 'sacred geometry and ceremony', 'holding space for transformation', 'sound healing', 'elemental connection'],
        'challenges' => ['rigidity about the "right way"', 'difficulty with change', 'isolation', 'over-responsibility', 'stubbornness disguised as discernment'],
        'life_lesson' => 'Trusting your own knowing without needing external validation',
        'description' => 'Sirians are the brightest stars in the room — and the most focused. They arrived here carrying specific knowledge, and they take the responsibility of transmitting it seriously. Ancient Egypt knew Sirius as the Spiritual Sun; Sirian souls tend to feel that gravity, that weight of mission, from a very early age.',
    ],
    'arcturian' => [
        'name' => 'Arcturian',
        'star_system' => 'Arcturus',
        'distance_ly' => 37,
        'constellation' => 'Boötes',
        'color' => 'violet',
        'color_hex' => '#c4b5fd',
        'bg_class' => 'bg-violet-50',
        'border_class' => 'border-violet-200',
        'text_class' => 'text-violet-700',
        'badge_class' => 'bg-violet-100 text-violet-700',
        'glyph' => '◈',
        'core_mission' => 'To build new structures — social, technological, healing-oriented — for the emerging paradigm',
        'tagline' => 'Architect of New Systems',
        'traits' => ['analytical with strong intuitive overlay', 'natural leader', 'strong ethics', 'efficiency-focused', 'older than their years', 'drawn to sacred geometry', 'bridging science and spirit'],
        'gifts' => ['healing with color and light', 'blueprint holding', 'strategic visioning', 'dimensional bridging', 'third eye and crown activation'],
        'challenges' => ['coming across as cold or aloof', 'frustration when others can\'t keep up', 'disconnection from the body', 'gap between vision and current reality', 'difficulty in intimate relationships'],
        'life_lesson' => 'Emotional fluency is the final frontier — and the most important one',
        'description' => 'Edgar Cayce called Arcturus "the most advanced civilization in this galaxy." Arcturian souls carry that frequency — the impatience of someone who can see exactly how things should be built, and the loneliness of being too far ahead to easily explain it to others. Their work is learning to translate the blueprint without losing the people around them.',
    ],
    'andromedan' => [
        'name' => 'Andromedan',
        'star_system' => 'Andromeda Galaxy (M31)',
        'distance_ly' => 2537000,
        'constellation' => 'Andromeda',
        'color' => 'sky',
        'color_hex' => '#7dd3fc',
        'bg_class' => 'bg-sky-50',
        'border_class' => 'border-sky-200',
        'text_class' => 'text-sky-700',
        'badge_class' => 'bg-sky-100 text-sky-700',
        'glyph' => '⬡',
        'core_mission' => 'To break paradigms and embody the living proof that freedom is possible',
        'tagline' => 'Pioneer of Boundless Space',
        'traits' => ['fiercely independent', 'highly creative', 'enthusiastic', 'freedom-driven', 'easily bored', 'genuinely caring about all beings\' liberation', 'anti-authoritarian by instinct'],
        'gifts' => ['quantum jumping and reality shifting', 'perceiving multiple timelines', 'breaking stuck energetic patterns', 'ET consciousness connection', 'inspiring others through embodied possibility'],
        'challenges' => ['commitment and follow-through', 'relationships under freedom need', 'feeling profoundly alien in society', 'financial instability from resisting systems', 'loneliness despite expansive social energy'],
        'life_lesson' => 'Structure chosen freely is not a cage — it is the vessel that carries your mission',
        'description' => 'Andromedans come from the closest galaxy to our own — 2.5 million light-years of distance, but on a collision course with the Milky Way. That tension lives in them: always moving toward, always expanding, always pushing the edge of what\'s currently possible. The Greek myth of Andromeda chained to the rock is not coincidental. Their deepest work is learning that freedom can be built, not just escaped toward.',
    ],
    'lyran' => [
        'name' => 'Lyran',
        'star_system' => 'Vega and the Lyra constellation',
        'distance_ly' => 25,
        'constellation' => 'Lyra',
        'color' => 'amber',
        'color_hex' => '#fcd34d',
        'bg_class' => 'bg-amber-50',
        'border_class' => 'border-amber-200',
        'text_class' => 'text-amber-700',
        'badge_class' => 'bg-amber-100 text-amber-700',
        'glyph' => '♦',
        'core_mission' => 'To master physical incarnation and pioneer the original human blueprint',
        'tagline' => 'Elder of the Original Flame',
        'traits' => ['strong-willed', 'confident', 'deeply sensory', 'pioneer energy', 'courageous', 'quick-tempered but quick to forgive', 'natural authority'],
        'gifts' => ['Akashic access to original human blueprints', 'mastery teaching', 'grounding spiritual frequencies into matter', 'physical healing activation', 'courage to pioneer new paradigms'],
        'challenges' => ['arrogance when out of balance', 'difficulty admitting error', 'impatience with perceived lesser evolution', 'physical pleasure as avoidance', 'power dynamics in relationships'],
        'life_lesson' => 'Mastery and domination are not the same thing — true strength includes the grace to be wrong',
        'description' => 'Lyra is considered the original home of humanoid consciousness in this galaxy. Lyran souls are the elders — they carry a pride and a power that is simply ancient. They are the ones who go first, who refuse to apologize for taking up space, who love food and craft and physical pleasure with a fullness others sometimes find overwhelming. They are here to demonstrate that divinity does not require diminishment.',
    ],
    'orion' => [
        'name' => 'Orion',
        'star_system' => 'Orion constellation (Betelgeuse, Rigel, Orion Nebula)',
        'distance_ly' => 1344,
        'constellation' => 'Orion',
        'color' => 'orange',
        'color_hex' => '#fdba74',
        'bg_class' => 'bg-orange-50',
        'border_class' => 'border-orange-200',
        'text_class' => 'text-orange-700',
        'badge_class' => 'bg-orange-100 text-orange-700',
        'glyph' => '⚔',
        'core_mission' => 'To integrate polarity — to model the synthesis of light and shadow as one coherent force',
        'tagline' => 'Wielder of the Polarity Flame',
        'traits' => ['intense and penetrating', 'competitive', 'loves debate', 'truth-seeker', 'skeptical even of spirituality', 'magnetic and sometimes polarizing', 'deeply loyal once trusted'],
        'gifts' => ['going into dark places others avoid', 'truth detection', 'transmutation of dense energy', 'strategic intelligence', 'catalyzing transformation through challenge'],
        'challenges' => ['combativeness and need to be right', 'power struggles', 'trust issues', 'difficulty with vulnerability', 'attracting or embodying adversarial energy'],
        'life_lesson' => 'The intensity that makes you a catalyst also burns the people who need you most — learning to modulate it is the work',
        'description' => 'The Egyptian pyramids align with Orion\'s Belt. The Hopi built their villages to mirror it. Orion has been at the center of civilization\'s mythology for a reason — this is where the tension between order and chaos has played out for millennia. Orion souls carry that history. They are not here for easy conversations. They are here to cut through illusion, even when it hurts — especially when it hurts.',
    ],
    'mintakan' => [
        'name' => 'Mintakan',
        'star_system' => 'Mintaka (Orion\'s Belt, westernmost star)',
        'distance_ly' => 1200,
        'constellation' => 'Orion',
        'color' => 'teal',
        'color_hex' => '#5eead4',
        'bg_class' => 'bg-teal-50',
        'border_class' => 'border-teal-200',
        'text_class' => 'text-teal-700',
        'badge_class' => 'bg-teal-100 text-teal-700',
        'glyph' => '◎',
        'core_mission' => 'To transform the soul\'s deepest longing into the frequency of belonging — building the New Earth from the inside out',
        'tagline' => 'Seeker of the Lost Home',
        'traits' => ['profound sense of not belonging', 'old soul energy', 'loving and open-hearted', 'searching quality', 'highly sensitive to inauthenticity', 'strong affinity for water', 'melancholic when out of alignment'],
        'gifts' => ['holding the original harmonic frequency of home', 'deep healing through presence', 'transmission of unconditional love', 'making others feel safe and seen', 'accessing the Akashic record of a harmonious world'],
        'challenges' => ['persistent loneliness even in relationship', 'depression when spiritually disconnected', 'difficulty feeling at home in the body', 'idealizing "elsewhere" rather than engaging here'],
        'life_lesson' => 'The home you\'re searching for is not a place you left — it is a frequency you are learning to build',
        'description' => 'Mintakans are the most homesick souls on Earth. Not depression exactly — it\'s something older and more oceanic than that. It is a cellular memory of a world where everything resonated cleanly and cruelty was not yet invented. They are here to carry that memory as a blueprint, not as a grief. The moment they stop searching for the world they came from and start building it here, everything changes.',
    ],
    'hadarian' => [
        'name' => 'Hadarian',
        'star_system' => 'Beta Centauri (Hadar/Agena)',
        'distance_ly' => 390,
        'constellation' => 'Centaurus',
        'color' => 'emerald',
        'color_hex' => '#6ee7b7',
        'bg_class' => 'bg-emerald-50',
        'border_class' => 'border-emerald-200',
        'text_class' => 'text-emerald-700',
        'badge_class' => 'bg-emerald-100 text-emerald-700',
        'glyph' => '❋',
        'core_mission' => 'To demonstrate what unconditional love looks like when it takes form — love as action, not just feeling',
        'tagline' => 'Living Transmission of Love',
        'traits' => ['radiantly loving', 'deep trust in others', 'highly spiritual from early age', 'strong service drive', 'easily taken advantage of', 'childlike wonder into adulthood', 'deeply affected by cruelty'],
        'gifts' => ['frequency transmission — raising vibration of any space entered', 'healing through pure love presence', 'activating others\' heart fields', 'innocence as a spiritual force', 'natural ability to receive as well as give'],
        'challenges' => ['exploitation by those with darker intentions', 'spiritual bypassing', 'grief when world doesn\'t match inner frequency', 'inability to understand cruelty', 'self-abandonment in service of others'],
        'life_lesson' => 'Love given from depletion is not love — it is self-erasure. Hadarans must learn to love themselves with the same ferocity they give to others',
        'description' => 'Hadarans are pure love made flesh. If Pleiadians heal through empathy, Hadarans transmit through their very presence — they walk into a room and the frequency shifts. This is extraordinary and costly. The world has a way of taking from those who give unconditionally, which is why their deepest work is learning discernment without closing the heart that makes them who they are.',
    ],
    'alpha_centaurian' => [
        'name' => 'Alpha Centaurian',
        'star_system' => 'Alpha Centauri system (including Proxima Centauri)',
        'distance_ly' => 4.37,
        'constellation' => 'Centaurus',
        'color' => 'cyan',
        'color_hex' => '#67e8f9',
        'bg_class' => 'bg-cyan-50',
        'border_class' => 'border-cyan-200',
        'text_class' => 'text-cyan-700',
        'badge_class' => 'bg-cyan-100 text-cyan-700',
        'glyph' => '⬡',
        'core_mission' => 'To advance human understanding by bridging consciousness and physical reality — life as a vast, sacred research project',
        'tagline' => 'Scientist of the Soul',
        'traits' => ['extraordinarily curious', 'highly intelligent across domains', 'independent thinker', 'quiet and observational', 'deeply fair', 'loves humanity in concept more than in mess', 'early sense of a specific mission'],
        'gifts' => ['scientific and metaphysical synthesis', 'advanced pattern recognition', 'bridging inner and outer cosmology', 'teaching that expands rather than fills minds', 'perceiving systems at vast scale'],
        'challenges' => ['social awkwardness', 'emotional detachment', 'difficulty with mundane human concerns', 'perfectionism and analysis paralysis', 'finding others superficial'],
        'life_lesson' => 'The most sophisticated laboratory in the universe is a single human relationship — stop studying life from the outside and step into it',
        'description' => 'At 4.37 light-years, Alpha Centauri is our nearest stellar neighbor — and yet Alpha Centaurian souls often feel the furthest from home. They experience life as an experiment they are running and observing simultaneously, which makes them brilliant and isolated in equal measure. The closest star to Earth is also, somehow, the one that produced souls who feel most like they don\'t quite fit.',
    ],
    'draconian' => [
        'name' => 'Draconian',
        'star_system' => 'Draco constellation (Alpha Draconis / Thuban)',
        'distance_ly' => 303,
        'constellation' => 'Draco',
        'color' => 'slate',
        'color_hex' => '#94a3b8',
        'bg_class' => 'bg-slate-50',
        'border_class' => 'border-slate-300',
        'text_class' => 'text-slate-700',
        'badge_class' => 'bg-slate-200 text-slate-700',
        'glyph' => '🜃',
        'core_mission' => 'To transmute primal power into sovereign service — integrating the densest frequencies into the highest purpose',
        'tagline' => 'Sovereign of the Ancient Fire',
        'traits' => ['powerful commanding presence', 'strong survival instincts', 'highly strategic', 'private and selective', 'disciplined and self-controlled', 'fiercely territorial and protective', 'projects intensity that unsettles others'],
        'gifts' => ['kundalini activation', 'protection of sacred spaces and people', 'seeing through deception instantly', 'holding boundaries others cannot', 'grounding high frequencies into raw earth energy'],
        'challenges' => ['control issues', 'difficulty surrendering or being vulnerable', 'isolation from distrust', 'power-over rather than power-with', 'others projecting fear onto them'],
        'life_lesson' => 'The most courageous act available to you is not protection — it is trust',
        'description' => 'Thuban, the star in the Draco constellation, was the pole star of ancient Egypt — the fixed point around which everything turned. Draconian souls carry that gravitational quality. They are not the villains of the star seed cosmology; they are among the most spiritually courageous, precisely because they are integrating the densest material. They are coming from a lineage that has itself been through the fire. What they\'ve earned from that is real.',
    ],
];

// ─── Quiz Questions ───────────────────────────────────────────────────────────

$questions = [
    [
        'id' => 'q1',
        'text' => 'What feels like your deepest reason for being here on Earth?',
        'answers' => [
            ['text' => 'To love and heal others — I feel the world\'s pain acutely and need to help ease it', 'scores' => ['pleiadian' => 3, 'hadarian' => 2]],
            ['text' => 'To carry and protect a body of sacred knowledge I feel I was trusted with', 'scores' => ['sirian' => 3, 'alpha_centaurian' => 1]],
            ['text' => 'To design and build something better — systems, structures, or healing technologies', 'scores' => ['arcturian' => 3, 'alpha_centaurian' => 1]],
            ['text' => 'To be free and to show others that freedom is actually possible', 'scores' => ['andromedan' => 3, 'lyran' => 1]],
            ['text' => 'To master something at a deep level and leave a legacy of real excellence', 'scores' => ['lyran' => 3, 'orion' => 1]],
            ['text' => 'To find where I truly belong — to locate the home I can\'t stop searching for', 'scores' => ['mintakan' => 3, 'hadarian' => 1]],
            ['text' => 'To hold power wisely and protect what matters — sovereignty in service', 'scores' => ['draconian' => 3, 'orion' => 1]],
        ],
    ],
    [
        'id' => 'q2',
        'text' => 'Which of these best describes how you felt as a child?',
        'answers' => [
            ['text' => 'I was the caretaker — always worrying about everyone else, often putting myself last', 'scores' => ['pleiadian' => 3, 'hadarian' => 2]],
            ['text' => 'I knew things I wasn\'t supposed to know yet — facts, history, spiritual ideas I hadn\'t been taught', 'scores' => ['sirian' => 3, 'arcturian' => 1]],
            ['text' => 'I felt like I was from somewhere else — not this country, not this planet, somewhere fundamentally other', 'scores' => ['andromedan' => 2, 'mintakan' => 3]],
            ['text' => 'I was intense and sometimes scared adults with my directness or my will', 'scores' => ['orion' => 2, 'draconian' => 2, 'lyran' => 2]],
            ['text' => 'I couldn\'t stop asking why. Everything was a question that needed a real answer', 'scores' => ['alpha_centaurian' => 3, 'arcturian' => 1]],
            ['text' => 'I carried a grief or an ache with no clear source — a homesickness for somewhere I\'d never been', 'scores' => ['mintakan' => 3, 'hadarian' => 1]],
            ['text' => 'I felt like I had a job to do, a purpose I couldn\'t quite see but could definitely feel', 'scores' => ['sirian' => 2, 'arcturian' => 2, 'draconian' => 1]],
        ],
    ],
    [
        'id' => 'q3',
        'text' => 'How do you relate to authority and institutional systems?',
        'answers' => [
            ['text' => 'I work within them to keep the peace, even when I disagree with them', 'scores' => ['pleiadian' => 2, 'hadarian' => 2]],
            ['text' => 'I respect them when they\'re grounded in genuine wisdom — I improve them when they\'re not', 'scores' => ['sirian' => 2, 'arcturian' => 2]],
            ['text' => 'I instinctively resist any authority I didn\'t personally choose or consent to', 'scores' => ['andromedan' => 3, 'lyran' => 1]],
            ['text' => 'I treat rules as tools — I\'ll use them or break them based purely on what serves the mission', 'scores' => ['orion' => 2, 'lyran' => 2]],
            ['text' => 'I observe systems as fascinating data — I\'m neither loyal nor rebellious, just analytical', 'scores' => ['alpha_centaurian' => 3]],
            ['text' => 'I hold myself to a stricter internal code than any external system could impose', 'scores' => ['draconian' => 3, 'sirian' => 1]],
            ['text' => 'I feel overwhelmed by complex institutional systems and tend to retreat from them', 'scores' => ['mintakan' => 2, 'hadarian' => 1, 'pleiadian' => 1]],
        ],
    ],
    [
        'id' => 'q4',
        'text' => 'How do you most naturally connect with the spiritual or divine?',
        'answers' => [
            ['text' => 'Through healing, presence, and love — heart-first, always', 'scores' => ['pleiadian' => 2, 'hadarian' => 3]],
            ['text' => 'Through ancient ceremony, ritual, or structured sacred knowledge systems', 'scores' => ['sirian' => 3, 'lyran' => 1]],
            ['text' => 'Through sound, color, geometry, and vibrational practices that feel almost technological', 'scores' => ['arcturian' => 3, 'alpha_centaurian' => 1]],
            ['text' => 'Through raw experience — travel, boundary-breaking, and going where convention doesn\'t follow', 'scores' => ['andromedan' => 2, 'lyran' => 2]],
            ['text' => 'Through shadow work and depth — confronting what others look away from', 'scores' => ['orion' => 2, 'draconian' => 2]],
            ['text' => 'Through water, stillness, and the longing itself — the ache is the prayer', 'scores' => ['mintakan' => 3, 'hadarian' => 1]],
            ['text' => 'Through research and synthesis — understanding the underlying mechanics of consciousness', 'scores' => ['alpha_centaurian' => 3, 'arcturian' => 1]],
        ],
    ],
    [
        'id' => 'q5',
        'text' => 'What recurring pain pattern shows up most in your life?',
        'answers' => [
            ['text' => 'Giving too much and losing myself — the need to be needed until I\'m empty', 'scores' => ['pleiadian' => 3, 'hadarian' => 2]],
            ['text' => 'Feeling burdened by a mission I can\'t quite articulate — a weight I agreed to but can\'t fully see', 'scores' => ['sirian' => 3, 'draconian' => 1]],
            ['text' => 'Being too far ahead of everyone else — the isolation of the frontier', 'scores' => ['arcturian' => 3, 'alpha_centaurian' => 2]],
            ['text' => 'Feeling caged, trapped, or controlled by circumstances I didn\'t choose', 'scores' => ['andromedan' => 3, 'lyran' => 1]],
            ['text' => 'The need to win, to be right, to prove that I see what others refuse to see', 'scores' => ['orion' => 3, 'lyran' => 1]],
            ['text' => 'A grief or homesickness with no specific address — missing something I\'ve never found', 'scores' => ['mintakan' => 3, 'hadarian' => 1]],
            ['text' => 'Trust — giving it and having it violated, or withholding it and paying for the isolation', 'scores' => ['draconian' => 3, 'orion' => 1]],
        ],
    ],
    [
        'id' => 'q6',
        'text' => 'What gift do people most often seek from you or comment on?',
        'answers' => [
            ['text' => 'My ability to make them feel genuinely loved, seen, and safe', 'scores' => ['pleiadian' => 2, 'hadarian' => 2, 'mintakan' => 1]],
            ['text' => 'My depth of knowledge on ancient, esoteric, or historical subjects', 'scores' => ['sirian' => 3, 'alpha_centaurian' => 1]],
            ['text' => 'My ability to see how things should be structured and build toward that vision', 'scores' => ['arcturian' => 3, 'alpha_centaurian' => 1]],
            ['text' => 'My energy — the way I make any situation feel charged with possibility', 'scores' => ['andromedan' => 2, 'lyran' => 2]],
            ['text' => 'My ability to cut through illusion and speak uncomfortable truths clearly', 'scores' => ['orion' => 3, 'draconian' => 1]],
            ['text' => 'My presence — the way I make people feel protected, held, or anchored', 'scores' => ['draconian' => 3, 'mintakan' => 1]],
            ['text' => 'My curiosity — the way I ask questions that open up what everyone else assumed was settled', 'scores' => ['alpha_centaurian' => 3, 'orion' => 1]],
        ],
    ],
    [
        'id' => 'q7',
        'text' => 'How do you experience the physical world and your own body?',
        'answers' => [
            ['text' => 'I\'m highly sensitive — harsh environments, dense energies, and synthetic substances affect me strongly', 'scores' => ['pleiadian' => 2, 'hadarian' => 2, 'mintakan' => 1]],
            ['text' => 'I\'m grounded and sensory — I love mastery through the body: food, craft, movement, texture', 'scores' => ['lyran' => 3, 'draconian' => 1]],
            ['text' => 'I live mostly in my mind and have to consciously remember I have a body at all', 'scores' => ['arcturian' => 2, 'alpha_centaurian' => 2, 'orion' => 1]],
            ['text' => 'My body feels like a constraint — I need movement, freedom, and open space constantly', 'scores' => ['andromedan' => 3]],
            ['text' => 'Water draws me like a magnet — oceans, rivers, rain. I feel most real near it', 'scores' => ['mintakan' => 3, 'sirian' => 1]],
            ['text' => 'My senses are precise instruments — I notice everything others miss and catalog it all', 'scores' => ['sirian' => 2, 'orion' => 2]],
            ['text' => 'My body holds a power that I\'m learning to claim rather than suppress', 'scores' => ['draconian' => 3, 'lyran' => 1]],
        ],
    ],
    [
        'id' => 'q8',
        'text' => 'In relationships, what is your primary pattern?',
        'answers' => [
            ['text' => 'I love deeply and give everything — sometimes too much, and I lose myself in the process', 'scores' => ['pleiadian' => 3, 'hadarian' => 2]],
            ['text' => 'I am intensely loyal to the few I let in, but the wall to entry is real', 'scores' => ['sirian' => 2, 'draconian' => 2]],
            ['text' => 'I care, but I struggle with the messy emotional terrain — I want connection without losing efficiency', 'scores' => ['arcturian' => 2, 'alpha_centaurian' => 2]],
            ['text' => 'I need space and freedom more than closeness — intimacy that feels like ownership pushes me away', 'scores' => ['andromedan' => 3, 'lyran' => 1]],
            ['text' => 'I tend to catalyze transformation in the people I love, sometimes through conflict', 'scores' => ['orion' => 3, 'draconian' => 1]],
            ['text' => 'I long for soul-level connection but often feel deeply lonely even in relationships', 'scores' => ['mintakan' => 3, 'hadarian' => 1]],
            ['text' => 'I protect those I love with everything I have — fiercely, absolutely, sometimes possessively', 'scores' => ['draconian' => 3, 'sirian' => 1]],
        ],
    ],
    [
        'id' => 'q9',
        'text' => 'When you encounter injustice or suffering in the world, what is your instinctive response?',
        'answers' => [
            ['text' => 'I feel it physically, almost as if it\'s happening to me — and I have to do something', 'scores' => ['pleiadian' => 2, 'hadarian' => 3]],
            ['text' => 'I want to understand its root cause — the system or belief that created it', 'scores' => ['sirian' => 2, 'alpha_centaurian' => 2]],
            ['text' => 'I immediately start designing the better structure that would prevent it', 'scores' => ['arcturian' => 3]],
            ['text' => 'I want to break the system responsible for it and build something that actually works', 'scores' => ['andromedan' => 3, 'lyran' => 1]],
            ['text' => 'I want to confront the people or forces causing it directly — truth first', 'scores' => ['orion' => 3, 'draconian' => 1]],
            ['text' => 'I grieve it deeply, carrying it like weight — it confirms something I already knew about this world', 'scores' => ['mintakan' => 3, 'pleiadian' => 1]],
            ['text' => 'I move to protect the vulnerable and neutralize the threat — strategy before emotion', 'scores' => ['draconian' => 3, 'sirian' => 1]],
        ],
    ],
    [
        'id' => 'q10',
        'text' => 'Which environment makes you feel most alive and most yourself?',
        'answers' => [
            ['text' => 'Somewhere quiet and beautiful — a healing space, a garden, a home that feels like sanctuary', 'scores' => ['pleiadian' => 2, 'hadarian' => 2]],
            ['text' => 'A place dense with history — ancient ruins, temples, archives, or sacred sites', 'scores' => ['sirian' => 3, 'lyran' => 1]],
            ['text' => 'A clean, purpose-built space that supports deep work and creative problem-solving', 'scores' => ['arcturian' => 3, 'alpha_centaurian' => 1]],
            ['text' => 'Somewhere vast and open — mountains, desert, ocean horizon, a city I\'ve never been to', 'scores' => ['andromedan' => 3, 'mintakan' => 1]],
            ['text' => 'Anywhere the conversation is real and the stakes are high — intensity is home', 'scores' => ['orion' => 3, 'draconian' => 1]],
            ['text' => 'Near water — especially the ocean, where I feel closest to something I can\'t name', 'scores' => ['mintakan' => 3, 'sirian' => 1]],
            ['text' => 'Alone, or with a very small circle of people who have genuinely earned my trust', 'scores' => ['draconian' => 2, 'sirian' => 1, 'alpha_centaurian' => 2]],
        ],
    ],
    [
        'id' => 'q11',
        'text' => 'What is your relationship to the concept of "mission" or life purpose?',
        'answers' => [
            ['text' => 'My purpose is simply to love — the mission IS the love', 'scores' => ['pleiadian' => 2, 'hadarian' => 3]],
            ['text' => 'I feel I agreed to something specific before I came here — I don\'t know what exactly, but the obligation is real', 'scores' => ['sirian' => 3, 'draconian' => 1]],
            ['text' => 'I have a clear sense of what needs to be built, and I won\'t rest until it exists', 'scores' => ['arcturian' => 3, 'lyran' => 1]],
            ['text' => 'My mission is to live as free as possible and demonstrate what that looks like for others', 'scores' => ['andromedan' => 3]],
            ['text' => 'My mission is to master my craft at the deepest level and leave something of lasting value', 'scores' => ['lyran' => 3, 'sirian' => 1]],
            ['text' => 'My mission is to find the home frequency — and then help others find theirs', 'scores' => ['mintakan' => 3, 'hadarian' => 1]],
            ['text' => 'My mission is to use power wisely — to be the force that protects what matters', 'scores' => ['draconian' => 3, 'orion' => 1]],
        ],
    ],
    [
        'id' => 'q12',
        'text' => 'Which statement resonates most deeply with you?',
        'answers' => [
            ['text' => '"I feel everything. That is both my gift and my wound."', 'scores' => ['pleiadian' => 3, 'hadarian' => 2]],
            ['text' => '"I carry knowledge that feels older than this lifetime. I\'m not sure I learned it — I think I remembered it."', 'scores' => ['sirian' => 3, 'lyran' => 1]],
            ['text' => '"I can see exactly how things should work. The gap between that vision and current reality is where I live."', 'scores' => ['arcturian' => 3, 'alpha_centaurian' => 1]],
            ['text' => '"Any cage — no matter how comfortable — is still a cage."', 'scores' => ['andromedan' => 3, 'draconian' => 1]],
            ['text' => '"I\'d rather lose a relationship than compromise on the truth."', 'scores' => ['orion' => 3, 'lyran' => 1]],
            ['text' => '"I have been searching for something my whole life. I\'m not sure I can name it. But I\'ll know it when I feel it."', 'scores' => ['mintakan' => 3, 'hadarian' => 1]],
            ['text' => '"Trust is the rarest thing. Once broken, you\'re done. But while it holds — I\'ll burn the world down for you."', 'scores' => ['draconian' => 3, 'sirian' => 1]],
        ],
    ],
];

// ─── Load Existing Result ─────────────────────────────────────────────────────

$existingResult = null;
if ($user) {
    $stmt = $db->prepare("SELECT * FROM as_starseed_results WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $existingResult = $stmt->fetch();
}

// ─── Handle POST: Submit Quiz ─────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_reading'])) {
    requireLogin();
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/starseed-quiz.php'));
        exit;
    }
    if ($existingResult) {
        $profile = null;
        if ($user['primary_profile_id']) {
            $stmt2 = $db->prepare("SELECT * FROM as_profiles WHERE id = ? AND user_id = ?");
            $stmt2->execute([$user['primary_profile_id'], $user['id']]);
            $profile = $stmt2->fetch();
        }
        $readingData = generateStarseedReading(
            ['primary_lineage' => $existingResult['primary_lineage'], 'secondary_lineage' => $existingResult['secondary_lineage']],
            $starseeds,
            $profile,
            $profile['western_sign'] ?? null
        );
        if ($readingData) {
            $db->prepare("UPDATE as_starseed_results SET reading_json = ? WHERE user_id = ?")
               ->execute([json_encode($readingData), $user['id']]);
            setFlash('success', 'Your lineage reading has been generated.');
        } else {
            setFlash('error', 'Reading generation failed. Please try again in a moment.');
        }
    }
    header('Location: ' . url('/starseed-quiz.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_submit'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/starseed-quiz.php'));
        exit;
    }

    // Tally scores
    $scores = array_fill_keys(array_keys($starseeds), 0);
    $answers = [];

    foreach ($questions as $q) {
        $qid = $q['id'];
        $answerIdx = isset($_POST[$qid]) ? (int)$_POST[$qid] : null;
        if ($answerIdx === null || !isset($q['answers'][$answerIdx])) continue;

        $answers[$qid] = $answerIdx;
        foreach ($q['answers'][$answerIdx]['scores'] as $type => $pts) {
            $scores[$type] = ($scores[$type] ?? 0) + $pts;
        }
    }

    arsort($scores);
    $types = array_keys($scores);
    $primaryLineage = $types[0];
    $secondaryLineage = $types[1];

    // Anonymous visitor: hold the scored result in the session and send them
    // to registration to reveal it. No AI call yet — signup stays fast.
    if (!$user) {
        $_SESSION['as_pending_starseed'] = [
            'primary_lineage'   => $primaryLineage,
            'secondary_lineage' => $secondaryLineage,
            'scores'            => $scores,
            'answers'           => $answers,
        ];
        header('Location: ' . authUrl('register', url('/starseed-quiz.php')));
        exit;
    }

    // Generate AI reading
    $profile = null;
    $db2 = getDB();
    if ($user['primary_profile_id']) {
        $stmt2 = $db2->prepare("SELECT * FROM as_profiles WHERE id = ? AND user_id = ?");
        $stmt2->execute([$user['primary_profile_id'], $user['id']]);
        $profile = $stmt2->fetch();
    }

    $readingData = generateStarseedReading(
        ['primary_lineage' => $primaryLineage, 'secondary_lineage' => $secondaryLineage],
        $starseeds,
        $profile,
        $profile['western_sign'] ?? null
    );

    // Upsert result
    $stmt3 = $db->prepare("DELETE FROM as_starseed_results WHERE user_id = ?");
    $stmt3->execute([$user['id']]);

    $stmt4 = $db->prepare("INSERT INTO as_starseed_results (user_id, primary_lineage, secondary_lineage, scores_json, answers_json, reading_json) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt4->execute([
        $user['id'],
        $primaryLineage,
        $secondaryLineage,
        json_encode($scores),
        json_encode($answers),
        $readingData ? json_encode($readingData) : null,
    ]);

    setFlash('success', 'Your star seed lineage has been revealed.');
    header('Location: ' . url('/starseed-quiz.php'));
    exit;
}

// ─── Prepare Result Data ──────────────────────────────────────────────────────

$resultData = null;
$reading = null;
$primarySeed = null;
$secondarySeed = null;
$allScores = null;

if ($existingResult) {
    $primarySeed = $starseeds[$existingResult['primary_lineage']] ?? null;
    $secondarySeed = $starseeds[$existingResult['secondary_lineage']] ?? null;
    $allScores = json_decode($existingResult['scores_json'], true) ?? [];
    $reading = $existingResult['reading_json'] ? json_decode($existingResult['reading_json'], true) : null;
    arsort($allScores);
}

// Logged in: quiz shows unless they have a saved result. Anonymous: quiz shows
// unless a pending (unrevealed) result is waiting — then the reveal gate shows.
$showQuiz = ($user ? !$existingResult : !$pendingResult) || isset($_GET['retake']);

$pageTitle = 'Star Seed Lineage Quiz';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto">

<?php if (!$user && $pendingResult && !isset($_GET['retake'])): ?>

    <!-- ─── Reveal Gate (anonymous, quiz completed) ──────────────────── -->

    <div class="bg-gradient-to-br from-violet-50 via-rose-50 to-amber-50 border border-violet-200 rounded-2xl p-8 sm:p-10 text-center mb-6">
        <div class="text-5xl mb-4">&#10022;</div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Your Results Are Ready</h1>
        <p class="text-gray-600 max-w-lg mx-auto mb-6">Your answers have been scored across all 10 lineages. Create your free account to reveal your primary and secondary lineage, your full resonance profile, and your personalized reading.</p>

        <!-- Blurred resonance preview -->
        <div class="max-w-md mx-auto bg-white border border-gray-200 rounded-xl p-5 mb-6 text-left">
            <h3 class="font-bold text-gray-900 mb-3 text-sm">Your Resonance Profile</h3>
            <div class="space-y-2 blur-content" aria-hidden="true">
                <?php foreach ([['Lineage', 100, '#c4b5fd'], ['Lineage', 78, '#f9a8d4'], ['Lineage', 61, '#5eead4'], ['Lineage', 45, '#fcd34d'], ['Lineage', 30, '#93c5fd']] as [$lbl, $pct, $hex]): ?>
                <div class="flex items-center gap-3">
                    <div class="w-24 text-sm font-medium text-gray-700 flex-shrink-0"><?= $lbl ?></div>
                    <div class="flex-1 bg-gray-100 rounded-full h-2.5">
                        <div class="h-2.5 rounded-full" style="width: <?= $pct ?>%; background-color: <?= $hex ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <a href="<?= h(authUrl('register', url('/starseed-quiz.php'))) ?>" class="inline-block bg-violet-700 text-white px-8 py-3 rounded-lg font-semibold hover:bg-violet-800 transition">
            Create Free Account to Reveal &rarr;
        </a>
        <p class="text-sm text-gray-500 mt-4">
            Already have an account?
            <a href="<?= authUrl('login') ?>" class="text-violet-700 hover:text-violet-800 font-medium">Sign in</a> to reveal your results.
        </p>
        <p class="text-xs text-gray-400 mt-3">
            <a href="<?= url('/starseed-quiz.php') ?>?retake=1" class="hover:text-gray-600">Or retake the quiz</a>
        </p>
    </div>

<?php elseif (!$showQuiz && $existingResult && $primarySeed): ?>

    <!-- ─── Results View ─────────────────────────────────────────────── -->

    <div class="mb-6 flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Your Star Seed Lineage</h1>
            <p class="text-sm text-gray-500 mt-1">Revealed <?= date('F j, Y', strtotime($existingResult['created_at'])) ?></p>
        </div>
        <a href="<?= url('/starseed-quiz.php') ?>?retake=1" class="text-sm text-gray-500 hover:text-brand-600 transition">Retake Quiz</a>
    </div>

    <!-- Primary Lineage Hero -->
    <div class="<?= $primarySeed['bg_class'] ?> <?= $primarySeed['border_class'] ?> border rounded-xl p-8 mb-6">
        <div class="flex items-start gap-5">
            <div class="text-6xl leading-none flex-shrink-0"><?= $primarySeed['glyph'] ?></div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-widest <?= $primarySeed['text_class'] ?> mb-1">Primary Lineage</div>
                <h2 class="text-3xl font-bold text-gray-900 mb-1"><?= h($primarySeed['name']) ?></h2>
                <div class="text-sm <?= $primarySeed['text_class'] ?> font-medium mb-3"><?= h($primarySeed['tagline']) ?></div>
                <p class="text-sm text-gray-600 leading-relaxed"><?= h($primarySeed['description']) ?></p>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
            <div class="bg-white bg-opacity-60 rounded-lg p-3">
                <div class="font-semibold text-gray-700 mb-1">Star System</div>
                <div class="text-gray-600"><?= h($primarySeed['star_system']) ?></div>
                <div class="text-xs text-gray-400 mt-0.5"><?= number_format($primarySeed['distance_ly']) ?> light-years</div>
            </div>
            <div class="bg-white bg-opacity-60 rounded-lg p-3">
                <div class="font-semibold text-gray-700 mb-1">Core Mission</div>
                <div class="text-gray-600"><?= h($primarySeed['core_mission']) ?></div>
            </div>
            <div class="bg-white bg-opacity-60 rounded-lg p-3">
                <div class="font-semibold text-gray-700 mb-1">Life Lesson</div>
                <div class="text-gray-600"><?= h($primarySeed['life_lesson']) ?></div>
            </div>
        </div>
    </div>

    <!-- Secondary Lineage -->
    <?php if ($secondarySeed): ?>
    <div class="<?= $secondarySeed['bg_class'] ?> <?= $secondarySeed['border_class'] ?> border rounded-xl p-6 mb-6">
        <div class="flex items-center gap-3 mb-3">
            <div class="text-3xl leading-none"><?= $secondarySeed['glyph'] ?></div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-widest <?= $secondarySeed['text_class'] ?> mb-0.5">Secondary Lineage</div>
                <h3 class="text-xl font-bold text-gray-900"><?= h($secondarySeed['name']) ?></h3>
            </div>
        </div>
        <p class="text-sm text-gray-600 leading-relaxed mb-3"><?= h($secondarySeed['description']) ?></p>
        <div class="text-sm <?= $secondarySeed['text_class'] ?>"><?= h($secondarySeed['star_system']) ?> &middot; <?= number_format($secondarySeed['distance_ly']) ?> light-years</div>
    </div>
    <?php endif; ?>

    <!-- Resonance Profile -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h3 class="font-bold text-gray-900 mb-4">Full Resonance Profile</h3>
        <div class="space-y-2">
        <?php
        $maxScore = max($allScores) ?: 1;
        foreach ($allScores as $type => $score):
            $seed = $starseeds[$type] ?? null;
            if (!$seed) continue;
            $pct = round(($score / $maxScore) * 100);
        ?>
        <div class="flex items-center gap-3">
            <div class="w-28 text-sm font-medium text-gray-700 flex-shrink-0"><?= h($seed['name']) ?></div>
            <div class="flex-1 bg-gray-100 rounded-full h-2.5">
                <div class="h-2.5 rounded-full transition-all" style="width: <?= $pct ?>%; background-color: <?= h($seed['color_hex']) ?>"></div>
            </div>
            <div class="w-8 text-xs text-gray-500 text-right"><?= $pct ?>%</div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Primary Traits & Gifts -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-3">Signature Traits</h3>
            <ul class="space-y-1.5">
                <?php foreach ($primarySeed['traits'] as $trait): ?>
                <li class="text-sm text-gray-700 flex items-start gap-2">
                    <span class="<?= $primarySeed['text_class'] ?> mt-0.5 flex-shrink-0">&#9658;</span>
                    <?= h(ucfirst($trait)) ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-3">Spiritual Gifts</h3>
            <ul class="space-y-1.5">
                <?php foreach ($primarySeed['gifts'] as $gift): ?>
                <li class="text-sm text-gray-700 flex items-start gap-2">
                    <span class="<?= $primarySeed['text_class'] ?> mt-0.5 flex-shrink-0">&#9658;</span>
                    <?= h(ucfirst($gift)) ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- AI Reading -->
    <?php if (!$reading): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6 text-center">
        <p class="text-gray-500 text-sm mb-4">Your personalized lineage reading hasn't been generated yet.</p>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="generate_reading" value="1">
            <button type="submit" class="bg-brand-600 text-white px-6 py-2.5 rounded-lg hover:bg-brand-700 font-medium transition">
                Generate My Reading
            </button>
        </form>
    </div>
    <?php elseif ($reading): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h3 class="font-bold text-gray-900 mb-5">Your Lineage Reading</h3>

        <?php if (!empty($reading['overview'])): ?>
        <div class="mb-5">
            <p class="text-gray-800 leading-relaxed italic border-l-4 <?= $primarySeed['border_class'] ?> pl-4"><?= h($reading['overview']) ?></p>
        </div>
        <?php endif; ?>

        <?php
        $sections = [
            'primary_lineage_reading' => 'Primary Lineage: ' . $primarySeed['name'],
            'secondary_lineage_reading' => 'Secondary Lineage: ' . $secondarySeed['name'],
            'mission' => 'Your Earth Mission',
            'gifts_to_develop' => 'Gifts to Develop',
            'core_challenges' => 'Core Challenges',
            'relationships' => 'Relationships & Soul Contracts',
            'activation_practices' => 'Activation Practices',
        ];
        foreach ($sections as $key => $label):
            if (empty($reading[$key])) continue;
        ?>
        <div class="mb-5">
            <h4 class="font-semibold text-gray-800 mb-2 text-sm uppercase tracking-wide"><?= h($label) ?></h4>
            <p class="text-gray-700 leading-relaxed text-sm"><?= h($reading[$key]) ?></p>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($reading['message'])): ?>
        <div class="<?= $primarySeed['bg_class'] ?> <?= $primarySeed['border_class'] ?> border rounded-lg p-5 mt-4">
            <div class="text-xs font-semibold uppercase tracking-widest <?= $primarySeed['text_class'] ?> mb-2">Transmission</div>
            <p class="text-gray-800 leading-relaxed font-medium"><?= h($reading['message']) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Work with Jillian CTA -->
    <div class="bg-gradient-to-br from-violet-100 via-rose-50 to-amber-50 border border-violet-200 rounded-xl p-6 sm:p-8 mb-6 text-center">
        <div class="text-3xl mb-3">&#10022;</div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Ready to go deeper?</h3>
        <p class="text-sm text-gray-600 max-w-lg mx-auto mb-4">Your reading above was generated from your quiz answers. A live 1:1 reading with Jillian goes further — your full lineage combination, your charts, and what they mean for your path right now. Group journeys continue from there.</p>
        <a href="<?= url('/work-with-jillian.php') ?>" class="inline-block bg-violet-700 text-white px-7 py-3 rounded-lg font-semibold hover:bg-violet-800 transition">Work with Jillian &rarr;</a>
    </div>

<?php else: ?>

    <!-- ─── Quiz View ────────────────────────────────────────────────── -->

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Star Seed Lineage Quiz</h1>
        <p class="text-gray-600">Star seeds are souls believed to originate from other star systems who incarnate on Earth carrying specific gifts, missions, and soul-level memories. This quiz identifies which cosmic lineage resonates most strongly with your nature.</p>
    </div>

    <form method="POST" id="starseed-form">
        <?= csrfField() ?>
        <input type="hidden" name="quiz_submit" value="1">

        <?php foreach ($questions as $i => $q): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-4" id="<?= h($q['id']) ?>">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Question <?= $i + 1 ?> of <?= count($questions) ?></div>
            <p class="font-semibold text-gray-900 mb-4 leading-snug"><?= h($q['text']) ?></p>
            <div class="space-y-2">
                <?php foreach ($q['answers'] as $ai => $answer): ?>
                <label class="flex items-start gap-3 p-3 rounded-lg border border-transparent hover:border-gray-200 hover:bg-gray-50 cursor-pointer transition group">
                    <input type="radio" name="<?= h($q['id']) ?>" value="<?= $ai ?>" required
                           class="mt-0.5 flex-shrink-0 text-brand-600 border-gray-300 focus:ring-brand-500">
                    <span class="text-sm text-gray-700 leading-relaxed"><?= h($answer['text']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <button type="submit"
                    class="w-full bg-brand-600 text-white py-3 rounded-lg hover:bg-brand-700 font-semibold text-base transition">
                Reveal My Star Seed Lineage
            </button>
            <p class="text-xs text-gray-400 text-center mt-3">Answer all <?= count($questions) ?> questions to receive your reading. This takes about 2 minutes.<?= $user ? '' : ' You\'ll create a free account to view your results.' ?></p>
        </div>
    </form>

<?php endif; ?>

</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
