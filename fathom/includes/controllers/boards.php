<?php
/** EventController, BoardController, ColumnController, SubscriptionController, FilterController. */

declare(strict_types=1);

/** A board in the current account, or 404 (route binding + authorizeBoard). */
function fm_board(string $id): Board
{
    $board = Board::find($id);
    if (!$board || $board->account_id !== fm_account()->id) {
        abort(404);
    }
    return $board;
}

function fm_column(Board $board, string $id): Column
{
    $column = Column::find($id) ?? abort(404);
    if ($column->board_id !== $board->id) {
        abort(404);
    }
    return $column;
}

const FM_COLORS = ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#6b7280'];

/* ── events (home after sign-in) ──────────────────────────────────────── */

function events_index(array $p): never
{
    $user    = fm_user();
    $account = fm_account();

    // Newest 50 across the account's boards (the original took 100 per board, then 50 overall).
    $events = Event::where(
        'board_id IN (SELECT id FROM fm_boards WHERE account_id = ? AND deleted_at IS NULL)',
        [$account->id],
        'ORDER BY created_at DESC LIMIT 50'
    );

    $recentBoards = new FmCollection(array_values(array_filter(array_map(
        fn($a) => $a->board,
        Access::where('user_id = ?', [$user->id], 'ORDER BY last_accessed_at DESC LIMIT 5')->all()
    ))));

    $pinnedCards = new FmCollection(array_map(
        fn($pin) => $pin->card,
        Pin::where('user_id = ?', [$user->id])->all()
    ));
    $pinnedCards = $pinnedCards->filter();

    view('events.index', compact('events', 'recentBoards', 'pinnedCards'));
}

/* ── boards ───────────────────────────────────────────────────────────── */

function boards_index(array $p): never
{
    $account = fm_account();
    $boards = Board::where('account_id = ? AND archived_at IS NULL', [$account->id], 'ORDER BY name');
    $archivedBoards = Board::where('account_id = ? AND archived_at IS NOT NULL', [$account->id], 'ORDER BY archived_at DESC');
    view('boards.index', compact('boards', 'archivedBoards'));
}

function boards_show(array $p): never
{
    $board = fm_board($p['board']);
    $postponed = $board->runAutoPostpone();
    $board->refreshStalledCards();

    // Columns with their OPEN cards in position order (the eager-load constraint).
    $board->forget('columns');
    foreach ($board->columns as $column) {
        $column->setRelation('cards', Card::where('column_id = ? AND closed_at IS NULL', [$column->id], 'ORDER BY position'));
    }

    $filters = Filter::where('user_id = ? AND board_id = ?', [fm_user()->id, $board->id]);

    Access::touchFor(fm_user()->id, $board->id, ['last_accessed_at' => now_sql()]);

    if ($postponed > 0) {
        $first = $board->columns->first();
        $noun  = $postponed === 1 ? 'card' : 'cards';
        $GLOBALS['fm_flash_now']['info'] = "{$postponed} {$noun} sent back to \"{$first->name}\" — no activity in {$board->postpone_days} days.";
    }

    view('boards.show', compact('board', 'filters'));
}

function boards_create(array $p): never
{
    view('boards.create');
}

function boards_store(array $p): never
{
    $v = validate([
        'name'        => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'color'       => 'nullable|string|max:20',
        'is_public'   => 'boolean',
    ]);
    $account = fm_account();
    $board = Board::create([
        'account_id'  => $account->id,
        'creator_id'  => fm_user()->id,
        'name'        => $v['name'],
        'description' => $v['description'] ?? null,
        'color'       => $v['color'] ?? null,
        'is_public'   => fm_bool($v['is_public'] ?? 0),
    ]);
    foreach (['To Do', 'In Progress', 'Done'] as $i => $name) {
        Column::create(['account_id' => $account->id, 'board_id' => $board->id, 'name' => $name, 'position' => $i + 1]);
    }
    flash('success', "Board \"{$board->name}\" created.");
    redirect_to(route('boards.show', $board));
}

function boards_edit(array $p): never
{
    $board = fm_board($p['board']);
    view('boards.edit', compact('board'));
}

function boards_update(array $p): never
{
    $board = fm_board($p['board']);
    $v = validate([
        'name'          => 'required|string|max:255',
        'description'   => 'nullable|string|max:1000',
        'color'         => 'nullable|string|max:20',
        'is_public'     => 'boolean',
        'auto_postpone' => 'boolean',
        'postpone_days' => 'nullable|integer|min:1|max:365',
    ]);
    // Unticked checkboxes send nothing. The original only updated validated
    // keys, so a board could be made public or auto-postponing but never
    // switched back. The edit form owns all of these fields, so absent = off.
    $board->update([
        'name'          => $v['name'],
        'description'   => $v['description'] ?? null,
        'color'         => $v['color'] ?? null,
        'is_public'     => fm_bool($v['is_public'] ?? 0),
        'auto_postpone' => fm_bool($v['auto_postpone'] ?? 0),
        'postpone_days' => isset($v['postpone_days']) ? (int) $v['postpone_days'] : $board->postpone_days,
    ]);
    flash('success', 'Board updated.');
    redirect_to(route('boards.show', $board));
}

function boards_destroy(array $p): never
{
    $board = fm_board($p['board']);
    if (!fm_user()->isAdmin()) {
        abort(403, 'Only admins can perform this action.');
    }
    $board->delete();
    flash('success', "Board \"{$board->name}\" deleted.");
    redirect_to(route('boards.index'));
}

