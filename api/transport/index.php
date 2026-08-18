<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/persist.php';
require_once dirname(__DIR__) . '/importer/bootstrap.php';
require_once dirname(__DIR__) . '/importer/transport.php';

app_start(['GET', 'POST', 'OPTIONS']);
auth_start_session();

$pdo = db_pdo(false);
$method = app_request_method();
$op = isset($_GET['op']) ? (string) $_GET['op'] : 'list';

function transport_require_tables(PDO $pdo): void
{
    try {
        $pdo->query('SELECT 1 FROM transport_lines LIMIT 1');
    } catch (Throwable $e) {
        app_error('not_migrated', 'Catálogo de transporte ainda não foi instalado.', 503);
    }
}

function transport_fetch_source(PDO $pdo, ?string $sourceId): ?array
{
    if ($sourceId === null || $sourceId === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT name, url, type, collected_at FROM transport_sources WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $sourceId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * @return list<array<string, mixed>>
 */
function transport_schedules_for(PDO $pdo, string $lineId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, direction, day_type, departure_time, control_point, notes
         FROM transport_schedules WHERE line_id = :id
         ORDER BY FIELD(day_type, "weekday", "saturday", "sunday", "holiday", "vacation", "atypical"),
                  FIELD(direction, "ida", "volta", "circular"), departure_time ASC'
    );
    $stmt->execute([':id' => $lineId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'id' => (string) $row['id'],
            'direction' => (string) $row['direction'],
            'day_type' => (string) $row['day_type'],
            'departure_time' => (string) $row['departure_time'],
            'control_point' => $row['control_point'] !== null ? (string) $row['control_point'] : null,
            'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
        ];
    }

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function transport_stops_for(PDO $pdo, string $lineId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, sequence, name, address, lat, lng, direction
         FROM transport_stops WHERE line_id = :id
         ORDER BY direction ASC, sequence ASC'
    );
    $stmt->execute([':id' => $lineId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'id' => (string) $row['id'],
            'sequence' => (int) $row['sequence'],
            'name' => (string) $row['name'],
            'address' => $row['address'] !== null ? (string) $row['address'] : null,
            'lat' => $row['lat'] !== null ? (float) $row['lat'] : null,
            'lng' => $row['lng'] !== null ? (float) $row['lng'] : null,
            'has_coords' => $row['lat'] !== null && $row['lng'] !== null,
            'direction' => (string) $row['direction'],
        ];
    }

    return $out;
}

/**
 * @return array<string, mixed>
 */
function transport_facets(PDO $pdo): array
{
    $cities = [];
    $types = [];
    $statuses = [];
    try {
        $stmt = $pdo->query(
            'SELECT ci.slug AS city_slug, COUNT(*) AS n
             FROM transport_lines l
             LEFT JOIN cities ci ON ci.id = l.city_id
             GROUP BY ci.slug'
        );
        foreach ($stmt->fetchAll() as $row) {
            $slug = $row['city_slug'] !== null ? (string) $row['city_slug'] : '';
            $cities[$slug] = (int) $row['n'];
        }
        $stmt = $pdo->query('SELECT type, COUNT(*) AS n FROM transport_lines GROUP BY type');
        foreach ($stmt->fetchAll() as $row) {
            $types[(string) $row['type']] = (int) $row['n'];
        }
        $stmt = $pdo->query('SELECT status, COUNT(*) AS n FROM transport_lines GROUP BY status');
        foreach ($stmt->fetchAll() as $row) {
            $statuses[(string) $row['status']] = (int) $row['n'];
        }
    } catch (Throwable $e) {
        return ['cities' => [], 'types' => [], 'statuses' => []];
    }

    return ['cities' => $cities, 'types' => $types, 'statuses' => $statuses];
}

if ($method === 'GET') {
    transport_require_tables($pdo);

    if ($op === 'list') {
        $norm = transport_normalize_list_query($_GET);
        if (!$norm['ok']) {
            app_error('invalid_query', (string) $norm['error'], 422);
        }
        $built = transport_list_where($norm['city'], $norm['type'], $norm['status'], $norm['q']);
        $whereSql = implode(' AND ', $built['where']);
        $params = $built['params'];
        $countSql = 'SELECT COUNT(*) FROM transport_lines l LEFT JOIN cities ci ON ci.id = l.city_id WHERE ' . $whereSql;
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $offset = ($norm['page'] - 1) * $norm['limit'];
        $sql = 'SELECT l.id, l.city_id, l.source_id, l.code, l.slug, l.name, l.type, l.status, l.fare,
                       l.operator_name, l.updated_at,
                       ci.slug AS city_slug, ci.name AS city_name,
                       (SELECT COUNT(*) FROM transport_schedules s WHERE s.line_id = l.id) AS schedule_count,
                       (SELECT COUNT(*) FROM transport_stops t WHERE t.line_id = l.id) AS stop_count
                FROM transport_lines l
                LEFT JOIN cities ci ON ci.id = l.city_id
                WHERE ' . $whereSql . '
                ORDER BY l.code ASC, l.name ASC
                LIMIT ' . (int) $norm['limit'] . ' OFFSET ' . (int) $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $sourceIds = [];
        foreach ($rows as $row) {
            if ($row['source_id'] !== null) {
                $sourceIds[(string) $row['source_id']] = true;
            }
        }
        $sources = [];
        if ($sourceIds !== []) {
            $keys = [];
            $sparams = [];
            $i = 0;
            foreach (array_keys($sourceIds) as $sid) {
                $k = ':s' . $i;
                $keys[] = $k;
                $sparams[$k] = $sid;
                $i++;
            }
            $sst = $pdo->prepare('SELECT id, name, url, type, collected_at FROM transport_sources WHERE id IN (' . implode(',', $keys) . ')');
            $sst->execute($sparams);
            foreach ($sst->fetchAll() as $srow) {
                $sources[(string) $srow['id']] = $srow;
            }
        }
        $lines = [];
        foreach ($rows as $row) {
            $sid = $row['source_id'] !== null ? (string) $row['source_id'] : null;
            $card = transport_line_row(
                $row,
                $sid !== null && isset($sources[$sid]) ? $sources[$sid] : null,
                [],
                []
            );
            unset($card['schedules'], $card['stops'], $card['notes']);
            $lines[] = $card;
        }
        app_success([
            'lines' => $lines,
            'total' => $total,
            'page' => $norm['page'],
            'limit' => $norm['limit'],
            'facets' => transport_facets($pdo),
        ]);
    }

    if ($op === 'show') {
        $slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
        $id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
        if ($slug === '' && $id === '') {
            app_error('invalid_id', 'Informe slug ou id.', 422);
        }
        if ($id !== '' && !transport_is_uuid($id)) {
            app_error('invalid_id', 'ID inválido.', 422);
        }
        if ($slug !== '' && (str_contains($slug, '/') || strlen($slug) > 191)) {
            app_error('invalid_id', 'Slug inválido.', 422);
        }
        if ($id !== '') {
            $stmt = $pdo->prepare(
                'SELECT l.*, ci.slug AS city_slug, ci.name AS city_name,
                        (SELECT COUNT(*) FROM transport_schedules s WHERE s.line_id = l.id) AS schedule_count,
                        (SELECT COUNT(*) FROM transport_stops t WHERE t.line_id = l.id) AS stop_count
                 FROM transport_lines l LEFT JOIN cities ci ON ci.id = l.city_id
                 WHERE l.id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $id]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT l.*, ci.slug AS city_slug, ci.name AS city_name,
                        (SELECT COUNT(*) FROM transport_schedules s WHERE s.line_id = l.id) AS schedule_count,
                        (SELECT COUNT(*) FROM transport_stops t WHERE t.line_id = l.id) AS stop_count
                 FROM transport_lines l LEFT JOIN cities ci ON ci.id = l.city_id
                 WHERE l.slug = :slug LIMIT 1'
            );
            $stmt->execute([':slug' => $slug]);
        }
        $row = $stmt->fetch();
        if ($row === false) {
            app_success(['line' => null]);
        }
        $source = transport_fetch_source($pdo, $row['source_id'] !== null ? (string) $row['source_id'] : null);
        $line = transport_line_row($row, $source, [], []);
        unset($line['schedules'], $line['stops']);
        app_success(['line' => $line]);
    }

    if ($op === 'schedules') {
        $lineId = isset($_GET['line_id']) ? trim((string) $_GET['line_id']) : '';
        if ($lineId === '' || !transport_is_uuid($lineId)) {
            app_error('invalid_id', 'line_id inválido.', 422);
        }
        app_success(['schedules' => transport_schedules_for($pdo, $lineId)]);
    }

    if ($op === 'stops') {
        $lineId = isset($_GET['line_id']) ? trim((string) $_GET['line_id']) : '';
        if ($lineId === '' || !transport_is_uuid($lineId)) {
            app_error('invalid_id', 'line_id inválido.', 422);
        }
        app_success(['stops' => transport_stops_for($pdo, $lineId)]);
    }

    app_error('invalid_op', 'Operação inválida.', 400);
}

