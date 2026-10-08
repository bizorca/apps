<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services\Calendar;

use Bizorca\Pilotage\Core\Config;

/**
 * Google Calendar.
 *
 * Two things about Google's API shape drive the code below.
 *
 * `syncToken` is how incremental sync works, and Google expires it whenever it
 * feels like it — after a retention window, or when the calendar changes in a
 * way it cannot express as a delta. It signals that with a 410, and the only
 * correct response is to throw the token away and do a full read. A caller
 * that treats 410 as an error silently stops receiving changes, which is worse
 * than failing loudly because the sync goes on reporting success.
 *
 * A deleted event is not absent from the delta; it comes back with
 * `status: cancelled`. Filtering it out rather than acting on it means a
 * meeting a coach removed from their calendar stays linked forever.
 */
final class GoogleProvider implements Provider
{
    private const AUTH  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN = 'https://oauth2.googleapis.com/token';
    private const API   = 'https://www.googleapis.com/calendar/v3';
    private const USERINFO = 'https://www.googleapis.com/oauth2/v2/userinfo';

    /**
     * calendar.events rather than calendar. We write and read events; we have
     * no business creating or deleting someone's calendars, and asking for
     * less is both correct and easier to get past a consent screen.
     */
    private const SCOPES = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/userinfo.email';

    public function key(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Calendar';
    }

    public function authorizeUrl(string $state, string $redirectUri, ?string $challenge = null): string
    {
        $params = [
            'client_id'     => (string) Config::get('calendar.google.client_id', ''),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            'state'         => $state,
            // offline + consent is what actually returns a refresh token.
            // Without prompt=consent Google withholds it on re-authorisation,
            // and the connection silently dies when the access token expires.
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'include_granted_scopes' => 'true',
        ];

        if ($challenge !== null) {
            $params['code_challenge'] = $challenge;
            $params['code_challenge_method'] = 'S256';
        }

        return self::AUTH . '?' . http_build_query($params);
    }

    public function exchangeCode(string $code, string $redirectUri, ?string $verifier = null): ?array
    {
        $params = [
            'code'          => $code,
            'client_id'     => (string) Config::get('calendar.google.client_id', ''),
            'client_secret' => (string) Config::get('calendar.google.client_secret', ''),
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ];

        if ($verifier !== null) {
            $params['code_verifier'] = $verifier;
        }

        $response = Http::request('POST', self::TOKEN, http_build_query($params));

        if ($response === null || $response['status'] >= 300) {
            return null;
        }

        return [
            'access_token'  => (string) ($response['body']['access_token'] ?? ''),
            'refresh_token' => isset($response['body']['refresh_token'])
                ? (string) $response['body']['refresh_token'] : null,
            'expires_in'    => (int) ($response['body']['expires_in'] ?? 3600),
            'scope'         => isset($response['body']['scope']) ? (string) $response['body']['scope'] : null,
        ];
    }

    public function refresh(string $refreshToken): ?array
    {
        $response = Http::request('POST', self::TOKEN, http_build_query([
            'refresh_token' => $refreshToken,
            'client_id'     => (string) Config::get('calendar.google.client_id', ''),
            'client_secret' => (string) Config::get('calendar.google.client_secret', ''),
            'grant_type'    => 'refresh_token',
        ]));

        if ($response === null || $response['status'] >= 300) {
            return null;
        }

        return [
            'access_token' => (string) ($response['body']['access_token'] ?? ''),
            'expires_in'   => (int) ($response['body']['expires_in'] ?? 3600),
        ];
    }

