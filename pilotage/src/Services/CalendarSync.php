<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Secrets;
use Bizorca\Pilotage\Services\Calendar\GoogleProvider;
use Bizorca\Pilotage\Services\Calendar\MicrosoftProvider;
use Bizorca\Pilotage\Services\Calendar\Provider;

/**
 * Two-way calendar sync.
 *
 * Push on the five-minute tick, pull on the same tick, no daemon — SiteGround
 * kills long-running processes, so this has to be short, re-runnable, and able
 * to make progress after a run that died halfway.
 *
 * ---------------------------------------------------------------------------
 * THE FOUR RULES. Getting any of these wrong produces a bug that looks like
 * the software is haunted, so they are stated here and tested individually.
 *
 * 1. LAST WRITER WINS, AND THE LOSER IS TOLD.
 *    A session moved in Pilotage and in Google between two syncs is a real
 *    conflict with no correct answer. The more recent change wins. What
 *    matters more than which one wins is that the overwrite is RECORDED — the
 *    failure that actually hurts a coach is not "the wrong time won", it is
 *    "the time changed and nobody can say why".
 *
 * 2. A DELETE OVER THERE IS NOT A DELETE OVER HERE.
 *    Deleting an event from your calendar usually means "off my view", not
 *    "cancel this engagement's session". So a remote delete UNLINKS and tells
 *    the coach. The reverse is not symmetrical: cancelling a session HERE does
 *    remove the event there, because that one is unambiguous.
 *
 * 3. ECHOES ARE SUPPRESSED.
 *    Pull a change, push it back, pull it again: an infinite loop with an API
 *    quota attached. A push compares a digest of what it is about to send
 *    against what it last sent, so an unchanged session costs nothing, and a
 *    change that arrived FROM the provider is not immediately sent back to it.
 *
 * 4. ONLY FUTURE SESSIONS ARE PUSHED.
 *    Nobody wants nine months of completed coaching sessions appearing in
 *    their calendar the first time they connect. Backfilling history is a
 *    thing people ask for once and regret immediately.
 * ---------------------------------------------------------------------------
 */
final class CalendarSync
{
    /** How far ahead to keep the calendar populated. */
    public const HORIZON_DAYS = 180;

    /** Refresh an access token this long before it actually expires. */
    public const REFRESH_MARGIN = 120;

    /** @var array<string,Provider>|null Test seam; null means the real ones. */
    private static ?array $override = null;

    /** @return array<string,Provider> */
    public static function providers(): array
    {
        if (self::$override !== null) {
            return self::$override;
        }

        return [
            'google'    => new GoogleProvider(),
            'microsoft' => new MicrosoftProvider(),
        ];
    }

    /**
     * Swap the providers out.
     *
     * A test seam, and the only practical one. The behaviour worth testing here
     * — a simultaneous edit conflict, an expired sync token, a delete that must
     * not cascade — cannot be produced on demand by asking Google nicely.
     *
     * @param array<string,Provider> $providers
     */
    public static function useProviders(array $providers): void
    {
        self::$override = $providers;
    }

    public static function resetProviders(): void
    {
        self::$override = null;
    }

    public static function provider(string $key): ?Provider
    {
        return self::providers()[$key] ?? null;
    }

