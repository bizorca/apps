<?php
/** The shared MySQL handle (native prepares, UTC). */
function db(): PDO {
    return tl_db();
}

function db_row(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function db_rows(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_val(string $sql, array $params = []): mixed {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function db_insert(string $table, array $data): int {
    $cols = implode(', ', array_keys($data));
    $phs  = implode(', ', array_fill(0, count($data), '?'));
    db()->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$phs})")->execute(array_values($data));
    return (int)db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $where_params = []): void {
    $set = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
    db()->prepare("UPDATE {$table} SET {$set} WHERE {$where}")
        ->execute([...array_values($data), ...$where_params]);
}

function db_delete(string $table, string $where, array $params = []): void {
    db()->prepare("DELETE FROM {$table} WHERE {$where}")->execute($params);
}
