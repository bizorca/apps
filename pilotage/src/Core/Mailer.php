<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Outbound mail via the SMTP2GO HTTP API.
 *
 * The HTTP API rather than SMTP: one curl call, no socket dance, no
 * dependency, and it works fine on shared hosting where outbound SMTP ports
 * are often filtered.
 *
 * Sending always uses the base domain (spec §7) — SPF, DKIM, and DMARC are
 * published for it alone. The tenant's firm name goes in the display name so
 * the client sees their coach, not us.
 *
 * With no API key configured (development), messages are written to
 * storage/mail.log and reported as sent, so the whole flow is exercisable
 * without wiring up a provider.
 */
final class Mailer
{
    private const ENDPOINT = 'https://api.smtp2go.com/v3/email/send';

    /** The product's own name, used when writing to a firm rather than a client. */
    public const PLATFORM_NAME = 'Pilotage';

    /**
     * Which name goes in the From line.
     *
     * This is the white-label rule, and it turns on WHO IS READING, not on
     * which code path sent the message:
     *
     *   client-side recipient -> the firm's name. A business owner should see
     *       their advisor, not a piece of software they have no relationship
     *       with. That is the whole reason subdomain tenancy was chosen.
     *
     *   firm-side recipient   -> "Pilotage". A coach receiving their own
     *       sign-in link is a customer of ours. Signing that as their own firm
     *       is confusing at best — it reads as though their practice emailed
     *       itself — and it hides who to contact when something breaks.
     *
     * @param array<string,mixed> $tenant
     */
    public static function senderName(?int $recipientClientOrgId, array $tenant): string
    {
        if ($recipientClientOrgId === null) {
            return self::PLATFORM_NAME;
        }

        // Treat empty as absent at every step. `??` only catches null, so a
        // tenant row with name = '' would otherwise send with a blank display
        // name — which reads as a spoofed or broken sender.
        foreach ([$tenant['mail_from_name'] ?? null, $tenant['name'] ?? null] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return self::PLATFORM_NAME;
    }

    /** @var array<int,array<string,mixed>> Captured instead of sent, when capturing is on. */
    private static array $captured = [];
    private static bool $capture = false;

    /**
     * @param array<string,string> $headers
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $textBody,
        ?string $htmlBody = null,
        ?string $fromName = null
    ): bool {
        $fromAddr = (string) Config::get('mail.from_addr');
        $fromName = $fromName ?? (string) Config::get('mail.from_name');

        $message = [
            'to'        => [self::rfcAddress($toEmail, $toName)],
            'sender'    => self::rfcAddress($fromAddr, $fromName),
            'subject'   => $subject,
            'text_body' => $textBody,
        ];

        if ($htmlBody !== null) {
            $message['html_body'] = $htmlBody;
        }

        if (self::$capture) {
            self::$captured[] = $message;
            return true;
        }

        $apiKey = (string) Config::get('mail.api_key', '');

        if ($apiKey === '') {
            return self::writeToLog($message);
        }

        return self::post($apiKey, $message);
    }

    /** @param array<string,mixed> $message */
    private static function post(string $apiKey, array $message): bool
    {
        $payload = $message + ['api_key' => $apiKey];

        $ch = curl_init(self::ENDPOINT);

        if ($ch === false) {
            error_log('Mailer: curl_init failed');
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($response === false || $status >= 300) {
            // Never log the payload: it contains sign-in links.
            error_log('Mailer: send failed, status ' . $status . ' ' . $error);
            return false;
        }

        return true;
    }

    /** @param array<string,mixed> $message */
    private static function writeToLog(array $message): bool
    {
        $dir = (string) Config::get('app.storage');

        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            error_log('Mailer: cannot create storage directory');
            return false;
        }

        $entry = sprintf(
            "[%s] To: %s\nSubject: %s\n\n%s\n%s\n",
            date('c'),
            $message['to'][0] ?? '',
            $message['subject'] ?? '',
            $message['text_body'] ?? '',
            str_repeat('-', 70)
        );

        return file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function rfcAddress(string $email, string $name): string
    {
        // Strip anything that could inject a header, then quote the display name.
        $name = trim(preg_replace('/[\r\n<>"]+/', '', $name) ?? '');
        $email = trim(preg_replace('/[\r\n<>,;]+/', '', $email) ?? '');

        return $name === '' ? $email : '"' . $name . '" <' . $email . '>';
    }

    // ---- test seams ----

    public static function startCapturing(): void
    {
        self::$capture = true;
        self::$captured = [];
    }

    public static function stopCapturing(): void
    {
        self::$capture = false;
        self::$captured = [];
    }

    /** @return array<int,array<string,mixed>> */
    public static function captured(): array
    {
        return self::$captured;
    }
}
