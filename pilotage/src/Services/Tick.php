<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Auth\Impersonation;
use Bizorca\Pilotage\Auth\RateLimiter;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Heartbeat;

/**
 * One pass of background work.
 *
 * Extracted from cron/tick.php so the CLI and the page-load heartbeat run
 * exactly the same code. Two implementations of "what the tick does" would
 * drift, and the one that drifted would be the one nobody watches.
 *
 * Runs across every active tenant, each wrapped, because a failure that
 * silently halts the accountability loop for the whole platform is far worse
 * than one tenant missing a night. That was not hypothetical: a collation error
 * in one tenant's digest window took out the whole run once.
 *
 * Everything in here is idempotent and window-based, which is what makes
 * irregular triggering acceptable. A tick that arrives late does the right
 * thing once rather than the wrong thing repeatedly.
 */
final class Tick
{
    public const MODES = ['five-minute', 'nightly'];

    /**
     * @param callable(string):void|null $log
     * @return array<string,int>
     */
    public static function run(string $mode, ?callable $log = null): array
    {
        $log = $log ?? static function (string $line): void {
        };

        $startedAt = time();

        $db = Database::conn();
        $tenants = $db->query(
            "SELECT id, slug, name, mail_from_name, digest_hour, client_digest_day,
                    client_digest_enabled, retention_months
             FROM pl_tenants WHERE status IN ('trial','active')"
        )->fetchAll();

        $log('tick start: ' . $mode . ' (' . count($tenants) . ' tenants)');

        $totals = [
            'missed' => 0, 'escalated' => 0, 'recurring' => 0, 'nudges' => 0,
            'sent' => 0, 'held' => 0, 'suppressed' => 0, 'digests' => 0,
            'scheduled' => 0, 'purged' => 0,
            'cal_pushed' => 0, 'cal_pulled' => 0,
        ];

        foreach ($tenants as $tenant) {
            $tenantId = (int) $tenant['id'];

            try {
                if ($mode === 'nightly') {
                    $rolled = AccountabilityLoop::rollRecurring($tenantId);
                    $swept = AccountabilityLoop::sweep($tenantId);

                    $totals['recurring'] += $rolled;
                    $totals['missed'] += $swept['missed'];
                    $totals['escalated'] += $swept['escalated'];

                    if ($swept['escalated'] > 0) {
                        $log($tenant['slug'] . ': ' . $swept['escalated'] . ' commitment(s) escalated to issues');
                    }

                    // Retention (FR-14.2), in the deliberate two-step: schedule and
                    // warn tonight, destroy thirty days from now. Nothing is purged
                    // that has not sat on the notice table for the full period with an
                    // email already sent — see Compliance::purge().
                    $scheduled = Compliance::scheduleRetention($tenantId, $tenant);

                    if ($scheduled !== []) {
                        Compliance::announce($tenantId, $scheduled, $tenant);
                        $totals['scheduled'] += count($scheduled);
                        $log($tenant['slug'] . ': ' . count($scheduled) . ' engagement(s) scheduled for deletion in '
                           . Compliance::NOTICE_DAYS . ' days, owner notified');
                    }

                    $purged = Compliance::purge($tenantId);

                    if ($purged !== []) {
                        $totals['purged'] += count($purged);
                        $log($tenant['slug'] . ': PURGED ' . count($purged) . ' engagement(s) past retention');
                    }
                }

                if ($mode === 'five-minute') {
                    // Queue, do not send. Everything about batching and preferences
                    // depends on the message still being ours when the reader's
                    // settings are consulted — see migrations/015.
                    foreach (AccountabilityLoop::dueForNudge($tenantId) as $task) {
                        $overdue = strtotime((string) $task['due_on']) < strtotime(date('Y-m-d'));
                        $due = date('j F', strtotime((string) $task['due_on']));

                        // queueOnce, because this sweep rediscovers the same overdue
                        // commitment every five minutes until it moves. Without it a
                        // digest would carry the same line 288 times.
                        $queued = Notifications::queueOnce(
                            $tenantId,
                            (int) $task['owner_user_id'],
                            $overdue ? 'task.overdue' : 'task.due_soon',
                            ($overdue ? 'Still open: ' : 'Due ' . $due . ': ') . $task['title'],
                            $overdue
                                ? 'This was due on ' . $due . ' and has not moved. If it is no longer the right '
                                  . 'thing to be doing, say so — that is a more useful conversation than another reminder.'
                                : 'A reminder that this is due on ' . $due . '.',
                            '/tasks/' . (int) $task['id'],
                            [
                                'client_org_id' => isset($task['client_org_id']) ? (int) $task['client_org_id'] : null,
                                'object_type'   => 'task',
                                'object_id'     => (int) $task['id'],
                            ]
                        );

                        if ($queued !== null) {
                            $totals['nudges']++;
                        }
                    }

                    $dispatched = NotificationDispatch::run($tenantId);
                    $totals['sent'] += $dispatched['sent'];
                    $totals['held'] += $dispatched['held'];
                    $totals['suppressed'] += $dispatched['suppressed'];

                    if ($dispatched['failed'] > 0) {
                        $log($tenant['slug'] . ': ' . $dispatched['failed'] . ' notification(s) failed to send');
                    }

                    // Digests are checked on the five-minute tick rather than nightly,
                    // because a firm can configure any hour and a nightly run would
                    // only ever be able to honour one of them. The window record makes
                    // the extra checks free: all but one of them find the window
                    // already claimed and do nothing.
                    $sentDigests = Digest::run($tenant);
                    $totals['digests'] += $sentDigests['firm'] + $sentDigests['client'];

                    // Calendar sync rides the same five-minute tick. Pull then push,
                    // per connection, all of it short and re-runnable — there are no
                    // daemons here (SPEC §7).
                    $cal = CalendarSync::run($tenantId);
                    $totals['cal_pushed'] += $cal['pushed'];
                    $totals['cal_pulled'] += $cal['pulled'];

                    if ($cal['unlinked'] > 0 || $cal['conflicts'] > 0 || $cal['failed'] > 0) {
                        $log($tenant['slug'] . ': calendar — ' . $cal['unlinked'] . ' unlinked, '
                           . $cal['conflicts'] . ' conflict(s) kept local, ' . $cal['failed'] . ' failed');
                    }
                }

            } catch (\Throwable $e) {
                // One bad tenant must not stop the tick for the rest.
                $log('ERROR tenant ' . $tenant['slug'] . ': ' . $e->getMessage());
            }
        }

        if ($mode === 'nightly') {
            try {
                // Spent and expired one-time tokens are swept a week after
                // they die. They are useless long before that; keeping them
                // briefly means an audit question about a suspicious sign-in
                // still has a row to look at.
                $log('pruned ' . Session::prune() . ' session(s), '
                   . RateLimiter::prune() . ' auth attempt(s), '
                   . Impersonation::expireStale() . ' stale impersonation(s)');
            } catch (\Throwable $e) {
                $log('ERROR housekeeping: ' . $e->getMessage());
            }
        }

        $log('tick done: ' . json_encode($totals));

        // Recorded even when triggered from the CLI, so "when did anything last
        // actually run" has one answer rather than two.
        Heartbeat::finished($mode, json_encode($totals) ?: '', time() - $startedAt);

        return $totals;
    }
}
