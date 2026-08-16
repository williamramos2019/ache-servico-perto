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
        'SELECT direction, day_type, departure_time, control_point, notes
         FROM transport_schedules WHERE line_id = :id
         ORDER BY FIELD(day_type, "weekday", "saturday", "sunday", "holiday"), departure_time ASC'
    );
    $stmt->execute([':id' => $lineId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
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
        'SELECT sequence, name, address, lat, lng, direction
         FROM transport_stops WHERE line_id = :id
         ORDER BY direction ASC, sequence ASC'
    );
    $stmt->execute([':id' => $lineId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'sequence' => (int) $row['sequence'],
            'name' => (string) $row['name'],
            'address' => $row['address'] !== null ? (string) $row['address'] : null,
            'lat' => $row['lat'] !== null ? (float) $row['lat'] : null,
            'lng' => $row['lng'] !== null ? (float) $row['lng'] : null,
            'direction' => (string) $row['direction'],
        ];
    }

    return $out;
}

if ($method === 'GET') {
    transport_require_tables($pdo);

    if ($op === 'list') {
        $where = ['1=1'];
        $params = [];
        $city = isset($_GET['city']) ? trim((string) $_GET['city']) : '';
        if ($city !== '' && $city !== 'todas' && $city !== 'intermunicipal') {
            try {
                $city = importer_resolve_city_slug($city);
            } catch (InvalidArgumentException $e) {
                app_success(['lines' => []]);
            }
            $where[] = 'ci.slug = :city';
            $params[':city'] = $city;
        } elseif ($city === 'intermunicipal') {
            $where[] = 'l.type = :itype';
            $params[':itype'] = 'intermunicipal';
        }
        $type = isset($_GET['type']) ? trim((string) $_GET['type']) : '';
        if ($type !== '' && in_array($type, TRANSPORT_TYPES, true)) {
            $where[] = 'l.type = :type';
            $params[':type'] = $type;
        }
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(l.code LIKE :q_code OR l.name LIKE :q_name OR l.operator_name LIKE :q_op)';
            $params[':q_code'] = $like;
            $params[':q_name'] = $like;
            $params[':q_op'] = $like;
        }
        $sql = 'SELECT l.*, ci.slug AS city_slug, ci.name AS city_name
                FROM transport_lines l
                LEFT JOIN cities ci ON ci.id = l.city_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY l.code ASC, l.name ASC
                LIMIT 400';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $ids = [];
        $sourceIds = [];
        foreach ($rows as $row) {
            $ids[] = (string) $row['id'];
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
        $schedulesBy = [];
        $stopsBy = [];
        if ($ids !== []) {
            $keys = [];
            $iparams = [];
            foreach ($ids as $i => $id) {
                $k = ':id' . $i;
                $keys[] = $k;
                $iparams[$k] = $id;
            }
            $in = implode(',', $keys);
            $sst = $pdo->prepare(
                'SELECT line_id, direction, day_type, departure_time, control_point, notes
                 FROM transport_schedules WHERE line_id IN (' . $in . ')
                 ORDER BY FIELD(day_type, "weekday", "saturday", "sunday", "holiday"), departure_time ASC'
            );
            $sst->execute($iparams);
            foreach ($sst->fetchAll() as $srow) {
                $lid = (string) $srow['line_id'];
                $schedulesBy[$lid][] = [
                    'direction' => (string) $srow['direction'],
                    'day_type' => (string) $srow['day_type'],
                    'departure_time' => (string) $srow['departure_time'],
                    'control_point' => $srow['control_point'] !== null ? (string) $srow['control_point'] : null,
                    'notes' => $srow['notes'] !== null ? (string) $srow['notes'] : null,
                ];
            }
            $tst = $pdo->prepare(
                'SELECT line_id, sequence, name, address, lat, lng, direction
                 FROM transport_stops WHERE line_id IN (' . $in . ')
                 ORDER BY direction ASC, sequence ASC'
            );
            $tst->execute($iparams);
            foreach ($tst->fetchAll() as $trow) {
                $lid = (string) $trow['line_id'];
                $stopsBy[$lid][] = [
                    'sequence' => (int) $trow['sequence'],
                    'name' => (string) $trow['name'],
                    'address' => $trow['address'] !== null ? (string) $trow['address'] : null,
                    'lat' => $trow['lat'] !== null ? (float) $trow['lat'] : null,
                    'lng' => $trow['lng'] !== null ? (float) $trow['lng'] : null,
                    'direction' => (string) $trow['direction'],
                ];
            }
        }
        $lines = [];
        foreach ($rows as $row) {
            $sid = $row['source_id'] !== null ? (string) $row['source_id'] : null;
            $lid = (string) $row['id'];
            $lines[] = transport_line_row(
                $row,
                $sid !== null && isset($sources[$sid]) ? $sources[$sid] : null,
                $schedulesBy[$lid] ?? [],
                $stopsBy[$lid] ?? []
            );
        }
        app_success(['lines' => $lines]);
    }

    if ($op === 'show') {
        $slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
        $id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
        if ($slug === '' && $id === '') {
            app_error('invalid_id', 'Informe slug ou id.', 422);
        }
        if ($id !== '') {
            $stmt = $pdo->prepare(
                'SELECT l.*, ci.slug AS city_slug, ci.name AS city_name
                 FROM transport_lines l LEFT JOIN cities ci ON ci.id = l.city_id
                 WHERE l.id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $id]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT l.*, ci.slug AS city_slug, ci.name AS city_name
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
        app_success([
            'line' => transport_line_row(
                $row,
                $source,
                transport_schedules_for($pdo, (string) $row['id']),
                transport_stops_for($pdo, (string) $row['id'])
            ),
        ]);
    }

    if ($op === 'schedules') {
        $lineId = isset($_GET['line_id']) ? trim((string) $_GET['line_id']) : '';
        if ($lineId === '') {
            app_error('invalid_id', 'line_id inválido.', 422);
        }
        app_success(['schedules' => transport_schedules_for($pdo, $lineId)]);
    }

    if ($op === 'stops') {
        $lineId = isset($_GET['line_id']) ? trim((string) $_GET['line_id']) : '';
        if ($lineId === '') {
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
        ':status' => in_array(($body['status'] ?? 'unknown'), TRANSPORT_STATUSES, true) ? $body['status'] : 'unknown',
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
    $allowed = ['name', 'status', 'fare', 'operator_name', 'notes', 'type', 'city_id'];
    $set = ['updated_at = :updated_at'];
    $params = [':id' => $id, ':updated_at' => auth_now()];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $body)) {
            continue;
        }
        $set[] = "`$col` = :$col";
        $params[":$col"] = $body[$col] === '' ? null : $body[$col];
    }
    $pdo->prepare('UPDATE transport_lines SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
    app_success(['ok' => true]);
}

if ($method === 'POST' && $op === 'line_delete') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $pdo->prepare('DELETE FROM transport_lines WHERE id = :id')->execute([':id' => $id]);
    app_success(['ok' => true]);
}

app_error('invalid_op', 'Operação inválida.', 400);
