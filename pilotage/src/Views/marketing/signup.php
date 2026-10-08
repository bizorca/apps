<?php
/**
 * Create a workspace.
 *
 * The form works with JavaScript off. The script below only adds two
 * conveniences — suggesting an address from the practice name, and checking
 * availability before submit — and everything it does is re-decided on the
 * server by Provisioning. A green tick here is a hint, not a reservation; the
 * UNIQUE index settles ties.
 *
 * @var bool $beta
 * @var array<string,string> $errors  field => problem
 * @var array<string,mixed> $input    what they typed, to give back
 * @var string|null $planName         a plan chosen on the pricing page
 * @var int $trialDays
 * @var int $renderedAt
 * @var array<string,mixed> $account   the signed-in Bizorca Tools account (the firm's owner)
 */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\Csp;
use Bizorca\Pilotage\Auth\Password;
use Bizorca\Pilotage\Services\Provisioning;

$val = static fn (string $k): string => h((string) ($input[$k] ?? ''));
$bad = static fn (string $k): bool => isset($errors[$k]);

/** Consistent field chrome, red when the server rejected that field. */
$field = static fn (string $k): string =>
    'w-full rounded-md border px-3 py-2.5 text-sm focus:outline-none focus:ring-1 '
    . (isset($errors[$k])
        ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
        : 'border-slate-300 focus:border-ink focus:ring-ink');
?>

<section class="mx-auto grid max-w-6xl gap-14 px-5 py-16 lg:grid-cols-[minmax(0,1fr)_360px]">

    <div>
        <h1 class="font-display text-4xl leading-tight tracking-tight">Create your workspace</h1>
        <p class="mt-4 max-w-xl leading-relaxed text-slate-600">
            <?php if ($planName !== null): ?>
                You picked <strong class="font-medium"><?= h($planName) ?></strong>.
            <?php endif; ?>
            <?= $beta
                ? 'Nothing is charged, no card is collected, and there is no seat limit while Pilotage is in beta.'
                : 'Your first ' . (int) $trialDays . ' days are free and no card is needed to start.' ?>
        </p>

        <?php if ($errors !== []): ?>
            <div class="mt-8 rounded-lg border border-red-200 bg-red-50 p-4">
                <h2 class="text-sm font-medium text-red-900">That did not go through</h2>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-red-800">
                    <?php foreach ($errors as $problem): ?>
                        <li><?= h($problem) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= h(app_url('/signup')) ?>" class="mt-8 max-w-xl space-y-7">
            <?= Csrf::field() ?>
            <input type="hidden" name="_t" value="<?= (int) $renderedAt ?>">
            <?php if (!empty($input['plan'])): ?>
                <input type="hidden" name="plan" value="<?= $val('plan') ?>">
            <?php endif; ?>

            <?php /* Honeypot. Hidden from people, irresistible to bots. */ ?>
            <div class="absolute left-[-9999px]" aria-hidden="true">
                <label>Company website
                    <input type="text" name="<?= h(Provisioning::HONEYPOT_FIELD) ?>" tabindex="-1" autocomplete="off">
                </label>
            </div>

            <fieldset class="space-y-5">
                <legend class="mb-5 text-xs font-semibold uppercase tracking-wider text-slate-500">Your practice</legend>

                <div>
                    <label for="firm_name" class="mb-1.5 block text-sm font-medium">Practice name</label>
                    <input id="firm_name" name="firm_name" type="text" required autofocus maxlength="255"
                           value="<?= $val('firm_name') ?>" placeholder="Harbourline Advisory"
                           class="<?= $field('firm_name') ?>">
                    <p class="mt-1.5 text-xs text-slate-500">This is what your clients see. You can change it later.</p>
                    <?php if ($bad('firm_name')): ?>
                        <p class="mt-1.5 text-xs text-red-700"><?= h($errors['firm_name']) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="slug" class="mb-1.5 block text-sm font-medium">Your address</label>
                    <div class="flex items-stretch">
                        <span class="flex items-center rounded-l-md border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500 whitespace-nowrap">
                            <?= h(base_domain() . PL_BASE) ?>/f/
                        </span>
                        <input id="slug" name="slug" type="text" required maxlength="63"
                               value="<?= $val('slug') ?>" placeholder="harbourline"
                               autocapitalize="off" autocorrect="off" spellcheck="false"
                               class="<?= $field('slug') ?> rounded-l-none">
                    </div>
                    <p id="slug-status" class="mt-1.5 text-xs text-slate-500" aria-live="polite">
                        Letters, numbers and hyphens. This is permanent — every link you send a client
                        contains it, so reclaiming one later would break them all.
                    </p>
                    <?php if ($bad('slug')): ?>
                        <p class="mt-1.5 text-xs text-red-700"><?= h($errors['slug']) ?></p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <?php /* The rule lives on a wrapper, not on the fieldset: a <legend>
                     is laid out inside its fieldset's border and punches a gap
                     through it, which reads as a rendering fault. */ ?>
            <div class="border-t border-slate-200 pt-7">
            <fieldset class="space-y-5">
                <legend class="mb-5 text-xs font-semibold uppercase tracking-wider text-slate-500">You</legend>

                <p class="text-sm text-slate-700">
                    <strong><?= h((string) $account['name']) ?></strong>
                    &lt;<?= h((string) $account['email']) ?>&gt; &mdash; your Bizorca Tools account
                    becomes the owner of this workspace. You sign in to it the same way you sign in to
                    every other tool on the site.
                </p>
                <p class="text-xs text-slate-500">
                    Not you? <a href="/account/logout.php" class="underline hover:text-slate-900">Sign out</a>
                    and sign in with the account that should own it.
                </p>
            </fieldset>
            </div>

            <div class="border-t border-slate-200 pt-7">
                <button type="submit"
                        class="w-full rounded-md bg-ink px-5 py-3 text-sm font-medium text-white hover:bg-ink-soft sm:w-auto">
                    Create my workspace
                </button>
                <p class="mt-3 text-xs text-slate-500">
                    You go straight into your new workspace and are asked to turn on two-factor
                    authentication before you go any further.
                </p>
            </div>
        </form>
    </div>

    <?php /* ------------------------------------------------ reassurance rail */ ?>
    <aside class="lg:pt-24">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
            <h2 class="text-sm font-medium">What happens next</h2>
            <ol class="mt-4 space-y-4 text-sm text-slate-600">
                <?php
                $steps = [
                    ['You land in your workspace', 'Signed in as the owner of a workspace nobody else can see into.'],
                    ['You turn on two-factor', 'Required for firm owners, and it takes about thirty seconds. You hold the keys to every client record in here.'],
                    ['Setup installs a starting shape', 'A neutral ninety-day operating rhythm and two session agendas, written to be rewritten.'],
                    ['You add your first client', 'A client organization, its contacts, and an engagement. Most firms get here in fifteen minutes.'],
                ];
                foreach ($steps as $i => [$head, $body]): ?>
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-ink text-[11px] font-medium text-white"><?= $i + 1 ?></span>
                        <span>
                            <span class="block font-medium text-ink"><?= h($head) ?></span>
                            <?= h($body) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div class="mt-6 space-y-4 px-1 text-sm text-slate-600">
            <p><span class="font-medium text-ink">Your clients are free.</span> Client-side contacts are never seats, in any number.</p>
            <p><span class="font-medium text-ink">Your data leaves whenever you want.</span> A full export, any time, without asking anyone.</p>
            <p><span class="font-medium text-ink">Already have a workspace?</span>
                <a href="<?= h(app_url('/signin')) ?>" class="font-medium text-tide-600 underline underline-offset-4">Sign in</a>.
            </p>
        </div>
    </aside>
