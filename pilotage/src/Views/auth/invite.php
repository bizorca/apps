<?php
/**
 * Invitation acceptance.
 *
 * The role is displayed but never submitted — it is read from the invitation
 * row on the server. Nothing on this form can change who the invitee becomes.
 *
 * Accepting attaches a membership of this firm to a Bizorca Tools account,
 * the one sign-in for every tool on the site. Three states:
 *   - signed in as the invited address: one button
 *   - signed in as someone else: refuse, offer to switch
 *   - not signed in: create the account here (name + password), or, if the
 *     address already has one, sign in first and come back
 *
 * @var array<string,mixed> $invitation
 * @var string $token
 * @var array<int,string> $errors
 * @var array<string,mixed>|null $account     the signed-in tools account
 * @var bool $accountExists                   the invited address already has one
 * @var string $signInUrl                     /account/login.php?next=<this page>
 */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Password;
use Bizorca\Pilotage\Core\View;

$roleLabels = [
    'firm_owner'    => 'Firm owner',
    'coach'         => 'Coach',
    'associate'     => 'Associate',
    'client_owner'  => 'Account owner',
    'client_member' => 'Team member',
    'sponsor'       => 'Observer',
];

$invitedEmail = (string) $invitation['email'];
$matches = $account !== null && mb_strtolower((string) $account['email']) === mb_strtolower($invitedEmail);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900';
$button = 'w-full rounded bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800';

ob_start(); ?>

<div class="rounded bg-slate-50 border border-slate-200 px-3 py-2 text-sm mb-4">
    <div class="text-slate-500 text-xs">Joining as</div>
    <div class="font-medium"><?= h($roleLabels[$invitation['role']] ?? $invitation['role']) ?></div>
    <div class="text-slate-500 text-xs mt-1"><?= h($invitedEmail) ?></div>
</div>

<?php if ($account !== null && !$matches): ?>

    <p class="text-sm text-slate-700">
        This invitation is for <strong><?= h($invitedEmail) ?></strong>, but you are signed in as
        <strong><?= h((string) $account['email']) ?></strong>.
    </p>
    <a href="/account/logout.php" class="mt-4 block text-center <?= $button ?>">Sign out to switch accounts</a>

<?php elseif ($account === null && $accountExists): ?>

    <p class="text-sm text-slate-700">
        <?= h($invitedEmail) ?> already has a Bizorca Tools account. Sign in with it and you will
        come straight back here to accept.
    </p>
    <a href="<?= h($signInUrl) ?>" class="mt-4 block text-center <?= $button ?>">Sign in to accept</a>

<?php else: ?>

    <form method="post" action="<?= h(url('/invite/' . $token)) ?>" class="space-y-4">
        <?= Csrf::field() ?>

        <div>
            <label for="name" class="block text-sm font-medium mb-1">Your name</label>
            <input id="name" name="name" type="text" required autofocus
                   value="<?= h((string) ($account['name'] ?? $invitation['name'] ?? '')) ?>" class="<?= $field ?>">
        </div>

        <?php if ($account === null): ?>
            <p class="text-sm text-slate-600">
                This creates your Bizorca Tools account: one sign-in for this workspace and every
                other tool on the site.
            </p>
            <div>
                <label for="password" class="block text-sm font-medium mb-1">Choose a password</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       minlength="<?= Password::MIN_LENGTH ?>" class="<?= $field ?>">
                <p class="text-xs text-slate-500 mt-1">At least <?= Password::MIN_LENGTH ?> characters.</p>
            </div>
            <div>
                <label for="password_confirm" class="block text-sm font-medium mb-1">Confirm password</label>
                <input id="password_confirm" name="password_confirm" type="password" required
                       autocomplete="new-password" class="<?= $field ?>">
            </div>
        <?php endif; ?>

        <button type="submit" class="<?= $button ?>">Accept invitation</button>
    </form>

<?php endif; ?>

<?php
$inner = (string) ob_get_clean();

echo View::render('auth.shell', [
    'heading' => 'Accept your invitation',
    'sub'     => null,
    'inner'   => $inner,
    'errors'  => $errors,
    'notice'  => null,
], null);
