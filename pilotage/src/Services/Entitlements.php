<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Config;

/**
 * What a firm can do right now (FR-13.2, FR-13.3).
 *
 * ---------------------------------------------------------------------------
 * THE LIMIT IS HARD, AND IT IS NON-DESTRUCTIVE.
 *
 * When a trial ends or a subscription lapses, the workspace becomes READ-ONLY.
 * That is the whole penalty. Nothing is deleted, nothing is hidden, no client
 * is locked out of documents their advisor already delivered, and the export
 * keeps working — permanently, without asking.
 *
 * This is not generosity. A firm that stops paying still has a year of client
 * relationships in here, and three things follow from that:
 *
 *   1. Holding a firm's client relationships hostage to get a card number is
 *      the kind of thing people write about publicly, and rightly.
 *   2. The export is the reason trusting us with those relationships was
 *      rational in the first place (FR-1.5). Revoking it exactly when someone
 *      needs it most would retroactively make that a lie.
 *   3. Firms come back. A workspace that is intact and one click from writable
 *      is a renewal; a workspace that was emptied is a competitor's customer.
 *
 * WHAT IS ALWAYS ALLOWED, even unpaid, even expired:
 *
 *   - reading everything
 *   - the full export
 *   - the billing screens, so they can fix it
 *   - signing in, and signing out
 *
 * WHAT NEVER HAPPENS on lapse: deletion, hiding, client lockout, or a support
 * request that has to be made to get data back.
 * ---------------------------------------------------------------------------
 */
final class Entitlements
{
    /**
     * Is the product free during beta?
     *
     * While this is true nothing is charged and nothing is gated. It is read in
     * exactly one place — here — and everything else asks this class, so the
     * banner, the billing screen and the write gate cannot end up telling a
     * firm three different stories about its own account.
     */
    public static function inBeta(): bool
    {
        return filter_var((string) Config::get('app.beta', 'false'), FILTER_VALIDATE_BOOL);
    }

    /**
     * Paths that stay writable when the workspace is read-only.
     *
     * Prefix-matched. Deliberately short: every entry is a hole in the gate, so
     * each one has to earn its place by being something a locked-out firm
     * genuinely must be able to do.
     */
    public const ALWAYS_WRITABLE = [
        '/billing',      // paying is how you get out of here
        '/firm/export',  // FR-1.5, and see the note above
        '/logout',
        // Covers /login/2fa: finishing sign-in is never a write to gate. The
        // password and emailed-link routes that used to sit beside it went
        // with the port to the shared Bizorca Tools account.
        '/login',
        // Two-factor enrolment is MANDATORY for firm owners before they can do
        // anything else (FR-2.2), so an owner of a lapsed firm must be able to
        // finish it or they cannot even reach the billing page. The original
        // list said '/two-factor', which matched no route: the enrolment POST
        // is /2fa/setup.
        '/2fa',
        '/stripe/webhook',
    ];

    /**
     * Can this firm write anything at all?
     *
     * @param array<string,mixed> $tenant
     */
    public static function writable(array $tenant): bool
    {
        if (self::inBeta()) {
            return true;   // free, and nothing is gated
        }

        $status = (string) ($tenant['billing_status'] ?? 'trialing');

        if (in_array($status, ['active', 'past_due'], true)) {
            // past_due is still writable, on purpose. A card expires; a bank
            // declines a legitimate charge. Locking a firm out of live client
            // work over a payment Stripe is still retrying would be a support
            // ticket and a cancellation, not a collection.
            return true;
        }

        if ($status === 'trialing') {
            return !self::trialExpired($tenant);
        }

        return false;
    }

    /** @param array<string,mixed> $tenant */
    public static function trialExpired(array $tenant): bool
    {
        if (self::inBeta()) {
            // The clock keeps running in the background so the date is there
            // when beta ends, but nothing expires while the product is free.
            return false;
        }

        $endsAt = $tenant['trial_ends_at'] ?? null;

        if ($endsAt === null) {
            return false;   // no clock started; not our place to expire them
        }

        return strtotime((string) $endsAt) < time();
    }

    /**
     * Days of trial left, or null if not on trial.
     *
     * @param array<string,mixed> $tenant
     */
    public static function trialDaysLeft(array $tenant): ?int
    {
        if ((string) ($tenant['billing_status'] ?? '') !== 'trialing') {
            return null;
        }

        $endsAt = $tenant['trial_ends_at'] ?? null;

        if ($endsAt === null) {
            return null;
        }

        return (int) max(0, ceil((strtotime((string) $endsAt) - time()) / 86400));
    }

