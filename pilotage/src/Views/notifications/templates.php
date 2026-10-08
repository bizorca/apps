<?php
/** @var array $templates @var bool $saved @var array|null $error @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<?php
/**
 * Native <details> rather than Alpine.
 *
 * This accordion was the only Alpine usage in the entire application, and it
 * was costing every page a CDN request plus an `unsafe-eval` in the CSP —
 * Alpine evaluates its expressions with the Function constructor. A disclosure
 * widget the browser already implements is not worth that.
 */
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Email wording</h1>
  <p class="text-sm text-slate-600 mb-6">
    Rewrite any of these in your own voice. Leave one alone and it keeps the wording we ship —
    editing one does not make you responsible for the rest. Use the listed
    <code class="text-xs bg-slate-100 px-1 rounded">{{variables}}</code>; anything else is refused when you save,
    so a typo never reaches a client.
  </p>

  <?php if ($saved): ?>
    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 mb-5">Saved.</div>
  <?php endif; ?>

  <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
    <?php foreach ($templates as $t): ?>
      <?php $isBad = $error !== null && $error['event_type'] === $t['event_type']; ?>
      <details class="px-4 py-3 group" <?= $isBad ? 'open' : '' ?>>
        <summary class="cursor-pointer list-none">
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="text-sm font-medium"><?= h($t['label']) ?></div>
              <div class="text-xs text-slate-500 mt-0.5">
                <?= h($t['audience']) ?>-facing<?= $t['transactional'] ? ' · always sent' : '' ?>
              </div>
            </div>
            <span class="text-xs <?= $t['customised'] ? 'text-emerald-700' : 'text-slate-400' ?> whitespace-nowrap">
              <?= $t['customised'] ? 'your wording' : 'default' ?>
            </span>
          </div>
        </summary>

        <div class="mt-4">
          <?php if ($isBad): ?>
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 mb-3">
              <?= h($error['message']) ?>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= h(url('/firm/email-wording')) ?>" class="space-y-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="event_type" value="<?= h($t['event_type']) ?>">

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Subject</label>
              <input type="text" name="subject" class="<?= $field ?>"
                     placeholder="Leave blank to keep the default"
                     value="<?= h((string) ($isBad ? ($_POST['subject'] ?? '') : ($t['subject'] ?? ''))) ?>">
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Body</label>
              <textarea name="body" rows="4" class="<?= $field ?>"
                        placeholder="Leave blank to keep the default"><?= h((string) ($isBad ? ($_POST['body'] ?? '') : ($t['body'] ?? ''))) ?></textarea>
            </div>

            <p class="text-xs text-slate-500">
              Available here:
              <?php foreach ($t['vars'] as $i => $v): ?><?= $i ? ', ' : '' ?><code class="bg-slate-100 px-1 rounded"><?= h('{{' . $v . '}}') ?></code><?php endforeach; ?>
            </p>

            <div class="flex items-center gap-2">
              <button class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Save</button>
              <?php if ($t['customised']): ?>
                <button name="action" value="reset"
                        class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Back to the default</button>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</div>
