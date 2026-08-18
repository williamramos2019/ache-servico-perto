<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/domains.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['procurements']);
$method = app_request_method();
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';
$statuses = ['open', 'suspended', 'canceled', 'finished', 'unknown', 'published', 'aberto', 'em_andamento'];

if ($method === 'GET') {
    $admin = $op === 'admin';
    if ($admin) {
        persist_require_admin();
    } elseif (!in_array($op, ['list', 'show'], true)) {
        app_error('invalid_op', 'Operação inválida.', 400);
    }
    $where = ['1=1'];
    $params = [];
    $q = domain_string($_GET['q'] ?? null, 'q', 0, 200);
    if ($q !== null) {
        $like = domain_or_like(['p.title', 'p.object', 'p.process_number'], persist_like($q), 'q');
        $where[] = $like['sql'];
        $params = array_merge($params, $like['params']);
    }
    foreach (['city' => 'c.slug', 'modality' => 'p.modality'] as $query => $column) {
        $value = domain_string($_GET[$query] ?? null, $query, 0, 191);
        if ($value !== null) {
            $where[] = $column . ' = :' . $query;
            $params[':' . $query] = $value;
        }
    }
    $status = domain_string($_GET['status'] ?? null, 'status', 0, 32);
    if ($status !== null && $status !== 'all') {
        if (!in_array($status, $statuses, true)) {
            app_error('invalid_status', 'status is invalid.', 422);
        }
        $where[] = 'p.status = :status';
        $params[':status'] = $status;
    }
    if ($op === 'show') {
        $where[] = 'p.id = :id';
        $params[':id'] = domain_uuid($_GET['id'] ?? null, 'id', false);
    }
    $page = domain_page($_GET);
    $limit = $op === 'show' ? 1 : domain_limit($_GET, 20, $admin ? 200 : 50);
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*) FROM procurements p JOIN cities c ON c.id = p.city_id WHERE ' . $whereSql);
    $count->execute($params);
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        'SELECT p.*, c.name AS city_name, c.slug AS city_slug FROM procurements p
         JOIN cities c ON c.id = p.city_id WHERE ' . $whereSql . '
         ORDER BY p.publish_date DESC, p.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $stmt->execute($params);
    $rows = array_map(
        static fn (array $r): array => domain_decode_row($r, ['files'], [], [], ['estimated_value']),
        $stmt->fetchAll()
    );
    app_success($op === 'show'
        ? ['procurement' => $rows[0] ?? null]
        : ['items' => $rows, 'total' => (int) $count->fetchColumn(), 'page' => $page, 'pageSize' => $limit]);
}

$body = domain_read_json();
$op = is_string($body['op'] ?? null) ? $body['op'] : $op;
domain_require_admin_write();
if ($op === 'save') {
    $id = domain_uuid($body['id'] ?? null, 'id');
    $status = domain_enum($body['status'] ?? 'open', 'status', $statuses, 'open');
    $sourceUrl = domain_optional_url($body['source_url'] ?? null, 'source_url');
    if ($sourceUrl === null) {
        app_error('invalid_source_url', 'source_url is required.', 422);
    }
    $fields = [
        'city_id' => domain_uuid($body['city_id'] ?? null, 'city_id', false),
        'source_site' => domain_string($body['source_site'] ?? null, 'source_site', 1, 255, false),
        'source_url' => $sourceUrl,
        'external_id' => domain_string($body['external_id'] ?? null, 'external_id', 0, 255),
        'process_number' => domain_string($body['process_number'] ?? null, 'process_number', 0, 255),
        'modality' => domain_string($body['modality'] ?? null, 'modality', 0, 64),
        'title' => domain_string($body['title'] ?? null, 'title', 2, 500, false),
        'object' => domain_string($body['object'] ?? null, 'object', 0, 20000),
        'agency' => domain_string($body['agency'] ?? null, 'agency', 0, 255),
        'status' => $status,
        'publish_date' => domain_date($body['publish_date'] ?? null, 'publish_date', true),
        'opening_date' => domain_date($body['opening_date'] ?? null, 'opening_date'),
        'deadline_date' => domain_date($body['deadline_date'] ?? null, 'deadline_date'),
        'estimated_value' => domain_decimal($body['estimated_value'] ?? null, 'estimated_value'),
        'files' => domain_json_array($body['files'] ?? [], 'files', 50),
        'raw_excerpt' => domain_string($body['raw_excerpt'] ?? null, 'raw_excerpt', 0, 10000),
        'content_hash' => domain_string($body['content_hash'] ?? null, 'content_hash', 0, 64),
        'scraped_at' => domain_date($body['scraped_at'] ?? null, 'scraped_at') ?? auth_now(),
    ];
    app_success(['id' => domain_upsert($pdo, 'procurements', $fields, $id)], $id === null ? 201 : 200);
}
if ($op === 'delete') {
    domain_delete($pdo, 'procurements', $body['id'] ?? null);
    app_success(['ok' => true]);
}
app_error('invalid_op', 'Operação inválida.', 400);
