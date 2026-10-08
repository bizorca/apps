<?php
/**
 * "Your workspaces" — the Pilotage sign-in signpost on tools.bizorca.com.
 *
 * Signing in is the shared Bizorca Tools account, so this page no longer asks
 * anyone to type their firm's address. Signed in, it lists the firms that
 * account is an active member of (its own memberships, nobody else's: the
 * query is keyed on the account, so it cannot be used to probe whether some
 * other firm exists). Signed out, it sends you to the shared sign-in and back.
 *
 * @var array<string,mixed>|null $account
 * @var array<int,array<string,mixed>> $workspaces   slug, name, role
 */
$roleLabels = [
    'firm_owner'    => 'Owner',
    'coach'         => 'Coach',
    'associate'     => 'Associate',
    'client_owner'  => 'Client',
    'client_member' => 'Client team',
    'sponsor'       => 'Observer',
];
?>

<section class="mx-auto max-w-xl px-5 py-20">
    <h1 class="font-display text-4xl leading-tight tracking-tight">Your workspaces</h1>

    <?php if ($account === null): ?>
        <p class="mt-4 leading-relaxed text-slate-600">
            Pilotage signs you in with your Bizorca Tools account &mdash; the same one for every
            tool on this site. Sign in and your workspaces are listed here.
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="/account/login.php?next=<?= h(rawurlencode(pl_route('/signin'))) ?>"
               class="rounded-md bg-ink px-5 py-3 text-sm font-medium text-white hover:bg-ink-soft">Sign in</a>
            <a href="<?= h(app_url('/signup')) ?>"
               class="rounded-md border border-slate-300 px-5 py-3 text-sm font-medium hover:bg-slate-50">Create a workspace</a>
        </div>
    <?php elseif ($workspaces === []): ?>
        <p class="mt-4 leading-relaxed text-slate-600">
            You are signed in as <strong><?= h((string) $account['email']) ?></strong>, and that
            account is not a member of any workspace yet. If your advisor invited you, the link
            is in the invitation email. Running a practice yourself?
        </p>
        <div class="mt-8">
            <a href="<?= h(app_url('/signup')) ?>"
               class="rounded-md bg-ink px-5 py-3 text-sm font-medium text-white hover:bg-ink-soft">Create a workspace</a>
        </div>
    <?php else: ?>
        <p class="mt-4 leading-relaxed text-slate-600">
            Signed in as <strong><?= h((string) $account['email']) ?></strong>.
        </p>
        <ul class="mt-8 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
            <?php foreach ($workspaces as $w): ?>
                <li>
                    <a href="<?= h(tenant_url('/', (string) $w['slug'])) ?>"
                       class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50">
                        <span class="font-medium"><?= h((string) $w['name']) ?></span>
                        <span class="text-xs text-slate-500"><?= h($roleLabels[$w['role']] ?? (string) $w['role']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
