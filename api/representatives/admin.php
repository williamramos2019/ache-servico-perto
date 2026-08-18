<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/representatives.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['representatives', 'representative_activities', 'representative_attendance', 'representative_sync_logs']);
$method = app_request_method();
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';

if ($method === 'GET') {
    persist_require_admin();
    if ($op === 'list') {
        $stmt = $pdo->query(
            'SELECT r.*, c.name AS city_name, c.slug AS city_slug
             FROM representatives r JOIN cities c ON c.id = r.city_id ORDER BY r.updated_at DESC LIMIT 500'
        );
        app_success(['rows' => array_map('representatives_row', $stmt->fetchAll())]);
    }
    if ($op === 'logs') {
        app_success(['logs' => $pdo->query(
            'SELECT * FROM representative_sync_logs ORDER BY created_at DESC LIMIT 100'
        )->fetchAll()]);
    }
    if ($op === 'activities') {
        app_success(['rows' => $pdo->query(
            'SELECT * FROM representative_activities ORDER BY occurred_at DESC LIMIT 500'
        )->fetchAll()]);
    }
    app_error('invalid_op', 'Operação inválida.', 400);
}

$body = domain_read_json();
$op = is_string($body['op'] ?? null) ? $body['op'] : $op;
domain_require_admin_write();

if ($op === 'representative_save') {
    $id = domain_uuid($body['id'] ?? null, 'id');
    app_success(['id' => domain_upsert($pdo, 'representatives', representatives_validated($body), $id)], $id === null ? 201 : 200);
}
if ($op === 'representative_delete') {
    domain_delete($pdo, 'representatives', $body['id'] ?? null);
    app_success(['ok' => true]);
}
if ($op === 'activity_save') {
    $id = domain_uuid($body['id'] ?? null, 'id');
    app_success(['id' => domain_upsert($pdo, 'representative_activities', representatives_activity_validated($body), $id)], $id === null ? 201 : 200);
}
if ($op === 'activity_delete') {
    domain_delete($pdo, 'representative_activities', $body['id'] ?? null);
    app_success(['ok' => true]);
}
if ($op === 'attendance_save') {
    $id = domain_uuid($body['id'] ?? null, 'id');
    $sessionDate = domain_date($body['session_date'] ?? null, 'session_date', true);
    if ($sessionDate === null) {
        app_error('invalid_session_date', 'session_date is required.', 422);
    }
    $fields = [
        'representative_id' => domain_uuid($body['representative_id'] ?? null, 'representative_id', false),
        'session_date' => $sessionDate,
        'session_type' => domain_string($body['session_type'] ?? null, 'session_type', 0, 128),
        'present' => domain_bool($body['present'] ?? null, true),
        'notes' => domain_string($body['notes'] ?? null, 'notes', 0, 2000),
    ];
    if ($id === null) {
        $fields['created_at'] = auth_now();
        $newId = auth_uuid();
        $pdo->prepare(
            'INSERT INTO representative_attendance
             (id, representative_id, session_date, session_type, present, notes, created_at)
             VALUES (:id, :representative_id, :session_date, :session_type, :present, :notes, :created_at)'
        )->execute([':id' => $newId, ...array_combine(
            array_map(static fn (string $k): string => ':' . $k, array_keys($fields)),
            array_values($fields)
        )]);
        app_success(['id' => $newId], 201);
    }
    unset($fields['created_at']);
    $pdo->prepare(
        'UPDATE representative_attendance SET representative_id = :representative_id, session_date = :session_date,
         session_type = :session_type, present = :present, notes = :notes WHERE id = :id'
    )->execute([':id' => $id, ...array_combine(
        array_map(static fn (string $k): string => ':' . $k, array_keys($fields)),
        array_values($fields)
    )]);
    app_success(['id' => $id]);
}
if ($op === 'attendance_delete') {
    domain_delete($pdo, 'representative_attendance', $body['id'] ?? null);
    app_success(['ok' => true]);
}
if ($op === 'import') {
    $source = domain_enum($body['source'] ?? 'manual', 'source', REPRESENTATIVE_IMPORT_SOURCES, 'manual');
    $cityId = domain_uuid($body['city_id'] ?? null, 'city_id');
    $items = $body['items'] ?? null;
    if (!is_array($items) || !array_is_list($items) || count($items) > 500) {
        app_error('invalid_items', 'items must be an array with at most 500 records.', 422);
    }
    app_success(representatives_import($pdo, $source, $cityId, $items));
}

app_error('invalid_op', 'Operação inválida.', 400);
