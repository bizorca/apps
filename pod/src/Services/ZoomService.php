<?php

namespace Bizorca\Pod\Services;

use Bizorca\Pod\Core\Database;

class ZoomService
{
    private string $accountId;
    private string $clientId;
    private string $clientSecret;
    private const API = 'https://api.zoom.us/v2';
    private const TOKEN_URL = 'https://zoom.us/oauth/token';

    public function __construct()
    {
        $this->accountId    = PD_ZOOM_ACCOUNT_ID;
        $this->clientId     = PD_ZOOM_CLIENT_ID;
        $this->clientSecret = PD_ZOOM_CLIENT_SECRET;
    }

    /** All three PD_ZOOM_* keys are set. */
    public static function configured(): bool
    {
        return PD_ZOOM_ACCOUNT_ID !== '' && PD_ZOOM_CLIENT_ID !== '' && PD_ZOOM_CLIENT_SECRET !== '';
    }

    /** Read-only check for bin/zoom-check.php: fetch a token, GET /users/me. */
    public function whoAmI(): array
    {
        return $this->request('GET', '/users/me');
    }

    private function getAccessToken(): string
    {
        $cached = Database::fetchOne(
            'SELECT access_token FROM pd_zoom_token_cache WHERE expires_at > NOW() ORDER BY id DESC LIMIT 1'
        );

        if ($cached) {
            return $cached['access_token'];
        }

        $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);

        $ch = curl_init(self::TOKEN_URL . '?grant_type=account_credentials&account_id=' . urlencode($this->accountId));
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . $credentials],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = json_decode((string) curl_exec($ch), true);
        curl_close($ch);

        if (empty($response['access_token'])) {
            throw new \RuntimeException('Zoom OAuth failed: ' . json_encode($response));
        }

        // Expiry computed by MySQL, the same clock as the NOW() check above.
        Database::query('DELETE FROM pd_zoom_token_cache');
        Database::insert(
            'INSERT INTO pd_zoom_token_cache (access_token, expires_at) VALUES (?, DATE_ADD(NOW(), INTERVAL ? SECOND))',
            [$response['access_token'], (int) ($response['expires_in'] ?? 3600) - 60]
        );

        return $response['access_token'];
    }

    private function request(string $method, string $endpoint, array $body = []): array
    {
        $token = $this->getAccessToken();

        $ch = curl_init(self::API . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = json_decode((string) curl_exec($ch), true);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new \RuntimeException('Zoom API error ' . $status . ': ' . json_encode($response));
        }

        return $response ?? [];
    }

    public function createMeeting(string $topic, string $startsAt, int $durationMinutes): array
    {
        $result = $this->request('POST', '/users/me/meetings', [
            'topic'      => $topic,
            'type'       => 2, // scheduled
            'start_time' => (new \DateTime($startsAt))->format('Y-m-d\TH:i:s'),
            // Explicit: without it Zoom reads start_time in the Zoom account's
            // own zone, which only matched by luck.
            'timezone'   => PD_TIMEZONE,
            'duration'   => $durationMinutes,
            'settings'   => [
                'host_video'        => true,
                'participant_video' => true,
                'join_before_host'  => false,
                'waiting_room'      => true,
            ],
        ]);

        return [
            'meeting_id' => (string)$result['id'],
            'join_url'   => $result['join_url'],
        ];
    }

    public function deleteMeeting(string $meetingId): void
    {
        try {
            $this->request('DELETE', '/meetings/' . $meetingId);
        } catch (\RuntimeException) {
            // Ignore — meeting may already be gone
        }
    }
}
