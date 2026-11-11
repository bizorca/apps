<?php

namespace Anglerfish\Services;

use Anthropic\Client;
use Anthropic\RequestOptions;
use GuzzleHttp\Client as Guzzle;

/**
 * Claude, for prose (SPEC §11.2).
 *
 * The composer runs here rather than on Gemini because the job is voice
 * matching against a long, specific style guide, and that is what this model is
 * good at. Image generation stays on Gemini — Claude does not generate images —
 * and extraction stays in Claude Code CLI, where it costs a subscription rather
 * than a per-token bill.
 */
final class Anthropic
{
    /** Leaves room for adaptive thinking plus the post itself. */
    private const MAX_TOKENS = 16000;

    /**
     * Hard ceiling per attempt.
     *
     * The web tick runs under PHP's 120s cap, so the SDK has to give up first —
     * otherwise the process is killed mid-request and the job's lease dangles
     * for the full 600s before anything can retry it.
     */
    private const TIMEOUT = 90.0;

    /**
     * Cron and `cli.php work` have no 120s cap, and a weekly post composed
     * against the full style guide and story bank can run past 90s at high
     * effort, so the command line gets five minutes (still inside the
     * 600s job lease). Web ticks keep the 90s ceiling above.
     */
    private static function timeout(): float
    {
        return PHP_SAPI === 'cli' ? 300.0 : self::TIMEOUT;
    }

    public static function configured(): bool
    {
        return self::key() !== '';
    }

    private static function key(): string
    {
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        return (string) ($cfg['anthropic']['key'] ?? '');
    }

    public static function model(): string
    {
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        return (string) ($cfg['anthropic']['model'] ?? 'claude-opus-5-5');
    }

    /**
     * Models where `thinking` must not be sent at all.
     *
     * On the Fable family thinking is always on and any explicit configuration
     * is a 400 — including `{type: "disabled"}`. This app never sent a thinking
     * parameter, so nothing here changes today; the list exists so that adding
     * one later for Opus does not silently break Hypnologue, which composes on
     * Fable 5.1.
     */
    private const THINKING_ALWAYS_ON = ['claude-opus-5-5', 'claude-fable-5-1', 'claude-fable-5'];

    public static function thinkingAlwaysOn(string $model): bool
    {
        return in_array($model, self::THINKING_ALWAYS_ON, true);
    }

