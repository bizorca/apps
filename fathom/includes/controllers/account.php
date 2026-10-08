<?php
/**
 * ClientController, TagController, CardTemplateController,
 * NotificationController, SearchController, AccountController, ExportController.
 */

declare(strict_types=1);

/* ── clients ──────────────────────────────────────────────────────────── */

function fm_own_client(string $id): Client
{
    $client = Client::find($id) ?? abort(404);
    if ($client->account_id !== fm_account()->id) {
        abort(403);
    }
    return $client;
}

function clients_index(array $p): never
{
    $clients = Client::where('account_id = ?', [fm_account()->id], 'ORDER BY name');
    view('clients.index', compact('clients'));
}

function clients_create(array $p): never
{
    view('clients.create');
}

function clients_store(array $p): never
{
    $v = validate(['name' => 'required|string|max:255', 'email_address' => 'required|email|max:255']);
    $email = strtolower(trim($v['email_address']));
    $account = fm_account();

    if (Client::countWhere('account_id = ? AND email_address = ?', [$account->id, $email]) > 0) {
        back(['_errors' => ['email_address' => ['A client with that email already exists.']], '_old' => $_POST]);
    }
    // A removed client is soft-deleted and still holds the unique
    // (account, email) slot. The original's INSERT then hit that index and
    // failed with a 500; bring the old row back instead.
    $s = tl_db()->prepare('SELECT id FROM fm_clients WHERE account_id = ? AND email_address = ? AND deleted_at IS NOT NULL');
    $s->execute([$account->id, $email]);
    if ($gone = $s->fetchColumn()) {
        tl_db()->prepare('UPDATE fm_clients SET name = ?, deleted_at = NULL, updated_at = ? WHERE id = ?')
               ->execute([$v['name'], now_sql(), $gone]);
    } else {
        Client::create(['account_id' => $account->id, 'name' => $v['name'], 'email_address' => $email]);
    }
    flash('success', 'Client created.');
    redirect_to(route('clients.index'));
}

function clients_edit(array $p): never
{
    $client = fm_own_client($p['client']);
    view('clients.edit', compact('client'));
}

function clients_update(array $p): never
{
    $client = fm_own_client($p['client']);
    $v = validate(['name' => 'required|string|max:255', 'email_address' => 'required|email|max:255']);
    $email = strtolower(trim($v['email_address']));
    $s = tl_db()->prepare('SELECT 1 FROM fm_clients WHERE account_id = ? AND email_address = ? AND id != ?');
    $s->execute([fm_account()->id, $email, $client->id]);
    if ($s->fetchColumn()) {
        back(['_errors' => ['email_address' => ['A client with that email already exists.']], '_old' => $_POST]);
    }
    $client->update(['name' => $v['name'], 'email_address' => $email]);
    flash('success', 'Client updated.');
    redirect_to(route('clients.index'));
}

function clients_destroy(array $p): never
{
    fm_own_client($p['client'])->delete();
    flash('success', 'Client removed.');
    redirect_to(route('clients.index'));
}

/* ── tags ─────────────────────────────────────────────────────────────── */

function fm_tag(string $id): Tag
{
    $tag = Tag::find($id) ?? abort(404);
    if ($tag->account_id !== fm_account()->id) {
        abort(404);
    }
    return $tag;
}

/** Laravel had no unique rule here, so a duplicate name was a 500 from the index; say so instead. */
function fm_tag_name_taken(string $name, ?string $exceptId = null): bool
{
    $s = tl_db()->prepare('SELECT 1 FROM fm_tags WHERE account_id = ? AND name = ?' . ($exceptId ? ' AND id != ?' : ''));
    $s->execute($exceptId ? [fm_account()->id, $name, $exceptId] : [fm_account()->id, $name]);
    return (bool) $s->fetchColumn();
}

function tags_index(array $p): never
{
    $tags = Tag::where('account_id = ?', [fm_account()->id], 'ORDER BY name');
    view('tags.index', compact('tags'));
}

