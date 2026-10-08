<?php
/**
 * Every route of the original routes/web.php, same names, same paths, same
 * methods, dispatched from public/index.php. Handlers live in controllers/.
 *
 * Middleware:
 *   auth    signed in to the shared tools account AND a member of a Fathom
 *           account that is not cancelled (was Laravel's auth + 'account')
 *   client  a client portal session (Fathom-side, magic-link sign-in)
 *   sysop   Fathom platform admin, and not currently impersonating
 *
 * Every non-GET request needs a CSRF token (_token field or X-CSRF-TOKEN
 * header), as Laravel's VerifyCsrfToken required.
 */

declare(strict_types=1);

/** [name, methods, pattern, handler, middleware[]] in registration order. */
function fm_routes(): array
{
    static $routes = null;
    if ($routes !== null) {
        return $routes;
    }

    $A = ['auth'];
    $r = [
        // Marketing pages (public; members are sent to their boards)
        ['home',     'GET', '/',         'marketing_home', []],
        ['features', 'GET', '/features', 'marketing_features', []],
        ['about',    'GET', '/about',    'marketing_about', []],
        ['services', 'GET', '/services', 'marketing_services', []],

        // Client portal (separate client session)
        ['portal.login',           'GET',   '/portal/login',  'portal_login', []],
        ['portal.login.store',     'POST',  '/portal/login',  'portal_login_store', []],
        ['portal.logout',          'POST',  '/portal/logout', 'portal_logout', []],
        ['portal.dashboard',       'GET',   '/portal',        'portal_dashboard', ['client']],
        ['portal.cards.show',      'GET',   '/portal/cards/{card}', 'portal_card', ['client']],
        ['portal.steps.complete',  'PATCH', '/portal/cards/{card}/steps/{step}/complete',   'portal_step_complete', ['client']],
        ['portal.steps.incomplete','PATCH', '/portal/cards/{card}/steps/{step}/incomplete', 'portal_step_incomplete', ['client']],
        ['portal.comments.store',  'POST',  '/portal/cards/{card}/comments', 'portal_comment_store', ['client']],

        ['up', 'GET', '/up', 'health', []],

        // Retired: sign-in is the shared tools account now. Kept so old links land somewhere sensible.
        ['sso.redirect',      'GET',    '/sso/redirect', 'auth_to_shared_login', []],
        ['sso.callback',      'GET',    '/sso/callback', 'auth_to_shared_login', []],
        ['sessions.new',      'GET',    '/login',        'auth_to_shared_login', []],
        ['sessions.create',   'POST',   '/login',        'auth_to_shared_login', []],
        ['sessions.destroy',  'DELETE', '/logout',       'auth_logout', []],
        ['magic_links.store', 'POST',   '/magic-links',  'auth_to_shared_login', []],
        ['magic_links.show',  'GET',    '/magic-links/{token}', 'magic_link_show', []],

        // Workspace signup / join (needs a shared account first)
        ['signup',       'GET',  '/signup',      'signup_create', []],
        ['signup.store', 'POST', '/signup',      'signup_store', []],
        ['join',         'GET',  '/join/{code}', 'join_show', []],
        ['join.store',   'POST', '/join/{code}', 'join_store', []],

        // Public sharing
        ['public.boards.show', 'GET', '/public/boards/{board:share_token}', 'public_board', []],
        ['public.cards.show',  'GET', '/public/cards/{card:share_token}',   'public_card', []],
        ['qr_codes.show',      'GET', '/qr-codes/{id}', 'qr_code', []],

        // ── authenticated ──────────────────────────────────────────────────
        ['events.index', 'GET', '/events', 'events_index', $A],

        ['boards.index',   'GET',       '/boards',               'boards_index', $A],
        ['boards.create',  'GET',       '/boards/create',        'boards_create', $A],
        ['boards.store',   'POST',      '/boards',               'boards_store', $A],
        ['boards.show',    'GET',       '/boards/{board}',       'boards_show', $A],
        ['boards.edit',    'GET',       '/boards/{board}/edit',  'boards_edit', $A],
        ['boards.update',  'PUT|PATCH', '/boards/{board}',       'boards_update', $A],
        ['boards.destroy', 'DELETE',    '/boards/{board}',       'boards_destroy', $A],
        ['boards.archive',   'PATCH', '/boards/{board}/archive',   'boards_archive', $A],
        ['boards.unarchive', 'PATCH', '/boards/{board}/unarchive', 'boards_unarchive', $A],
        ['boards.export',    'GET',   '/boards/{board}/export',    'boards_export', $A],
        ['boards.cards.reorder', 'POST', '/boards/{board}/cards/reorder', 'cards_reorder', $A],

        ['boards.columns.create',  'GET',       '/boards/{board}/columns/create',        'columns_create', $A],
        ['boards.columns.store',   'POST',      '/boards/{board}/columns',               'columns_store', $A],
        ['boards.columns.edit',    'GET',       '/boards/{board}/columns/{column}/edit', 'columns_edit', $A],
        ['boards.columns.update',  'PUT|PATCH', '/boards/{board}/columns/{column}',      'columns_update', $A],
        ['boards.columns.destroy', 'DELETE',    '/boards/{board}/columns/{column}',      'columns_destroy', $A],
        ['boards.columns.move_left',  'PATCH', '/boards/{board}/columns/{column}/move-left',  'columns_move_left', $A],
        ['boards.columns.move_right', 'PATCH', '/boards/{board}/columns/{column}/move-right', 'columns_move_right', $A],

        ['boards.subscribe',   'POST',   '/boards/{board}/subscribe',   'subscription_store', $A],
        ['boards.unsubscribe', 'DELETE', '/boards/{board}/unsubscribe', 'subscription_destroy', $A],

        ['boards.filters.store',   'POST',      '/boards/{board}/filters',          'filters_store', $A],
        ['boards.filters.update',  'PUT|PATCH', '/boards/{board}/filters/{filter}', 'filters_update', $A],
        ['boards.filters.destroy', 'DELETE',    '/boards/{board}/filters/{filter}', 'filters_destroy', $A],

        ['cards.index',   'GET',       '/cards',             'cards_index', $A],
        ['cards.create',  'GET',       '/cards/create',      'cards_create', $A],
        ['cards.store',   'POST',      '/cards',             'cards_store', $A],
        ['cards.show',    'GET',       '/cards/{card}',      'cards_show', $A],
        ['cards.edit',    'GET',       '/cards/{card}/edit', 'cards_edit', $A],
        ['cards.update',  'PUT|PATCH', '/cards/{card}',      'cards_update', $A],
        ['cards.destroy', 'DELETE',    '/cards/{card}',      'cards_destroy', $A],
        ['cards.duplicate',    'POST',  '/cards/{card}/duplicate', 'cards_duplicate', $A],
        ['cards.move',         'PATCH', '/cards/{card}/move',      'cards_move', $A],
        ['cards.publish',      'PATCH', '/cards/{card}/publish',   'cards_publish', $A],
        ['cards.unpublish',    'PATCH', '/cards/{card}/unpublish', 'cards_unpublish', $A],
        ['cards.close',        'PATCH', '/cards/{card}/close',     'closure_store', $A],
        ['cards.reopen',       'PATCH', '/cards/{card}/reopen',    'closure_destroy', $A],
        ['cards.change_board', 'PATCH', '/cards/{card}/board',     'cards_change_board', $A],

        ['clients.index',   'GET',       '/clients',               'clients_index', $A],
        ['clients.create',  'GET',       '/clients/create',        'clients_create', $A],
        ['clients.store',   'POST',      '/clients',               'clients_store', $A],
        ['clients.edit',    'GET',       '/clients/{client}/edit', 'clients_edit', $A],
        ['clients.update',  'PUT|PATCH', '/clients/{client}',      'clients_update', $A],
        ['clients.destroy', 'DELETE',    '/clients/{client}',      'clients_destroy', $A],

        ['cards.assignments.store',   'POST',   '/cards/{card}/assignments',              'assignments_store', $A],
        ['cards.assignments.destroy', 'DELETE', '/cards/{card}/assignments/{assignment}', 'assignments_destroy', $A],
        ['cards.reactions.store',     'POST',   '/cards/{card}/reactions',            'reactions_store', $A],
        ['cards.reactions.destroy',   'DELETE', '/cards/{card}/reactions/{reaction}', 'reactions_destroy', $A],
        ['cards.steps.store',      'POST',      '/cards/{card}/steps',                 'steps_store', $A],
        ['cards.steps.update',     'PUT|PATCH', '/cards/{card}/steps/{step}',          'steps_update', $A],
        ['cards.steps.destroy',    'DELETE',    '/cards/{card}/steps/{step}',          'steps_destroy', $A],
        ['cards.steps.complete',   'PATCH',     '/cards/{card}/steps/{step}/complete', 'steps_complete', $A],
        ['cards.steps.incomplete', 'PATCH',     '/cards/{card}/steps/{step}/incomplete', 'steps_incomplete', $A],
        ['cards.comments.store',   'POST',      '/cards/{card}/comments',                'comments_store', $A],
        ['cards.comments.edit',    'GET',       '/cards/{card}/comments/{comment}/edit', 'comments_edit', $A],
        ['cards.comments.update',  'PUT|PATCH', '/cards/{card}/comments/{comment}',      'comments_update', $A],
        ['cards.comments.destroy', 'DELETE',    '/cards/{card}/comments/{comment}',      'comments_destroy', $A],
        ['cards.watch',   'POST',   '/cards/{card}/watch',   'watch_store', $A],
        ['cards.unwatch', 'DELETE', '/cards/{card}/unwatch', 'watch_destroy', $A],
        ['cards.pin',     'POST',   '/cards/{card}/pin',     'pin_store', $A],
        ['cards.unpin',   'DELETE', '/cards/{card}/unpin',   'pin_destroy', $A],
        ['cards.clients.store',   'POST',   '/cards/{card}/clients',          'client_cards_store', $A],
        ['cards.clients.destroy', 'DELETE', '/cards/{card}/clients/{client}', 'client_cards_destroy', $A],

        ['comments.reactions.store',   'POST',   '/comments/{comment}/reactions',            'reactions_store_on_comment', $A],
        ['comments.reactions.destroy', 'DELETE', '/comments/{comment}/reactions/{reaction}', 'reactions_destroy_on_comment', $A],

        ['tags.index',   'GET',       '/tags',            'tags_index', $A],
        ['tags.create',  'GET',       '/tags/create',     'tags_create', $A],
        ['tags.store',   'POST',      '/tags',            'tags_store', $A],
        ['tags.edit',    'GET',       '/tags/{tag}/edit', 'tags_edit', $A],
        ['tags.update',  'PUT|PATCH', '/tags/{tag}',      'tags_update', $A],
        ['tags.destroy', 'DELETE',    '/tags/{tag}',      'tags_destroy', $A],

        ['card-templates.index',   'GET',       '/card-templates',                      'templates_index', $A],
        ['card-templates.create',  'GET',       '/card-templates/create',               'templates_create', $A],
        ['card-templates.store',   'POST',      '/card-templates',                      'templates_store', $A],
        ['card-templates.edit',    'GET',       '/card-templates/{card_template}/edit', 'templates_edit', $A],
        ['card-templates.update',  'PUT|PATCH', '/card-templates/{card_template}',      'templates_update', $A],
        ['card-templates.destroy', 'DELETE',    '/card-templates/{card_template}',      'templates_destroy', $A],

        ['notifications.index',           'GET',   '/notifications',                      'notifications_index', $A],
        ['notifications.read',            'PATCH', '/notifications/{notification}/read',  'notifications_read', $A],
        ['notifications.read_all',        'PATCH', '/notifications/read-all',             'notifications_read_all', $A],
        ['notifications.settings',        'GET',   '/notifications/settings',             'notifications_settings', $A],
        ['notifications.settings.update', 'PATCH', '/notifications/settings',             'notifications_settings_update', $A],

        ['search', 'GET', '/search', 'search_index', $A],

        ['account.show',            'GET',    '/account',                  'account_show', $A],
        ['account.profile',         'GET',    '/account/profile',          'account_profile', $A],
        ['account.profile.update',  'PATCH',  '/account/profile',          'account_profile_update', $A],
        ['account.password',        'GET',    '/account/password',         'account_password', $A],
        ['account.password.update', 'PATCH',  '/account/password',         'account_password', $A],
        ['account.members',         'GET',    '/account/members',          'account_members', $A],
        ['account.members.destroy', 'DELETE', '/account/members/{user}',   'account_remove_member', $A],
        ['account.invite',          'GET',    '/account/invite',           'account_invite', $A],
        ['account.invite.send',     'POST',   '/account/invite',           'account_send_invite', $A],
        ['account.export',          'GET',    '/account/export',           'export_create', $A],
        ['account.export.store',    'POST',   '/account/export',           'export_store', $A],
        ['account.export.download', 'GET',    '/account/export/{export}/download', 'export_download', $A],
        ['account.danger',          'GET',    '/account/danger',           'account_danger', $A],
        ['account.cancel',          'DELETE', '/account/cancel',           'account_cancel', $A],
        ['account.avatar',          'GET',    '/account/avatar/{user}',    'account_avatar', $A],

        ['admin.dashboard',        'GET',   '/admin',                          'admin_dashboard', ['auth', 'sysop']],
        ['admin.accounts.index',   'GET',   '/admin/accounts',                 'admin_accounts', ['auth', 'sysop']],
        ['admin.accounts.show',    'GET',   '/admin/accounts/{account}',       'admin_account', ['auth', 'sysop']],
        ['admin.accounts.cancel',  'PATCH', '/admin/accounts/{account}/cancel', 'admin_cancel_account', ['auth', 'sysop']],
        ['admin.impersonate.store', 'POST', '/admin/impersonate/{user}',       'impersonate_store', ['auth', 'sysop']],
        // Stop impersonating: auth only (the impersonated member is not a sysop)
        ['admin.impersonate.destroy', 'DELETE', '/admin/impersonate',          'impersonate_destroy', ['auth']],
    ];

    // The original controllers redirect to 'card_templates.index', a name the
    // resource route never had, so every template save ended in a 500 after
    // the write. Register the underscore names too.
    foreach ($r as $route) {
        if (str_starts_with($route[0], 'card-templates.')) {
            $alias = $route;
            $alias[0] = str_replace('card-templates.', 'card_templates.', $route[0]);
            $r[] = $alias;
        }
    }

    $routes = [];
    foreach ($r as $route) {
        $routes[$route[0]] ??= $route;
    }
    return $routes;
}

