<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Mailer;

/**
 * Draining the queue (M11).
 *
 * Runs on the five-minute tick. For each undecided notification it looks up the
 * reader's preference, and does one of four things: send it now, hold it for a
 * digest, leave it in the inbox and send nothing, or suppress it.
 *
 * The decision is made HERE and not at queue time, on purpose. Preferences
 * change, and the reader's preference at the moment of sending is the one that
 * should govern. Someone who switches digests off at four o'clock has said
 * something about the mail they are about to receive, not only about mail that
 * has not been thought of yet.
 *
 * Everything about this class is shaped by one constraint: SiteGround kills
 * long-running processes. There is no worker and no queue daemon, so a tick has
 * to be short, has to be safely re-runnable, and has to make progress even if
 * the previous one died halfway through.
 */
final class NotificationDispatch
{
    /** Ceiling on one tick. Beyond this the next tick picks up the rest. */
    public const BATCH = 200;

    /**
     * Decide and act on pending notifications for one tenant.
     *
     * @return array{sent:int, held:int, in_app:int, suppressed:int, failed:int}
     */
    public static function run(int $tenantId, int $limit = self::BATCH): array
    {
        $limit = max(1, min($limit, 1000));
        $db = Database::conn();

        $stmt = $db->prepare(
            "SELECT n.*, u.email, u.name AS user_name, u.client_org_id, u.status AS user_status,
                    t.name AS firm_name, t.slug AS firm_slug, t.mail_from_name,
                    o.name AS org_name
             FROM pl_notifications n
             JOIN pl_users u ON u.id = n.user_id AND u.tenant_id = n.tenant_id
             JOIN pl_tenants t ON t.id = n.tenant_id
             LEFT JOIN pl_client_orgs o ON o.id = n.client_org_id AND o.tenant_id = n.tenant_id
             WHERE n.tenant_id = :tid AND n.delivery = 'pending'
             ORDER BY n.created_at ASC, n.id ASC
             LIMIT " . $limit
        );
        $stmt->execute(['tid' => $tenantId]);

        $totals = ['sent' => 0, 'held' => 0, 'in_app' => 0, 'suppressed' => 0, 'failed' => 0];

        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['id'];

            // A disabled account is not a preference, but the effect is the
            // same and mailing someone whose access has been revoked is worse
            // than silence.
            if ((string) $row['user_status'] !== 'active') {
                self::decide($tenantId, $id, 'suppressed');
                $totals['suppressed']++;
                continue;
            }

            $channel = Notifications::channelFor($tenantId, (int) $row['user_id'], (string) $row['event_type']);

            if ($channel === Notifications::OFF) {
                self::decide($tenantId, $id, 'suppressed');
                $totals['suppressed']++;
                continue;
            }

            if ($channel === Notifications::IN_APP) {
                self::decide($tenantId, $id, 'in_app');
                $totals['in_app']++;
                continue;
            }

            if ($channel === Notifications::DIGEST) {
                self::decide($tenantId, $id, 'digest');
                $totals['held']++;
                continue;
            }

            // Claim it BEFORE sending. If the process dies between the send and
            // the bookkeeping, the row is already marked and the next tick will
            // not send it again. The failure mode this trades into is a
            // notification that is silently lost, which is much better than one
            // that arrives every five minutes forever.
            self::decide($tenantId, $id, 'immediate');

            if (self::sendOne($row)) {
                $totals['sent']++;
            } else {
                $totals['failed']++;
            }
        }

        return $totals;
    }

    /** @param array<string,mixed> $row */
    private static function sendOne(array $row): bool
    {
        $email = trim((string) $row['email']);

        if ($email === '') {
            return false;
        }

        $tenant = ['name' => $row['firm_name'], 'mail_from_name' => $row['mail_from_name']];
        $sender = Mailer::senderName(
            $row['client_org_id'] === null ? null : (int) $row['client_org_id'],
            $tenant
        );

        $rendered = NotificationTemplates::render(
            (int) $row['tenant_id'],
            (string) $row['event_type'],
            (string) $row['title'],
            $row['body'] === null ? null : (string) $row['body'],
            [
                'recipient_name' => (string) $row['user_name'],
                'firm_name'      => (string) $row['firm_name'],
                'org_name'       => (string) ($row['org_name'] ?? ''),
            ]
        );

        $link = (string) ($row['link'] ?? '/');
        $url = tenant_url($link, (string) $row['firm_slug']);

        $footnotes = [];

        if (!Notifications::CATALOGUE[(string) $row['event_type']]['transactional']) {
            $footnotes[] = 'You can change which of these you receive, or stop them, at '
                . tenant_url('/settings/notifications', (string) $row['firm_slug']) . '.';
        }

        $body = MailTemplate::action(
            'Hello ' . $row['user_name'] . ',',
            array_values(array_filter([$rendered['body'], self::contextLine($row)])),
            'Open it',
            $url,
            $footnotes,
            $sender
        );

        $sent = Mailer::send(
            $email,
            (string) $row['user_name'],
            $rendered['subject'],
            $body['text'],
            $body['html'],
            $sender
        );

        if ($sent) {
            Database::conn()->prepare(
                'UPDATE pl_notifications SET emailed_at = NOW() WHERE tenant_id = :tid AND id = :id'
            )->execute(['tid' => (int) $row['tenant_id'], 'id' => (int) $row['id']]);
        }

        return $sent;
    }

    /**
     * The "why am I getting this" sentence.
     *
     * MailTemplate exists because the first magic-link mail landed in spam for
     * being mostly URL. Every message wants real prose for a filter to weigh,
     * and a reader wants to know which relationship an email belongs to.
     *
     * @param array<string,mixed> $row
     */
    private static function contextLine(array $row): ?string
    {
        $firm = trim((string) $row['firm_name']);
        $org = trim((string) ($row['org_name'] ?? ''));

        if ($org !== '' && $row['client_org_id'] === null) {
            // Firm-side reader, client context: name the client.
            return 'This is from your work with ' . $org . '.';
        }

        if ($org !== '') {
            return 'This is part of your work with ' . $firm . ' on ' . $org . '.';
        }

        return $firm === '' ? null : 'This is part of your work with ' . $firm . '.';
    }

    private static function decide(int $tenantId, int $id, string $delivery): void
    {
        Database::conn()->prepare(
            'UPDATE pl_notifications SET delivery = :d
             WHERE tenant_id = :tid AND id = :id AND delivery = \'pending\''
        )->execute(['d' => $delivery, 'tid' => $tenantId, 'id' => $id]);
    }
}
