<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services\Calendar;

/**
 * The curl the providers share.
 *
 * Small on purpose. Both providers are JSON over HTTPS with a bearer token, so
 * the only thing worth factoring out is the request itself — and having it in
 * one place means the timeout, the error handling and the "never log the body"
 * rule are decided once.
 */
final class Http
{
    /** Seconds. A calendar call that takes longer than this has failed. */
    public const TIMEOUT = 20;

    /**
     * @param array<string,mixed>|string|null $body Array is sent as JSON.
     * @param array<int,string> $headers
     * @return array{status:int, body:array<string,mixed>, raw:string}|null
     */
    public static function request(
        string $method,
        string $url,
        array|string|null $body = null,
        array $headers = []
    ): ?array {
        $ch = curl_init($url);

        if ($ch === false) {
            error_log('Calendar: curl_init failed');
            return null;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ];

        if (is_array($body)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        } elseif (is_string($body)) {
            $options[CURLOPT_POSTFIELDS] = $body;
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if ($headers !== []) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($raw === false) {
            // The URL alone, never the body: a token exchange body contains the
            // refresh token, and an event body contains a client's meeting.
            error_log('Calendar: ' . $method . ' ' . self::safeUrl($url) . ' failed: ' . $error);
            return null;
        }

        $decoded = json_decode((string) $raw, true);

        return [
            'status' => $status,
            'body'   => is_array($decoded) ? $decoded : [],
            'raw'    => (string) $raw,
        ];
    }

    /** Query strings can carry tokens. Log the path, not the parameters. */
    public static function safeUrl(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['host'] ?? '?') . ($parts['path'] ?? '');
    }

    /**
     * A readable reason from a provider error body, for showing a coach.
     *
     * @param array<string,mixed> $body
     */
    public static function reason(array $body, int $status): string
    {
        foreach ([
            $body['error']['message'] ?? null,
            $body['error_description'] ?? null,
            is_string($body['error'] ?? null) ? $body['error'] : null,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return mb_substr(trim($candidate), 0, 300);
            }
        }

        return 'The calendar service returned ' . $status . '.';
    }
}
