<?php
/**
 * Company account management.
 */

function createCompany(int $userId, string $name, int $seatLimit = 50): int {
    $db = getDB();
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $name)));
    $slug = $slug ?: 'company-' . time();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tr_companies WHERE slug = ?");
    $stmt->execute([$slug]);
    if ((int) $stmt->fetchColumn() > 0) {
        $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    $stmt = $db->prepare("INSERT INTO tr_companies (name, slug, admin_user_id, seat_limit) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $slug, $userId, $seatLimit]);
    $companyId = (int) $db->lastInsertId();

    // Add creator as admin
    $stmt = $db->prepare("INSERT INTO tr_company_members (company_id, user_id, role) VALUES (?, ?, 'admin')");
    $stmt->execute([$companyId, $userId]);

    return $companyId;
}

function getCompany(string $slug): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tr_companies WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function getUserCompany(int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.*, cm.role AS my_role
        FROM tr_companies c
        JOIN tr_company_members cm ON cm.company_id = c.id AND cm.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function getCompanyMembers(int $companyId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, up.role_title, up.industry, cm.role, cm.joined_at,
               (SELECT COUNT(*) FROM tr_responses WHERE user_id = u.id) AS total_sessions,
               (SELECT AVG(total_score) FROM tr_responses WHERE user_id = u.id) AS avg_score,
               (SELECT AVG(model_correct) * 100 FROM tr_responses WHERE user_id = u.id) AS accuracy
        FROM tr_company_members cm
        JOIN users u ON u.id = cm.user_id
        LEFT JOIN tr_profiles up ON up.user_id = u.id
        WHERE cm.company_id = ?
        ORDER BY cm.role = 'admin' DESC, cm.role = 'manager' DESC, u.name ASC
    ");
    $stmt->execute([$companyId]);
    return $stmt->fetchAll();
}

function addCompanyMember(int $companyId, string $email, string $role = 'member'): ?string {
    $db = getDB();

    // Check seat limit
    $stmt = $db->prepare("SELECT seat_limit FROM tr_companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $limit = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tr_company_members WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $current = (int) $stmt->fetchColumn();

    if ($current >= $limit) {
        return 'Seat limit reached (' . $limit . ' seats).';
    }

    // Find or note user
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return 'No user found with that email. They need a Bizorca Tools account first.';
    }

    // Already a member?
    $stmt = $db->prepare("SELECT COUNT(*) FROM tr_company_members WHERE company_id = ? AND user_id = ?");
    $stmt->execute([$companyId, $user['id']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return 'Already a member.';
    }

    if (!in_array($role, ['admin', 'manager', 'member'])) $role = 'member';

    $stmt = $db->prepare("INSERT INTO tr_company_members (company_id, user_id, role) VALUES (?, ?, ?)");
    $stmt->execute([$companyId, $user['id'], $role]);

    return null; // success
}

function removeCompanyMember(int $companyId, int $userId): void {
    $db = getDB();
    $db->prepare("DELETE FROM tr_company_members WHERE company_id = ? AND user_id = ?")->execute([$companyId, $userId]);
}

function updateMemberRole(int $companyId, int $userId, string $role): void {
    $db = getDB();
    if (!in_array($role, ['admin', 'manager', 'member'])) return;
    $db->prepare("UPDATE tr_company_members SET role = ? WHERE company_id = ? AND user_id = ?")->execute([$role, $companyId, $userId]);
}

function isCompanyAdmin(int $companyId, int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT role FROM tr_company_members WHERE company_id = ? AND user_id = ?");
    $stmt->execute([$companyId, $userId]);
    $row = $stmt->fetch();
    return $row && $row['role'] === 'admin';
}

function isCompanyManager(int $companyId, int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT role FROM tr_company_members WHERE company_id = ? AND user_id = ?");
    $stmt->execute([$companyId, $userId]);
    $row = $stmt->fetch();
    return $row && in_array($row['role'], ['admin', 'manager']);
}

function getCompanyOrgStats(int $companyId): array {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT cm.user_id) AS total_members,
               (SELECT COUNT(*) FROM tr_responses r
                JOIN tr_company_members cm2 ON cm2.user_id = r.user_id AND cm2.company_id = ?) AS total_sessions,
               (SELECT AVG(r.total_score) FROM tr_responses r
                JOIN tr_company_members cm2 ON cm2.user_id = r.user_id AND cm2.company_id = ?) AS avg_score,
               (SELECT AVG(r.model_correct) * 100 FROM tr_responses r
                JOIN tr_company_members cm2 ON cm2.user_id = r.user_id AND cm2.company_id = ?) AS accuracy
        FROM tr_company_members cm
        WHERE cm.company_id = ?
    ");
    $stmt->execute([$companyId, $companyId, $companyId, $companyId]);
    return $stmt->fetch();
}

function getCompanyBlindspots(int $companyId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT mm.id AS model_id, mm.name AS model_name, mm.slug AS model_slug,
               COALESCE(SUM(bc.times_presented), 0) AS total_presented,
               COALESCE(SUM(bc.times_correct), 0) AS total_correct,
               COALESCE(AVG(NULLIF(bc.avg_reasoning, 0)), 0) AS avg_reasoning,
               COALESCE(SUM(bc.overuse_count), 0) AS total_overuse
        FROM tr_mental_models mm
        LEFT JOIN tr_blindspot_cache bc ON bc.model_id = mm.id
            AND bc.user_id IN (SELECT user_id FROM tr_company_members WHERE company_id = ?)
        GROUP BY mm.id, mm.name, mm.slug
        ORDER BY mm.display_order
    ");
    $stmt->execute([$companyId]);
    $all = $stmt->fetchAll();

    foreach ($all as &$row) {
        $row['accuracy'] = $row['total_presented'] > 0
            ? ($row['total_correct'] / $row['total_presented'])
            : 0;
    }
    return $all;
}