function tags_create(array $p): never
{
    view('tags.create');
}

function tags_store(array $p): never
{
    $v = validate(['name' => 'required|string|max:100', 'color' => 'nullable|string|max:20']);
    if (fm_tag_name_taken($v['name'])) {
        back(['_errors' => ['name' => ['A tag with that name already exists.']], '_old' => $_POST]);
    }
    $tag = Tag::create(['account_id' => fm_account()->id, 'name' => $v['name'], 'color' => $v['color'] ?? null]);
    flash('success', "Tag \"{$tag->name}\" created.");
    redirect_to(route('tags.index'));
}

function tags_edit(array $p): never
{
    $tag = fm_tag($p['tag']);
    view('tags.edit', compact('tag'));
}

function tags_update(array $p): never
{
    $tag = fm_tag($p['tag']);
    $v = validate(['name' => 'required|string|max:100', 'color' => 'nullable|string|max:20']);
    if (fm_tag_name_taken($v['name'], $tag->id)) {
        back(['_errors' => ['name' => ['A tag with that name already exists.']], '_old' => $_POST]);
    }
    $tag->update(['name' => $v['name'], 'color' => $v['color'] ?? null]);
    flash('success', "Tag \"{$v['name']}\" updated.");
    redirect_to(route('tags.index'));
}

function tags_destroy(array $p): never
{
    $tag = fm_tag($p['tag']);
    $name = $tag->name;
    $tag->delete();
    flash('success', "Tag \"{$name}\" deleted.");
    redirect_to(route('tags.index'));
}

/* ── card templates ───────────────────────────────────────────────────── */

function fm_template(string $id): CardTemplate
{
    $t = CardTemplate::find($id) ?? abort(404);
    if ($t->account_id !== fm_account()->id) {
        abort(404);
    }
    return $t;
}

function fm_template_rules(): array
{
    return [
        'name'              => 'required|string|max:255',
        'title_pattern'     => 'nullable|string|max:500',
        'description'       => 'nullable|string',
        'default_column_id' => 'nullable|uuid',
        'tags'              => 'array',
        'tags.*'            => 'uuid',
        'steps'             => 'array',
        'steps.*'           => 'string|max:500',
    ];
}

/** A default column only if it belongs to this account. */
function fm_template_column(?string $id): ?string
{
    if ($id === null) {
        return null;
    }
    $c = Column::find($id);
    return $c && $c->account_id === fm_account()->id ? $c->id : null;
}

function fm_template_boards(): FmCollection
{
    return Board::where('account_id = ? AND archived_at IS NULL', [fm_account()->id], 'ORDER BY name');
}

function templates_index(array $p): never
{
    $templates = CardTemplate::where('account_id = ?', [fm_account()->id], 'ORDER BY name');
    view('card_templates.index', compact('templates'));
}

function templates_create(array $p): never
{
    $boards = fm_template_boards();
    $tags   = Tag::where('account_id = ?', [fm_account()->id], 'ORDER BY name');
    view('card_templates.create', compact('boards', 'tags'));
}

function fm_save_template_steps(CardTemplate $t, array $steps): void
{
    foreach (array_values($steps) as $i => $title) {
        if ($title !== null && trim((string) $title) !== '') {
            CardTemplateStep::create(['card_template_id' => $t->id, 'title' => $title, 'position' => $i + 1]);
        }
    }
}

function templates_store(array $p): never
{
    $v = validate(fm_template_rules());
    $t = CardTemplate::create([
        'account_id'        => fm_account()->id,
        'creator_id'        => fm_user()->id,
        'name'              => $v['name'],
        'title_pattern'     => $v['title_pattern'] ?? null,
        'description'       => $v['description'] ?? null,
        'default_column_id' => fm_template_column($v['default_column_id'] ?? null),
    ]);
    $t->syncTags(fm_account_tag_ids_t($v['tags'] ?? []));
    fm_save_template_steps($t, $v['steps'] ?? []);
    flash('success', "Template \"{$t->name}\" created.");
    redirect_to(route('card_templates.index'));
}

