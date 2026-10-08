<?php
/**
 * CardController, ClosureController, AssignmentController, ReactionController,
 * StepController, CommentController, WatchController, PinController,
 * ClientCardController.
 */

declare(strict_types=1);

/** A card in the current account, or 404. */
function fm_card(string $id): Card
{
    $card = Card::find($id);
    if (!$card || $card->account_id !== fm_account()->id) {
        abort(404);
    }
    return $card;
}

/* ── cards ────────────────────────────────────────────────────────────── */

function cards_index(array $p): never
{
    $account = fm_account();
    $where  = ['account_id = ?'];
    $params = [$account->id];

    if (request()->filled('board')) {
        $where[] = 'board_id = ?';
        $params[] = (string) request('board');
    }
    if (request()->filled('assignee')) {
        $where[] = 'id IN (SELECT card_id FROM fm_assignments WHERE user_id = ?)';
        $params[] = (string) request('assignee');
    }
    if (request()->filled('tag')) {
        $where[] = 'id IN (SELECT taggable_id FROM fm_taggings WHERE tag_id = ? AND taggable_type = ?)';
        $params[] = (string) request('tag');
        $params[] = Card::MORPH;
    }
    $status = request('status');
    $where[] = match ($status) {
        'open'    => 'closed_at IS NULL',
        'closed'  => 'closed_at IS NOT NULL',
        'draft'   => 'is_draft = 1',
        'golden'  => 'is_golden = 1',
        'stalled' => 'stalled_at IS NOT NULL',
        default   => '1=1',
    };
    $sql = implode(' AND ', $where);

    $perPage = 25;
    $page    = max(1, (int) request('page', 1));
    $total   = Card::countWhere($sql, $params);
    $cards   = Card::where($sql, $params, 'ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
    $paginator = new FmPaginator($total, $perPage, $page, 'cards.index');

    $boards  = Board::where('account_id = ? AND archived_at IS NULL', [$account->id], 'ORDER BY name');
    $tags    = Tag::where('account_id = ?', [$account->id], 'ORDER BY name');
    $members = $account->users;

    view('cards.index', compact('cards', 'boards', 'tags', 'members', 'paginator'));
}

function cards_show(array $p): never
{
    $card = fm_card($p['card']);
    $isWatching = fm_user()->isWatching($card);
    $isPinned   = fm_user()->hasPinned($card);
    $availableClients = Client::where('account_id = ?', [fm_account()->id], 'ORDER BY name');
    view('cards.show', compact('card', 'isWatching', 'isPinned', 'availableClients'));
}

function cards_create(array $p): never
{
    $account = fm_account();
    $board = null;
    $column = null;
    if (request()->filled('board_id')) {
        $board = Board::find((string) request('board_id'));
        if (!$board || $board->account_id !== $account->id) {
            abort(404);
        }
    }
    if (request()->filled('column_id')) {
        $column = Column::find((string) request('column_id'));
        if (!$column || $column->account_id !== $account->id) {
            abort(404);
        }
        $board ??= $column->board;
    }
    $boards    = Board::where('account_id = ? AND archived_at IS NULL', [$account->id], 'ORDER BY name');
    $tags      = Tag::where('account_id = ?', [$account->id], 'ORDER BY name');
    $members   = $account->users;
    $templates = CardTemplate::where('account_id = ?', [$account->id], 'ORDER BY name');
    view('cards.create', compact('board', 'column', 'boards', 'tags', 'members', 'templates'));
}

function cards_store(array $p): never
{
    $v = validate([
        'board_id'    => 'required|uuid',
        'column_id'   => 'required|uuid',
        'title'       => 'required|string|max:500',
        'description' => 'nullable|string',
        'color'       => 'nullable|string|max:20',
        'is_draft'    => 'boolean',
        'due_at'      => 'nullable|date',
        'tags'        => 'array',
        'tags.*'      => 'uuid',
        'assignees'   => 'array',
        'assignees.*' => 'uuid',
    ]);
    $account = fm_account();
    $board = Board::find($v['board_id']);
    if (!$board || $board->account_id !== $account->id) {
        abort(404);
    }
    $column = Column::find($v['column_id']);
    if (!$column || $column->board_id !== $board->id) {
        abort(404);
    }

    $card = Card::create([
        'account_id'  => $account->id,
        'board_id'    => $board->id,
        'column_id'   => $column->id,
        'creator_id'  => fm_user()->id,
        'title'       => $v['title'],
        'description' => $v['description'] ?? null,
        'color'       => $v['color'] ?? null,
        'is_draft'    => fm_bool($v['is_draft'] ?? 0),
        'due_at'      => isset($v['due_at']) ? gmdate('Y-m-d H:i:s', strtotime($v['due_at'] . ' UTC')) : null,
    ]);

    // Tags and assignees must belong to this account. The original attached
    // whatever ids were posted.
    $card->syncTags(fm_account_tag_ids($v['tags'] ?? []));
    foreach (fm_account_member_ids($v['assignees'] ?? []) as $userId) {
        Assignment::create(['card_id' => $card->id, 'user_id' => $userId, 'assigner_id' => fm_user()->id]);
    }
    Watch::firstOrCreate(fm_user()->id, $card->id);

    flash('success', "Card \"{$card->title}\" created.");
    redirect_to(route('cards.show', $card));
}

/** Only ids of tags in the current account. */
function fm_account_tag_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter($ids, 'fm_is_uuid')));
    if (!$ids) {
        return [];
    }
    return Tag::where('account_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', [fm_account()->id, ...$ids])->pluck('id')->all();
}

