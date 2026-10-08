<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

/**
 * Calendar attachments (FR-5.7).
 *
 * ICS plus a booking link covers the MVP; two-way Google and Microsoft sync is
 * Phase 3. An .ics attachment works in every calendar ever written and needs
 * no OAuth, no token refresh, and no consent screen.
 */
final class Ics
{
    /** @param array<string,mixed> $session */
    public static function forSession(array $session, string $organizerEmail, string $organizerName): string
    {
        $start = $session['scheduled_at'] === null ? time() : strtotime((string) $session['scheduled_at']);
        $end = $start + (((int) ($session['duration_minutes'] ?? 60)) * 60);

        // Times are stored UTC (see the clock invariant), so Z suffixes are correct.
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Pilotage//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:' . self::uid($session),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . gmdate('Ymd\THis\Z', $start),
            'DTEND:' . gmdate('Ymd\THis\Z', $end),
            'SUMMARY:' . self::escape((string) $session['title']),
            'ORGANIZER;CN=' . self::escape($organizerName) . ':mailto:' . $organizerEmail,
            'STATUS:CONFIRMED',
            'SEQUENCE:0',
        ];

        if (!empty($session['location'])) {
            $lines[] = 'LOCATION:' . self::escape((string) $session['location']);
        }

        // 24-hour and 1-hour reminders, per FR-5.7.
        foreach ([1440, 60] as $minutes) {
            $lines[] = 'BEGIN:VALARM';
            $lines[] = 'TRIGGER:-PT' . $minutes . 'M';
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:' . self::escape((string) $session['title']);
            $lines[] = 'END:VALARM';
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /** @param array<string,mixed> $session */
    private static function uid(array $session): string
    {
        return 'session-' . (int) $session['id'] . '-' . substr(hash('sha256', (string) ($session['created_at'] ?? '')), 0, 12) . '@' . base_domain();
    }

    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\\,', '\\n', '\\n', '\\n'],
            $value
        );
    }

    /** RFC 5545 caps lines at 75 octets; longer ones continue with a leading space. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = substr($line, 0, 75);
        $rest = substr($line, 75);

        foreach (str_split($rest, 74) as $chunk) {
            $out .= "\r\n " . $chunk;
        }

        return $out;
    }
}
