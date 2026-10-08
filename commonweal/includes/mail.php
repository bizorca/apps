<?php

function sendMail(string $to, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    if (!SMTP2GO_API_KEY) return false;
    if (!$textBody) {
        $textBody = strip_tags($htmlBody);
    }
    $payload = [
        'api_key'   => SMTP2GO_API_KEY,
        'to'        => ["{$toName} <{$to}>"],
        'sender'    => APP_NAME . ' <' . MAIL_FROM . '>',
        'subject'   => $subject,
        'html_body' => $htmlBody,
        'text_body' => $textBody,
    ];
    $ch = curl_init('https://api.smtp2go.com/v3/email/send');
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return false;
    $data = json_decode($response, true);
    return isset($data['data']['succeeded']) && $data['data']['succeeded'] > 0;
}

function sendPasswordResetEmail(string $to, string $name, string $token): bool {
    $resetUrl = APP_URL . url('/reset-password.php') . '?token=' . urlencode($token);
    $subject  = 'Reset your ' . APP_NAME . ' password';
    $html     = "
    <p>Hi {$name},</p>
    <p>You requested a password reset. Click the link below to set a new password. This link expires in 1 hour.</p>
    <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
    <p>If you didn't request this, you can ignore this email.</p>
    <p>— The " . APP_NAME . " Team</p>
    ";
    return sendMail($to, $name, $subject, $html);
}

function sendWelcomeEmail(string $to, string $name): bool {
    $subject = 'Welcome to ' . APP_NAME;
    $html    = "
    <p>Hi {$name},</p>
    <p>Your account has been created. You can now log in and begin the cooperative conversion process for your business.</p>
    <p><a href='" . APP_URL . url('/login.php') . "'>Log in to your account</a></p>
    <p>— The " . APP_NAME . " Team</p>
    ";
    return sendMail($to, $name, $subject, $html);
}

function sendCoordinatorApprovalEmail(string $to, string $name): bool {
    $subject = 'Your coordinator account has been approved';
    $html    = "
    <p>Hi {$name},</p>
    <p>Your volunteer coordinator account has been approved. You can now log in and start helping businesses with their cooperative conversions.</p>
    <p><a href='" . APP_URL . url('/coordinator.php') . "'>Go to your coordinator dashboard</a></p>
    <p>Thank you for volunteering your time and expertise.</p>
    <p>— The " . APP_NAME . " Team</p>
    ";
    return sendMail($to, $name, $subject, $html);
}
