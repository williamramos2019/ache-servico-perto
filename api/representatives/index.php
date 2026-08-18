<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/representatives.php';

app_start(['GET', 'OPTIONS']);
$pdo = db_pdo(false);
domain_require_tables($pdo, ['representatives', 'representative_activities', 'representative_attendance']);
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';

if ($op === 'show') {
    $key = domain_string($_GET['id'] ?? ($_GET['slug'] ?? null), 'id', 1, 191, false);
    $column = companies_is_uuid($key) ? 'id' : 'slug';
    $stmt = $pdo->prepare("SELECT * FROM representatives WHERE `$column` = :key AND is_active = 1 LIMIT 1");
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch();
    if ($row === false) {
        app_success(['representative' => null]);
    }
    $rep = representatives_row($row);
    $activities = $pdo->prepare(
        'SELECT id, kind, title, description, status, source_url, source_name, occurred_at
         FROM representative_activities WHERE representative_id = :id ORDER BY occurred_at DESC LIMIT 100'
    );
    $activities->execute([':id' => $rep['id']]);
    $attendance = $pdo->prepare(
        'SELECT id, session_date, session_type, present, notes
         FROM representative_attendance WHERE representative_id = :id ORDER BY session_date DESC LIMIT 200'
    );
    $attendance->execute([':id' => $rep['id']]);
    $rep['activities'] = $activities->fetchAll();
    $rep['attendance'] = array_map(
        static fn (array $r): array => domain_decode_row($r, [], ['present']),
        $attendance->fetchAll()
    );
    app_success(['representative' => $rep]);
}

if ($op === 'list') {
    $page = domain_page($_GET);
    $limit = domain_limit($_GET, 30, 100);
    $where = ['r.is_active = 1'];
    $params = [];
    $city = domain_string($_GET['city'] ?? null, 'city', 0, 191);
    if ($city !== null) {
        $where[] = 'c.slug = :city';
        $params[':city'] = $city;
    }
    $role = domain_string($_GET['role'] ?? null, 'role', 0, 32);
    if ($role !== null) {
        $where[] = 'r.role = :role';
        $params[':role'] = $role;
    }
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*) FROM representatives r JOIN cities c ON c.id = r.city_id WHERE ' . $whereSql);
    $count->execute($params);
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        'SELECT r.*, c.name AS city_name, c.slug AS city_slug
         FROM representatives r JOIN cities c ON c.id = r.city_id WHERE ' . $whereSql .
        ' ORDER BY r.role, r.name LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $stmt->execute($params);
    app_success([
        'rows' => array_map('representatives_row', $stmt->fetchAll()),
        'total' => (int) $count->fetchColumn(),
        'page' => $page,
        'pageSize' => $limit,
    ]);
}

if ($op === 'feed') {
    $limit = domain_limit($_GET, 60, 100);
    $sinceDays = domain_int($_GET['sinceDays'] ?? 60, 'sinceDays', 1, 365, 60);
    $where = ['a.occurred_at >= DATE_SUB(UTC_TIMESTAMP(3), INTERVAL ' . $sinceDays . ' DAY)'];
    $params = [];
    $cursor = domain_cursor($_GET['cursor'] ?? null);
    if ($cursor !== null) {
        $where[] = 'a.occurred_at > :cursor';
        $params[':cursor'] = $cursor;
    }
    foreach (['kind' => 'a.kind', 'status' => 'a.status', 'city' => 'c.slug'] as $query => $column) {
        $value = domain_string($_GET[$query] ?? null, $query, 0, 191);
        if ($value !== null) {
            $where[] = $column . ' = :' . $query;
            $params[':' . $query] = $value;
        }
    }
    $feedColumns = implode(', ', array_map(
        static fn (string $column): string => 'a.`' . $column . '`',
        representatives_feed_columns()
    ));
    $stmt = $pdo->prepare(
        'SELECT ' . $feedColumns . ', r.name AS representative_name, r.slug AS representative_slug, r.role AS representative_role,
                c.slug AS city_slug
         FROM representative_activities a
         LEFT JOIN representatives r ON r.id = a.representative_id
         JOIN cities c ON c.id = a.city_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY a.occurred_at DESC LIMIT ' . $limit
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    app_success([
        'rows' => $rows,
        'cursor' => $rows !== [] ? (string) $rows[0]['occurred_at'] : $cursor,
    ]);
}

if ($op === 'ranking') {
    $city = domain_string($_GET['city'] ?? null, 'city', 1, 191, false);
    $stmt = $pdo->prepare(representatives_ranking_sql());
    $stmt->execute([':city' => $city]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $stats = [
            'activities_count' => (int) $row['activities_count'],
            'sessions_count' => (int) $row['sessions_count'],
            'absences_count' => (int) $row['absences_count'],
            'attendance_rate' => (int) $row['attendance_rate'],
        ];
        $rows[] = ['representative' => representatives_row($row), ...$stats];
    }
    app_success(['rows' => $rows]);
}

app_error('invalid_op', 'Operação inválida.', 400);