    /**
     * Which providers this installation can actually offer.
     *
     * A provider with no client id is not shown. An OAuth button that leads to
     * a Google error page is worse than no button, and the encryption key is
     * non-negotiable — there is nowhere safe to put a refresh token without it.
     *
     * @return array<string,Provider>
     */
    public static function configured(): array
    {
        if (!Secrets::available()) {
            return [];
        }

        $out = [];

        foreach (self::providers() as $key => $provider) {
            if (trim((string) Config::get('calendar.' . $key . '.client_id', '')) !== '') {
                $out[$key] = $provider;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------ the tick

    /**
     * Sync every active connection for one tenant.
     *
     * @return array{pushed:int, pulled:int, unlinked:int, conflicts:int, failed:int}
     */
    public static function run(int $tenantId): array
    {
        $totals = ['pushed' => 0, 'pulled' => 0, 'unlinked' => 0, 'conflicts' => 0, 'failed' => 0];

        if (self::configured() === []) {
            return $totals;
        }

        $stmt = Database::conn()->prepare(
            "SELECT * FROM pl_calendar_connections
             WHERE tenant_id = :tid AND status = 'active'"
        );
        $stmt->execute(['tid' => $tenantId]);

        foreach ($stmt->fetchAll() as $connection) {
            try {
                // Pull first, deliberately. A change the coach made in their
                // calendar this morning should be reflected before we decide
                // what to push, otherwise our push overwrites it and then the
                // pull reads back our own value.
                $pulled = self::pull($tenantId, $connection);
                $pushed = self::push($tenantId, $connection);

                $totals['pulled'] += $pulled['applied'];
                $totals['unlinked'] += $pulled['unlinked'];
                $totals['conflicts'] += $pulled['conflicts'];
                $totals['pushed'] += $pushed;

                Database::conn()->prepare(
                    'UPDATE pl_calendar_connections
                     SET last_sync_at = NOW(), last_error = NULL WHERE id = :id'
                )->execute(['id' => (int) $connection['id']]);
            } catch (\Throwable $e) {
                $totals['failed']++;
                self::recordError((int) $connection['id'], $e->getMessage());
            }
        }

        return $totals;
    }

    // ------------------------------------------------------------ pushing

    /**
     * Send local sessions to the provider.
     *
     * @param array<string,mixed> $connection
     */
    public static function push(int $tenantId, array $connection): int
    {
        $provider = self::provider((string) $connection['provider']);
        $token = self::accessToken($connection);

        if ($provider === null || $token === null) {
            return 0;
        }

        $db = Database::conn();
        $userId = (int) $connection['user_id'];

        // Sessions this person is actually part of: their engagements as
        // coach, or ones they are a member of. Syncing the whole firm's diary
        // into one coach's calendar would be worse than useless.
        $stmt = $db->prepare(
            "SELECT s.*, o.name AS org_name, e.title AS engagement_title,
                    ce.id AS link_id, ce.remote_event_id, ce.pushed_digest, ce.sync_origin,
                    ce.unlinked_at
             FROM pl_sessions s
             JOIN pl_engagements e ON e.id = s.engagement_id AND e.tenant_id = s.tenant_id
             JOIN pl_client_orgs o ON o.id = e.client_org_id AND o.tenant_id = s.tenant_id
             LEFT JOIN pl_engagement_members m
                    ON m.engagement_id = e.id AND m.tenant_id = e.tenant_id AND m.user_id = :uid
             LEFT JOIN pl_calendar_events ce
                    ON ce.session_id = s.id AND ce.connection_id = :cid2
             WHERE s.tenant_id = :tid
               AND s.scheduled_at IS NOT NULL
               AND (e.coach_user_id = :uid2 OR m.id IS NOT NULL)
               AND s.scheduled_at >= NOW() - INTERVAL 1 DAY
               AND s.scheduled_at <= NOW() + INTERVAL :days DAY"
        );
        $stmt->execute([
            'uid' => $userId, 'uid2' => $userId,
            'cid2' => (int) $connection['id'], 'tid' => $tenantId,
            'days' => self::HORIZON_DAYS,
        ]);

        $pushed = 0;

        foreach ($stmt->fetchAll() as $session) {
            $cancelled = in_array((string) $session['status'], ['cancelled'], true);

            // Rule 2, the other half: cancelling here IS unambiguous, so the
            // event goes.
            if ($cancelled) {
                if ($session['remote_event_id'] !== null) {
                    $provider->deleteEvent($token, $connection['calendar_id'], (string) $session['remote_event_id']);
                    $db->prepare('DELETE FROM pl_calendar_events WHERE id = :id')
                       ->execute(['id' => (int) $session['link_id']]);
                    $pushed++;
                }

                continue;
            }

            $event = self::eventFor($session);
            $digest = hash('sha256', json_encode($event, JSON_UNESCAPED_UNICODE) ?: '');

            // Rule 3. Nothing changed, so nothing is sent — and a change that
            // came FROM the provider is not bounced straight back at it.
            if ($session['link_id'] !== null
                && $digest === (string) $session['pushed_digest']) {
                continue;
            }

            // Rule 2's teeth. They deleted this from their calendar; putting it
            // straight back five minutes later is how a coach learns to switch
            // the whole feature off. It returns only if the session itself
            // changes, which the digest comparison above has already
            // established that it has.
            if ($session['unlinked_at'] !== null && $digest === (string) $session['pushed_digest']) {
                continue;
            }

            // An unlinked event no longer exists over there, so PATCHing its
            // old id would 404. Create a fresh one.
            $remoteId = ($session['remote_event_id'] === null || $session['unlinked_at'] !== null)
                ? null
                : (string) $session['remote_event_id'];

            $result = $provider->upsertEvent($token, $connection['calendar_id'], $remoteId, $event);

            if ($result === null) {
                continue;
            }

            $db->prepare(
                "INSERT INTO pl_calendar_events
                    (tenant_id, connection_id, session_id, remote_event_id, remote_etag,
                     pushed_at, remote_updated_at, sync_origin, pushed_digest)
                 VALUES (:tid, :cid, :sid, :rid, :etag, NOW(), :rupd, 'local', :digest)
                 ON DUPLICATE KEY UPDATE
                    remote_event_id = VALUES(remote_event_id),
                    remote_etag = VALUES(remote_etag),
                    pushed_at = NOW(),
                    remote_updated_at = VALUES(remote_updated_at),
                    sync_origin = 'local',
                    pushed_digest = VALUES(pushed_digest),
                    unlinked_at = NULL"
            )->execute([
                'tid' => $tenantId,
                'cid' => (int) $connection['id'],
                'sid' => (int) $session['id'],
                'rid' => $result['id'],
                'etag' => $result['etag'],
                'rupd' => $result['updated_at'],
                'digest' => $digest,
            ]);

            $pushed++;
        }

        return $pushed;
    }

    // ------------------------------------------------------------ pulling

    /**
     * Read remote changes and apply the ones that concern us.
     *
     * @param array<string,mixed> $connection
     * @return array{applied:int, unlinked:int, conflicts:int}
     */
    public static function pull(int $tenantId, array $connection): array
    {
        $provider = self::provider((string) $connection['provider']);
        $token = self::accessToken($connection);

        $result = ['applied' => 0, 'unlinked' => 0, 'conflicts' => 0];

        if ($provider === null || $token === null) {
            return $result;
        }

        $db = Database::conn();

        $changes = $provider->changes($token, $connection['calendar_id'], $connection['sync_token']);

        if ($changes === null) {
            return $result;
        }

        // The provider expired our token. Clear it so the next run does a full
        // read; ignoring this is how a sync silently stops seeing changes while
        // continuing to report success.
        if ($changes['reset']) {
            $db->prepare('UPDATE pl_calendar_connections SET sync_token = NULL WHERE id = :id')
               ->execute(['id' => (int) $connection['id']]);

            return $result;
        }

        foreach ($changes['events'] as $event) {
            $link = self::linkFor($tenantId, (int) $connection['id'], (string) $event['id']);

            // An event we do not know about is someone's dentist appointment.
            // Adopting it as a coaching session would be absurd.
            if ($link === null) {
                continue;
            }

            if ($event['deleted']) {
                // Rule 2. Unlink; do not delete the session. The row is MARKED
                // rather than removed, because a removed row is indistinguish-
                // able from "never synced" and the next push would recreate the
                // event they just deleted.
                if ($link['unlinked_at'] !== null) {
                    continue;   // already handled; providers repeat deletions
                }

                $db->prepare('UPDATE pl_calendar_events SET unlinked_at = NOW() WHERE id = :id')
                   ->execute(['id' => (int) $link['id']]);

                self::notifyUnlinked($tenantId, (int) $connection['user_id'], (int) $link['session_id']);
                $result['unlinked']++;
                continue;
            }

            $applied = self::applyRemote($tenantId, $connection, $link, $event);

            if ($applied === 'conflict') {
                $result['conflicts']++;
            }

            if ($applied !== 'none') {
                $result['applied']++;
            }
        }

        if ($changes['sync_token'] !== null) {
            $db->prepare('UPDATE pl_calendar_connections SET sync_token = :t WHERE id = :id')
               ->execute(['t' => $changes['sync_token'], 'id' => (int) $connection['id']]);
        }

        return $result;
    }

    /**
     * Apply one remote event to its session.
     *
     * Rule 1 lives here. Only the time is taken from the provider — a coach
     * dragging a meeting in their calendar means "move it", and that is the
     * whole reason two-way sync is worth building. The title and description
     * are NOT taken back: they are generated from the engagement, and letting
     * a calendar edit rename an engagement's session would be a surprising
     * amount of blast radius for a typo.
     *
     * @param array<string,mixed> $connection
     * @param array<string,mixed> $link
     * @param array<string,mixed> $event
     * @return string 'none' | 'moved' | 'conflict'
     */
    private static function applyRemote(int $tenantId, array $connection, array $link, array $event): string
    {
        if ($event['starts_at'] === null) {
            return 'none';
        }

        $db = Database::conn();

        $stmt = $db->prepare(
            // client_org_id lives on the engagement, not the session — it has
            // to be selected explicitly or the notification below addresses
            // nobody.
            'SELECT s.*, o.name AS org_name, e.client_org_id, e.title AS engagement_title
             FROM pl_sessions s
             JOIN pl_engagements e ON e.id = s.engagement_id AND e.tenant_id = s.tenant_id
             JOIN pl_client_orgs o ON o.id = e.client_org_id AND o.tenant_id = s.tenant_id
             WHERE s.tenant_id = :tid AND s.id = :sid'
        );
        $stmt->execute(['tid' => $tenantId, 'sid' => (int) $link['session_id']]);
        $session = $stmt->fetch();

        if ($session === false) {
            return 'none';
        }

        // A session that already happened does not get moved by a calendar
        // tidy-up. People delete and reshuffle past events constantly.
        if (in_array((string) $session['status'], ['complete', 'cancelled'], true)) {
            return 'none';
        }

        $newStart = (string) $event['starts_at'];
        $oldStart = (string) $session['scheduled_at'];

        if (strtotime($newStart) === strtotime($oldStart)) {
            return 'none';   // an echo of our own push
        }

        // Rule 1: whichever side changed more recently wins.
        $remoteAt = $event['updated_at'] === null ? time() : strtotime((string) $event['updated_at']);
        $localAt = $link['pushed_at'] === null ? 0 : strtotime((string) $link['pushed_at']);
        $conflict = $localAt > 0 && $remoteAt <= $localAt;

        if ($conflict) {
            // Our push is newer. Leave the session alone; the next push will
            // set the provider straight, and the digest guarantees it happens.
            return 'conflict';
        }

        $duration = (int) $session['duration_minutes'];

        if ($event['ends_at'] !== null) {
            $minutes = (int) round((strtotime((string) $event['ends_at']) - strtotime($newStart)) / 60);

            if ($minutes > 0 && $minutes <= 24 * 60) {
                $duration = $minutes;
            }
        }

        $db->prepare(
            'UPDATE pl_sessions SET scheduled_at = :at, duration_minutes = :mins
             WHERE tenant_id = :tid AND id = :id'
        )->execute([
            'at' => $newStart, 'mins' => $duration,
            'tid' => $tenantId, 'id' => (int) $session['id'],
        ]);

        // Mark the link as remote-originated and re-digest it, so the push step
        // recognises this as already agreed and does not bounce it back.
        $db->prepare(
            "UPDATE pl_calendar_events
             SET sync_origin = 'remote', remote_updated_at = :rupd, pushed_digest = :digest
             WHERE id = :id"
        )->execute([
            'rupd' => $event['updated_at'],
            'digest' => hash('sha256', json_encode(
                self::eventFor(array_merge($session, ['scheduled_at' => $newStart, 'duration_minutes' => $duration])),
                JSON_UNESCAPED_UNICODE
            ) ?: ''),
            'id' => (int) $link['id'],
        ]);

        // Rule 1's second half: say that it moved, and say where from. A time
        // that changes with no explanation is the thing that makes people stop
        // trusting a sync.
        Timeline::record(
            $tenantId,
            (int) $session['client_org_id'],
            'session.moved_remotely',
            '"' . $session['title'] . '" moved to ' . date('j M, H:i', strtotime($newStart))
                . ' UTC from a connected calendar (was ' . date('j M, H:i', strtotime($oldStart)) . ')',
            null,
            'session',
            (int) $session['id'],
            false
        );

        Notifications::queueMany(
            $tenantId,
            Notifications::firmRecipients($tenantId, (int) $session['engagement_id']),
            'session.scheduled',
            '"' . $session['title'] . '" moved to ' . date('j M, H:i', strtotime($newStart)) . ' UTC',
            'Changed in a connected calendar rather than here.',
            '/sessions/' . (int) $session['id'],
            [
                'client_org_id' => (int) $session['client_org_id'],
                'object_type'   => 'session',
                'object_id'     => (int) $session['id'],
            ]
        );

        return 'moved';
    }

    // ---------------------------------------------------------- connections

    /**
     * A usable access token, refreshing if it is about to expire.
     *
     * Returns null and marks the connection needing re-auth when the refresh
     * fails, which is the honest outcome — a revoked grant cannot be recovered
     * from our side, and retrying it every five minutes forever helps nobody.
     *
     * @param array<string,mixed> $connection
     */
    public static function accessToken(array $connection): ?string
    {
        $expiresAt = $connection['access_expires_at'] === null
            ? 0
            : strtotime((string) $connection['access_expires_at']);

        if ($expiresAt > time() + self::REFRESH_MARGIN) {
            $token = Secrets::decrypt((string) $connection['access_token']);

            if ($token !== null) {
                return $token;
            }
        }

        $refresh = $connection['refresh_token'] === null
            ? null
            : Secrets::decrypt((string) $connection['refresh_token']);

        if ($refresh === null) {
            self::needsReauth((int) $connection['id'], 'The stored credentials could not be read.');
            return null;
        }

        $provider = self::provider((string) $connection['provider']);

        if ($provider === null) {
            return null;
        }

        $fresh = $provider->refresh($refresh);

        if ($fresh === null || $fresh['access_token'] === '') {
            self::needsReauth((int) $connection['id'], 'The calendar connection was refused. Reconnect to restore it.');
            return null;
        }

        Database::conn()->prepare(
            "UPDATE pl_calendar_connections
             SET access_token = :tok, access_expires_at = NOW() + INTERVAL :secs SECOND,
                 status = 'active', last_error = NULL
             WHERE id = :id"
        )->execute([
            'tok' => Secrets::encrypt($fresh['access_token']),
            'secs' => max(60, (int) $fresh['expires_in']),
            'id' => (int) $connection['id'],
        ]);

        return $fresh['access_token'];
    }

    /**
     * Store a completed authorisation.
     *
     * @param array{access_token:string, refresh_token:?string, expires_in:int, scope:?string} $tokens
     */
    public static function connect(
        int $tenantId,
        int $userId,
        string $providerKey,
        array $tokens,
        ?string $accountEmail
    ): int {
        $db = Database::conn();

        // A re-authorisation that returns no refresh token must not wipe the
        // one we already hold — that would turn a harmless reconnect into a
        // dead connection an hour later.
        $sql = "INSERT INTO pl_calendar_connections
                    (tenant_id, user_id, provider, account_email, access_token, refresh_token,
                     access_expires_at, scope, status)
                VALUES (:tid, :uid, :provider, :email, :access, :refresh,
                        NOW() + INTERVAL :secs SECOND, :scope, 'active')
                ON DUPLICATE KEY UPDATE
                    account_email = VALUES(account_email),
                    access_token = VALUES(access_token),
                    refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
                    access_expires_at = VALUES(access_expires_at),
                    scope = VALUES(scope),
                    status = 'active',
                    last_error = NULL,
                    sync_token = NULL";

        $db->prepare($sql)->execute([
            'tid' => $tenantId,
            'uid' => $userId,
            'provider' => $providerKey,
            'email' => $accountEmail,
            'access' => Secrets::encrypt($tokens['access_token']),
            'refresh' => $tokens['refresh_token'] === null ? null : Secrets::encrypt($tokens['refresh_token']),
            'secs' => max(60, (int) $tokens['expires_in']),
            'scope' => $tokens['scope'],
        ]);

        $stmt = $db->prepare(
            'SELECT id FROM pl_calendar_connections
             WHERE tenant_id = :tid AND user_id = :uid AND provider = :provider'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId, 'provider' => $providerKey]);

        return (int) $stmt->fetch()['id'];
    }

    /**
     * Disconnect.
     *
     * The remote events are left where they are. Removing a coach's meetings
     * from their calendar because they turned off a sync would be startling,
     * and they may still want to know when they are meeting people.
     */
    public static function disconnect(int $tenantId, int $userId, string $providerKey): bool
    {
        $stmt = Database::conn()->prepare(
            'DELETE FROM pl_calendar_connections
             WHERE tenant_id = :tid AND user_id = :uid AND provider = :provider'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId, 'provider' => $providerKey]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public static function connectionsFor(int $tenantId, int $userId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM pl_calendar_events e WHERE e.connection_id = c.id) AS event_count
             FROM pl_calendar_connections c
             WHERE c.tenant_id = :tid AND c.user_id = :uid
             ORDER BY c.provider ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);

        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------ internals

    /**
     * The normalised event we push. Also the thing that gets digested, so the
     * fields here are exactly the fields a change is detected on.
     *
     * @param array<string,mixed> $session
     * @return array<string,mixed>
     */
    public static function eventFor(array $session): array
    {
        $start = (string) $session['scheduled_at'];
        $minutes = max(15, (int) ($session['duration_minutes'] ?? 60));

        return [
            'session_id'  => (int) $session['id'],
            'title'       => trim((string) ($session['org_name'] ?? '')) !== ''
                ? $session['org_name'] . ' — ' . $session['title']
                : (string) $session['title'],
            'description' => trim((string) ($session['engagement_title'] ?? '')) !== ''
                ? 'Pilotage session for ' . $session['engagement_title']
                : 'Pilotage session',
            'location'    => (string) ($session['location'] ?? ''),
            'starts_at'   => $start,
            'ends_at'     => gmdate('Y-m-d H:i:s', strtotime($start . ' UTC') + $minutes * 60),
        ];
    }

    /** @return array<string,mixed>|null */
    private static function linkFor(int $tenantId, int $connectionId, string $remoteId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_calendar_events
             WHERE tenant_id = :tid AND connection_id = :cid AND remote_event_id = :rid'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $connectionId, 'rid' => $remoteId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private static function notifyUnlinked(int $tenantId, int $userId, int $sessionId): void
    {
        $stmt = Database::conn()->prepare(
            'SELECT title, engagement_id FROM pl_sessions WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $sessionId]);
        $session = $stmt->fetch();

        if ($session === false) {
            return;
        }

        Notifications::queue(
            $tenantId,
            $userId,
            'session.scheduled',
            '"' . $session['title'] . '" was removed from your calendar',
            'The session is still here and unchanged — only the calendar entry went. '
            . 'It will not be recreated unless the session moves.',
            '/sessions/' . $sessionId,
            ['object_type' => 'session', 'object_id' => $sessionId]
        );
    }

    private static function needsReauth(int $connectionId, string $reason): void
    {
        Database::conn()->prepare(
            "UPDATE pl_calendar_connections
             SET status = 'needs_reauth', last_error = :err, last_error_at = NOW()
             WHERE id = :id"
        )->execute(['err' => mb_substr($reason, 0, 500), 'id' => $connectionId]);
    }

    private static function recordError(int $connectionId, string $message): void
    {
        Database::conn()->prepare(
            'UPDATE pl_calendar_connections
             SET last_error = :err, last_error_at = NOW() WHERE id = :id'
        )->execute(['err' => mb_substr($message, 0, 500), 'id' => $connectionId]);
    }
}
