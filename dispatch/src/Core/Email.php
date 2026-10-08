<?php

declare(strict_types=1);

namespace Dispatch\Core;

class Email
{
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        array $cc = []
    ): bool {
        $apiKey      = DP_MAIL_KEY;
        $apiUrl      = 'https://api.smtp2go.com/v3/email/send';
        $fromAddress = DP_MAIL_FROM_ADDRESS;
        $fromName    = DP_MAIL_FROM_NAME;

        if ($apiKey === '') {
            error_log('Dispatch email not sent: SMTP2GO_API_KEY not configured');
            return false;
        }

        $body = [
            'api_key'   => $apiKey,
            // Names are user-editable: no CR/LF or angle brackets in a header.
            'to'        => [trim(str_replace(["\r", "\n", '<', '>'], '', $toName)) . " <{$toEmail}>"],
            'sender'    => "{$fromName} <{$fromAddress}>",
            'subject'   => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody ?: strip_tags($htmlBody),
        ];

        if (!empty($cc)) {
            $body['cc'] = $cc;
        }

        $payload = json_encode($body);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('Email API failed: HTTP ' . $httpCode . ' — ' . $response);
            return false;
        }

        $result = json_decode($response, true);
        if (($result['data']['succeeded'] ?? 0) < 1) {
            error_log('Email API failed: ' . $response);
            return false;
        }

        return true;
    }

    /**
     * Render an email view template.
     */
    public static function renderTemplate(string $template, array $data = []): string
    {
        extract($data);
        ob_start();
        $templatePath = DP_ROOT . '/views/emails/' . $template . '.php';
        if (file_exists($templatePath)) {
            include $templatePath;
        }
        return ob_get_clean();
    }
}