function templates_edit(array $p): never
{
    $cardTemplate = fm_template($p['card_template']);
    $boards = fm_template_boards();
    $tags   = Tag::where('account_id = ?', [fm_account()->id], 'ORDER BY name');
    view('card_templates.edit', compact('cardTemplate', 'boards', 'tags'));
}

function templates_update(array $p): never
{
    $t = fm_template($p['card_template']);
    $v = validate(fm_template_rules());
    $t->update([
        'name'              => $v['name'],
        'title_pattern'     => $v['title_pattern'] ?? null,
        'description'       => $v['description'] ?? null,
        'default_column_id' => fm_template_column($v['default_column_id'] ?? null),
    ]);
    $t->syncTags(fm_account_tag_ids_t($v['tags'] ?? []));
    tl_db()->prepare('DELETE FROM fm_card_template_steps WHERE card_template_id = ?')->execute([$t->id]);
    fm_save_template_steps($t, $v['steps'] ?? []);
    flash('success', "Template \"{$t->name}\" updated.");
    redirect_to(route('card_templates.index'));
}

function templates_destroy(array $p): never
{
    $t = fm_template($p['card_template']);
    $name = $t->name;
    $t->delete();
    flash('success', "Template \"{$name}\" deleted.");
    redirect_to(route('card_templates.index'));
}

/** Tag ids limited to this account (cards.php has the same; keep this file standalone). */
function fm_account_tag_ids_t(array $ids): array
{
    $ids = array_values(array_unique(array_filter($ids, 'fm_is_uuid')));
    if (!$ids) {
        return [];
    }
    return Tag::where('account_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', [fm_account()->id, ...$ids])->pluck('id')->all();
}

/* ── notifications ────────────────────────────────────────────────────── */