function boards_archive(array $p): never
{
    $board = fm_board($p['board']);
    $board->update(['archived_at' => now_sql()]);
    flash('success', "Board \"{$board->name}\" archived.");
    redirect_to(route('boards.index'));
}

function boards_unarchive(array $p): never
{
    $board = fm_board($p['board']);
    $board->update(['archived_at' => null]);
    flash('success', "Board \"{$board->name}\" restored.");
    redirect_to(route('boards.show', $board));
}

/** JSON download: the board, its columns, and its cards with comments, assignees, tags, steps. */
function boards_export(array $p): never
{
    $board = fm_board($p['board']);
    $cards = [];
    foreach (Card::where('board_id = ?', [$board->id]) as $card) {
        $cards[] = $card->jsonSerialize() + [
            'comments'  => $card->comments->toArray(),
            'assignees' => array_map(fn($u) => fm_user_public($u), $card->assignees->all()),
            'tags'      => $card->tags->toArray(),
            'steps'     => $card->steps->toArray(),
        ];
    }
    $data = ['board' => $board->jsonSerialize(), 'columns' => $board->columns->toArray(), 'cards' => $cards];
    $filename = Str::slug($board->name) . '-export-' . gmdate('Y-m-d') . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($data);
    throw new FmHalt();
}

/** A member as exported: the fields the original's User->toArray() exposed. */
function fm_user_public(FmUser $u): array
{
    $a = $u->jsonSerialize();
    unset($a['user_id']);
    return $a;
}

/* ── columns ──────────────────────────────────────────────────────────── */

function columns_create(array $p): never
{
    $board = fm_board($p['board']);
    view('columns.create', compact('board'));
}

function fm_column_rules(): array
{
    return ['name' => 'required|string|max:255', 'color' => 'nullable|string|max:20', 'cards_limit' => 'nullable|integer|min:1'];
}

function columns_store(array $p): never
{
    $board = fm_board($p['board']);
    $v = validate(fm_column_rules());
    Column::create([
        'account_id'  => fm_account()->id,
        'board_id'    => $board->id,
        'name'        => $v['name'],
        'color'       => $v['color'] ?? null,
        'cards_limit' => isset($v['cards_limit']) ? (int) $v['cards_limit'] : null,
    ]);
    flash('success', "Column \"{$v['name']}\" added.");
    redirect_to(route('boards.show', $board));
}

function columns_edit(array $p): never
{
    $board  = fm_board($p['board']);
    $column = fm_column($board, $p['column']);
    view('columns.edit', compact('board', 'column'));
}

function columns_update(array $p): never
{
    $board  = fm_board($p['board']);
    $column = fm_column($board, $p['column']);
    $v = validate(fm_column_rules());
    $column->update([
        'name'        => $v['name'],
        'color'       => $v['color'] ?? null,
        'cards_limit' => isset($v['cards_limit']) ? (int) $v['cards_limit'] : null,
    ]);
    flash('success', 'Column updated.');
    redirect_to(route('boards.show', $board));
}

function columns_destroy(array $p): never
{
    $board  = fm_board($p['board']);
    $column = fm_column($board, $p['column']);
    $column->delete();
    flash('success', "Column \"{$column->name}\" deleted. Cards have been unassigned.");
    redirect_to(route('boards.show', $board));
}

function columns_move_left(array $p): never
{
    $board = fm_board($p['board']);
    fm_column($board, $p['column'])->moveLeft();
    redirect_to(route('boards.show', $board));
}

function columns_move_right(array $p): never
{
    $board = fm_board($p['board']);
    fm_column($board, $p['column'])->moveRight();
    redirect_to(route('boards.show', $board));
}

/* ── board subscriptions ──────────────────────────────────────────────── */

function subscription_store(array $p): never
{
    $board = fm_board($p['board']);
    Access::touchFor(fm_user()->id, $board->id, ['involvement' => 'watching', 'last_accessed_at' => now_sql()]);
    back(['success' => "You're now subscribed to \"{$board->name}\"."]);
}

function subscription_destroy(array $p): never
{
    $board = fm_board($p['board']);
    tl_db()->prepare('DELETE FROM fm_accesses WHERE user_id = ? AND board_id = ?')->execute([fm_user()->id, $board->id]);
    back(['success' => "Unsubscribed from \"{$board->name}\"."]);
}

/* ── saved filters ────────────────────────────────────────────────────── */

function fm_filter(string $id): Filter
{
    $filter = Filter::find($id) ?? abort(404);
    if ($filter->user_id !== fm_user()->id) {
        abort(403);
    }
    return $filter;
}

function filters_store(array $p): never
{
    $board = fm_board($p['board']);
    $v = validate(['name' => 'required|string|max:255', 'params' => 'required|array']);
    Filter::create(['user_id' => fm_user()->id, 'board_id' => $board->id, 'name' => $v['name'], 'params' => $v['params']]);
    back(['success' => "Filter \"{$v['name']}\" saved."]);
}

function filters_update(array $p): never
{
    fm_board($p['board']);
    $filter = fm_filter($p['filter']);
    $v = validate(['name' => 'required|string|max:255', 'params' => 'required|array']);
    $filter->update(['name' => $v['name'], 'params' => $v['params']]);
    back(['success' => 'Filter updated.']);
}

function filters_destroy(array $p): never
{
    fm_board($p['board']);
    $filter = fm_filter($p['filter']);
    $filter->delete();
    back(['success' => "Filter \"{$filter->name}\" deleted."]);
}
