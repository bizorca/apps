<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Auth\Token;
use Bizorca\Pilotage\Core\Database;

/**
 * One-click unsubscribe (FR-11.5).
 *
 * Selector/verifier like every other token here: the selector is indexed so
 * lookup is a single keyed read rather than a scan that leaks timing, and only
 * a hash of the verifier is stored so a database leak does not hand anyone a
 * pile of working unsubscribe links.
 *
 * Two things are deliberately different from the other tokens in this codebase.
 *
 * It does not expire. Everywhere else that would be a bug; here it is the
 * requirement. An unsubscribe link in a nine-month-old email has to work, and
 * an expired one produces a spam complaint instead of a preference change.
 *
 * It is idempotent and reusable. Clicking it twice is not an error, and it is
 * not consumed on use — people forward emails, click from two devices, and come
 * back a year later to check. `used_at` records the most recent use for
 * support's benefit and grants nothing.
 *
 * What it CANNOT do is switch off transactional mail. While someone is an
 * active participant in an engagement, "your coach has delivered a document
 * that needs your acknowledgment" is part of the service they are receiving,
 * not marketing about it. That boundary lives in the catalogue's
 * `transactional` flag, in code, rather than in a column somebody could edit
 * through a support request.
 */
final class Unsubscribe
{
    /**
     * The durable token for a reader, minting one if they have none.
     *
     * The plaintext only exists at creation. On later calls there is nothing to
     * return but the existing selector, so links are minted once and the row
     * carries them — which is why `linkFor` regenerates rather than reading.
     *
     * @return string The plaintext token, to be put in a URL.
     */
    public static function tokenFor(int $tenantId, int $userId): string
    {
        $db = Database::conn();

        $token = Token::create();

        // A fresh verifier each time the link is minted. Older links stop
        // working, which is the correct trade: an unsubscribe link is a
        // bearer credential for one small action, and the newest email a
        // person holds is the one they will click.
        $db->prepare(
            'INSERT INTO pl_unsubscribe_tokens (tenant_id, user_id, selector, verifier_hash)
             VALUES (:tid, :uid, :sel, :hash)
             ON DUPLICATE KEY UPDATE selector = VALUES(selector),
                                     verifier_hash = VALUES(verifier_hash),
                                     revoked_at = NULL'
        )->execute([
            'tid'  => $tenantId,
            'uid'  => $userId,
            'sel'  => $token['selector'],
            'hash' => $token['verifier_hash'],
        ]);

        return $token['plaintext'];
    }

    /** The path to put in an email. Absolute URLs are built by the caller. */
    public static function pathFor(int $tenantId, int $userId): string
    {
        return '/unsubscribe/' . self::tokenFor($tenantId, $userId);
    }

    /**
     * Resolve a presented token to the reader it belongs to.
     *
     * @return array{tenant_id:int, user_id:int}|null
     */
    public static function resolve(string $plaintext): ?array
    {
        $parts = Token::split($plaintext);

        if ($parts === null) {
            return null;
        }

        [$selector, $verifier] = $parts;

        $stmt = Database::conn()->prepare(
            'SELECT tenant_id, user_id, verifier_hash FROM pl_unsubscribe_tokens
             WHERE selector = :sel AND revoked_at IS NULL'
        );
        $stmt->execute(['sel' => $selector]);

        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        if (!Token::verify($verifier, (string) $row['verifier_hash'])) {
            return null;
        }

        return ['tenant_id' => (int) $row['tenant_id'], 'user_id' => (int) $row['user_id']];
    }

    /**
     * Exercise the link.
     *
     * @return array{switched_off:int, still_receiving:array<int,string>}|null
     *         Null if the token does not resolve. `still_receiving` is the
     *         honest part: the reader is told exactly what will keep arriving,
     *         rather than being allowed to believe everything has stopped.
     */
    public static function apply(string $plaintext): ?array
    {
        $who = self::resolve($plaintext);

        if ($who === null) {
            return null;
        }

        $count = Notifications::unsubscribeAll($who['tenant_id'], $who['user_id']);

        Database::conn()->prepare(
            'UPDATE pl_unsubscribe_tokens SET used_at = NOW()
             WHERE tenant_id = :tid AND user_id = :uid'
        )->execute(['tid' => $who['tenant_id'], 'uid' => $who['user_id']]);

        $remaining = [];

        foreach (Notifications::CATALOGUE as $meta) {
            if ($meta['transactional']) {
                $remaining[] = $meta['label'];
            }
        }

        return ['switched_off' => $count, 'still_receiving' => $remaining];
    }

    public static function revoke(int $tenantId, int $userId): void
    {
        Database::conn()->prepare(
            'UPDATE pl_unsubscribe_tokens SET revoked_at = NOW()
             WHERE tenant_id = :tid AND user_id = :uid AND revoked_at IS NULL'
        )->execute(['tid' => $tenantId, 'uid' => $userId]);
    }
}
