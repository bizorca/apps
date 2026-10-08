<?php
require_once dirname(__DIR__) . '/_bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!csrf_ok($input['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

$attempt_id = (int)($input['attempt_id'] ?? 0);
$slot_id    = (int)($input['slot_id']    ?? 0);
$variant_id = (int)($input['variant_id'] ?? 0);
$answer_id  = (int)($input['answer_id']  ?? 0);

$attempt = db_row('SELECT * FROM lt_challenge_attempts WHERE id = ? AND user_id = ?',
    [$attempt_id, current_user()['id']]);
if (!$attempt || $attempt['completed_at']) {
    http_response_code(400); echo json_encode(['error' => 'Invalid attempt']); exit;
}

// Validate slot belongs to this challenge
$slot_challenge = (int)db_val("
    SELECT l.challenge_id FROM lt_question_slots qs JOIN lt_lessons l ON l.id = qs.lesson_id WHERE qs.id = ?
", [$slot_id]);
if ($slot_challenge !== (int)$attempt['challenge_id']) {
    http_response_code(400); echo json_encode(['error' => 'Slot mismatch']); exit;
}

// The variant must be the one this slot is serving next. The original took any
// variant_id with a matching answer, so a student could answer a different
// slot's (or an easier, already-seen) variant and have this slot marked correct.
$current_status = get_lesson_status($slot_id, $attempt_id);
if ($current_status['is_complete']) {
    http_response_code(400); echo json_encode(['error' => 'Lesson already complete']); exit;
}
$expected = get_next_variant($slot_id, $current_status['next_variant_number']);
if (!$expected || (int)$expected['id'] !== $variant_id) {
    http_response_code(400); echo json_encode(['error' => 'Variant mismatch']); exit;
}

// is_active guards against a submission for an option the admin has retired.
$answer = db_row('SELECT * FROM lt_answers WHERE id = ? AND variant_id = ? AND is_active = 1', [$answer_id, $variant_id]);
if (!$answer) {
    http_response_code(400); echo json_encode(['error' => 'Invalid answer']); exit;
}

$is_correct = (bool)$answer['is_correct'];

db_insert('lt_question_responses', [
    'attempt_id'         => $attempt_id,
    'slot_id'            => $slot_id,
    'variant_id'         => $variant_id,
    'selected_answer_id' => $answer_id,
    'is_correct'         => $is_correct ? 1 : 0,
]);

$correct_answer_id = db_val('SELECT id FROM lt_answers WHERE variant_id = ? AND is_correct = 1 AND is_active = 1', [$variant_id]);
$new_status        = get_lesson_status($slot_id, $attempt_id);
$lesson_complete   = $new_status['is_complete'];

$next_variant_id = null;
if (!$lesson_complete) {
    $nv = get_next_variant($slot_id, $new_status['next_variant_number']);
    $next_variant_id = $nv['id'] ?? null;
}

$challenge_complete = false;
if ($lesson_complete) {
    $challenge_complete = maybe_complete_attempt($attempt_id, $attempt['challenge_id']);
}

$challenge_id = $attempt['challenge_id'];
$course_id    = (int)db_val(
    'SELECT u.course_id FROM lt_units u JOIN lt_challenges c ON c.unit_id = u.id WHERE c.id = ?',
    [$challenge_id]
);

$next_lesson_url = null;
if ($lesson_complete && !$challenge_complete) {
    $all = db_rows('SELECT l.*, qs.id as slot_id FROM lt_lessons l
                    LEFT JOIN lt_question_slots qs ON qs.lesson_id = l.id
                    WHERE l.challenge_id = ? ORDER BY l.sort_order', [$challenge_id]);
    $found = false;
    foreach ($all as $l) {
        if ($l['slot_id'] == $slot_id) { $found = true; continue; }
        if ($found) {
            $next_lesson_url = APP_URL . "/challenge.php?id={$challenge_id}&course_id={$course_id}&lesson={$l['sort_order']}";
            break;
        }
    }
}

echo json_encode([
    'is_correct'         => $is_correct,
    'correct_answer_id'  => $correct_answer_id,
    'lesson_complete'    => $lesson_complete,
    'next_variant_id'    => $next_variant_id,
    'challenge_complete' => $challenge_complete,
    'next_lesson_url'    => $next_lesson_url,
    'course_url'         => APP_URL . "/course.php?id={$course_id}",
]);
