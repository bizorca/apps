<?php
/** Site admin: members, comps, revenue at a glance, and the enforcement state. */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/private_html/includes/bootstrap.php';
require_once TL_PRIVATE . '/includes/chrome.php';
require_once TL_PRIVATE . '/includes/billing.php';

$admin  = tl_require_admin();
$db     = tl_db();
$error  = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tl_csrf_ok($_POST['_csrf'] ?? null)) {
        $error = 'That form had gone stale. Try again.';
    } elseif (($_POST['action'] ?? '') === 'comp') {
        $s = $db->prepare('SELECT id FROM users WHERE email = ?');
        $s->execute([trim(strtolower((string) ($_POST['email'] ?? '')))]);
        $uid = (int) $s->fetchColumn();
        $exp = trim((string) ($_POST['expires'] ?? ''));
        if (!$uid) {
            $error = 'No tools account with that email.';
        } elseif ($exp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) {
            $error = 'Expiry must be a date (YYYY-MM-DD) or blank.';
        } else {
            $db->prepare('INSERT INTO tl_comps (user_id, note, expires_at, granted_by) VALUES (?, ?, ?, ?) AS new
                          ON DUPLICATE KEY UPDATE note = new.note, expires_at = new.expires_at, granted_by = new.granted_by')
               ->execute([$uid, mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255), $exp === '' ? null : $exp . ' 23:59:59', $admin['id']]);
            tl_billing_sync($uid);
            $notice = 'Comp saved.';
        }
    } elseif (($_POST['action'] ?? '') === 'uncomp') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $db->prepare('DELETE FROM tl_comps WHERE user_id = ?')->execute([$uid]);
        tl_billing_sync($uid);
        $notice = 'Comp removed.';
    }
}

$subs  = $db->query('SELECT s.*, u.email, u.name FROM tl_subscriptions s JOIN users u ON u.id = s.user_id ORDER BY s.updated_at DESC')->fetchAll();
$comps = $db->query('SELECT c.*, u.email, u.name FROM tl_comps c JOIN users u ON u.id = c.user_id ORDER BY u.email')->fetchAll();
$mrr   = 0;
foreach ($subs as $s) {
    if (in_array($s['status'], ['active', 'trialing'], true)) {
        $mrr += $s['billing_interval'] === 'year'
            ? (int) round((int) tl_env('TL_MEMBERSHIP_ANNUAL_CENTS', 33000) / 12)
            : (int) tl_env('TL_MEMBERSHIP_MONTHLY_CENTS', 3300);
    }
}
$paid = tl_paid_tools();

tl_page_open('Billing admin');
?>
    <div class="max-w-4xl mx-auto space-y-8">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 mb-2">Billing</h1>
        <?php if ($error): ?><div class="text-sm rounded-lg px-4 py-3 mb-3 bg-red-50 text-red-800 border border-red-200"><?= tl_h($error) ?></div><?php endif; ?>
        <?php if ($notice): ?><div class="text-sm rounded-lg px-4 py-3 mb-3 bg-emerald-50 text-emerald-800 border border-emerald-200"><?= tl_h($notice) ?></div><?php endif; ?>
        <p class="text-sm text-slate-600">
          Enforcement: <strong><?= tl_billing_enforced() ? 'ON' : 'off' ?></strong> (TL_BILLING_ENFORCE).
          Paid tools: <strong><?= $paid ? tl_h(implode(', ', $paid)) : 'none, every tool is free' ?></strong> (TL_PAID_TOOLS).
          Stripe: <strong><?= tl_env('STRIPE_SECRET_KEY', '') !== '' ? (str_starts_with((string) tl_env('STRIPE_SECRET_KEY'), 'sk_live') ? 'live' : 'test') : 'not configured' ?></strong>,
          webhook secret <strong><?= tl_env('STRIPE_WEBHOOK_SECRET', '') !== '' ? 'set' : 'missing' ?></strong>.
        </p>
        <p class="text-sm text-slate-600 mt-1">Monthly recurring revenue: <strong><?= tl_money($mrr) ?></strong></p>
      </div>

      <section class="bg-white border border-slate-200 rounded-2xl p-6">
        <h2 class="font-bold text-slate-900 mb-3">Subscriptions (<?= count($subs) ?>)</h2>
        <?php if (!$subs): ?><p class="text-sm text-slate-500">None yet.</p><?php else: ?>
        <table class="w-full text-sm"><thead><tr class="text-left text-slate-500"><th class="py-1">Member</th><th>Plan</th><th>Status</th><th>Period end</th></tr></thead><tbody>
        <?php foreach ($subs as $s): ?>
          <tr class="border-t border-slate-100"><td class="py-1.5"><?= tl_h($s['name']) ?> <span class="text-slate-400"><?= tl_h($s['email']) ?></span></td>
          <td><?= $s['billing_interval'] === 'year' ? 'Annual' : 'Monthly' ?></td>
          <td><?= tl_h($s['status']) ?><?= $s['cancel_at_period_end'] ? ' (cancelling)' : '' ?></td>
          <td><?= tl_h((string) $s['current_period_end']) ?></td></tr>
        <?php endforeach; ?></tbody></table>
        <?php endif; ?>
      </section>

      <section class="bg-white border border-slate-200 rounded-2xl p-6">
        <h2 class="font-bold text-slate-900 mb-3">Comped memberships (<?= count($comps) ?>)</h2>
        <?php foreach ($comps as $c): ?>
          <form method="post" class="flex items-center justify-between border-t border-slate-100 py-2 text-sm">
            <?= tl_csrf_field() ?><input type="hidden" name="action" value="uncomp"><input type="hidden" name="user_id" value="<?= (int) $c['user_id'] ?>">
            <span><?= tl_h($c['name']) ?> <span class="text-slate-400"><?= tl_h($c['email']) ?></span>
              <?= $c['note'] !== '' ? ' &middot; ' . tl_h($c['note']) : '' ?>
              <?= $c['expires_at'] ? ' &middot; until ' . tl_h(substr($c['expires_at'], 0, 10)) : ' &middot; no expiry' ?></span>
            <button class="text-red-600 hover:underline">Remove</button>
          </form>
        <?php endforeach; ?>
        <form method="post" class="grid sm:grid-cols-4 gap-2 mt-4">
          <?= tl_csrf_field() ?><input type="hidden" name="action" value="comp">
          <input name="email" type="email" required placeholder="email" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <input name="note" placeholder="note" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <input name="expires" placeholder="expires YYYY-MM-DD (blank = never)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <button class="bg-brand-600 text-white rounded-lg px-3 py-2 text-sm hover:bg-brand-700">Comp membership</button>
        </form>
      </section>
    </div>
<?php
tl_page_close();