/**
 * App path and leftover query for a named route. $params: one value, a model,
 * a list (positional), or an assoc array (named; unknown keys -> query).
 */
function fm_route_path(string $name, mixed $params = [], ?array &$query = null): string
{
    $route = fm_routes()[$name] ?? throw new RuntimeException("Route [{$name}] not defined.");
    $params = is_array($params) ? $params : [$params];
    $query  = [];

    $positional = array_values(array_filter($params, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY));
    $named      = array_filter($params, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);

    $path = preg_replace_callback('/\{(\w+)(?::(\w+))?\}/', function ($m) use (&$positional, &$named) {
        $key   = $m[1];
        $field = $m[2] ?? 'id';
        if (array_key_exists($key, $named)) {
            $v = $named[$key];
            unset($named[$key]);
        } else {
            $v = array_shift($positional);
        }
        if ($v instanceof FmModel) {
            $v = $v->$field;
        }
        return rawurlencode((string) $v);
    }, $route[2]);

    $query = $named;
    return $path;
}

function route(string $name, mixed $params = []): string
{
    $path = fm_route_path($name, $params, $query);
    return fm_url($path, $query);
}

/* ───────────────────────────────────────────────────────── auth state ──── */

/** The Fathom member acting in this request (the impersonated one, if any). */
function fm_user(bool $forget = false): ?FmUser
{
    static $cache = false;
    if ($forget) {
        $cache = false;
        return null;
    }
    if ($cache !== false) {
        return $cache;
    }
    $shared = tl_user();
    $real   = $shared ? FmUser::forSharedUser((int) $shared['id']) : null;
    $cache  = $real;
    $imp    = $_SESSION['fm_impersonating'] ?? null;
    if ($real && $imp && $real->isSysop()) {
        $cache = FmUser::find((string) $imp) ?? $real;
    } elseif ($imp && session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['fm_impersonating']);
    }
    return $cache;
}

