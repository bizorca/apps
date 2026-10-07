<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// Core sender — calls SMTP2Go REST API
// ---------------------------------------------------------------------------
function sendEmail(string $toAddr, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    $payload = json_encode([
        'api_key'   => SMTP2GO_API_KEY,
        'to'        => [empty($toName) ? $toAddr : "{$toName} <{$toAddr}>"],
        'sender'    => EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDR . '>',
        'subject'   => $subject,
        'html_body' => $htmlBody,
        'text_body' => $textBody !== '' ? $textBody : wordwrap(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody)), 80),
    ]);

    $ch = curl_init(SMTP2GO_API_URL . 'email/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) return false;
    $data = json_decode($response, true);
    return (int)($data['data']['succeeded'] ?? 0) > 0;
}

// ---------------------------------------------------------------------------
// Shared email chrome — wraps content in a consistent layout
// ---------------------------------------------------------------------------
function emailWrap(string $body, string $preheader = ''): string {
    $appUrl = APP_URL;
    $pre    = $preheader !== '' ? '<div style="display:none;max-height:0;overflow:hidden;color:#fff;">' . htmlspecialchars($preheader) . '</div>' : '';
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ProForma</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;color:#1f2937;">
{$pre}
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <!-- Header -->
      <tr><td style="padding-bottom:24px;text-align:center;">
        <a href="{$appUrl}" style="text-decoration:none;font-size:20px;font-weight:700;color:#4f46e5;letter-spacing:-0.5px;">ProForma</a>
      </td></tr>

      <!-- Card -->
      <tr><td style="background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;padding:32px;">
        {$body}
      </td></tr>

      <!-- Footer -->
      <tr><td style="padding-top:20px;text-align:center;font-size:12px;color:#9ca3af;">
        ProForma &mdash; pro forma modeling for small businesses<br>
        <a href="{$appUrl}" style="color:#9ca3af;">proforma.bizorca.com</a>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ---------------------------------------------------------------------------
// Welcome email
// ---------------------------------------------------------------------------
function sendWelcomeEmail(string $email): void {
    $appUrl      = APP_URL;
    $dashUrl     = $appUrl . '/dashboard.php';

    $html = emailWrap(<<<HTML
<h2 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#111827;">Welcome to ProForma</h2>
<p style="margin:0 0 20px;color:#6b7280;line-height:1.6;">
    Your account is set up. ProForma helps small wellness and health businesses model their finances
    so you can answer the questions that matter: Am I charging enough? Can I afford my staff? What
    does it take to pay myself?
</p>
<p style="margin:0 0 8px;font-weight:600;color:#374151;">To get started:</p>
<ol style="margin:0 0 24px;padding-left:20px;color:#4b5563;line-height:2;">
    <li>Create your first business from the dashboard</li>
    <li>Work through the 5–6 step wizard (takes about 10 minutes)</li>
    <li>Review your pro forma report and use the What-If sliders to explore scenarios</li>
    <li>Log actuals each month to track how your real numbers compare</li>
</ol>
<div style="text-align:center;margin:28px 0;">
    <a href="{$dashUrl}" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;font-size:15px;">
        Go to your dashboard &rarr;
    </a>
</div>
<p style="margin:0;color:#9ca3af;font-size:12px;line-height:1.6;">
    Questions? Reply to this email — it goes directly to a real person.
</p>
HTML, 'Your ProForma account is ready.');

    $text = "Welcome to ProForma!\n\n"
          . "Your account is ready. Visit your dashboard to create your first business:\n"
          . $dashUrl . "\n\n"
          . "Questions? Reply to this email.\n";

    sendEmail($email, '', 'Welcome to ProForma', $html, $text);
}

// ---------------------------------------------------------------------------
// Monthly digest
// ---------------------------------------------------------------------------

/**
 * Send the monthly digest to a single user.
 * $summaries = array of ['business' => [...], 'report' => [...], 'lastActual' => [...] | null]
 */
function sendMonthlyDigest(string $email, array $summaries, string $monthLabel): void {
    if (empty($summaries)) return;

    $appUrl    = APP_URL;
    $reportUrl = $appUrl . '/wizard/report.php';
    $actualUrl = $appUrl . '/actuals.php';

    // Build per-business rows
    $rows = '';
    foreach ($summaries as $s) {
        $biz     = $s['business'];
        $r       = $s['report'];
        $actual  = $s['lastActual'];

        $netColor = $r['net_income'] >= 0 ? '#16a34a' : '#dc2626';
        $netLabel = money_fmt($r['net_income'], true);
        $typeLabel = $biz['business_name'];

        $actualRow = '';
        if ($actual) {
            $revVar   = (float)$actual['gross_revenue'] - $r['gross_revenue'];
            $varColor = $revVar >= 0 ? '#16a34a' : '#dc2626';
            $varLabel = money_fmt($revVar, true);
            $mName    = date('M Y', mktime(0, 0, 0, $actual['month'], 1, $actual['year']));
            $actualRow = "<tr><td style='padding:4px 0;color:#6b7280;font-size:13px;'>Last logged ({$mName}):</td>"
                       . "<td style='padding:4px 0;text-align:right;font-weight:600;color:{$varColor};font-size:13px;'>{$varLabel} vs projected</td></tr>";
        } else {
            $actualRow = "<tr><td colspan='2' style='padding:4px 0;font-size:12px;color:#9ca3af;'>"
                       . "No actuals logged &mdash; <a href='{$actualUrl}' style='color:#4f46e5;'>log {$monthLabel}</a></td></tr>";
        }

        $rows .= <<<HTML
<div style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:16px;">
    <div style="font-weight:700;font-size:15px;color:#111827;margin-bottom:8px;">{$typeLabel}</div>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="color:#6b7280;font-size:13px;">Projected net income:</td>
            <td style="text-align:right;font-weight:700;font-size:16px;color:{$netColor};">{$netLabel}/mo</td>
        </tr>
        {$actualRow}
    </table>
</div>
HTML;
    }

    $html = emailWrap(<<<HTML
<h2 style="margin:0 0 4px;font-size:20px;font-weight:700;color:#111827;">Your {$monthLabel} Summary</h2>
<p style="margin:0 0 24px;color:#6b7280;font-size:13px;">Monthly snapshot from ProForma</p>
{$rows}
<div style="text-align:center;margin:24px 0 8px;">
    <a href="{$reportUrl}" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:10px 24px;border-radius:8px;font-weight:600;font-size:14px;">
        View full report &rarr;
    </a>
</div>
HTML, "Your ProForma summary for {$monthLabel}");

    // Plain text fallback
    $text = "ProForma — {$monthLabel} Summary\n\n";
    foreach ($summaries as $s) {
        $r    = $s['report'];
        $text .= $s['business']['business_name'] . "\n";
        $text .= "Projected net: " . money_fmt($r['net_income'], true) . "/mo\n\n";
    }
    $text .= "View report: {$reportUrl}\n";

    sendEmail($email, '', "Your ProForma Summary — {$monthLabel}", $html, $text);
}

// ---------------------------------------------------------------------------
// Localrev: new opportunities notification
// ---------------------------------------------------------------------------

/**
 * Notify a user that new market opportunities have been identified for their business.
 * $recommendations = array of recommendation rows from the DB.
 */
function sendLocalrevNotification(string $email, string $bizName, array $recommendations): void
{
    if (empty($recommendations)) return;

    $appUrl     = APP_URL;
    $localrevUrl = $appUrl . '/localrev.php';
    $count       = count($recommendations);
    $noun        = $count === 1 ? 'opportunity' : 'opportunities';

    $rows = '';
    foreach (array_slice($recommendations, 0, 5) as $rec) {
        $cat        = ucfirst((string)($rec['category'] ?? 'b2b'));
        $estimate   = htmlspecialchars((string)($rec['estimated_monthly_revenue'] ?? ''), ENT_QUOTES);
        $title      = htmlspecialchars((string)($rec['title'] ?? ''), ENT_QUOTES);
        $estLabel   = $estimate !== '' ? " &mdash; est. {$estimate}/mo" : '';
        $rows .= "<li style='margin-bottom:10px;'><strong>{$title}</strong>{$estLabel}<br>"
               . "<span style='font-size:12px;color:#6b7280;'>{$cat}</span></li>";
    }
    if (count($recommendations) > 5) {
        $extra = count($recommendations) - 5;
        $rows .= "<li style='color:#6b7280;font-size:13px;'>...and {$extra} more</li>";
    }

    $html = emailWrap(<<<HTML
<h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#111827;">
    {$count} local revenue {$noun} identified for {$bizName}
</h2>
<p style="margin:0 0 20px;color:#6b7280;line-height:1.6;">
    ProForma analyzed your market profile and nearby organizations and found {$count} revenue {$noun} matched to your business type and geography.
</p>
<ul style="margin:0 0 24px;padding-left:20px;color:#374151;line-height:1.8;">
    {$rows}
</ul>
<div style="text-align:center;margin:28px 0;">
    <a href="{$localrevUrl}" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;font-size:15px;">
        View full playbooks &rarr;
    </a>
</div>
<p style="margin:0;color:#9ca3af;font-size:12px;line-height:1.6;">
    Each opportunity includes a step-by-step playbook: who to contact, what to offer, sample pricing, and an outreach template you can send today.
</p>
HTML, "ProForma found {$count} revenue {$noun} for {$bizName}");

    $text = "Localrev — {$count} Revenue {$noun} Found\n\n"
          . "Business: {$bizName}\n\n";
    foreach (array_slice($recommendations, 0, 5) as $rec) {
        $text .= '- ' . ($rec['title'] ?? '') . ' (' . ($rec['estimated_monthly_revenue'] ?? '') . "/mo)\n";
    }
    $text .= "\nView full playbooks: {$localrevUrl}\n";

    sendEmail($email, '', "ProForma Market Intel: {$count} revenue {$noun} found for {$bizName}", $html, $text);
}

// Simple money formatter for use inside email.php (no dependency on helpers.php)
function money_fmt(float $amount, bool $signed = false): string {
    $formatted = '$' . number_format(abs($amount), 2);
    if ($signed && $amount < 0) return '-' . $formatted;
    if ($signed && $amount > 0) return '+' . $formatted;
    return ($amount < 0 ? '-' : '') . $formatted;
}
