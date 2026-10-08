<?php

declare(strict_types=1);

namespace TimeBank\Core;

/**
 * SMTP2GO over its REST API, with the shared account's key (TM_MAIL_KEY).
 * TimeBank keeps its own sender instead of tl_mail() because it sends HTML.
 *
 * With no key set, nothing is sent and the attempt is logged.
 */
class Mailer
{
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        if (TM_MAIL_KEY === '') {
            error_log('TimeBank Mailer: no SMTP2GO key, not sending "' . $subject . '" to ' . $toEmail);
            return false;
        }

        $toName = str_replace(['<', '>', '"', "\r", "\n"], '', $toName);
        $payload = [
            'api_key'   => TM_MAIL_KEY,
            'to'        => [$toName !== '' ? "{$toName} <{$toEmail}>" : $toEmail],
            'sender'    => TM_MAIL_FROM_NAME . ' <' . TM_MAIL_FROM_ADDRESS . '>',
            'subject'   => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody !== '' ? $textBody : trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES, 'UTF-8')),
        ];

        $ch = curl_init('https://api.smtp2go.com/v3/email/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('TimeBank Mailer: curl error to ' . $toEmail . ': ' . $curlError);
            return false;
        }

        $result = json_decode((string) $response, true);
        if ($httpCode !== 200 || (int) ($result['data']['succeeded'] ?? 0) < 1) {
            error_log('TimeBank Mailer: failed to ' . $toEmail . ': HTTP ' . $httpCode);
            return false;
        }

        return true;
    }

    /**
     * Send a community's email template with {{variable}} placeholders filled.
     *
     * Templates are plain text written by community admins. The original sent
     * them as the HTML body, so every line break collapsed into one paragraph;
     * here the text goes as the text body and an escaped, line-broken copy as
     * the HTML body.
     */
    public static function sendTemplate(int $tenantId, string $templateSlug, string $toEmail, string $toName, array $variables = []): bool
    {
        $template = DB::fetch(
            'SELECT * FROM `tm_email_templates` WHERE tenant_id = ? AND slug = ?',
            [$tenantId, $templateSlug]
        );

        if (!$template) {
            error_log('TimeBank Mailer: template not found: ' . $templateSlug . ' for tenant ' . $tenantId);
            return false;
        }

        $subject = static::fill($template['subject'], $variables);
        $text    = static::fill($template['body'], $variables);
        $html    = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));

        return static::send($toEmail, $toName, $subject, $html, $text);
    }

    private static function fill(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }
        return $content;
    }

    /**
     * Weekly digest to every opted-in member of a community.
     *
     * @return array{sent: int, failed: int, recipients: int}
     */
    public static function buildWeeklyDigest(int $tenantId, bool $dryRun = false): array
    {
        $tenant = DB::fetch('SELECT * FROM `tm_tenants` WHERE id = ?', [$tenantId]);
        if (!$tenant) {
            return ['sent' => 0, 'failed' => 0, 'recipients' => 0];
        }

        // A member with no saved preferences gets the digest: that is the
        // default getEmailPreferences() shows them on their profile. The
        // original's JSON_EXTRACT(...) = true skipped them.
        $members = DB::fetchAll(
            "SELECT * FROM `tm_members`
             WHERE tenant_id = ? AND is_active = 1 AND is_approved = 1
               AND (email_preferences IS NULL
                    OR JSON_EXTRACT(email_preferences, '$.weekly_digest') IS NULL
                    OR JSON_EXTRACT(email_preferences, '$.weekly_digest') = true)",
            [$tenantId]
        );

        if (!$members) {
            return ['sent' => 0, 'failed' => 0, 'recipients' => 0];
        }

        $recent = function (string $type) use ($tenantId): array {
            return DB::fetchAll(
                "SELECT o.*, c.name AS category_name,
                        COALESCE(m.display_name, CONCAT(m.first_name,' ',m.last_name)) AS member_name
                 FROM `tm_offers` o
                 JOIN `tm_members` m ON m.id = o.member_id
                 LEFT JOIN `tm_categories` c ON c.id = o.category_id
                 WHERE o.tenant_id = ? AND o.type = ? AND o.is_active = 1
                   AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY o.created_at DESC, o.id DESC LIMIT 20",
                [$tenantId, $type]
            );
        };

        $link = fn(string $path) => TM_ORIGIN . community_url($tenant['subdomain'], $path);
        $h    = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $rows = function (array $items) use ($link, $h): string {
            $out = '';
            foreach ($items as $o) {
                $out .= '<li><a href="' . $h($link('/offers/' . (int) $o['id'])) . '">' . $h($o['title'])
                    . '</a> — ' . $h($o['member_name']) . '</li>';
            }
            return $out;
        };

        $offerRows   = $rows($recent('offer'));
        $requestRows = $rows($recent('request'));

        $html = '<h2>Weekly Digest — ' . $h($tenant['name']) . '</h2>'
            . '<h3>New Offers This Week</h3>'
            . ($offerRows ? '<ul>' . $offerRows . '</ul>' : '<p>No new offers this week.</p>')
            . '<h3>New Requests This Week</h3>'
            . ($requestRows ? '<ul>' . $requestRows . '</ul>' : '<p>No new requests this week.</p>')
            . '<p><a href="' . $h($link('/offers')) . '">View all offers</a> | '
            . '<a href="' . $h($link('/profile')) . '">Update preferences</a></p>';

        $sent = $failed = 0;
        foreach ($members as $member) {
            if ($dryRun) {
                $sent++;
                continue;
            }
            $name = $member['display_name'] ?: $member['first_name'] . ' ' . $member['last_name'];
            static::send($member['email'], $name, 'Weekly Digest: ' . $tenant['name'], $html) ? $sent++ : $failed++;
            if (($sent + $failed) % 20 === 0) {
                usleep(500000);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'recipients' => count($members)];
    }
}
