<?php
/**
 * Sign-in, signup and joining, rebuilt on the shared tools account.
 *
 * The original had its own users with magic-link and Bizorca SSO sign-in.
 * Here a person signs in once at /account/ for every tool, and Fathom only
 * keeps their membership (fm_users): which workspace, which role. "Sign up"
 * now means "create a workspace", and an invite link adds a membership.
 * Client portal magic links are untouched; they are a separate population.
 */

declare(strict_types=1);

function fm_login_url(string $next): string
{
    return '/account/login.php?next=' . rawurlencode($next);
}

function fm_register_url(string $next): string
{
    return '/account/register.php?next=' . rawurlencode($next);
}

/** /login, /sso/*, magic-link requests for members: all go to the shared sign-in. */
function auth_to_shared_login(array $p): never
{
    redirect_to(fm_login_url(route('events.index')));
}

/** Sign out of the shared account (every tool), as the original signed out of Fathom. */
function auth_logout(array $p): never
{
    tl_logout();
    redirect_to(fm_login_url(route('events.index')));
}

/** Client portal sign-in links. Member links from the old app are retired. */
function magic_link_show(array $p): never
{
    $link = MagicLink::firstWhere('token = ?', [$p['token']]);
    if (!$link || !$link->isValid()) {
        flash('_errors', ['email_address' => ['This sign-in link has expired or already been used. Request a new one below.']]);
        redirect_to(route('portal.login'));
    }
    if ($link->authenticatable_type !== Client::MORPH) {
        redirect_to(fm_login_url(route('events.index')));
    }
    $client = Client::find((string) $link->authenticatable_id);
    if (!$client) {
        flash('_errors', ['email_address' => ['This sign-in link is no longer valid.']]);
        redirect_to(route('portal.login'));
    }
    $link->consume();
    tl_session(true);
    session_regenerate_id(true);
    $_SESSION['fm_client_id'] = $client->id;
    flash('success', "Welcome, {$client->name}!");
    redirect_to(route('portal.dashboard'));
}

/* ── create a workspace ───────────────────────────────────────────────── */

const FM_BUSINESS_TYPES = ['financial_coach', 'tax_consultant', 'career_coach', 'yoga_instructor', 'energy_healer', 'none'];

function signup_create(array $p): never
{
    if (!tl_user()) {
        redirect_to(fm_register_url(route('signup')));
    }
    if (fm_user()) {
        redirect_to(route('boards.index'));
    }
    view('signup.create', ['shared' => tl_user()]);
}

/** A slug nobody has; the original's unique index made a repeated workspace name a 500. */
function fm_unique_slug(string $name): string
{
    $base = Str::slug($name) ?: 'workspace';
    $slug = $base;
    $s = tl_db()->prepare('SELECT 1 FROM fm_accounts WHERE slug = ?');
    while (true) {
        $s->execute([$slug]);
        if (!$s->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . strtolower(Str::random(4));
    }
}

/** Give the signed-in shared user a membership, reviving a removed one (user_id is unique). */
function fm_add_membership(int $sharedUserId, string $accountId, string $role): FmUser
{
    $old = FmUser::forSharedUser($sharedUserId, true);
    if ($old) {
        tl_db()->prepare('UPDATE fm_users SET account_id = ?, role = ?, deleted_at = NULL, updated_at = ? WHERE id = ?')
               ->execute([$accountId, $role, now_sql(), $old->id]);
        fm_user(true);
        return FmUser::find($old->id);
    }
    $id = fm_uuid();
    tl_db()->prepare('INSERT INTO fm_users (id, user_id, account_id, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$id, $sharedUserId, $accountId, $role, now_sql(), now_sql()]);
    fm_user(true);
    return FmUser::find($id);
}

function signup_store(array $p): never
{
    $shared = tl_user() ?? redirect_to(fm_register_url(route('signup')));
    if (fm_user()) {
        redirect_to(route('boards.index'));
    }
    $key = 'signup:' . FmRequest::ip();
    if (fm_too_many($key, 5)) {
        back(['_errors' => ['account_name' => ['Too many sign-up attempts. Please try again later.']], '_old' => $_POST]);
    }
    fm_hit($key, 600);

    $v = validate([
        'account_name'  => 'required|string|max:255',
        'business_type' => 'nullable|string|in:' . implode(',', FM_BUSINESS_TYPES),
    ]);
    $type = ($v['business_type'] ?? 'none') !== 'none' ? $v['business_type'] : null;

    $account = Account::create([
        'name'          => $v['account_name'],
        'slug'          => fm_unique_slug($v['account_name']),
        'business_type' => $type,
        'invite_code'   => strtolower(Str::random(8)),
        'plan'          => 'free',
    ]);
    $user = fm_add_membership((int) $shared['id'], $account->id, 'admin');
    fm_onboarding_seed($account, $user);

    flash('success', "Your workspace is ready, {$user->name}.");
    redirect_to(route('events.index'));
}

/* ── join with an invite code ─────────────────────────────────────────── */

function fm_invited_account(string $code): Account
{
    return Account::firstWhere('invite_code = ?', [$code]) ?? abort(404);
}

function join_show(array $p): never
{
    $account = fm_invited_account($p['code']);
    $code    = $p['code'];
    $shared  = tl_user();
    $member  = fm_user();
    if ($member && $member->account_id === $account->id) {
        flash('info', "You're already a member of {$account->name}.");
        redirect_to(route('boards.index'));
    }
    view('signup.join', compact('account', 'code', 'shared', 'member'));
}

function join_store(array $p): never
{
    $account = fm_invited_account($p['code']);
    $shared  = tl_user() ?? redirect_to(fm_login_url(route('join', $p['code'])));
    $member  = fm_user();
    if ($member) {
        // One workspace per person, as before (email was unique across Fathom).
        back(['_errors' => ['email_address' => ["You're already a member of {$member->account?->name}. Fathom keeps one workspace per person."]]]);
    }
    fm_add_membership((int) $shared['id'], $account->id, 'member');
    flash('success', "You've joined {$account->name}.");
    redirect_to(route('events.index'));
}
