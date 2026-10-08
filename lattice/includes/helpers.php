<?php
// XSS-safe output
function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function thumb_url(?string $path): string {
    return APP_URL . '/thumb.php?f=' . rawurlencode(basename((string)$path));
}

// ── Progress & Scoring ─────────────────────────────────────────────────────

function get_course_score(int $user_id, int $course_id): array {
    $challenges = db_rows("
        SELECT c.id, c.type,
               MAX(ca.score_pct) AS best_score
        FROM   lt_challenges c
        JOIN   lt_units u ON u.id = c.unit_id
        LEFT JOIN lt_challenge_attempts ca
               ON ca.challenge_id = c.id
              AND ca.user_id = ?
              AND ca.completed_at IS NOT NULL
        WHERE  u.course_id = ?
          AND  c.type != 'practice_milestone'
        GROUP  BY c.id, c.type
    ", [$user_id, $course_id]);

    $total_weight = 0;
    $weighted_sum = 0.0;
    $completed    = 0;

    foreach ($challenges as $c) {
        $w = (in_array($c['type'], ['milestone', 'final_milestone'])) ? MILESTONE_WEIGHT : 1;
        $total_weight += $w;
        if ($c['best_score'] !== null) {
            $weighted_sum += (float)$c['best_score'] * $w;
            $completed++;
        }
    }

    $pct = ($total_weight > 0 && $completed > 0)
        ? round($weighted_sum / $total_weight, 1)
        : 0.0;

    return [
        'pct'       => $pct,
        'pass'      => $pct >= PASS_THRESHOLD,
        'completed' => $completed,
        'total'     => count($challenges),
    ];
}

function get_progress_dots(int $user_id, int $course_id): array {
    return db_rows("
        SELECT c.id, c.type, c.title, c.unit_id,
               COALESCE(MAX(CASE WHEN ca.completed_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS completed,
               COALESCE(MAX(CASE WHEN ca.id IS NOT NULL           THEN 1 ELSE 0 END), 0) AS attempted
        FROM   lt_challenges c
        JOIN   lt_units u ON u.id = c.unit_id
        LEFT JOIN lt_challenge_attempts ca
               ON ca.challenge_id = c.id AND ca.user_id = ?
        WHERE  u.course_id = ?
        GROUP  BY c.id, c.type, c.title, c.unit_id
        ORDER  BY u.sort_order, c.sort_order
    ", [$user_id, $course_id]);
}

// ── Challenge / Lesson State ───────────────────────────────────────────────

function get_or_create_attempt(int $user_id, int $challenge_id): array {
    // Get the most recent incomplete attempt, or the highest attempt number
    $attempt = db_row("
        SELECT * FROM lt_challenge_attempts
        WHERE user_id = ? AND challenge_id = ?
        ORDER BY attempt_number DESC
        LIMIT 1
    ", [$user_id, $challenge_id]);

    if (!$attempt) {
        $id      = db_insert('lt_challenge_attempts', [
            'user_id'        => $user_id,
            'challenge_id'   => $challenge_id,
            'attempt_number' => 1,
        ]);
        $attempt = db_row('SELECT * FROM lt_challenge_attempts WHERE id = ?', [$id]);
    }

    return $attempt;
}

function get_lesson_status(int $slot_id, int $attempt_id): array {
    $responses = db_rows("
        SELECT qr.is_correct
        FROM   lt_question_responses qr
        WHERE  qr.slot_id = ? AND qr.attempt_id = ?
        ORDER  BY qr.responded_at ASC
    ", [$slot_id, $attempt_id]);

    $total_variants = (int)db_val(
        'SELECT COUNT(*) FROM lt_question_variants WHERE slot_id = ?',
        [$slot_id]
    );

    $response_count = count($responses);
    $ever_correct   = (bool)array_filter($responses, fn($r) => $r['is_correct']);
    $exhausted      = $total_variants > 0 && $response_count >= $total_variants;

    return [
        'response_count'      => $response_count,
        'is_correct'          => $ever_correct,
        'is_exhausted'        => $exhausted,
        'is_complete'         => $ever_correct || $exhausted,
        'next_variant_number' => $response_count + 1,
        'total_variants'      => $total_variants,
    ];
}

function get_next_variant(int $slot_id, int $next_variant_number): ?array {
    $variant = db_row(
        'SELECT * FROM lt_question_variants WHERE slot_id = ? AND variant_number = ?',
        [$slot_id, $next_variant_number]
    );
    // Fall back to variant 1 if the requested number doesn't exist
    if (!$variant && $next_variant_number > 1) {
        $variant = db_row(
            'SELECT * FROM lt_question_variants WHERE slot_id = ? AND variant_number = 1',
            [$slot_id]
        );
    }
    return $variant ?: null;
}

function get_answers_for_variant(int $variant_id): array {
    return db_rows(
        'SELECT * FROM lt_answers WHERE variant_id = ? AND is_active = 1 ORDER BY sort_order',
        [$variant_id]
    );
}

// Remove an answer option from a question. An answer a student has already
// selected cannot be deleted — question_responses.selected_answer_id references
// it and MySQL RESTRICTs the delete — so retire it instead of destroying the
// response history that references it.
function retire_answer(int $answer_id): void {
    $in_use = (int)db_val(
        'SELECT COUNT(*) FROM lt_question_responses WHERE selected_answer_id = ?',
        [$answer_id]
    );
    if ($in_use) {
        db_update('lt_answers', ['is_active' => 0], 'id = ?', [$answer_id]);
    } else {
        db_delete('lt_answers', 'id = ?', [$answer_id]);
    }
}

// Returns true if all non-practice_milestone challenges in the unit are complete
function is_milestone_unlocked(int $user_id, int $unit_id): bool {
    $total = (int)db_val(
        "SELECT COUNT(*) FROM lt_challenges WHERE unit_id = ? AND type = 'challenge'",
        [$unit_id]
    );
    if ($total === 0) return true;

    $done = (int)db_val("
        SELECT COUNT(DISTINCT ca.challenge_id)
        FROM   lt_challenge_attempts ca
        JOIN   lt_challenges c ON c.id = ca.challenge_id
        WHERE  c.unit_id = ? AND c.type = 'challenge'
          AND  ca.user_id = ? AND ca.completed_at IS NOT NULL
    ", [$unit_id, $user_id]);

    return $done >= $total;
}

// Returns true once all unit milestones are complete
function is_final_milestone_unlocked(int $user_id, int $course_id): bool {
    $total = (int)db_val("
        SELECT COUNT(*) FROM lt_challenges c
        JOIN lt_units u ON u.id = c.unit_id
        WHERE u.course_id = ? AND c.type = 'milestone'
    ", [$course_id]);
    if ($total === 0) return false;

    $done = (int)db_val("
        SELECT COUNT(DISTINCT ca.challenge_id)
        FROM   lt_challenge_attempts ca
        JOIN   lt_challenges c ON c.id = ca.challenge_id
        JOIN   lt_units u ON u.id = c.unit_id
        WHERE  u.course_id = ? AND c.type = 'milestone'
          AND  ca.user_id = ? AND ca.completed_at IS NOT NULL
    ", [$course_id, $user_id]);

    return $done >= $total;
}

function maybe_complete_attempt(int $attempt_id, int $challenge_id): bool {
    $lesson_ids = db_rows(
        'SELECT id FROM lt_lessons WHERE challenge_id = ? ORDER BY sort_order',
        [$challenge_id]
    );

    foreach ($lesson_ids as $row) {
        $slot = db_row('SELECT id FROM lt_question_slots WHERE lesson_id = ?', [$row['id']]);
        if (!$slot) return false; // lesson has no slot — not ready
        $status = get_lesson_status($slot['id'], $attempt_id);
        if (!$status['is_complete']) return false;
    }

    // All lessons answered — score it and close
    $correct = (int)db_val("
        SELECT COUNT(*) FROM lt_question_responses
        WHERE attempt_id = ? AND is_correct = 1
    ", [$attempt_id]);

    $total = (int)db_val("
        SELECT COUNT(*) FROM lt_question_responses WHERE attempt_id = ?
    ", [$attempt_id]);

    // Score = best correct / number of slots (one point per lesson slot)
    $slot_count = count($lesson_ids);
    $unique_correct = (int)db_val("
        SELECT COUNT(DISTINCT slot_id) FROM lt_question_responses
        WHERE attempt_id = ? AND is_correct = 1
    ", [$attempt_id]);

    $score = $slot_count > 0 ? round(($unique_correct / $slot_count) * 100, 2) : 0;

    db_update('lt_challenge_attempts', [
        'completed_at' => gmdate('Y-m-d H:i:s'),   // tl_db() runs the session in UTC
        'score_pct'    => $score,
    ], 'id = ?', [$attempt_id]);

    return true;
}