</section>

<script <?= Csp::attr() ?>>
(function () {
  var firm   = document.getElementById('firm_name');
  var slug   = document.getElementById('slug');
  var status = document.getElementById('slug-status');
  if (!firm || !slug || !status) return;

  // Routed through ?r= on tools.bizorca.com, so the URL comes from the server.
  var availableUrl = <?= json_encode(app_url('/signup/available'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;

  var hint = status.textContent;
  // Once someone edits the address themselves we stop guessing at it.
  var touched = slug.value.trim() !== '';
  slug.addEventListener('input', function () { touched = true; });

  function normalise(v) {
    return v.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 63);
  }

  firm.addEventListener('input', function () {
    if (touched) return;
    slug.value = normalise(firm.value).replace(/-(llc|inc|ltd|limited|llp|plc|co|corp|group)$/, '');
    check();
  });

  function say(text, tone) {
    status.textContent = text;
    status.className = 'mt-1.5 text-xs ' + (
      tone === 'ok' ? 'text-tide-600' : tone === 'bad' ? 'text-red-700' : 'text-slate-500'
    );
  }

  var timer = null;
  var seq = 0;

  function check() {
    var value = normalise(slug.value);
    if (slug.value !== value) slug.value = value;

    if (value.length < 2) { say(hint, null); return; }

    // Ignore a response that arrives after a newer one — otherwise a slow
    // answer for an old value overwrites a fresh answer for the current one.
    var mine = ++seq;

    fetch(availableUrl + (availableUrl.indexOf('?') === -1 ? '?' : '&') + 'slug=' + encodeURIComponent(value), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || mine !== seq) return;
        say(data.available
              ? data.domain + '/f/' + value + ' is available'
              : (data.reason || 'That address is not available'),
            data.available ? 'ok' : 'bad');
      })
      .catch(function () { /* Offline or blocked: the server still decides. */ });
  }

  slug.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(check, 300);
  });

  if (slug.value) check();
})();
</script>
