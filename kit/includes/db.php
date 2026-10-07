<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// Clients
//
// Every accessor takes $userId and filters on it. Ownership is not checked in
// the page and then trusted by the query — it is the query.
// ---------------------------------------------------------------------------
function clientFields(): array
{
    return ['business_name', 'owner_name', 'business_type', 'county', 'state_code',
            'entity_type', 'year_started', 'employee_count', 'seasonality',
            'engagement_status', 'notes'];
}

function getClients(int $userId, bool $includeArchived = false): array
{
    $sql = 'SELECT * FROM kit_clients WHERE user_id = ?';
    if (!$includeArchived) $sql .= " AND engagement_status <> 'archived'";
    // No COLLATE NOCASE needed: the column's utf8mb4_unicode_ci collation is
    // already case-insensitive.
    $sql .= ' ORDER BY business_name';
    $stmt = getDb()->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getClient(int $clientId, int $userId): ?array
{
    $stmt = getDb()->prepare('SELECT * FROM kit_clients WHERE id = ? AND user_id = ?');
    $stmt->execute([$clientId, $userId]);
    return $stmt->fetch() ?: null;
}

/** Load a client or stop the request. Used at the top of every client page. */
function requireClient(int $clientId, int $userId): array
{
    $client = getClient($clientId, $userId);
    if (!$client) {
        http_response_code(404);
        exit('Client not found.');
    }
    return $client;
}

function createClient(int $userId, array $data): int
{
    $db = getDb();
    $db->prepare(
        'INSERT INTO kit_clients (user_id, business_name, owner_name, business_type, county,
             state_code, entity_type, year_started, employee_count, seasonality,
             engagement_status, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $userId,
        $data['business_name'], $data['owner_name'] ?? '', $data['business_type'] ?? '',
        $data['county'] ?? '', $data['state_code'] ?? 'WA', $data['entity_type'] ?? '',
        $data['year_started'] ?? '', $data['employee_count'] ?? '', $data['seasonality'] ?? '',
        $data['engagement_status'] ?? 'active', $data['notes'] ?? '',
    ]);
    return (int) $db->lastInsertId();
}

function updateClient(int $clientId, int $userId, array $data): void
{
    getDb()->prepare(
        'UPDATE kit_clients SET business_name=?, owner_name=?, business_type=?, county=?,
             state_code=?, entity_type=?, year_started=?, employee_count=?, seasonality=?,
             engagement_status=?, notes=?, updated_at=NOW()
         WHERE id=? AND user_id=?'
    )->execute([
        $data['business_name'], $data['owner_name'] ?? '', $data['business_type'] ?? '',
        $data['county'] ?? '', $data['state_code'] ?? 'WA', $data['entity_type'] ?? '',
        $data['year_started'] ?? '', $data['employee_count'] ?? '', $data['seasonality'] ?? '',
        $data['engagement_status'] ?? 'active', $data['notes'] ?? '',
        $clientId, $userId,
    ]);
}

function deleteClient(int $clientId, int $userId): void
{
    getDb()->prepare('DELETE FROM kit_clients WHERE id = ? AND user_id = ?')
           ->execute([$clientId, $userId]);
}

// ---------------------------------------------------------------------------
// Assessments
// ---------------------------------------------------------------------------
function getAssessment(int $id, int $userId): ?array
{
    $stmt = getDb()->prepare(
        'SELECT a.* FROM kit_assessments a
         JOIN kit_clients c ON c.id = a.client_id
         WHERE a.id = ? AND c.user_id = ?'
    );
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['data'] = json_decode($row['data_json'], true) ?: [];
    return $row;
}

