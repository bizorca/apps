<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Config;

/**
 * Transactional email bodies.
 *
 * This exists because of a real deliverability failure: the first magic-link
 * email Pilotage sent went to Gmail's spam folder. Authentication was not the
 * problem — SPF, DKIM and the return-path all aligned. The problems were a
 * one-day-old domain with no reputation, and a message that was fifteen lines
 * of text dominated by a 130-character bare URL, sent as plain text only.
 * That is very close to the shape of a phishing message, and filters read
 * shape before they read intent.
 *
 * So every message goes out as multipart: a readable plain-text part and a
 * plain HTML part with the link behind a button. Three rules the templates
 * follow, all aimed at not looking like phishing:
 *
 *   1. The raw URL never dominates. It appears once, as a fallback, below the
 *      button and in smaller type.
 *   2. There is real sentence content explaining what the message is and why
 *      it arrived — a filter weighing text against links needs text to weigh.
 *   3. The firm's name appears in the body, not only in the From line, so the
 *      message is self-evidently from a relationship the reader has.
 *
 * Deliberately no images, no tracking pixels, no external CSS. Every one is a
 * deliverability cost for transactional mail and buys nothing here.
 */
final class MailTemplate
{
    /**
     * @param array<int,string> $paragraphs Sentences before the button.
     * @param array<int,string> $footnotes  Smaller print after it.
     * @return array{text: string, html: string}
     */
    public static function action(
        string $greeting,
        array $paragraphs,
        string $buttonLabel,
        string $buttonUrl,
        array $footnotes = [],
        ?string $firmName = null
    ): array {
        $firmName = $firmName ?? 'Pilotage';

        // ---- plain text -------------------------------------------------
        $text = $greeting . "\n\n";
        $text .= implode("\n\n", $paragraphs) . "\n\n";
        $text .= $buttonLabel . ":\n" . $buttonUrl . "\n";

        if ($footnotes !== []) {
            $text .= "\n" . implode("\n", $footnotes) . "\n";
        }

        $text .= "\n-- \n" . $firmName . "\n";

        // ---- html -------------------------------------------------------
        $body = '';

        foreach ($paragraphs as $p) {
            $body .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#0f172a">'
                . nl2br(h($p)) . '</p>';
        }

        $button = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0">'
            . '<tr><td style="background:#0f172a;border-radius:6px">'
            . '<a href="' . h($buttonUrl) . '" '
            . 'style="display:inline-block;padding:12px 22px;font-size:15px;font-weight:600;'
            . 'color:#ffffff;text-decoration:none">' . h($buttonLabel) . '</a>'
            . '</td></tr></table>';

        $fallback = '<p style="margin:0 0 16px;font-size:12px;line-height:1.5;color:#64748b">'
            . 'If the button does not work, copy this into your browser:<br>'
            . '<span style="word-break:break-all;color:#475569">' . h($buttonUrl) . '</span></p>';

        $notes = '';

        foreach ($footnotes as $note) {
            $notes .= '<p style="margin:0 0 10px;font-size:12px;line-height:1.5;color:#64748b">'
                . h($note) . '</p>';
        }

        $html = '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:24px;background:#f8fafc;'
            . 'font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:520px;margin:0 auto">'
            . '<tr><td style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:28px">'
            . '<p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#0f172a">' . h($greeting) . '</p>'
            . $body
            . $button
            . $fallback
            . $notes
            . '</td></tr>'
            . '<tr><td style="padding:16px 4px;font-size:12px;color:#94a3b8">'
            . h($firmName) . ' &middot; sent from ' . h((string) Config::get('domains.base'))
            . '</td></tr></table></body></html>';

        return ['text' => $text, 'html' => $html];
    }
}