/** Only ids of members of the current account. */
function fm_account_member_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter($ids, 'fm_is_uuid')));
    if (!$ids) {
        return [];
    }
    return FmUser::where('f.account_id = ? AND f.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', [fm_account()->id, ...$ids])->pluck('id')->all();
}

function cards_edit(array $p): never
{
    $card = fm_card($p['card']);
    $account = fm_account();
    $boards  = Board::where('account_id = ? AND archived_at IS NULL', [$account->id], 'ORDER BY name');
    $tags    = Tag::where('account_id = ?', [$account->id], 'ORDER BY name');
    $members = $account->users;
    view('cards.edit', compact('card', 'boards', 'tags', 'members'));
}

function cards_update(array $p): never
{
    $card = fm_card($p['card']);
    $v = validate([
        'title'       => 'required|string|max:500',
        'description' => 'nullable|string',
        'color'       => 'nullable|string|max:20',
        'due_at'      => 'nullable|date',
        'is_golden'   => 'boolean',
        'tags'        => 'array',
        'tags.*'      => 'uuid',
    ]);
    // The edit form always renders the golden checkbox, and the tag checkboxes
    // whenever the account has tags. Absent means unticked; the original kept
    // the old value, so a card could never be un-golden'd or lose its last tag.
    $card->update([
        'title'       => $v['title'],
        'description' => $v['description'] ?? null,
        'color'       => $v['color'] ?? null,
        'due_at'      => isset($v['due_at']) ? gmdate('Y-m-d H:i:s', strtotime($v['due_at'] . ' UTC')) : null,
        'is_golden'   => fm_bool($v['is_golden'] ?? 0),
    ]);
    if (Tag::countWhere('account_id = ?', [fm_account()->id]) > 0) {
        $card->syncTags(fm_account_tag_ids($v['tags'] ?? []));
    }
    flash('success', 'Card updated.');
    redirect_to(route('cards.show', $card));
}

function cards_destroy(array $p): never
{
    $card  = fm_card($p['card']);
    $board = $card->board;
    $card->delete();
    flash('success', "Card \"{$card->title}\" deleted.");
    redirect_to(route('boards.show', $board));
}

function cards_duplicate(array $p): never
{
    $card = fm_card($p['card']);
    $copy = $card->duplicate(fm_user());
    flash('success', "Card duplicated as \"{$copy->title}\".");
    redirect_to(route('cards.show', $copy));
}

function cards_move(array $p): never
{
    $card = fm_card($p['card']);
    $v = validate(['column_id' => 'required|uuid', 'position' => 'nullable|integer|min:1']);
    $column = Column::find($v['column_id']);
    if (!$column || $column->board_id !== $card->board_id) {
        abort(404);
    }
    $card->update([
        'column_id' => $column->id,
        'position'  => isset($v['position']) ? (int) $v['position'] : Card::maxPosition($column->id) + 1,
    ]);
    if (FmRequest::wantsJson()) {
        json_response(['ok' => true]);
    }
    back(['success' => 'Card moved.']);
}

