<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services\Calendar;

use Bizorca\Pilotage\Core\Config;

/**
 * Microsoft 365 / Outlook, via Graph.
 *
 * Same shape as Google, three differences that matter:
 *
 * The delta link is a whole URL, not a token. Graph hands back an
 * `@odata.deltaLink` containing the endpoint and the token together, and the
 * next call is a GET of that URL verbatim. Storing just the token and trying to
 * rebuild the URL does not work.
 *
 * A deleted event is annotated `@removed` rather than carrying a status. It is
 * otherwise almost empty, so the id is all there is to go on.
 *
 * `offline_access` is the scope that returns a refresh token. Omitting it gets
 * you an hour of working sync and then silence, which is a particularly
 * annoying bug to find later.
 */
final class MicrosoftProvider implements Provider
{
    private const AUTH  = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    private const TOKEN = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    private const API   = 'https://graph.microsoft.com/v1.0';

    private const SCOPES = 'offline_access openid email Calendars.ReadWrite';

    public function key(): string
    {
        return 'microsoft';
    }

    public function label(): string
    {
        return 'Outlook / Microsoft 365';
    }

    public function authorizeUrl(string $state, string $redirectUri, ?string $challenge = null): string
    {
        $params = [
            'client_id'     => (string) Config::get('calendar.microsoft.client_id', ''),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'response_mode' => 'query',
            'scope'         => self::SCOPES,
            'state'         => $state,
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
            'client_id'     => (string) Config::get('calendar.microsoft.client_id', ''),
            'client_secret' => (string) Config::get('calendar.microsoft.client_secret', ''),
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
            'scope'         => self::SCOPES,
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
            'client_id'     => (string) Config::get('calendar.microsoft.client_id', ''),
            'client_secret' => (string) Config::get('calendar.microsoft.client_secret', ''),
            'grant_type'    => 'refresh_token',
            'scope'         => self::SCOPES,
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
        $response = Http::request('GET', self::API . '/me', null, ['Authorization: Bearer ' . $accessToken]);

        if ($response === null || $response['status'] >= 300) {
            return null;
        }

        foreach ([$response['body']['mail'] ?? null, $response['body']['userPrincipalName'] ?? null] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    public function upsertEvent(string $accessToken, ?string $calendarId, ?string $remoteId, array $event): ?array
    {
        $payload = [
            'subject' => $event['title'],
            'body'    => ['contentType' => 'text', 'content' => $event['description'] ?? ''],
            'location' => ['displayName' => $event['location'] ?? ''],
            'start'   => ['dateTime' => self::graphTime($event['starts_at']), 'timeZone' => 'UTC'],
            'end'     => ['dateTime' => self::graphTime($event['ends_at']),   'timeZone' => 'UTC'],
            // Graph's equivalent of Google's extended properties. Same job:
            // recognise our own event if the link row is ever lost.
            'singleValueExtendedProperties' => [[
                'id'    => 'String {66f5a359-4659-4830-9070-00047ec6ac6e} Name pilotage_session',
                'value' => (string) ($event['session_id'] ?? ''),
            ]],
        ];

        $base = self::API . '/me' . ($calendarId === null ? '' : '/calendars/' . rawurlencode($calendarId));

        [$method, $url] = $remoteId === null
            ? ['POST', $base . '/events']
            : ['PATCH', $base . '/events/' . rawurlencode($remoteId)];

        $response = Http::request($method, $url, $payload, ['Authorization: Bearer ' . $accessToken]);

        if ($response === null || $response['status'] >= 300) {
            return null;
        }

        return [
            'id'         => (string) ($response['body']['id'] ?? ''),
            'etag'       => isset($response['body']['@odata.etag']) ? (string) $response['body']['@odata.etag'] : null,
            'updated_at' => isset($response['body']['lastModifiedDateTime'])
                ? gmdate('Y-m-d H:i:s', strtotime((string) $response['body']['lastModifiedDateTime'])) : null,
        ];
    }

    public function deleteEvent(string $accessToken, ?string $calendarId, string $remoteId): bool
    {
        $base = self::API . '/me' . ($calendarId === null ? '' : '/calendars/' . rawurlencode($calendarId));

        $response = Http::request(
            'DELETE',
            $base . '/events/' . rawurlencode($remoteId),
            null,
            ['Authorization: Bearer ' . $accessToken]
        );

        return $response !== null && ($response['status'] < 300 || $response['status'] === 404);
    }

    public function changes(string $accessToken, ?string $calendarId, ?string $syncToken): ?array
    {
        // The stored value IS a full URL. Graph builds it; we replay it.
        if ($syncToken !== null && str_starts_with($syncToken, 'https://')) {
            $url = $syncToken;
        } else {
            $base = self::API . '/me' . ($calendarId === null ? '' : '/calendars/' . rawurlencode($calendarId));
            $url = $base . '/calendarView/delta?' . http_build_query([
                'startDateTime' => gmdate('c', strtotime('-30 days')),
                'endDateTime'   => gmdate('c', strtotime('+365 days')),
            ]);
        }

        $response = Http::request('GET', $url, null, ['Authorization: Bearer ' . $accessToken]);

        if ($response === null) {
            return null;
        }

        // 410 Gone means the same thing here as it does at Google: the delta
        // link has expired, so forget it and read again from scratch.
        if ($response['status'] === 410) {
            return ['events' => [], 'sync_token' => null, 'reset' => true];
        }

        if ($response['status'] >= 300) {
            return null;
        }

        $events = [];

        foreach ($response['body']['value'] ?? [] as $item) {
            $removed = array_key_exists('@removed', $item);

            $events[] = [
                'id'         => (string) ($item['id'] ?? ''),
                'deleted'    => $removed,
                'title'      => (string) ($item['subject'] ?? ''),
                'location'   => $item['location']['displayName'] ?? null,
                'starts_at'  => self::parseTime($item['start'] ?? null),
                'ends_at'    => self::parseTime($item['end'] ?? null),
                'updated_at' => isset($item['lastModifiedDateTime'])
                    ? gmdate('Y-m-d H:i:s', strtotime((string) $item['lastModifiedDateTime'])) : null,
                'etag'       => isset($item['@odata.etag']) ? (string) $item['@odata.etag'] : null,
                'session_id' => null,
            ];
        }

        return [
            'events'     => $events,
            'sync_token' => isset($response['body']['@odata.deltaLink'])
                ? (string) $response['body']['@odata.deltaLink'] : null,
            'reset'      => false,
        ];
    }

    /** Graph wants no timezone suffix when timeZone is supplied separately. */
    private static function graphTime(string $utcDateTime): string
    {
        return gmdate('Y-m-d\TH:i:s', strtotime($utcDateTime . ' UTC'));
    }

    /** @param array<string,mixed>|null $slot */
    private static function parseTime(?array $slot): ?string
    {
        $value = $slot['dateTime'] ?? null;

        if (!is_string($value)) {
            return null;
        }

        // Graph returns naive datetimes with the zone in a sibling field.
        $zone = (string) ($slot['timeZone'] ?? 'UTC');

        try {
            $dt = new \DateTimeImmutable($value, new \DateTimeZone($zone === '' ? 'UTC' : $zone));
        } catch (\Throwable) {
            return null;
        }

        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