    public function accountEmail(string $accessToken): ?string
    {
        $response = Http::request('GET', self::USERINFO, null, ['Authorization: Bearer ' . $accessToken]);

        if ($response === null || $response['status'] >= 300) {
            return null;
        }

        $email = $response['body']['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function upsertEvent(string $accessToken, ?string $calendarId, ?string $remoteId, array $event): ?array
    {
        $calendar = rawurlencode($calendarId ?? 'primary');

        $payload = [
            'summary'     => $event['title'],
            'description' => $event['description'] ?? '',
            'location'    => $event['location'] ?? '',
            'start' => ['dateTime' => self::rfc3339($event['starts_at']), 'timeZone' => 'UTC'],
            'end'   => ['dateTime' => self::rfc3339($event['ends_at']),   'timeZone' => 'UTC'],
            // Our own marker. Lets the pull step recognise an event we created
            // even if the link row has gone, rather than adopting it as new.
            'extendedProperties' => [
                'private' => ['pilotage_session' => (string) ($event['session_id'] ?? '')],
            ],
        ];

        [$method, $url] = $remoteId === null
            ? ['POST', self::API . '/calendars/' . $calendar . '/events']
            : ['PATCH', self::API . '/calendars/' . $calendar . '/events/' . rawurlencode($remoteId)];

        $response = Http::request($method, $url, $payload, ['Authorization: Bearer ' . $accessToken]);

        if ($response === null || $response['status'] >= 300) {
            return null;
        }

        return [
            'id'         => (string) ($response['body']['id'] ?? ''),
            'etag'       => isset($response['body']['etag']) ? (string) $response['body']['etag'] : null,
            'updated_at' => isset($response['body']['updated'])
                ? gmdate('Y-m-d H:i:s', strtotime((string) $response['body']['updated'])) : null,
        ];
    }

    public function deleteEvent(string $accessToken, ?string $calendarId, string $remoteId): bool
    {
        $response = Http::request(
            'DELETE',
            self::API . '/calendars/' . rawurlencode($calendarId ?? 'primary') . '/events/' . rawurlencode($remoteId),
            null,
            ['Authorization: Bearer ' . $accessToken]
        );

        // 410 means it is already gone, which is the outcome we wanted.
        return $response !== null && ($response['status'] < 300 || $response['status'] === 410);
    }

    public function changes(string $accessToken, ?string $calendarId, ?string $syncToken): ?array
    {
        $params = ['maxResults' => 250, 'showDeleted' => 'true', 'singleEvents' => 'true'];

        if ($syncToken !== null && $syncToken !== '') {
            $params['syncToken'] = $syncToken;
        } else {
            // A first sync reads a window rather than the whole calendar. A
            // coach with ten years of history does not need all of it pulled
            // in to find the four sessions Pilotage put there.
            $params['timeMin'] = gmdate('c', strtotime('-30 days'));
        }

        $url = self::API . '/calendars/' . rawurlencode($calendarId ?? 'primary') . '/events?' . http_build_query($params);
        $response = Http::request('GET', $url, null, ['Authorization: Bearer ' . $accessToken]);

        if ($response === null) {
            return null;
        }

        // Google expires sync tokens. 410 is not a failure, it is an
        // instruction: forget the token and read again from scratch.
        if ($response['status'] === 410) {
            return ['events' => [], 'sync_token' => null, 'reset' => true];
        }

        if ($response['status'] >= 300) {
            return null;
        }

        $events = [];

        foreach ($response['body']['items'] ?? [] as $item) {
            $events[] = [
                'id'         => (string) ($item['id'] ?? ''),
                'deleted'    => ($item['status'] ?? '') === 'cancelled',
                'title'      => (string) ($item['summary'] ?? ''),
                'location'   => isset($item['location']) ? (string) $item['location'] : null,
                'starts_at'  => self::parseTime($item['start'] ?? null),
                'ends_at'    => self::parseTime($item['end'] ?? null),
                'updated_at' => isset($item['updated'])
                    ? gmdate('Y-m-d H:i:s', strtotime((string) $item['updated'])) : null,
                'etag'       => isset($item['etag']) ? (string) $item['etag'] : null,
                'session_id' => $item['extendedProperties']['private']['pilotage_session'] ?? null,
            ];
        }

        return [
            'events'     => $events,
            'sync_token' => isset($response['body']['nextSyncToken'])
                ? (string) $response['body']['nextSyncToken'] : null,
            'reset'      => false,
        ];
    }

    private static function rfc3339(string $utcDateTime): string
    {
        return gmdate('c', strtotime($utcDateTime . ' UTC'));
    }

    /** @param array<string,mixed>|null $slot */
    private static function parseTime(?array $slot): ?string
    {
        // An all-day event has `date` and no `dateTime`. It is not a coaching
        // session, but it will appear in the delta, so it has to parse rather
        // than blow up.
        $value = $slot['dateTime'] ?? $slot['date'] ?? null;

        return is_string($value) ? gmdate('Y-m-d H:i:s', strtotime($value)) : null;
    }
}
