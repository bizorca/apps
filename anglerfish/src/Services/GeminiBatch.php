<?php

namespace Anglerfish\Services;

/**
 * Gemini's Batch API — the same image model at half price, answered later.
 *
 * $0.067 a 2K image against $0.134, with a 24-hour turnaround target and
 * results kept for six weeks. Verified against the live key on 2026-09-04:
 * `GET /v1beta/models` lists `batchGenerateContent` on `gemini-3-pro-image`,
 * `gemini-3.1-flash-image` and `gemini-3.1-flash-lite-image`.
 *
 * Two things differ from `ServerJobs::generateImage()`, and both are why this
 * is a separate service rather than a flag on that one:
 *
 * 1. **Batch speaks `generateContent`, not the Interactions API.** There is no
 *    `response_format` here; the size and aspect ratio go in
 *    `generationConfig.imageConfig` instead. Same model, same picture,
 *    different envelope.
 * 2. **Requests go inline, not through the Files API.** The 20MB cap is on the
 *    request, and a request is prompts only — roughly 2KB each, so even the
 *    whole library's 494 chapters would be about 1MB. What is big is the
 *    *response*: base64 at 2K runs a couple of megabytes an image, so batches
 *    are chunked at CHUNK requests to keep one poll from having to hold a
 *    hundred megabytes of JSON in PHP's memory.
 */
final class GeminiBatch
{
    /**
     * Requests per batch. Bounded by the response size, not the request size.
     *
     * A finished 2K entry measured on 2026-09-04 is about 800KB of base64 image
     * *plus* a 1.2MB `thoughtSignature` that comes back whether or not anyone
     * wants it — call it 3–5MB an image once decoded. PHP has 768MB on this
     * host and json_decode holds the body and the array at once, so sixteen is
     * the number that keeps a poll comfortably inside it.
     */
    public const CHUNK = 16;

    /** Batch price for a 1K–2K Nano Banana Pro image. Half of standard. */
    public const PRICE_PER_IMAGE = 0.067;

    /** Terminal states, in Gemini's spelling. */
    public const SUCCEEDED = 'BATCH_STATE_SUCCEEDED';
    public const FAILED    = 'BATCH_STATE_FAILED';
    public const CANCELLED = 'BATCH_STATE_CANCELLED';
    public const EXPIRED   = 'BATCH_STATE_EXPIRED';