require_csrf();
persist_require_admin();
$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = [];
}
$op = isset($body['op']) ? (string) $body['op'] : $op;

if ($method === 'POST' && $op === 'line_create') {
    transport_require_tables($pdo);
    $code = persist_optional_string($body['code'] ?? null, 32);
    $name = persist_optional_string($body['name'] ?? null, 255);
    $type = persist_optional_string($body['type'] ?? 'municipal', 32) ?? 'municipal';
    if ($code === null || $name === null || !in_array($type, TRANSPORT_TYPES, true)) {
        app_error('invalid_payload', 'code, name e type são obrigatórios.', 422);
    }
    $status = (string) ($body['status'] ?? 'unknown');
    if (!in_array($status, TRANSPORT_STATUSES, true)) {
        $status = 'unknown';
    }
    $now = auth_now();
    $id = auth_uuid();
    $slug = transport_slugify($code, $name);
    $pdo->prepare(
        'INSERT INTO transport_lines
            (id, city_id, source_id, code, slug, name, type, status, fare, operator_name, notes, created_at, updated_at)
         VALUES
            (:id, :city_id, NULL, :code, :slug, :name, :type, :status, :fare, :operator_name, :notes, :created_at, :updated_at)'
    )->execute([
        ':id' => $id,
        ':city_id' => persist_optional_uuid($body['city_id'] ?? null),
        ':code' => $code,
        ':slug' => $slug,
        ':name' => $name,
        ':type' => $type,
        ':status' => $status,
        ':fare' => persist_optional_string($body['fare'] ?? null, 64),
        ':operator_name' => persist_optional_string($body['operator_name'] ?? null, 255),
        ':notes' => persist_optional_string($body['notes'] ?? null, 2000),
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    app_success(['id' => $id, 'slug' => $slug]);
}

if ($method === 'POST' && $op === 'line_update') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $allowed = ['name', 'status', 'fare', 'operator_name', 'notes', 'type', 'city_id', 'source_id'];
    $set = ['updated_at = :updated_at'];
    $params = [':id' => $id, ':updated_at' => auth_now()];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $body)) {
            continue;
        }
        $value = $body[$col];
        if ($col === 'type' && !in_array((string) $value, TRANSPORT_TYPES, true)) {
            app_error('invalid_payload', 'type inválido.', 422);
        }
        if ($col === 'status' && $value !== null && $value !== '' && !in_array((string) $value, TRANSPORT_STATUSES, true)) {
            app_error('invalid_payload', 'status inválido.', 422);
        }
        $set[] = "`$col` = :$col";
        $params[":$col"] = $value === '' ? null : $value;
    }
    $pdo->prepare('UPDATE transport_lines SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
    app_success(['ok' => true]);
}

