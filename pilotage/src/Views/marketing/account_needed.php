<?php
/**
 * Signup needs a signed-in Bizorca Tools account: it becomes the firm's owner.
 *
 * @var string $next   where to come back to after signing in or registering
 */
?>
<section class="mx-auto max-w-xl px-5 py-20">
    <h1 class="font-display text-4xl leading-tight tracking-tight">Create your workspace</h1>
    <p class="mt-4 leading-relaxed text-slate-600">
        Your workspace belongs to a Bizorca Tools account &mdash; one free sign-in for Pilotage and
        every other tool on this site. Create one (or sign in) and you will come straight back here
        to pick your workspace's name and address.
    </p>
    <div class="mt-8 flex flex-wrap gap-3">
        <a href="/account/register.php?next=<?= h(rawurlencode($next)) ?>"
           class="rounded-md bg-ink px-5 py-3 text-sm font-medium text-white hover:bg-ink-soft">Create a free account</a>
        <a href="/account/login.php?next=<?= h(rawurlencode($next)) ?>"
           class="rounded-md border border-slate-300 px-5 py-3 text-sm font-medium hover:bg-slate-50">I already have one</a>
    </div>
</section>