/** The most recent run of one instrument for one client, or null. */
function latestAssessment(int $clientId, string $instrument): ?array
{
    $stmt = getDb()->prepare(
        'SELECT * FROM kit_assessments WHERE client_id = ? AND instrument = ?
         ORDER BY updated_at DESC, id DESC LIMIT 1'
    );
    $stmt->execute([$clientId, $instrument]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['data'] = json_decode($row['data_json'], true) ?: [];
    return $row;
}

/** Every run of every instrument for one client, newest first. */
function clientAssessments(int $clientId): array
{
    $stmt = getDb()->prepare(
        'SELECT * FROM kit_assessments WHERE client_id = ? ORDER BY updated_at DESC, id DESC'
    );
    $stmt->execute([$clientId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) $r['data'] = json_decode($r['data_json'], true) ?: [];
    return $rows;
}

function assessmentHistory(int $clientId, string $instrument): array
{
    $stmt = getDb()->prepare(
        'SELECT id, period_label, status, created_at, updated_at FROM kit_assessments
         WHERE client_id = ? AND instrument = ? ORDER BY updated_at DESC, id DESC'
    );
    $stmt->execute([$clientId, $instrument]);
    return $stmt->fetchAll();
}

function createAssessment(int $clientId, string $instrument, array $data = [], string $period = '', string $status = 'draft'): int
{
    $db = getDb();
    $db->prepare(
        'INSERT INTO kit_assessments (client_id, instrument, period_label, data_json, status)
         VALUES (?,?,?,?,?)'
    )->execute([$clientId, $instrument, $period, json_encode($data, JSON_UNESCAPED_SLASHES), $status]);
    return (int) $db->lastInsertId();
}

function saveAssessment(int $id, array $data, string $status, string $period = ''): void
{
    getDb()->prepare(
        'UPDATE kit_assessments SET data_json = ?, status = ?, period_label = ?,
             updated_at = NOW() WHERE id = ?'
    )->execute([json_encode($data, JSON_UNESCAPED_SLASHES), $status, $period, $id]);
}

function deleteAssessment(int $id, int $userId): void
{
    // Multi-table DELETE: MySQL refuses a subquery on the table being deleted
    // from (error 1093), which is the shape the SQLite version used.
    getDb()->prepare(
        'DELETE a FROM kit_assessments a JOIN kit_clients c ON c.id = a.client_id
         WHERE a.id = ? AND c.user_id = ?'
    )->execute([$id, $userId]);
}

/**
 * Find or create the working record for an instrument.
 *
 * Opening an instrument the advisor has never run creates the draft; opening
 * one that exists resumes it. Starting a fresh period is an explicit action,
 * not a side effect of navigation.
 */
function openAssessment(int $clientId, string $instrument, bool $forceNew = false): array
{
    if (!$forceNew) {
        $existing = latestAssessment($clientId, $instrument);
        if ($existing) return $existing;
    }
    $id = createAssessment($clientId, $instrument);
    $row = getDb()->prepare('SELECT * FROM kit_assessments WHERE id = ?');
    $row->execute([$id]);
    $a = $row->fetch();
    $a['data'] = [];
    return $a;
}

// ---------------------------------------------------------------------------
// Progress record
// ---------------------------------------------------------------------------
function getProgress(int $clientId): array
{
    $stmt = getDb()->prepare(
        'SELECT * FROM kit_progress_entries WHERE client_id = ? ORDER BY entry_date DESC, id DESC'
    );
    $stmt->execute([$clientId]);
    return $stmt->fetchAll();
}

function addProgress(int $clientId, array $data): int
{
    $db = getDb();
    $db->prepare(
        'INSERT INTO kit_progress_entries (client_id, assessment_id, entry_date, instrument,
             what_changed, next_instrument) VALUES (?,?,?,?,?,?)'
    )->execute([
        $clientId,
        $data['assessment_id'] ?? null,
        // The column is a DATE now, and strict mode rejects anything else.
        preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($data['entry_date'] ?? '')) ? $data['entry_date'] : date('Y-m-d'),
        $data['instrument'] ?? '',
        $data['what_changed'] ?? '',
        $data['next_instrument'] ?? '',
    ]);
    return (int) $db->lastInsertId();
}

function deleteProgress(int $id, int $clientId): void
{
    getDb()->prepare('DELETE FROM kit_progress_entries WHERE id = ? AND client_id = ?')
           ->execute([$id, $clientId]);
}