function notifications_index(array $p): never
{
    $perPage = 30;
    $page    = max(1, (int) request('page', 1));
    $uid     = fm_user()->id;
    $total   = Notification::countWhere('user_id = ?', [$uid]);
    $notifications = Notification::where('user_id = ?', [$uid], 'ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
    require_once FM_ROOT . '/includes/controllers/cards.php';
    $paginator = new FmPaginator($total, $perPage, $page, 'notifications.index');
    view('notifications.index', compact('notifications', 'paginator'));
}

function notifications_read(array $p): never
{
    $n = Notification::find($p['notification']) ?? abort(404);
    if ($n->user_id !== fm_user()->id) {
        abort(403);
    }
    $n->markAsRead();
    back();
}

function notifications_read_all(array $p): never
{
    tl_db()->prepare('UPDATE fm_notifications SET read_at = ?, updated_at = ? WHERE user_id = ? AND read_at IS NULL')
           ->execute([now_sql(), now_sql(), fm_user()->id]);
    back(['success' => 'All notifications marked as read.']);
}

function notifications_settings(array $p): never
{
    $user = fm_user();
    view('notifications.settings', compact('user'));
}

function notifications_settings_update(array $p): never
{
    $v = validate(['notification_email' => 'nullable|email', 'notification_digest' => 'boolean']);
    fm_user()->update([
        'notification_email'  => $v['notification_email'] ?? null,
        'notification_digest' => fm_bool($v['notification_digest'] ?? 0),
    ]);
    back(['success' => 'Notification settings updated.']);
}

/* ── search ───────────────────────────────────────────────────────────── */

function search_index(array $p): never
{
    $query   = (string) request('q', '');
    $results = new FmCollection();
    if (strlen(trim($query)) >= 2) {
        $acc  = fm_account()->id;
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $cards = Card::where('account_id = ? AND (title LIKE ? OR description LIKE ?)', [$acc, $like, $like], 'ORDER BY updated_at DESC LIMIT 20');
        $boards = Board::where('account_id = ? AND (name LIKE ? OR description LIKE ?) AND archived_at IS NULL', [$acc, $like, $like], 'LIMIT 5');
        $items = [];
        foreach ($boards as $b) {
            $items[] = ['type' => 'board', 'item' => $b];
        }
        foreach ($cards as $c) {
            $items[] = ['type' => 'card', 'item' => $c];
        }
        $results = new FmCollection($items);
    }
    view('searches.index', compact('query', 'results'));
}

/* ── account ──────────────────────────────────────────────────────────── */

function fm_require_admin(): void
{
    if (!fm_user()->isAdmin()) {
        abort(403, 'Only admins can perform this action.');
    }
}

function account_show(array $p): never
{
    $account = fm_account();
    view('account.show', compact('account'));
}

function account_profile(array $p): never
{
    $user = fm_user();
    view('account.profile', compact('user'));
}

/**
 * Name and email now belong to the shared tools account. Name is still
 * editable here (it writes the shared name); email changes are not, since that
 * address is the sign-in for every tool.
 */
function account_profile_update(array $p): never
{
    $user = fm_user();
    $v = validate([
        'name'      => 'required|string|max:255',
        'time_zone' => 'nullable|string|max:100',
        'avatar'    => 'nullable|image|max:2048',
    ]);

    tl_db()->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([mb_substr($v['name'], 0, 100), $user->user_id]);
    $data = ['time_zone' => $v['time_zone'] ?? null];

    if (!empty($v['avatar'])) {
        $info = getimagesize($v['avatar']['tmp_name']);
        $ext  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2]];
        $dir  = FM_DATA . '/avatars';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'avatars/' . Str::random(40) . '.' . $ext;
        move_uploaded_file($v['avatar']['tmp_name'], FM_DATA . '/' . $name);
        if ($user->avatar_path && is_file(FM_DATA . '/' . $user->avatar_path)) {
            @unlink(FM_DATA . '/' . $user->avatar_path);
        }
        $data['avatar_path'] = $name;
    }
    $user->update($data);
    back(['success' => 'Profile updated.']);
}

