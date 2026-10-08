<?php

namespace App\Models;

use App\Core\Database;

class Owner
{
    private Database $db;

    public const TIERS = [0, 1, 16, 256, 4096, 65536, 1048576];

    public const TIER_NAMES = [
        0       => 'Freeloader',
        1       => 'Trust Fund Baby',
        16      => 'Silver Spoon',
        256     => 'Gold Digger',
        4096    => 'Platinum Spender',
        65536   => 'Diamond Destroyer',
        1048576 => 'Inner Circle of Ruin',
    ];

    public const TIER_UNLOCKS = [
        1 => [
            'Multiple player slots',
            'Entourage upgrade system',
            'Vanity Project focus action',
        ],
        16 => [
            'Premium & ultra-exclusive property listings',
            'Advanced toy categories (helicopter, submarine, private space)',
            'Full entourage upgrade capability',
        ],
        256 => [
            'Exclusive high-risk investment deals',
            'Advanced analytics charts (net worth breakdown, economy)',
        ],
        4096 => [
            'Rare catastrophic crisis event modifiers',
            'Detailed financial breakdown on end-game screen',
        ],
        65536 => [
            'All premium features fully unlocked',
            'Diamond Destroyer status',
        ],
        1048576 => [
            'VIP badge on the Hall of Shame leaderboard',
            'Inner Circle of Ruin status — permanent bragging rights',
        ],
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Increment RoundsCompleted and advance the owner to the next membership tier.
     * Returns upgrade info including what was unlocked.
     */
    public function upgradeToNextTier(int $ownerId): array
    {
        $owner = $this->findById($ownerId);
        $currentLevel = (int) $owner->MemberLevel;

        $nextLevel = $currentLevel;
        foreach (self::TIERS as $tier) {
            if ($tier > $currentLevel) {
                $nextLevel = $tier;
                break;
            }
        }

        $rounds = (int) $owner->RoundsCompleted + 1;
        $this->update($ownerId, [
            'RoundsCompleted' => $rounds,
            'MemberLevel'     => $nextLevel,
        ]);

        return [
            'previousLevel' => $currentLevel,
            'newLevel'      => $nextLevel,
            'previousName'  => self::TIER_NAMES[$currentLevel] ?? 'Unknown',
            'newName'       => self::TIER_NAMES[$nextLevel] ?? 'Unknown',
            'unlocks'       => self::TIER_UNLOCKS[$nextLevel] ?? [],
            'upgraded'      => $nextLevel > $currentLevel,
            'roundsCompleted' => $rounds,
        ];
    }

    /**
     * The owner row: br_owners joined to the shared account. Email and name
     * come from users; FirstName/LastName are split from users.name so the
     * views written for the original Owners table keep working.
     */
    private const SELECT = "SELECT o.OwnerID, o.MemberLevel, o.RoundsCompleted, o.SignUpDate, o.LastLoginDate, o.IsAdmin,
                                   u.email AS Email, u.name AS Name,
                                   SUBSTRING_INDEX(u.name, ' ', 1) AS FirstName,
                                   TRIM(SUBSTRING(u.name, LENGTH(SUBSTRING_INDEX(u.name, ' ', 1)) + 1)) AS LastName
                            FROM br_owners o JOIN users u ON u.id = o.OwnerID";

    public function findById(int $id): ?object
    {
        return $this->db->fetch(self::SELECT . " WHERE o.OwnerID = ? LIMIT 1", [$id]);
    }

    public function findByEmail(string $email): ?object
    {
        return $this->db->fetch(self::SELECT . " WHERE u.email = ? LIMIT 1", [strtolower(trim($email))]);
    }

    /**
     * The br_owners row for a shared account, created on first visit with the
     * defaults the original gave a new SSO sign-in (Freeloader, 0 rounds).
     * Also stamps LastLoginDate once per session, as the SSO callback did.
     */
    public function ensure(int $userId): ?object
    {
        $this->db->query(
            "INSERT IGNORE INTO br_owners (OwnerID, MemberLevel, RoundsCompleted, SignUpDate) VALUES (?, 0, 0, ?)",
            [$userId, date('Y-m-d H:i:s')]
        );
        if (!isset($_SESSION['br_login_stamped'])) {
            $this->update($userId, ['LastLoginDate' => date('Y-m-d H:i:s')]);
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['br_login_stamped'] = 1;
            }
        }
        return $this->findById($userId);
    }

    public function update(int $id, array $data): int
    {
        return $this->db->update('br_owners', $data, 'OwnerID = ?', [$id]);
    }

    public function getAll(int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            self::SELECT . " ORDER BY o.OwnerID DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function count(): int
    {
        return (int) $this->db->fetchColumn("SELECT COUNT(*) FROM br_owners");
    }
}
