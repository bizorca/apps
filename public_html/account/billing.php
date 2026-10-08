<?php
/** Membership: status, subscribe (Stripe Checkout), manage (Stripe portal). */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/private_html/includes/bootstrap.php';
require_once TL_PRIVATE . '/includes/chrome.php';
require_once TL_PRIVATE . '/includes/billing.php';

$user  = tl_require_login();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tl_csrf_ok($_POST['_csrf'] ?? null)) {
        $error = 'That form had gone stale. Try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'subscribe') {
                $interval = ($_POST['interval'] ?? '') === 'year' ? 'year' : 'month';
                header('Location: ' . tl_stripe_checkout_url($user, $interval, tl_origin()), true, 303);
                exit;
            }
            if ($action === 'portal') {
                header('Location: ' . tl_stripe_portal_url($user, tl_origin()), true, 303);
                exit;
            }
        } catch (Throwable $e) {
            error_log('billing: ' . $e->getMessage());
            $error = 'Payments are not available right now. Nothing was charged. Please try again later.';
        }
    }
}

$sub      = tl_billing_subscription((int) $user['id']);
$comp     = tl_billing_comp((int) $user['id']);
$member   = tl_is_member((int) $user['id']);
$monthly  = (int) tl_env('TL_MEMBERSHIP_MONTHLY_CENTS', 3300);
$annual   = (int) tl_env('TL_MEMBERSHIP_ANNUAL_CENTS', 33000);
$notice   = match ($_GET['checkout'] ?? '') {
    'success' => 'Thanks! Your membership is being confirmed with Stripe; it shows here within a minute.',
    'cancel'  => 'Checkout was cancelled. Nothing was charged.',
    default   => '',
};
$tool = (string) ($_GET['tool'] ?? '');
if ($tool !== '' && !$member) {
    $notice = 'That tool is part of the Bizorca membership.';
}

tl_page_open('Membership');
tl_card_open('Membership', '', $error, $notice);
?>
      <?php if ($member && $sub && in_array($sub['status'], TL_MEMBER_STATUSES, true)): ?>
        <p class="text-slate-700 mb-2"><strong>You're a member.</strong> <?= $sub['billing_interval'] === 'year' ? 'Annual' : 'Monthly' ?> plan.</p>
        <?php if ($sub['current_period_end']): ?>
          <p class="text-sm text-slate-600 mb-1">
            <?= $sub['cancel_at_period_end'] ? 'Ends' : 'Renews' ?> on <?= tl_h(date('F j, Y', strtotime($sub['current_period_end'] . ' UTC'))) ?>.
          </p>
        <?php endif; ?>
        <?php if (in_array($sub['status'], ['past_due', 'unpaid'], true)): ?>
          <p class="text-sm text-amber-700 mb-1">Your last payment didn't go through. Update your card below to keep your membership.</p>
        <?php endif; ?>
        <form method="post" class="mt-6">
          <?= tl_csrf_field() ?>
          <input type="hidden" name="action" value="portal">
          <button type="submit" class="<?= TL_BUTTON ?>">Manage billing, card and invoices</button>
        </form>
      <?php elseif ($member && $comp): ?>
        <p class="text-slate-700"><strong>You're a member</strong>, on the house<?= $comp['expires_at'] ? ' until ' . tl_h(date('F j, Y', strtotime($comp['expires_at']))) : '' ?>.</p>
      <?php else: ?>
        <p class="text-slate-700 leading-relaxed mb-6">
          One membership covers every paid tool on this site, now and as new ones arrive.
          Cancel any time from this page.
        </p>
        <div class="grid grid-cols-2 gap-3">
          <form method="post">
            <?= tl_csrf_field() ?>
            <input type="hidden" name="action" value="subscribe">
            <input type="hidden" name="interval" value="month">
            <button type="submit" class="w-full border border-slate-300 rounded-lg px-4 py-4 hover:border-brand-500 text-left">
              <span class="block text-2xl font-bold text-slate-900"><?= tl_money($monthly) ?></span>
              <span class="block text-sm text-slate-500">per month</span>
            </button>
          </form>
          <form method="post">
            <?= tl_csrf_field() ?>
            <input type="hidden" name="action" value="subscribe">
            <input type="hidden" name="interval" value="year">
            <button type="submit" class="w-full border-2 border-brand-500 rounded-lg px-4 py-4 hover:bg-brand-50 text-left">
              <span class="block text-2xl font-bold text-slate-900"><?= tl_money($annual) ?></span>
              <span class="block text-sm text-slate-500">per year<?= $annual < $monthly * 12 ? ', ' . (int) round(100 - $annual * 100 / ($monthly * 12)) . '% off' : '' ?></span>
            </button>
          </form>
        </div>
        <p class="text-xs text-slate-400 mt-4">Payments are handled by Stripe. Your card details never touch this site.</p>
        <?php if ($sub): ?>
          <form method="post" class="mt-4">
            <?= tl_csrf_field() ?>
            <input type="hidden" name="action" value="portal">
            <button type="submit" class="text-sm text-slate-500 hover:text-brand-600 underline">Past invoices and billing details</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
<?php
tl_card_close();
tl_page_close();
