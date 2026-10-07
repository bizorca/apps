<?php
/**
 * Outgoing mail via SMTP2GO, for the shared account pages and request.php.
 *
 * With no SMTP2GO_API_KEY in .env.php this appends to data/mail.log instead of
 * sending, and returns false, so a missing key is visible rather than silent.
 */

declare(strict_types=1);

function tl_mail(string $to, string $subject, string $text, string $replyTo = ''): bool
{
    $key  = (string) tl_env('SMTP2GO_API_KEY', '');
    $from = (string) tl_env('MAIL_FROM', 'Bizorca Tools <tools@bizorca.com>');

    if ($key === '') {
        $dir = TL_PRIVATE . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            $dir . '/mail.log',
            sprintf("[%s] to=%s from=%s\nsubject: %s\n\n%s\n%s\n", date('c'), $to, $from, $subject, $text, str_repeat('-', 60)),
            FILE_APPEND | LOCK_EX
        );
        return false;
    }

    $payload = [
        'api_key'   => $key,
        'to'        => [$to],
        'sender'    => $from,
        'subject'   => $subject,
        'text_body' => $text,
    ];
    $replyTo = str_replace(["\r", "\n"], '', $replyTo);
    if ($replyTo !== '') {
        $payload['custom_headers'] = [['header' => 'Reply-To', 'value' => $replyTo]];
    }

    $ch = curl_init('https://api.smtp2go.com/v3/email/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Parse it. SMTP2GO's JSON has no space after the colon, and matching the
    // substring '"succeeded": 1' made every send look like a failure on
    // financialhypnosis.com.
    $json = is_string($body) ? json_decode($body, true) : null;

    return $code === 200 && is_array($json) && (int) ($json['data']['succeeded'] ?? 0) > 0;
}