/** Avatars are stored outside the web root; members of the same account may see each other's. */
function account_avatar(array $p): never
{
    $u = FmUser::find($p['user']) ?? abort(404);
    if ($u->account_id !== fm_account()->id || !$u->avatar_path) {
        abort(404);
    }
    $file = FM_DATA . '/' . $u->avatar_path;
    if (!is_file($file) || str_contains($u->avatar_path, '..')) {
        abort(404);
    }
    header('Content-Type: ' . (getimagesize($file)['mime'] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    readfile($file);
    throw new FmHalt();
}

/** Passwords belong to the shared account now. */
function account_password(array $p): never
{
    redirect_to('/account/settings.php');
}

function account_members(array $p): never
{
    $account = fm_account();
    $members = $account->users;
    view('account.members', compact('account', 'members'));
}

function account_remove_member(array $p): never
{
    fm_require_admin();
    $member = FmUser::find($p['user']) ?? abort(404);
    if ($member->account_id !== fm_account()->id) {
        abort(404);
    }
    if ($member->id === fm_user()->id) {
        back(['_errors' => ['general' => ["You can't remove yourself."]]]);
    }
    $member->delete();
    back(['success' => "{$member->name} has been removed."]);
}

function account_invite(array $p): never
{
    fm_require_admin();
    $account = fm_account();
    view('account.invite', compact('account'));
}

/** The original never emailed the invite either; it flashes the link to share. */
function account_send_invite(array $p): never
{
    fm_require_admin();
    validate(['email' => 'required|email']);
    $url = fm_absolute(route('join', fm_account()->invite_code));
    back(['success' => "Invite link: {$url}"]);
}

function account_danger(array $p): never
{
    fm_require_admin();
    view('account.danger');
}

/**
 * Cancels the Fathom workspace (data kept, as before). The shared tools
 * account and its sign-in are untouched; the original signed the user out
 * because the account WAS their login.
 */
function account_cancel(array $p): never
{
    fm_require_admin();
    $v = FmRequest::input('confirmation');
    if ($v !== 'DELETE') {
        back(['_errors' => ['confirmation' => [$v === null ? 'The confirmation field is required.' : 'The selected confirmation is invalid.']]]);
    }
    fm_account()->cancel();
    flash('info', 'Your account has been cancelled.');
    redirect_to(route('home'));
}

/* ── data export ──────────────────────────────────────────────────────── */

function export_create(array $p): never
{
    $exports = Export::where('user_id = ?', [fm_user()->id], 'ORDER BY created_at DESC LIMIT 5');
    view('account.export', compact('exports'));
}

/**
 * Builds the export now, in the request. The original queued DataExportJob on
 * a database queue that no worker ever ran on SiteGround, so every export sat
 * at "Pending" forever, and its Download link pointed at a URL nothing served.
 */
function export_store(array $p): never
{
    $account = fm_account();
    $user    = fm_user();
    $export  = Export::create(['account_id' => $account->id, 'user_id' => $user->id, 'status' => 'processing']);

    try {
        $boards = [];
        foreach (Board::where('account_id = ?', [$account->id]) as $board) {
            $b = $board->jsonSerialize();
            $b['columns'] = $board->columns->toArray();
            $b['cards'] = [];
            foreach (Card::where('board_id = ?', [$board->id]) as $card) {
                $c = $card->jsonSerialize();
                $c['comments'] = array_map(fn($cm) => $cm->jsonSerialize() + ['creator' => $cm->creator ? fm_user_public_a($cm->creator) : null], $card->comments->all());
                $c['assignments'] = array_map(fn($a) => $a->jsonSerialize() + ['user' => $a->user ? fm_user_public_a($a->user) : null], $card->assignments->all());
                $c['tags']  = $card->tags->toArray();
                $c['steps'] = $card->steps->toArray();
                $b['cards'][] = $c;
            }
            $boards[] = $b;
        }
        $data = [
            'exported_at' => gmdate('Y-m-d\TH:i:sP'),
            'account'     => ['name' => $account->name, 'created_at' => $account->created_at?->format('Y-m-d\TH:i:sP')],
            'boards'      => $boards,
        ];
        $dir = FM_DATA . '/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = 'exports/' . $export->id . '.json';
        file_put_contents(FM_DATA . '/' . $path, json_encode($data, JSON_PRETTY_PRINT));
        $export->update(['status' => 'completed', 'file_path' => $path, 'completed_at' => now_sql()]);

        fm_mail(
            (string) $user->email_address,
            'Your Fathom export is ready',
            "Hi {$user->name},\n\nYour Fathom data export is ready:\n\n"
            . fm_absolute(route('account.export.download', $export)) . "\n\nYou'll need to be signed in to download it.\n"
        );
    } catch (Throwable $e) {
        $export->update(['status' => 'failed', 'failed_at' => now_sql(), 'error_message' => $e->getMessage()]);
        back(['_errors' => ['general' => ['The export failed. Please try again.']]]);
    }
    // Outside the try: back() ends the request by throwing FmHalt.
    back(['success' => 'Export ready. Download it below.']);
}

function fm_user_public_a(FmUser $u): array
{
    $a = $u->jsonSerialize();
    unset($a['user_id']);
    return $a;
}

function export_download(array $p): never
{
    $export = Export::find($p['export']) ?? abort(404);
    if ($export->account_id !== fm_account()->id || !$export->isCompleted() || !$export->file_path) {
        abort(404);
    }
    $file = FM_DATA . '/' . $export->file_path;
    if (!is_file($file)) {
        abort(404);
    }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="fathom-export-' . $export->created_at->format('Y-m-d') . '.json"');
    readfile($file);
    throw new FmHalt();
}