    private static function request(string $method, string $path, ?array $body = null,
                                    int $timeout = 300): array
    {
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        $key = (string) ($cfg['gemini']['key'] ?? '');
        if ($key === '') {
            throw new \RuntimeException('GEMINI_API_KEY not set on the server.');
        }

        $ch = curl_init('https://generativelanguage.googleapis.com/' . ltrim($path, '/'));
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $key,
                'User-Agent: AnglerfishServer/1.0',
            ],
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException("Gemini unreachable: $err");
        }
        $out = json_decode((string) $raw, true);
        if ($code >= 400) {
            $msg = $out['error']['message'] ?? substr((string) $raw, 0, 300);
            throw new \RuntimeException("Gemini $code: $msg");
        }
        if (!is_array($out)) {
            throw new \RuntimeException('Gemini returned something that is not JSON.');
        }
        return $out;
    }

    /**
     * One inline request line: a prompt, a key it comes back under, and the
     * size settings. `responseModalities` must name IMAGE or the model answers
     * with a description of the picture instead of the picture.
     *
     * @return array<string,mixed>
     */
    public static function imageRequest(string $prompt, string $key,
                                        string $aspect = '16:9',
                                        string $size = '2K'): array
    {
        return [
            'request' => [
                'contents' => [[
                    'role'  => 'user',
                    'parts' => [['text' => $prompt]],
                ]],
                'generation_config' => [
                    'responseModalities' => ['IMAGE'],
                    'imageConfig' => [
                        'aspectRatio' => $aspect,
                        'imageSize'   => $size,
                    ],
                ],
            ],
            'metadata' => ['key' => $key],
        ];
    }

    /**
     * Submit a batch. Returns the remote job name, which is the only handle
     * there is — without it the results cannot be collected at all.
     *
     * @param array<int,array<string,mixed>> $requests from imageRequest()
     * @return array{name:string,state:string}
     */
    public static function submit(string $model, array $requests, string $displayName): array
    {
        if (!$requests) {
            throw new \RuntimeException('Nothing to submit — the batch is empty.');
        }
        if (count($requests) > self::CHUNK) {
            throw new \RuntimeException(
                'Batch of ' . count($requests) . ' exceeds the ' . self::CHUNK
                . '-request chunk; split it before submitting.');
        }

        $res = self::request('POST', "v1beta/models/{$model}:batchGenerateContent", [
            'batch' => [
                'display_name'  => mb_substr($displayName, 0, 120),
                'input_config'  => ['requests' => ['requests' => array_values($requests)]],
            ],
        ], 120);

        $name = (string) ($res['name'] ?? '');
        if ($name === '') {
            throw new \RuntimeException(
                'Gemini accepted the batch but returned no name: '
                . mb_substr(json_encode($res) ?: '', 0, 300));
        }
        return ['name' => $name, 'state' => self::stateOf($res)];
    }

    /** @return array<string,mixed> the raw operation */
    public static function status(string $remoteName): array
    {
        return self::request('GET', 'v1beta/' . ltrim($remoteName, '/'), null, 300);
    }

    public static function cancel(string $remoteName): void
    {
        self::request('POST', 'v1beta/' . ltrim($remoteName, '/') . ':cancel', [], 60);
    }

    /**
     * The state, wherever it is hiding.
     *
     * Long-running-operation shapes vary between `metadata.state`,
     * `response.state` and a bare `done` flag, and guessing one of them is how
     * a poller reports "still running" forever on a finished job.
     */
    public static function stateOf(array $op): string
    {
        foreach ([$op['metadata']['state'] ?? null, $op['response']['state'] ?? null,
                  $op['state'] ?? null] as $s) {
            if (is_string($s) && $s !== '') {
                return $s;
            }
        }
        return !empty($op['done']) ? self::SUCCEEDED : 'BATCH_STATE_UNSPECIFIED';
    }

    public static function isDone(array $op): bool
    {
        return !empty($op['done']) || in_array(self::stateOf($op),
            [self::SUCCEEDED, self::FAILED, self::CANCELLED, self::EXPIRED], true);
    }

    /**
     * Pull the images out of a finished batch, keyed by the key they were sent
     * with.
     *
     * Written as a walk rather than a path lookup on purpose. The response
     * nests differently depending on whether the batch was inline or file-based
     * (`inlinedResponses.inlinedResponses[]` versus a downloaded JSONL), and
     * both wrap each answer in a full GenerateContentResponse. A walk that
     * tracks the nearest enclosing key survives either shape; a hardcoded path
     * breaks on the first one Google changes.
     *
     * @return array{images:array<string,string>,errors:array<string,string>}
     */
    public static function harvest(array $op): array
    {
        $images = [];
        $errors = [];
        self::walk($op, null, $images, $errors);
        return ['images' => $images, 'errors' => $errors];
    }

    /**
     * @param array<string,string> $images
     * @param array<string,string> $errors
     */
    private static function walk(array $node, ?string $key, array &$images, array &$errors): void
    {
        // A response envelope names itself before it carries anything.
        foreach ([$node['metadata']['key'] ?? null, $node['key'] ?? null] as $k) {
            if (is_string($k) && $k !== '') {
                $key = $k;
                break;
            }
        }

        if ($key !== null) {
            foreach (['inlineData', 'inline_data'] as $f) {
                if (isset($node[$f]['data']) && is_string($node[$f]['data'])
                    && !isset($images[$key])) {
                    $images[$key] = $node[$f]['data'];
                }
            }
            if (isset($node['error']) && is_array($node['error'])) {
                $errors[$key] ??= (string) ($node['error']['message']
                    ?? json_encode($node['error']));
            }
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                self::walk($child, $key, $images, $errors);
            }
        }
    }

    /**
     * A copy of an operation safe to write to disk: base64 payloads replaced by
     * their length. The first run of anything against a new API shape wants a
     * record of what came back, and a raw dump of a 25-image batch is 50MB of
     * unreadable base64.
     *
     * @return array<string,mixed>
     */
    public static function sanitise(array $node): array
    {
        $out = [];
        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $out[$k] = self::sanitise($v);
            } elseif (is_string($v) && strlen($v) > 512) {
                $out[$k] = '[' . strlen($v) . ' bytes elided]';
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