function fm_impersonating(): bool
{
    return !empty($_SESSION['fm_impersonating']);
}

function fm_account(): ?Account
{
    return fm_user()?->account;
}

function fm_client(): ?Client
{
    if (!tl_session()) {
        return null;
    }
    $id = $_SESSION['fm_client_id'] ?? null;
    return $id ? Client::find((string) $id) : null;
}

/** auth()->user() / auth()->id() / auth()->check(), for the templates. */
function auth(): object
{
    return new class {
        public function user(): ?FmUser { return fm_user(); }
        public function id(): ?string { return fm_user()?->id; }
        public function check(): bool { return fm_user() !== null; }
    };
}

/* ─────────────────────────────────────────────────────────── dispatch ──── */

function fm_dispatch(): void
{
    try {
        fm_flash_boot();
        $method = FmRequest::method();
        $path   = fm_current_path();

        $allowed = [];
        foreach (fm_routes() as $name => [$rname, $methods, $pattern, $handler, $mw]) {
            $regex = '#^' . preg_replace('/\\\\\{\w+(?:\\\\:\w+)?\\\\\}/', '([^/]+)', preg_quote($pattern, '#')) . '$#';
            if (!preg_match($regex, $path, $m)) {
                continue;
            }
            $verbs = explode('|', $methods);
            if ($method === 'HEAD') {
                $method = 'GET';
            }
            if (!in_array($method, $verbs, true)) {
                $allowed = array_merge($allowed, $verbs);
                continue;
            }
            preg_match_all('/\{(\w+)(?::\w+)?\}/', $pattern, $keys);
            $params = array_combine($keys[1], array_map('rawurldecode', array_slice($m, 1)));

            $GLOBALS['fm_route_name'] = $rname;
            if ($method !== 'GET' && !fm_csrf_valid()) {
                abort(419);
            }
            fm_middleware($mw);
            require_once FM_ROOT . '/includes/controllers/' . fm_controller_file($handler) . '.php';
            $handler($params);
            return;
        }
        if ($allowed) {
            abort(405);
        }
        abort(404);
    } catch (FmHalt) {
        return;
    } catch (FmAbort $e) {
        fm_error_page($e->status, $e->getMessage());
    }
}

