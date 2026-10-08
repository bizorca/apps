<?php

namespace Bizorca\Consulting\Controllers;

use Bizorca\Consulting\Auth\CSRF;
use Bizorca\Consulting\Core\Database;
use Bizorca\Consulting\Models\Board;
use Bizorca\Consulting\Models\Card;
use Bizorca\Consulting\Models\Engagement;

class CardController
{
    public function comment(string $cardId): void
    {
        require_auth();
        CSRF::verify();
        $user = current_user();
        $card = Card::find((int) $cardId);

        if (!$card) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }

        // Verify user has access to this card's engagement
        $this->assertBoardAccess($card['board_id'], $user);

        $board      = Board::find($card['board_id']);
        $engagement = Engagement::find($board['engagement_id']);

        // The original redirected to the literal path "back" on an empty body.
        $body = trim($_POST['body'] ?? '');
        if ($body !== '') {
            Card::addComment($card['id'], $user['id'], $body);
        }

        redirect("/engagements/{$engagement['id']}#card-{$card['id']}");
    }

    public function toggleStep(string $cardId, string $stepId): void
    {
        require_auth();
        CSRF::verify();
        $user = current_user();
        $card = Card::find((int) $cardId);

        if (!$card) { http_response_code(404); exit; }
        $this->assertBoardAccess($card['board_id'], $user);

        // The step must be on this card. The original toggled any step id it
        // was given, so a client could tick steps on another client's board.
        Card::toggleStep((int) $stepId, (int) $card['id'], $user['id']);

        $board      = Board::find($card['board_id']);
        $engagement = Engagement::find($board['engagement_id']);
        redirect("/engagements/{$engagement['id']}");
    }

    public function reorder(string $boardId): void
    {
        require_auth();
        $user  = current_user();
        $board = Board::find((int) $boardId);

        if (!$board) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }
        $this->assertBoardAccess((int) $boardId, $user);

        // Only admin can reorder (clients view only)
        if (!$user['is_admin']) {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden']);
            exit;
        }

        // Nothing in the UI calls this yet ("Future: drag-drop" in the view),
        // but it changes data, so it needs the CSRF token the original never
        // asked for: X-CSRF-Token header or _csrf in the JSON body.
        $payload  = json_decode(file_get_contents('php://input'), true) ?: [];
        if (!\Bizorca\Consulting\Auth\CSRF::valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['_csrf'] ?? null))) {
            http_response_code(403);
            echo json_encode(['error' => 'csrf']);
            exit;
        }
        $columnId = (int) ($payload['column_id'] ?? 0);
        $cardIds  = array_map('intval', $payload['card_ids'] ?? []);

        // And the column must be on this board.
        if ($columnId && !Database::fetchOne('SELECT 1 AS x FROM fd_columns WHERE id = ? AND board_id = ?', [$columnId, (int) $boardId])) {
            $columnId = 0;
        }

        if (!$columnId || empty($cardIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'bad request']);
            exit;
        }

        Card::reorder((int) $boardId, $columnId, $cardIds);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    private function assertBoardAccess(int $boardId, array $user): void
    {
        $board      = Board::find($boardId);
        $engagement = $board ? Engagement::find($board['engagement_id']) : null;

        if (!$engagement || (!$user['is_admin'] && $engagement['client_id'] != $user['id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden']);
            exit;
        }
    }
}