/** Drag-and-drop: rewrite every position in the target column in one transaction. */
function cards_reorder(array $p): never
{
    require_once FM_ROOT . '/includes/controllers/boards.php';
    $board = fm_board($p['board']);
    $v = validate(['column_id' => 'required|uuid', 'card_ids' => 'required|array', 'card_ids.*' => 'uuid']);
    $column = Column::find($v['column_id']);
    if (!$column || $column->board_id !== $board->id) {
        abort(404);
    }
    $db = tl_db();
    $db->beginTransaction();
    // Scoped to this board: the original only checked the account, so a card
    // id from another board in the same account could be pulled into this
    // column with no board change.
    $upd = $db->prepare('UPDATE fm_cards SET column_id = ?, position = ?, updated_at = ? WHERE id = ? AND board_id = ? AND deleted_at IS NULL');
    foreach (array_values($v['card_ids']) as $i => $cardId) {
        $upd->execute([$column->id, $i + 1, now_sql(), $cardId, $board->id]);
    }
    $db->commit();
    json_response(['ok' => true]);
}

function cards_publish(array $p): never
{
    fm_card($p['card'])->update(['is_draft' => 0]);
    back(['success' => 'Card published.']);
}

function cards_unpublish(array $p): never
{
    fm_card($p['card'])->update(['is_draft' => 1]);
    back(['success' => 'Card unpublished.']);
}

function cards_change_board(array $p): never
{
    $card = fm_card($p['card']);
    $v = validate(['board_id' => 'required|uuid', 'column_id' => 'nullable|uuid']);
    $newBoard = Board::find($v['board_id']);
    if (!$newBoard || $newBoard->account_id !== fm_account()->id) {
        abort(404);
    }
    if (isset($v['column_id'])) {
        $newColumn = Column::find($v['column_id']);
        if (!$newColumn || $newColumn->board_id !== $newBoard->id) {
            abort(404);
        }
    } else {
        $newColumn = $newBoard->defaultColumn();
    }
    $card->update(['board_id' => $newBoard->id, 'column_id' => $newColumn?->id]);
    flash('success', "Card moved to \"{$newBoard->name}\".");
    redirect_to(route('cards.show', $card));
}

/* ── close / reopen ───────────────────────────────────────────────────── */

function closure_store(array $p): never
{
    $card = fm_card($p['card']);
    if ($card->isClosed()) {
        back(['_errors' => ['general' => ['Card is already closed.']]]);
    }
    $v = validate(['reason' => 'nullable|string|max:1000']);
    $card->close(fm_user(), $v['reason'] ?? null);
    back(['success' => 'Card closed.']);
}

function closure_destroy(array $p): never
{
    $card = fm_card($p['card']);
    if ($card->isOpen()) {
        back(['_errors' => ['general' => ['Card is already open.']]]);
    }
    $card->reopen();
    back(['success' => 'Card reopened.']);
}

/* ── assignments ──────────────────────────────────────────────────────── */

function assignments_store(array $p): never
{
    $card = fm_card($p['card']);
    $v = validate(['user_id' => 'required|uuid']);
    $user = FmUser::find($v['user_id']);
    if (!$user || $user->account_id !== fm_account()->id) {
        abort(404);
    }
    if (Assignment::countWhere('card_id = ? AND user_id = ?', [$card->id, $user->id]) === 0) {
        Assignment::create(['card_id' => $card->id, 'user_id' => $user->id, 'assigner_id' => fm_user()->id]);
    }
    back(['success' => "{$user->name} assigned."]);
}

function assignments_destroy(array $p): never
{
    $card = fm_card($p['card']);
    $assignment = Assignment::find($p['assignment']) ?? abort(404);
    if ($assignment->card_id !== $card->id) {
        abort(404);
    }
    $name = $assignment->user?->name;
    $assignment->delete();
    back(['success' => "{$name} unassigned."]);
}

/* ── reactions (toggle) ───────────────────────────────────────────────── */

function fm_toggle_reaction(string $type, string $id): void
{
    $v = validate(['emoji' => 'required|string|max:10']);
    $me = fm_user()->id;
    $existing = Reaction::firstWhere('reactable_type = ? AND reactable_id = ? AND user_id = ? AND emoji = ?', [$type, $id, $me, $v['emoji']]);
    if ($existing) {
        $existing->delete();
    } else {
        Reaction::create(['user_id' => $me, 'reactable_type' => $type, 'reactable_id' => $id, 'emoji' => $v['emoji']]);
    }
}

function fm_own_reaction(string $id): Reaction
{
    $reaction = Reaction::find($id) ?? abort(404);
    if ($reaction->user_id !== fm_user()->id) {
        abort(403);
    }
    return $reaction;
}

function reactions_store(array $p): never
{
    $card = fm_card($p['card']);
    fm_toggle_reaction(Card::MORPH, $card->id);
    back();
}