/** Which controllers/*.php holds a handler, by its prefix. */
function fm_controller_file(string $handler): string
{
    $map = [
        'marketing_' => 'marketing', 'portal_' => 'portal', 'health' => 'marketing',
        'auth_' => 'auth', 'magic_link_' => 'auth', 'signup_' => 'auth', 'join_' => 'auth',
        'public_' => 'public', 'qr_' => 'public',
        'events_' => 'boards', 'boards_' => 'boards', 'columns_' => 'boards', 'subscription_' => 'boards', 'filters_' => 'boards',
        'cards_' => 'cards', 'closure_' => 'cards', 'assignments_' => 'cards', 'reactions_' => 'cards',
        'steps_' => 'cards', 'comments_' => 'cards', 'watch_' => 'cards', 'pin_' => 'cards', 'client_cards_' => 'cards',
        'clients_' => 'account', 'tags_' => 'account', 'templates_' => 'account', 'notifications_' => 'account',
        'search_' => 'account', 'account_' => 'account', 'export_' => 'account',
        'admin_' => 'admin', 'impersonate_' => 'admin',
    ];
    foreach ($map as $prefix => $file) {
        if (str_starts_with($handler, $prefix)) {
            return $file;
        }
    }
    throw new RuntimeException("No controller for {$handler}");
}

function fm_middleware(array $mw): void
{
    foreach ($mw as $m) {
        if ($m === 'auth') {
            tl_require_login();
            $user = fm_user();
            if (!$user) {
                flash('info', 'Create a workspace, or open an invite link from your team, to start using Fathom.');
                redirect_to(route('signup'));
            }
            if ($user->account?->isCancelled()) {
                abort(403, 'This Fathom account has been cancelled.');
            }
        } elseif ($m === 'client') {
            if (!fm_client()) {
                redirect_to(route('portal.login'));
            }
        } elseif ($m === 'sysop') {
            $u = fm_user();
            if (!$u || !$u->isSysop() || fm_impersonating()) {
                abort(403);
            }
        }
    }
}

function fm_error_page(int $status, string $message = ''): void
{
    http_response_code($status);
    $titles = [403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 419 => 'Page Expired', 429 => 'Too Many Requests', 500 => 'Server Error'];
    if (FmRequest::wantsJson()) {
        header('Content-Type: application/json');
        echo json_encode(['message' => $message ?: ($titles[$status] ?? 'Error')]);
        return;
    }
    echo fm_render('errors.error', ['status' => $status, 'title' => $titles[$status] ?? 'Error', 'message' => $message]);
}
