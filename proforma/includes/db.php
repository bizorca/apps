<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// Business helpers
// ---------------------------------------------------------------------------
/** Users with at least one Proforma business: the digest's audience. The account is shared, so "all users" would mail people who never used this tool. */
function getAllUsers(): array {
    return getDb()->query('SELECT DISTINCT u.id, u.email FROM users u JOIN pf_businesses b ON b.user_id = u.id ORDER BY u.id')->fetchAll();
}

function getBusinessById(int $id, int $userId): ?array {
    $db  = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_businesses WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->fetch() ?: null;
}

function getUserBusinesses(int $userId): array {
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_businesses WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getExpenses(int $businessId): array {
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_expenses WHERE business_id = ? ORDER BY sort_order, id');
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

function getClassSchedules(int $businessId): array {
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_class_schedules WHERE business_id = ?');
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

function getRevenueStreams(int $businessId): array {
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_revenue_streams WHERE business_id = ? ORDER BY id');
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

function getInstructors(int $businessId): array {
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_instructors WHERE business_id = ? ORDER BY id');
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

// ---------------------------------------------------------------------------
// Therapist-vertical helpers
// ---------------------------------------------------------------------------
function getInsurancePayers(int $businessId): array {
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_insurance_payers WHERE business_id = ? ORDER BY sort_order, id');
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

function getCptCodes(int $businessId): array {
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_cpt_codes WHERE business_id = ? ORDER BY sort_order, id');
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

function getPayerRates(int $businessId): array {
    $db   = getDb();
    $stmt = $db->prepare(
        'SELECT pr.*, ip.payer_name, ip.client_pct, ip.is_cash_pay, c.code, c.description, c.sessions_per_month
         FROM pf_payer_rates pr
         JOIN pf_insurance_payers ip ON ip.id = pr.payer_id
         JOIN pf_cpt_codes c ON c.id = pr.cpt_id
         WHERE ip.business_id = ?'
    );
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

function getStaffProviders(int $businessId): array {
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_staff_providers WHERE business_id = ? ORDER BY is_owner DESC, id');
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

// ---------------------------------------------------------------------------
// User settings helpers
// ---------------------------------------------------------------------------
function getUserSettings(int $userId): array {
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_user_settings WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        // Return defaults if no row saved yet
        return [
            'federal_payroll_tax_pct' => 0.0765,
            'state_payroll_tax_pct'   => 0.03,
            'workers_comp_pct'        => 0.02,
        ];
    }
    return $row;
}

function saveUserSettings(int $userId, array $settings): void {
    $db = getDb();
    $db->prepare(
        'INSERT INTO pf_user_settings (user_id, federal_payroll_tax_pct, state_payroll_tax_pct, workers_comp_pct)
         VALUES (?, ?, ?, ?) AS new
         ON DUPLICATE KEY UPDATE
             federal_payroll_tax_pct = new.federal_payroll_tax_pct,
             state_payroll_tax_pct   = new.state_payroll_tax_pct,
             workers_comp_pct        = new.workers_comp_pct'
    )->execute([
        $userId,
        (float)($settings['federal_payroll_tax_pct'] ?? 0.0765),
        (float)($settings['state_payroll_tax_pct']   ?? 0.03),
        (float)($settings['workers_comp_pct']        ?? 0.02),
    ]);
}

// ---------------------------------------------------------------------------
// Share token helpers
// ---------------------------------------------------------------------------
function getBusinessByToken(string $token): ?array {
    if ($token === '') return null;
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM pf_businesses WHERE share_token = ?');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function generateShareToken(int $businessId, int $userId): string {
    $token = bin2hex(random_bytes(24)); // 48-char hex token
    $db    = getDb();
    $db->prepare('UPDATE pf_businesses SET share_token = ? WHERE id = ? AND user_id = ?')
       ->execute([$token, $businessId, $userId]);
    return $token;
}

function revokeShareToken(int $businessId, int $userId): void {
    $db = getDb();
    $db->prepare('UPDATE pf_businesses SET share_token = NULL WHERE id = ? AND user_id = ?')
       ->execute([$businessId, $userId]);
}

// ---------------------------------------------------------------------------
// Monthly actuals helpers
// ---------------------------------------------------------------------------
function saveMonthlyActual(int $businessId, int $year, int $month, array $data): void {
    $db = getDb();
    $db->prepare(
        'INSERT INTO pf_monthly_actuals (business_id, year, month, gross_revenue, student_visits, total_expenses, notes, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW()) AS new
         ON DUPLICATE KEY UPDATE
             gross_revenue  = new.gross_revenue,
             student_visits = new.student_visits,
             total_expenses = new.total_expenses,
             notes          = new.notes,
             updated_at     = new.updated_at'
    )->execute([
        $businessId, $year, $month,
        (float)($data['gross_revenue']  ?? 0),
        (int)  ($data['student_visits'] ?? 0),
        (float)($data['total_expenses'] ?? 0),
        trim($data['notes'] ?? ''),
    ]);
}

function getMonthlyActuals(int $businessId, int $limit = 12): array {
    $db   = getDb();
    $stmt = $db->prepare(
        'SELECT * FROM pf_monthly_actuals WHERE business_id = ?
         ORDER BY year DESC, month DESC LIMIT ?'
    );
    // Native prepares: LIMIT needs a real int, not a string.
    $stmt->bindValue(1, $businessId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function deleteMonthlyActual(int $id, int $businessId): void {
    getDb()->prepare('DELETE FROM pf_monthly_actuals WHERE id = ? AND business_id = ?')
           ->execute([$id, $businessId]);
}

// ---------------------------------------------------------------------------
// Clone business
// ---------------------------------------------------------------------------
function cloneBusiness(int $businessId, int $userId): int {
    $db  = getDb();
    $biz = $db->prepare('SELECT * FROM pf_businesses WHERE id = ? AND user_id = ?');
    $biz->execute([$businessId, $userId]);
    $orig = $biz->fetch();
    if (!$orig) return 0;

    // Create the new business record
    $db->prepare(
        'INSERT INTO pf_businesses (user_id, business_type, business_name, owner_salary_annual, weeks_per_year)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $userId,
        $orig['business_type'],
        $orig['business_name'] . ' (Copy)',
        $orig['owner_salary_annual'],
        $orig['weeks_per_year'],
    ]);
    $newId = (int)$db->lastInsertId();

    // Copy expenses
    $rows = $db->prepare('SELECT * FROM pf_expenses WHERE business_id = ? ORDER BY sort_order, id');
    $rows->execute([$businessId]);
    foreach ($rows->fetchAll() as $row) {
        $db->prepare('INSERT INTO pf_expenses (business_id, category, label, amount_monthly, is_variable, sort_order) VALUES (?,?,?,?,?,?)')
           ->execute([$newId, $row['category'], $row['label'], $row['amount_monthly'], $row['is_variable'], $row['sort_order']]);
    }

    // Copy class schedules
    $rows = $db->prepare('SELECT * FROM pf_class_schedules WHERE business_id = ?');
    $rows->execute([$businessId]);
    foreach ($rows->fetchAll() as $row) {
        $db->prepare('INSERT INTO pf_class_schedules (business_id, class_name, class_type, classes_per_week, room_capacity, avg_fill_rate) VALUES (?,?,?,?,?,?)')
           ->execute([$newId, $row['class_name'], $row['class_type'], $row['classes_per_week'], $row['room_capacity'], $row['avg_fill_rate']]);
    }

    // Copy revenue streams
    $rows = $db->prepare('SELECT * FROM pf_revenue_streams WHERE business_id = ?');
    $rows->execute([$businessId]);
    foreach ($rows->fetchAll() as $row) {
        $db->prepare('INSERT INTO pf_revenue_streams (business_id, stream_type, label, price, units_included, estimated_monthly_units, conversion_source, conversion_rate, is_enabled) VALUES (?,?,?,?,?,?,?,?,?)')
           ->execute([$newId, $row['stream_type'], $row['label'], $row['price'], $row['units_included'], $row['estimated_monthly_units'], $row['conversion_source'], $row['conversion_rate'], $row['is_enabled']]);
    }

    // Copy instructors
    $rows = $db->prepare('SELECT * FROM pf_instructors WHERE business_id = ?');
    $rows->execute([$businessId]);
    foreach ($rows->fetchAll() as $row) {
        $db->prepare('INSERT INTO pf_instructors (business_id, name, worker_type, pay_type, pay_per_class, revenue_share_pct, classes_per_week) VALUES (?,?,?,?,?,?,?)')
           ->execute([$newId, $row['name'], $row['worker_type'], $row['pay_type'], $row['pay_per_class'], $row['revenue_share_pct'], $row['classes_per_week']]);
    }

    // Therapist-only: payers, CPT codes, rates, providers
    if ($orig['business_type'] === 'therapist') {
        $payerMap = [];
        $rows = $db->prepare('SELECT * FROM pf_insurance_payers WHERE business_id = ? ORDER BY sort_order, id');
        $rows->execute([$businessId]);
        foreach ($rows->fetchAll() as $row) {
            $db->prepare('INSERT INTO pf_insurance_payers (business_id, payer_name, client_pct, is_cash_pay, sort_order) VALUES (?,?,?,?,?)')
               ->execute([$newId, $row['payer_name'], $row['client_pct'], $row['is_cash_pay'], $row['sort_order']]);
            $payerMap[(int)$row['id']] = (int)$db->lastInsertId();
        }

        $cptMap = [];
        $rows = $db->prepare('SELECT * FROM pf_cpt_codes WHERE business_id = ? ORDER BY sort_order, id');
        $rows->execute([$businessId]);
        foreach ($rows->fetchAll() as $row) {
            $db->prepare('INSERT INTO pf_cpt_codes (business_id, code, description, sessions_per_month, sort_order) VALUES (?,?,?,?,?)')
               ->execute([$newId, $row['code'], $row['description'], $row['sessions_per_month'], $row['sort_order']]);
            $cptMap[(int)$row['id']] = (int)$db->lastInsertId();
        }

        // Rates: join via payer_id so we get old IDs to remap
        $rows = $db->prepare(
            'SELECT pr.* FROM pf_payer_rates pr
             JOIN pf_insurance_payers ip ON ip.id = pr.payer_id
             WHERE ip.business_id = ?'
        );
        $rows->execute([$businessId]);
        foreach ($rows->fetchAll() as $row) {
            $newPayerId = $payerMap[(int)$row['payer_id']] ?? null;
            $newCptId   = $cptMap[(int)$row['cpt_id']]   ?? null;
            if ($newPayerId && $newCptId) {
                $db->prepare('INSERT IGNORE INTO pf_payer_rates (payer_id, cpt_id, rate) VALUES (?,?,?)')
                   ->execute([$newPayerId, $newCptId, $row['rate']]);
            }
        }

        $rows = $db->prepare('SELECT * FROM pf_staff_providers WHERE business_id = ?');
        $rows->execute([$businessId]);
        foreach ($rows->fetchAll() as $row) {
            $db->prepare('INSERT INTO pf_staff_providers (business_id, name, credential, worker_type, sessions_per_week, hours_per_week, pay_type, pay_rate, is_owner) VALUES (?,?,?,?,?,?,?,?,?)')
               ->execute([$newId, $row['name'], $row['credential'], $row['worker_type'], $row['sessions_per_week'], $row['hours_per_week'], $row['pay_type'], $row['pay_rate'], $row['is_owner']]);
        }
    }

    // Clone Localrev market profile and organizations (not recommendations/feedback — those regenerate)
    $mp = $db->prepare('SELECT * FROM pf_market_profiles WHERE business_id = ?');
    $mp->execute([$businessId]);
    $mpRow = $mp->fetch();
    if ($mpRow) {
        $db->prepare(
            'INSERT INTO pf_market_profiles (business_id, town_name, state_code, zip_code, population, median_income, lat, lng)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$newId, $mpRow['town_name'], $mpRow['state_code'], $mpRow['zip_code'], $mpRow['population'], $mpRow['median_income'], $mpRow['lat'], $mpRow['lng']]);

        $orgs = $db->prepare('SELECT * FROM pf_local_organizations WHERE business_id = ? ORDER BY sort_order, id');
        $orgs->execute([$businessId]);
        foreach ($orgs->fetchAll() as $org) {
            $db->prepare('INSERT INTO pf_local_organizations (business_id, org_name, org_type, place_id, source, employee_count, notes, sort_order) VALUES (?,?,?,?,?,?,?,?)')
               ->execute([$newId, $org['org_name'], $org['org_type'], $org['place_id'], $org['source'], $org['employee_count'], $org['notes'], $org['sort_order']]);
        }
    }

    return $newId;
}

// ---------------------------------------------------------------------------
// API cache helpers (Census, Google Places)
// ---------------------------------------------------------------------------
function getCachedApiResponse(string $key): ?array
{
    $db   = getDb();
    $stmt = $db->prepare("SELECT response FROM pf_api_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $decoded = json_decode($row['response'], true);
    return is_array($decoded) ? $decoded : null;
}

function setCachedApiResponse(string $key, array $data, int $ttlSeconds): void
{
    $db  = getDb();
    $exp = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $db->prepare(
        'INSERT INTO pf_api_cache (cache_key, response, expires_at)
         VALUES (?, ?, ?) AS new
         ON DUPLICATE KEY UPDATE response = new.response, fetched_at = NOW(), expires_at = new.expires_at'
    )->execute([$key, json_encode($data), $exp]);
}

// ---------------------------------------------------------------------------
// Localrev: market profiles
// ---------------------------------------------------------------------------
function getMarketProfile(int $businessId): ?array
{
    $stmt = getDb()->prepare('SELECT * FROM pf_market_profiles WHERE business_id = ?');
    $stmt->execute([$businessId]);
    return $stmt->fetch() ?: null;
}

function saveMarketProfile(int $businessId, array $data): void
{
    $db = getDb();
    $db->prepare(
        'INSERT INTO pf_market_profiles (business_id, town_name, state_code, zip_code, population, median_income, lat, lng, census_fetched_at, places_fetched_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) AS new
         ON DUPLICATE KEY UPDATE
             town_name         = new.town_name,
             state_code        = new.state_code,
             zip_code          = new.zip_code,
             population        = new.population,
             median_income     = COALESCE(new.median_income, pf_market_profiles.median_income),
             lat               = COALESCE(new.lat, pf_market_profiles.lat),
             lng               = COALESCE(new.lng, pf_market_profiles.lng),
             census_fetched_at = COALESCE(new.census_fetched_at, pf_market_profiles.census_fetched_at),
             places_fetched_at = COALESCE(new.places_fetched_at, pf_market_profiles.places_fetched_at),
             updated_at        = new.updated_at'
    )->execute([
        $businessId,
        trim($data['town_name']  ?? ''),
        strtoupper(trim($data['state_code'] ?? '')),
        trim($data['zip_code']   ?? ''),
        (int)($data['population'] ?? 0),
        isset($data['median_income']) ? (float)$data['median_income'] : null,
        isset($data['lat'])           ? (float)$data['lat']           : null,
        isset($data['lng'])           ? (float)$data['lng']           : null,
        $data['census_fetched_at']    ?? null,
        $data['places_fetched_at']    ?? null,
    ]);
}

// ---------------------------------------------------------------------------
// Localrev: local organizations
// ---------------------------------------------------------------------------
function getLocalOrgs(int $businessId): array
{
    $stmt = getDb()->prepare('SELECT * FROM pf_local_organizations WHERE business_id = ? ORDER BY sort_order, id');
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

function saveLocalOrg(int $businessId, array $data): int
{
    $db = getDb();
    $db->prepare(
        'INSERT INTO pf_local_organizations (business_id, org_name, org_type, place_id, source, employee_count, notes, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $businessId,
        trim($data['org_name']      ?? ''),
        trim($data['org_type']      ?? 'other'),
        $data['place_id']           ?? null,
        $data['source']             ?? 'manual',
        isset($data['employee_count']) && $data['employee_count'] !== '' ? (int)$data['employee_count'] : null,
        trim($data['notes']         ?? ''),
        (int)($data['sort_order']   ?? 0),
    ]);
    return (int)$db->lastInsertId();
}

function deleteLocalOrg(int $id, int $businessId): void
{
    getDb()->prepare('DELETE FROM pf_local_organizations WHERE id = ? AND business_id = ?')
           ->execute([$id, $businessId]);
}

// ---------------------------------------------------------------------------
// Localrev: recommendations
// ---------------------------------------------------------------------------
function getRecommendations(int $businessId, bool $includeDismissed = false): array
{
    $sql  = 'SELECT r.*, f.status AS feedback_status, f.monthly_revenue AS feedback_revenue
             FROM pf_recommendations r
             LEFT JOIN pf_recommendation_feedback f ON f.recommendation_id = r.id AND f.business_id = r.business_id
             WHERE r.business_id = ?';
    if (!$includeDismissed) $sql .= ' AND r.is_dismissed = 0';
    $sql .= ' ORDER BY r.priority DESC, r.id';
    $stmt = getDb()->prepare($sql);
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

function getRecommendationById(int $id): ?array
{
    $stmt = getDb()->prepare('SELECT * FROM pf_recommendations WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function upsertRecommendation(int $businessId, array $data): int
{
    $db = getDb();
    $db->prepare(
        'INSERT INTO pf_recommendations (business_id, rule_key, title, description, category, estimated_monthly_revenue, priority, playbook_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?) AS new
         ON DUPLICATE KEY UPDATE
             title                     = new.title,
             description               = new.description,
             category                  = new.category,
             estimated_monthly_revenue = new.estimated_monthly_revenue,
             priority                  = new.priority,
             playbook_json             = COALESCE(new.playbook_json, pf_recommendations.playbook_json)'
    )->execute([
        $businessId,
        $data['rule_key'],
        $data['title'],
        $data['description'],
        $data['category'] ?? 'b2b',
        $data['estimated_monthly_revenue'] ?? null,
        $data['priority'] ?? 50,
        $data['playbook_json'] ?? null,
    ]);
    // Get the id (INSERT or existing via conflict)
    $stmt = $db->prepare('SELECT id FROM pf_recommendations WHERE business_id = ? AND rule_key = ?');
    $stmt->execute([$businessId, $data['rule_key']]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function dismissRecommendation(int $id, int $businessId): void
{
    getDb()->prepare('UPDATE pf_recommendations SET is_dismissed = 1 WHERE id = ? AND business_id = ?')
           ->execute([$id, $businessId]);
}

function undismissRecommendation(int $id, int $businessId): void
{
    getDb()->prepare('UPDATE pf_recommendations SET is_dismissed = 0 WHERE id = ? AND business_id = ?')
           ->execute([$id, $businessId]);
}

function getDismissedRecommendations(int $businessId): array
{
    $stmt = getDb()->prepare(
        'SELECT * FROM pf_recommendations WHERE business_id = ? AND is_dismissed = 1 ORDER BY priority DESC, id'
    );
    $stmt->execute([$businessId]);
    return $stmt->fetchAll();
}

function getRecommendationCount(int $businessId): int
{
    $stmt = getDb()->prepare('SELECT COUNT(*) FROM pf_recommendations WHERE business_id = ? AND is_dismissed = 0');
    $stmt->execute([$businessId]);
    return (int)$stmt->fetchColumn();
}

// ---------------------------------------------------------------------------
// Localrev: recommendation feedback
// ---------------------------------------------------------------------------
function saveRecommendationFeedback(int $recommendationId, int $businessId, array $data): void
{
    getDb()->prepare(
        'INSERT INTO pf_recommendation_feedback (recommendation_id, user_id, business_id, status, monthly_revenue, notes, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW()) AS new
         ON DUPLICATE KEY UPDATE
             status          = new.status,
             monthly_revenue = new.monthly_revenue,
             notes           = new.notes,
             updated_at      = new.updated_at'
    )->execute([
        $recommendationId,
        $data['user_id'],
        $businessId,
        $data['status'] ?? 'considering',
        isset($data['monthly_revenue']) && $data['monthly_revenue'] !== '' ? (float)$data['monthly_revenue'] : null,
        trim($data['notes'] ?? ''),
    ]);
}

// ---------------------------------------------------------------------------
// Wizard completion check (type-aware)
// ---------------------------------------------------------------------------
function wizardStepStatus(int $businessId, string $businessType = 'yoga'): array {
    if ($businessType === 'therapist') {
        return [
            1 => true,
            2 => count(getExpenses($businessId)) > 0,
            3 => count(getInsurancePayers($businessId)) > 0,
            4 => count(getStaffProviders($businessId)) > 0,
            5 => true, // report always accessible
        ];
    }
    return [
        1 => true,
        2 => count(getExpenses($businessId)) > 0,
        3 => count(getClassSchedules($businessId)) > 0,
        4 => count(getRevenueStreams($businessId)) > 0,
        5 => count(getInstructors($businessId)) > 0,
        6 => true,
    ];
}