if ($method === 'POST' && $op === 'line_delete') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $pdo->prepare('DELETE FROM transport_lines WHERE id = :id')->execute([':id' => $id]);
    app_success(['ok' => true]);
}

if ($method === 'POST' && $op === 'source_upsert') {
    $name = persist_optional_string($body['name'] ?? null, 255);
    $url = persist_optional_string($body['url'] ?? null, 500);
    $lineId = persist_optional_uuid($body['line_id'] ?? null);
    if ($name === null) {
        app_error('invalid_payload', 'Nome da fonte obrigatório.', 422);
    }
    $type = (string) ($body['type'] ?? 'other');
    if (!in_array($type, TRANSPORT_SOURCE_TYPES, true)) {
        $type = 'other';
    }
    $now = auth_now();
    $id = auth_uuid();
    $pdo->prepare(
        'INSERT INTO transport_sources (id, name, url, type, collected_at, created_at, updated_at)
         VALUES (:id, :name, :url, :type, :collected_at, :created_at, :updated_at)'
    )->execute([
        ':id' => $id,
        ':name' => $name,
        ':url' => $url,
        ':type' => $type,
        ':collected_at' => $now,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    if ($lineId !== null) {
        $pdo->prepare('UPDATE transport_lines SET source_id = :sid, updated_at = :u WHERE id = :id')->execute([
            ':sid' => $id,
            ':u' => $now,
            ':id' => $lineId,
        ]);
    }
    app_success(['id' => $id]);
}

if ($method === 'POST' && $op === 'schedule_save') {
    $lineId = companies_require_uuid($body['line_id'] ?? null, 'invalid_id', 'line_id inválido.');
    $time = trim((string) ($body['departure_time'] ?? ''));
    $day = strtolower(trim((string) ($body['day_type'] ?? 'weekday')));
    $dir = strtolower(trim((string) ($body['direction'] ?? 'ida')));
    if (!transport_validate_time($time) || !in_array($day, TRANSPORT_DAY_TYPES, true) || !in_array($dir, TRANSPORT_DIRECTIONS, true)) {
        app_error('invalid_payload', 'Horário, dia ou sentido inválido.', 422);
    }
    $now = auth_now();
    $id = persist_optional_uuid($body['id'] ?? null);
    if ($id === null) {
        $id = auth_uuid();
        $pdo->prepare(
            'INSERT INTO transport_schedules
                (id, line_id, direction, day_type, departure_time, control_point, notes, created_at, updated_at)
             VALUES
                (:id, :line_id, :direction, :day_type, :departure_time, :control_point, :notes, :created_at, :updated_at)'
        )->execute([
            ':id' => $id,
            ':line_id' => $lineId,
            ':direction' => $dir,
            ':day_type' => $day,
            ':departure_time' => $time,
            ':control_point' => persist_optional_string($body['control_point'] ?? null, 255),
            ':notes' => persist_optional_string($body['notes'] ?? null, 500),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    } else {
        $pdo->prepare(
            'UPDATE transport_schedules SET direction = :direction, day_type = :day_type, departure_time = :departure_time,
                control_point = :control_point, notes = :notes, updated_at = :updated_at
             WHERE id = :id AND line_id = :line_id'
        )->execute([
            ':direction' => $dir,
            ':day_type' => $day,
            ':departure_time' => $time,
            ':control_point' => persist_optional_string($body['control_point'] ?? null, 255),
            ':notes' => persist_optional_string($body['notes'] ?? null, 500),
            ':updated_at' => $now,
            ':id' => $id,
            ':line_id' => $lineId,
        ]);
    }
    $pdo->prepare('UPDATE transport_lines SET updated_at = :u WHERE id = :id')->execute([':u' => $now, ':id' => $lineId]);
    app_success(['id' => $id]);
}

if ($method === 'POST' && $op === 'schedule_delete') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $pdo->prepare('DELETE FROM transport_schedules WHERE id = :id')->execute([':id' => $id]);
    app_success(['ok' => true]);
}

if ($method === 'POST' && $op === 'stop_save') {
    $lineId = companies_require_uuid($body['line_id'] ?? null, 'invalid_id', 'line_id inválido.');
    $name = persist_optional_string($body['name'] ?? null, 255);
    if ($name === null) {
        app_error('invalid_payload', 'Nome do ponto obrigatório.', 422);
    }
    $dir = strtolower(trim((string) ($body['direction'] ?? 'ida')));
    if (!in_array($dir, TRANSPORT_DIRECTIONS, true)) {
        $dir = 'ida';
    }
    $addr = persist_optional_string($body['address'] ?? null, 500);
    $nbhd = persist_optional_string($body['neighborhood'] ?? null, 255);
    if ($nbhd !== null) {
        $addr = $addr === null ? ('Bairro: ' . $nbhd) : ($addr . ' — ' . $nbhd);
    }
    $now = auth_now();
    $id = persist_optional_uuid($body['id'] ?? null);
    $seq = isset($body['sequence']) ? (int) $body['sequence'] : 0;
    $lat = isset($body['lat']) && is_numeric($body['lat']) ? $body['lat'] : null;
    $lng = isset($body['lng']) && is_numeric($body['lng']) ? $body['lng'] : null;
    if ($id === null) {
        $id = auth_uuid();
        $pdo->prepare(
            'INSERT INTO transport_stops
                (id, line_id, sequence, name, address, lat, lng, direction, created_at, updated_at)
             VALUES
                (:id, :line_id, :sequence, :name, :address, :lat, :lng, :direction, :created_at, :updated_at)'
        )->execute([
            ':id' => $id,
            ':line_id' => $lineId,
            ':sequence' => $seq,
            ':name' => $name,
            ':address' => $addr,
            ':lat' => $lat,
            ':lng' => $lng,
            ':direction' => $dir,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    } else {
        $pdo->prepare(
            'UPDATE transport_stops SET sequence = :sequence, name = :name, address = :address,
                lat = :lat, lng = :lng, direction = :direction, updated_at = :updated_at
             WHERE id = :id AND line_id = :line_id'
        )->execute([
            ':sequence' => $seq,
            ':name' => $name,
            ':address' => $addr,
            ':lat' => $lat,
            ':lng' => $lng,
            ':direction' => $dir,
            ':updated_at' => $now,
            ':id' => $id,
            ':line_id' => $lineId,
        ]);
    }
    $pdo->prepare('UPDATE transport_lines SET updated_at = :u WHERE id = :id')->execute([':u' => $now, ':id' => $lineId]);
    app_success(['id' => $id]);
}

if ($method === 'POST' && $op === 'stop_delete') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $pdo->prepare('DELETE FROM transport_stops WHERE id = :id')->execute([':id' => $id]);
    app_success(['ok' => true]);
}

app_error('invalid_op', 'Operação inválida.', 400);
