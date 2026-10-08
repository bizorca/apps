<?php
/**
 * AdminController and ImpersonationController (sysop only).
 *
 * Sysop is Fathom's own platform-admin flag (fm_users.is_sysop), not the
 * site-wide users.is_admin. Impersonation swaps only the Fathom identity for
 * this session; the shared tools sign-in, and every other tool, stays the
 * sysop's own.
 */

declare(strict_types=1);

function admin_dashboard(array $p): never
{
    $db = tl_db();
    $count = fn(string $sql) => (int) $db->query($sql)->fetchColumn();
    $stats = [
        'accounts_total'     => $count('SELECT COUNT(*) FROM fm_accounts WHERE deleted_at IS NULL'),
        'accounts_active'    => $count('SELECT COUNT(*) FROM fm_accounts WHERE deleted_at IS NULL AND cancelled_at IS NULL'),
        'accounts_cancelled' => $count('SELECT COUNT(*) FROM fm_accounts WHERE deleted_at IS NULL AND cancelled_at IS NOT NULL'),
        'users_total'        => $count('SELECT COUNT(*) FROM fm_users WHERE deleted_at IS NULL'),
        'boards_total'       => $count('SELECT COUNT(*) FROM fm_boards WHERE deleted_at IS NULL'),
        'cards_total'        => $count('SELECT COUNT(*) FROM fm_cards WHERE deleted_at IS NULL'),
        'clients_total'      => $count('SELECT COUNT(*) FROM fm_clients WHERE deleted_at IS NULL'),
        'exports_total'      => $count('SELECT COUNT(*) FROM fm_exports'),
        'magic_links_total'  => $count('SELECT COUNT(*) FROM fm_magic_links'),
    ];
    $recentAccounts = Account::where('1=1', [], 'ORDER BY created_at DESC LIMIT 15');
    view('admin.dashboard', compact('stats', 'recentAccounts'));
}

function admin_accounts(array $p): never
{
    $accounts = Account::where('1=1', [], 'ORDER BY created_at DESC');
    view('admin.accounts.index', compact('accounts'));
}

function admin_account(array $p): never
{
    $account = Account::findOrFail($p['account']);
    $users = FmUser::where('f.account_id = ?', [$account->id], "ORDER BY f.role = 'admin' DESC, u.name");
    view('admin.accounts.show', compact('account', 'users'));
}

function admin_cancel_account(array $p): never
{
    $account = Account::findOrFail($p['account']);
    $account->cancel();
    flash('success', "Account \"{$account->name}\" has been cancelled.");
    redirect_to(route('admin.accounts.show', $account));
}

function impersonate_store(array $p): never
{
    $target = FmUser::findOrFail($p['user']);
    $_SESSION['fm_impersonating'] = $target->id;
    fm_user(true);
    flash('info', "Now impersonating {$target->name} ({$target->account?->name}).");
    redirect_to(route('boards.index'));
}

function impersonate_destroy(array $p): never
{
    if (empty($_SESSION['fm_impersonating'])) {
        redirect_to(route('boards.index'));
    }
    unset($_SESSION['fm_impersonating']);
    fm_user(true);
    flash('success', 'Impersonation ended.');
    redirect_to(route('admin.dashboard'));
}