function reactions_destroy(array $p): never
{
    fm_card($p['card']);
    fm_own_reaction($p['reaction'])->delete();
    back();
}

function fm_comment_card(string $commentId): array
{
    $comment = Comment::find($commentId) ?? abort(404);
    $card = $comment->card;
    if (!$card || $card->account_id !== fm_account()->id) {
        abort(404);
    }
    return [$comment, $card];
}

function reactions_store_on_comment(array $p): never
{
    [$comment] = fm_comment_card($p['comment']);
    fm_toggle_reaction(Comment::MORPH, $comment->id);
    back();
}

function reactions_destroy_on_comment(array $p): never
{
    fm_comment_card($p['comment']);
    fm_own_reaction($p['reaction'])->delete();
    back();
}

/* ── steps ────────────────────────────────────────────────────────────── */

function fm_step(Card $card, string $id): Step
{
    $step = Step::find($id) ?? abort(404);
    if ($step->card_id !== $card->id) {
        abort(404);
    }
    return $step;
}

function steps_store(array $p): never
{
    $card = fm_card($p['card']);
    $v = validate(['title' => 'required|string|max:500']);
    Step::create(['card_id' => $card->id, 'title' => $v['title']]);
    back(['success' => 'Step added.']);
}

function steps_update(array $p): never
{
    $card = fm_card($p['card']);
    $step = fm_step($card, $p['step']);
    $v = validate(['title' => 'required|string|max:500']);
    $step->update(['title' => $v['title']]);
    back();
}

function steps_destroy(array $p): never
{
    $card = fm_card($p['card']);
    fm_step($card, $p['step'])->delete();
    back();
}

function steps_complete(array $p): never
{
    $card = fm_card($p['card']);
    fm_step($card, $p['step'])->complete(fm_user());
    back();
}

function steps_incomplete(array $p): never
{
    $card = fm_card($p['card']);
    fm_step($card, $p['step'])->incomplete();
    back();
}

/* ── comments ─────────────────────────────────────────────────────────── */

function fm_editable_comment(Card $card, string $id): Comment
{
    $comment = Comment::find($id) ?? abort(404);
    // Bound to the card in the URL: the original let /cards/A/comments/B edit
    // comment B on any card, as long as you wrote it or were an admin.
    if ($comment->card_id !== $card->id) {
        abort(404);
    }
    if (!$comment->isEditableBy(fm_user())) {
        abort(403);
    }
    return $comment;
}

function comments_store(array $p): never
{
    $card = fm_card($p['card']);
    $v = validate(['body' => 'required|string|max:50000']);
    $comment = Comment::create([
        'card_id'    => $card->id,
        'creator_id' => fm_user()->id,
        'body'       => $v['body'],
        'body_html'  => markdown($v['body']),
    ]);
    fm_parse_mentions($comment, $v['body']);
    flash('success', 'Comment added.');
    redirect_to(route('cards.show', $card) . "#comment-{$comment->id}");
}

/** @name mentions, matched against member names the way the original did. */
function fm_parse_mentions(Comment $comment, string $body): void
{
    preg_match_all('/@([a-zA-Z0-9_]+)/', $body, $m);
    if (empty($m[1])) {
        return;
    }
    $names = array_values(array_unique($m[1]));
    $users = FmUser::where('f.account_id = ? AND u.name IN (' . implode(',', array_fill(0, count($names), '?')) . ')', [fm_account()->id, ...$names]);
    foreach ($users as $user) {
        if (Mention::countWhere('comment_id = ? AND user_id = ?', [$comment->id, $user->id]) === 0) {
            Mention::create(['comment_id' => $comment->id, 'user_id' => $user->id]);
        }
    }
}

function comments_edit(array $p): never
{
    $card = fm_card($p['card']);
    $comment = fm_editable_comment($card, $p['comment']);
    view('comments.edit', compact('card', 'comment'));
}

function comments_update(array $p): never
{
    $card = fm_card($p['card']);
    $comment = fm_editable_comment($card, $p['comment']);
    $v = validate(['body' => 'required|string|max:50000']);
    $comment->update(['body' => $v['body'], 'body_html' => markdown($v['body'])]);
    flash('success', 'Comment updated.');
    redirect_to(route('cards.show', $card) . "#comment-{$comment->id}");
}

function comments_destroy(array $p): never
{
    $card = fm_card($p['card']);
    fm_editable_comment($card, $p['comment'])->delete();
    flash('success', 'Comment deleted.');
    redirect_to(route('cards.show', $card));
}

