<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Tenant-editable notification copy (FR-11.4).
 *
 * An override, not a replacement. A tenant row supplies a subject and a body
 * for one event; anything it leaves blank falls through to what the module
 * queued. So a firm that rewrites one template does not silently become
 * responsible for the other twenty, and an event added next quarter ships
 * working copy to everyone the day it lands.
 *
 * Variables are {{double_braced}} and validated when the template is SAVED,
 * against the per-event allowlist in the catalogue. Validating at save is the
 * whole point: a coach who types {{naem}} should be told so in the editor, not
 * discover it when a client receives an email addressed to {{naem}}.
 *
 * Unknown variables that somehow reach render() are stripped rather than left
 * in place. A blank is a small embarrassment; visible template syntax in a
 * client-facing email is a large one.
 */
final class NotificationTemplates
{
    /**
     * Render subject and body for one notification.
     *
     * @param string $fallbackTitle What the module queued — used when no
     *        tenant override exists, which is the common case.
     * @param array<string,string> $vars
     * @return array{subject:string, body:string}
     */
    public static function render(
        int $tenantId,
        string $eventType,
        string $fallbackTitle,
        ?string $fallbackBody,
        array $vars
    ): array {
        $override = self::find($tenantId, $eventType);

        $subject = $fallbackTitle;
        $body = $fallbackBody ?? $fallbackTitle;

        if ($override !== null) {
            $s = trim((string) ($override['subject'] ?? ''));
            $b = trim((string) ($override['body'] ?? ''));

            if ($s !== '') {
                $subject = $s;
            }
            if ($b !== '') {
                $body = $b;
            }
        }

        return [
            'subject' => mb_substr(self::substitute($subject, $vars), 0, 255),
            'body'    => self::substitute($body, $vars),
        ];
    }

    /**
     * Replace {{vars}}, then strip anything left over.
     *
     * @param array<string,string> $vars
     */
    public static function substitute(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string) $value, $text);
        }

        // Anything still braced was either not supplied for this event or was
        // mistyped past the save-time check. Either way it must not ship.
        $cleaned = preg_replace('/\{\{\s*[a-z0-9_]+\s*\}\}/i', '', $text);

        return trim(preg_replace('/[ \t]{2,}/', ' ', $cleaned ?? $text) ?? $text);
    }

    /**
     * Save a tenant's override.
     *
     * @throws \InvalidArgumentException on an unknown event or an unknown variable.
     */
    public static function save(
        int $tenantId,
        string $eventType,
        ?string $subject,
        ?string $body,
        ?int $editorId = null
    ): void {
        if (!Notifications::isKnown($eventType)) {
            throw new \InvalidArgumentException('Unknown notification event.');
        }

        $subject = trim((string) $subject);
        $body = trim((string) $body);

        $unknown = self::unknownVariables($eventType, $subject . ' ' . $body);

        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                (count($unknown) === 1 ? 'This variable is not available here: ' : 'These variables are not available here: ')
                . implode(', ', array_map(static fn (string $v): string => '{{' . $v . '}}', $unknown))
                . '. Available: ' . implode(', ', array_map(
                    static fn (string $v): string => '{{' . $v . '}}',
                    Notifications::CATALOGUE[$eventType]['vars']
                )) . '.'
            );
        }

        // Both blank means "go back to the built-in copy", which should remove
        // the row rather than store two empty strings that render() has to
        // reason about every time.
        if ($subject === '' && $body === '') {
            self::reset($tenantId, $eventType);
            return;
        }

        Database::conn()->prepare(
            'INSERT INTO pl_notification_templates (tenant_id, event_type, subject, body, updated_by)
             VALUES (:tid, :type, :subj, :body, :by)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body), updated_by = VALUES(updated_by)'
        )->execute([
            'tid'  => $tenantId,
            'type' => $eventType,
            'subj' => $subject === '' ? null : mb_substr($subject, 0, 255),
            'body' => $body === '' ? null : $body,
            'by'   => $editorId,
        ]);
    }

    /**
     * Variables used in a template that the event does not offer.
     *
     * @return array<int,string>
     */
    public static function unknownVariables(string $eventType, string $text): array
    {
        if (!Notifications::isKnown($eventType)) {
            throw new \InvalidArgumentException('Unknown notification event.');
        }

        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $m);

        $allowed = Notifications::CATALOGUE[$eventType]['vars'];
        $used = array_unique(array_map('strtolower', $m[1] ?? []));

        return array_values(array_diff($used, $allowed));
    }

    public static function reset(int $tenantId, string $eventType): void
    {
        Database::conn()->prepare(
            'DELETE FROM pl_notification_templates WHERE tenant_id = :tid AND event_type = :type'
        )->execute(['tid' => $tenantId, 'type' => $eventType]);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $tenantId, string $eventType): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_notification_templates WHERE tenant_id = :tid AND event_type = :type'
        );
        $stmt->execute(['tid' => $tenantId, 'type' => $eventType]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Every event with its override, if any. The editor's index.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function all(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_notification_templates WHERE tenant_id = :tid'
        );
        $stmt->execute(['tid' => $tenantId]);

        $byType = [];

        foreach ($stmt->fetchAll() as $row) {
            $byType[(string) $row['event_type']] = $row;
        }

        $out = [];

        foreach (Notifications::CATALOGUE as $type => $meta) {
            $out[] = [
                'event_type'    => $type,
                'label'         => $meta['label'],
                'audience'      => $meta['audience'],
                'transactional' => $meta['transactional'],
                'vars'          => $meta['vars'],
                'subject'       => $byType[$type]['subject'] ?? null,
                'body'          => $byType[$type]['body'] ?? null,
                'customised'    => isset($byType[$type]),
                'updated_at'    => $byType[$type]['updated_at'] ?? null,
            ];
        }

        return $out;
    }
}
