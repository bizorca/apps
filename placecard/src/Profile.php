<?php

declare(strict_types=1);

/**
 * pc_profiles: what the original kept on its own users table, keyed on the
 * shared users.id. public_id is the id the API exposes ("id", "user_id",
 * "host_user_id"): the original user's UUID for imported people, a new UUID
 * for everyone else, so the iOS app keeps seeing string ids.
 */
final class Profile
{
    public static function uuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }

    public static function exists(int $userId): bool
    {
        $s = tl_db()->prepare('SELECT 1 FROM pc_profiles WHERE user_id = ?');
        $s->execute([$userId]);
        return (bool) $s->fetchColumn();
    }

    /** Join Placecard: the original's registration fields, on an existing shared account. */
    public static function create(int $userId, string $firstName, string $lastName, int $age): string
    {
        $publicId = self::uuid();
        tl_db()->prepare(
            'INSERT INTO pc_profiles (user_id, public_id, first_name, last_name, age) VALUES (?, ?, ?, ?, ?)'
        )->execute([$userId, $publicId, $firstName, $lastName, $age]);
        return $publicId;
    }

    public static function publicId(int $userId): string
    {
        $s = tl_db()->prepare('SELECT public_id FROM pc_profiles WHERE user_id = ?');
        $s->execute([$userId]);
        return (string) $s->fetchColumn();
    }
}