    /**
     * Assemble output_config for a call.
     *
     * `effort` is a publication's decision (Publication::effort) and controls
     * how much thinking the model does before answering. With none chosen this
     * sends `high`, which is what every Bizorca compose got on Opus 5 by
     * default; Opus 5.5's own default is medium.
     *
     * @param  array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function outputConfig(?string $effort, array $extra = []): array
    {
        $cfg = $extra;
        if ($effort !== null && in_array($effort, ['low', 'medium', 'high', 'xhigh', 'max'], true)) {
            $cfg['effort'] = $effort;
        } else {
            // Opus 5.5 defaults to medium where Opus 5 defaulted to high, so
            // "no effort chosen" has to say high out loud to keep what every
            // compose and art direction got before the switch (2026-11-11).
            $cfg['effort'] = 'high';
        }
        return $cfg;
    }

    private static function client(): Client
    {
        $key = self::key();
        if ($key === '') {
            throw new \RuntimeException('ANTHROPIC_API_KEY not set on the server.');
        }

        // The SDK's own `timeout` is advisory — the PSR-18 transporter is what
        // actually enforces it, so Guzzle has to be told separately.
        //
        // maxRetries is 0 on purpose. The SDK default of 2 would turn one
        // timed-out call into 270s of wall clock. The job queue already retries
        // (attempts < max_attempts), so retrying here as well only risks
        // getting the PHP process killed before it can record the failure.
        return new Client(
            apiKey: $key,
            requestOptions: RequestOptions::with(
                timeout: self::timeout(),
                maxRetries: 0,
                transporter: new Guzzle([
                    'timeout'         => self::timeout(),
                    'connect_timeout' => 10.0,
                ]),
            ),
        );
    }

    /**
     * A completion constrained to a JSON schema.
     *
     * Used where the result feeds machinery rather than a reader — art
     * direction becomes another model's prompt, so a missing field is a broken
     * render, not a slightly worse sentence. The schema is enforced at the API
     * rather than parsed hopefully on this side.
     *
     * @param  array<string,mixed> $schema
     * @return array{data:array<string,mixed>,model:string}
     */
    public static function structured(
        string $system,
        string $user,
        array $schema,
        ?string $model = null,
        ?string $effort = null,
    ): array {
        $model ??= self::model();
        if (trim($system) === '') {
            throw new \RuntimeException('Refusing to call Claude with an empty system prompt.');
        }

        $message = self::client()->messages->create(
            model: $model,
            maxTokens: self::MAX_TOKENS,
            system: [[
                'type'         => 'text',
                'text'         => $system,
                'cacheControl' => ['type' => 'ephemeral'],
            ]],
            messages: [['role' => 'user', 'content' => $user]],
            outputConfig: self::outputConfig(
                $effort,
                ['format' => ['type' => 'json_schema', 'schema' => $schema]]
            ),
        );

        if ($message->stopReason === 'refusal') {
            throw new \RuntimeException('Claude declined this request.');
        }

        $json = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $json .= $block->text;
            }
        }
        $data = json_decode(trim($json), true);
        if (!is_array($data)) {
            // stop_reason tells you whether this was truncation or something
            // else, which is the difference between raising max_tokens and
            // fixing the schema.
            throw new \RuntimeException(
                "Claude returned no usable JSON (stop_reason: {$message->stopReason}).");
        }

        return ['data' => $data, 'model' => $message->model];
    }

    /**
     * One completion. Returns the concatenated text blocks.
     *
     * @return array{text:string,model:string,input_tokens:int,output_tokens:int,cached:int}
     */
    public static function complete(
        string $system,
        string $user,
        ?string $model = null,
        ?string $effort = null,
    ): array {
        $model ??= self::model();

        // An empty system prompt with a cache breakpoint is a 400 from the API
        // ("cache_control cannot be set for empty text blocks"), which reads as
        // an SDK fault rather than what it is: a spec file that did not deploy.
        if (trim($system) === '') {
            throw new \RuntimeException(
                'Refusing to call Claude with an empty system prompt — a prompt '
                . 'spec is missing. Check worker/prompts/ deployed.');
        }

        $message = self::client()->messages->create(
            model: $model,
            maxTokens: self::MAX_TOKENS,
            // The voice and format specs are identical on every compose, so the
            // breakpoint goes at the end of them. Everything that varies is in
            // the user turn, after the cached prefix.
            system: [[
                'type'         => 'text',
                'text'         => $system,
                'cacheControl' => ['type' => 'ephemeral'],
            ]],
            messages: [['role' => 'user', 'content' => $user]],
            outputConfig: self::outputConfig($effort),
        );

        // A refusal is a successful HTTP 200 with empty content, so this has to
        // be checked before reading blocks rather than caught as an exception.
        if ($message->stopReason === 'refusal') {
            $why = $message->stopDetails->explanation ?? 'no explanation given';
            throw new \RuntimeException("Claude declined this request: $why");
        }

        $text = '';
        foreach ($message->content as $block) {
            // Thinking blocks come first and carry no text under the default
            // display setting; only text blocks are the answer.
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        if (trim($text) === '') {
            throw new \RuntimeException(
                "Claude returned no text (stop_reason: {$message->stopReason})."
            );
        }

        return [
            'text'          => $text,
            'model'         => $message->model,
            'input_tokens'  => (int) $message->usage->inputTokens,
            'output_tokens' => (int) $message->usage->outputTokens,
            'cached'        => (int) ($message->usage->cacheReadInputTokens ?? 0),
        ];
    }
}