    /**
     * Is this request allowed to write?
     *
     * A request is writable if the firm is in good standing, OR the path is one
     * of the few that must work regardless.
     *
     * @param array<string,mixed> $tenant
     */
    public static function allowsWrite(array $tenant, string $method, string $path): bool
    {
        if (!in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;   // reading is always allowed. Always.
        }

        foreach (self::ALWAYS_WRITABLE as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return self::writable($tenant);
    }

    // ------------------------------------------------------------- features

    /** @param array<string,mixed> $tenant */
    public static function plan(array $tenant): array
    {
        return Billing::plan((string) ($tenant['plan'] ?? 'trial')) ?? Billing::PLANS['trial'];
    }

    /** @param array<string,mixed> $tenant */
    public static function seatLimit(array $tenant): int
    {
        return (int) self::plan($tenant)['seats'];
    }

    /**
     * Is there room for another coach?
     *
     * No limit during beta. Seat caps are a billing construct, and enforcing
     * one while charging nothing is the worst of both — a firm cannot pay to
     * lift it, so the limit is just an obstacle.
     *
     * @param array<string,mixed> $tenant
     */
    public static function canAddSeat(array $tenant): bool
    {
        if (self::inBeta()) {
            return true;
        }

        return Billing::seatsInUse((int) $tenant['id']) < self::seatLimit($tenant);
    }

    /** @param array<string,mixed> $tenant */
    public static function storageLimitBytes(array $tenant): int
    {
        return ((int) self::plan($tenant)['storage_gb']) * 1024 * 1024 * 1024;
    }

    /**
     * Is there room for a file of this size?
     *
     * Checked before the upload is stored rather than after, so a firm at its
     * limit gets an honest error instead of a file that lands and then counts
     * against them.
     *
     * @param array<string,mixed> $tenant
     */
    public static function canStore(array $tenant, int $bytes): bool
    {
        if (self::inBeta()) {
            return true;
        }

        return (Storage::tenantUsage((int) $tenant['id']) + $bytes) <= self::storageLimitBytes($tenant);
    }

    /**
     * May this firm hide "powered by Pilotage"?
     *
     * The one feature gate that is a pure product decision rather than a cost:
     * the credit line is cheap marketing, and a firm that cares enough to
     * remove it is a firm on a plan that can pay for it.
     *
     * @param array<string,mixed> $tenant
     */
    public static function canRemoveCredit(array $tenant): bool
    {
        if (self::inBeta()) {
            return true;
        }

        return (bool) self::plan($tenant)['remove_credit'];
    }

    /**
     * A short, honest sentence about where this firm stands.
     *
     * Returned rather than rendered so the same words appear in the banner, in
     * the billing screen, and in any email about it. A firm hearing three
     * different descriptions of its own account state does not trust any of
     * them.
     *
     * @param array<string,mixed> $tenant
     * @return array{tone:string, headline:string, detail:string, cta:?string}
     */
    public static function statusMessage(array $tenant): array
    {
        if (self::inBeta()) {
            return [
                'tone' => 'ok',
                'headline' => 'Free during beta',
                'detail' => 'Every feature, no card, no limits, no countdown. When Pilotage starts '
                          . 'charging you will get plenty of notice and the chance to decide — and '
                          . 'your work stays yours either way.',
                'cta' => null,
            ];
        }

        $status = (string) ($tenant['billing_status'] ?? 'trialing');
        $planName = (string) self::plan($tenant)['name'];

        if ($status === 'trialing') {
            $left = self::trialDaysLeft($tenant);

            if ($left === null) {
                return ['tone' => 'neutral', 'headline' => 'On trial', 'detail' => 'Everything is available.', 'cta' => null];
            }

            if ($left <= 0) {
                return [
                    'tone' => 'blocked',
                    'headline' => 'Your trial has ended',
                    'detail' => 'Everything you have is still here and still readable, and the export still '
                              . 'works. Choose a plan to start writing again.',
                    'cta' => 'Choose a plan',
                ];
            }

            return [
                'tone' => $left <= 3 ? 'warning' : 'neutral',
                'headline' => $left . ' day' . ($left === 1 ? '' : 's') . ' left of your trial',
                'detail' => 'No card yet. When it ends, your workspace stays exactly as it is — readable, '
                          . 'exportable, and one click from writable again.',
                'cta' => 'Choose a plan',
            ];
        }

        return match ($status) {
            'active' => [
                'tone' => 'ok',
                'headline' => $planName,
                'detail' => !empty($tenant['cancel_at_period_end'])
                    ? 'Cancelled, and paid up until ' . date('j F Y', strtotime((string) $tenant['current_period_end'])) . '.'
                    : 'Renews ' . ($tenant['current_period_end'] === null
                        ? 'automatically'
                        : date('j F Y', strtotime((string) $tenant['current_period_end']))) . '.',
                'cta' => null,
            ],
            'past_due' => [
                'tone' => 'warning',
                'headline' => 'A payment did not go through',
                'detail' => 'Everything still works while we retry. Updating the card now avoids any '
                          . 'interruption.',
                'cta' => 'Update payment details',
            ],
            'canceled' => [
                'tone' => 'blocked',
                'headline' => 'Your subscription has ended',
                'detail' => 'Nothing has been deleted. The workspace is readable and the export still works. '
                          . 'Subscribing again picks up exactly where you left off.',
                'cta' => 'Choose a plan',
            ],
            default => [
                'tone' => 'blocked',
                'headline' => 'Your account needs attention',
                'detail' => 'The workspace is read-only until this is sorted out. Nothing has been deleted.',
                'cta' => 'Sort out billing',
            ],
        };
    }

    /**
     * Is the read-only gate live?
     *
     * Beta wins over the enforcement flag rather than the other way round. If
     * both were somehow set, the safe reading is "do not lock anyone out of a
     * product we are telling them is free", and that should not depend on
     * remembering to unset the other one.
     */
    public static function enforced(): bool
    {
        if (self::inBeta()) {
            return false;
        }

        return filter_var(
            (string) Config::get('stripe.enforce', 'false'),
            FILTER_VALIDATE_BOOL
        );
    }
}