/* ── watch / pin ──────────────────────────────────────────────────────── */

function watch_store(array $p): never
{
    $card = fm_card($p['card']);
    Watch::firstOrCreate(fm_user()->id, $card->id);
    back(['success' => "You're now watching this card."]);
}

function watch_destroy(array $p): never
{
    $card = fm_card($p['card']);
    tl_db()->prepare('DELETE FROM fm_watches WHERE user_id = ? AND card_id = ?')->execute([fm_user()->id, $card->id]);
    back(['success' => "You've stopped watching this card."]);
}

function pin_store(array $p): never
{
    $card = fm_card($p['card']);
    if (Pin::countWhere('user_id = ? AND card_id = ?', [fm_user()->id, $card->id]) === 0) {
        Pin::create(['user_id' => fm_user()->id, 'card_id' => $card->id]);
    }
    back(['success' => 'Card pinned.']);
}

function pin_destroy(array $p): never
{
    $card = fm_card($p['card']);
    tl_db()->prepare('DELETE FROM fm_pins WHERE user_id = ? AND card_id = ?')->execute([fm_user()->id, $card->id]);
    back(['success' => 'Card unpinned.']);
}

/* ── client access to a card ──────────────────────────────────────────── */

function client_cards_store(array $p): never
{
    $card = Card::find($p['card']) ?? abort(404);
    if ($card->account_id !== fm_account()->id) {
        abort(403);
    }
    $v = validate(['client_id' => 'required|uuid']);
    $client = Client::find($v['client_id']);
    if (!$client || $client->account_id !== fm_account()->id) {
        abort(404);
    }
    $exists = tl_db()->prepare('SELECT 1 FROM fm_client_cards WHERE client_id = ? AND card_id = ?');
    $exists->execute([$client->id, $card->id]);
    if (!$exists->fetchColumn()) {
        tl_db()->prepare('INSERT INTO fm_client_cards (id, client_id, card_id, granted_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([fm_uuid(), $client->id, $card->id, fm_user()->id, now_sql(), now_sql()]);
    }
    flash('success', "{$client->name} added to this card.");
    redirect_to(route('cards.show', $card));
}

function client_cards_destroy(array $p): never
{
    $card = Card::find($p['card']) ?? abort(404);
    $client = Client::find($p['client']) ?? abort(404);
    if ($card->account_id !== fm_account()->id || $client->account_id !== fm_account()->id) {
        abort(403);
    }
    tl_db()->prepare('DELETE FROM fm_client_cards WHERE client_id = ? AND card_id = ?')->execute([$client->id, $card->id]);
    flash('success', "{$client->name} removed from this card.");
    redirect_to(route('cards.show', $card));
}

/* ── pagination ───────────────────────────────────────────────────────── */

/** Laravel's paginator output, simplified: "Showing x to y of z results" and Previous/Next. */
final class FmPaginator
{
    public function __construct(public int $total, public int $perPage, public int $page, public string $route) {}

    public function lastPage(): int { return max(1, (int) ceil($this->total / $this->perPage)); }

    public function links(): string
    {
        if ($this->total <= $this->perPage) {
            return '';
        }
        $q = FmRequest::input();
        unset($q['page']);
        $url = fn(int $n) => route($this->route, $q + ['page' => $n]);
        $from = ($this->page - 1) * $this->perPage + 1;
        $to   = min($this->total, $this->page * $this->perPage);
        $btn  = 'relative inline-flex items-center px-4 py-2 text-sm font-medium border border-gray-300 rounded-md';
        $out  = '<nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">';
        $out .= '<p class="text-sm text-gray-700 leading-5">Showing <span class="font-medium">' . $from . '</span> to <span class="font-medium">' . $to . '</span> of <span class="font-medium">' . $this->total . '</span> results</p><div class="flex gap-2">';
        $out .= $this->page > 1
            ? '<a href="' . e($url($this->page - 1)) . '" class="' . $btn . ' text-gray-700 bg-white hover:text-gray-500">&laquo; Previous</a>'
            : '<span class="' . $btn . ' text-gray-500 bg-white cursor-default">&laquo; Previous</span>';
        $out .= $this->page < $this->lastPage()
            ? '<a href="' . e($url($this->page + 1)) . '" class="' . $btn . ' text-gray-700 bg-white hover:text-gray-500">Next &raquo;</a>'
            : '<span class="' . $btn . ' text-gray-500 bg-white cursor-default">Next &raquo;</span>';
        return $out . '</div></nav>';
    }
}
