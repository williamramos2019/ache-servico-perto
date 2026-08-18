<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/domains.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['tourist_attractions']);
$method = app_request_method();
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';

if ($method === 'GET') {
    $admin = $op === 'admin';
    if ($admin) {
        persist_require_admin();
    } elseif (!in_array($op, ['list', 'show'], true)) {
        app_error('invalid_op', 'Operação inválida.', 400);
    }
    $where = [$admin ? '1=1' : 't.is_active = 1'];
    $params = [];
    $city = domain_string($_GET['city'] ?? null, 'city', 0, 191);
    if ($city !== null) {
        $where[] = 'c.slug = :city';
        $params[':city'] = $city;
    }
    $category = domain_string($_GET['category'] ?? null, 'category', 0, 128);
    if ($category !== null) {
        $where[] = 't.category = :category';
        $params[':category'] = $category;
    }
    if ($op === 'show') {
        $key = domain_string($_GET['id'] ?? ($_GET['slug'] ?? null), 'id', 1, 191, false);
        $column = companies_is_uuid($key) ? 't.id' : 't.slug';
        $where[] = $column . ' = :key';
        $params[':key'] = $key;
    }
    $page = domain_page($_GET);
    $limit = $op === 'show' ? 1 : domain_limit($_GET, 24, $admin ? 100 : 50);
    $whereSql = implode(' AND ', $where);
    $total = 1;
    if ($op !== 'show') {
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM tourist_attractions t LEFT JOIN cities c ON c.id = t.city_id WHERE ' . $whereSql
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();
    }
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        'SELECT t.*, c.name AS city_name, c.slug AS city_slug
         FROM tourist_attractions t LEFT JOIN cities c ON c.id = t.city_id
         WHERE ' . $whereSql . '
         ORDER BY t.sort_order, t.title LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $stmt->execute($params);
    $rows = array_map(
        static fn (array $r): array => domain_decode_row($r, [], ['is_active'], ['sort_order']),
        $stmt->fetchAll()
    );
    app_success($op === 'show'
        ? ['attraction' => $rows[0] ?? null]
        : ['rows' => $rows, 'total' => $total, 'page' => $page, 'pageSize' => $limit]);
}

$body = domain_read_json();
$op = is_string($body['op'] ?? null) ? $body['op'] : $op;
domain_require_admin_write();
if ($op === 'save') {
    $id = domain_uuid($body['id'] ?? null, 'id');
    $title = domain_string($body['title'] ?? null, 'title', 2, 255, false);
    $slug = domain_string($body['slug'] ?? null, 'slug', 0, 191) ?? domain_slug($title);
    $fields = [
        'title' => $title,
        'slug' => $slug,
        'description' => domain_string($body['description'] ?? null, 'description', 2, 20000, false),
        'category' => domain_string($body['category'] ?? 'geral', 'category', 1, 128, false),
        'city_id' => domain_uuid($body['city_id'] ?? null, 'city_id'),
        'image_url' => domain_optional_url($body['image_url'] ?? null, 'image_url'),
        'link_url' => domain_optional_url($body['link_url'] ?? null, 'link_url'),
        'meta' => domain_string($body['meta'] ?? null, 'meta', 0, 2000),
        'tag' => domain_string($body['tag'] ?? null, 'tag', 0, 128),
        'sort_order' => domain_int($body['sort_order'] ?? 0, 'sort_order', -10000, 10000),
        'is_active' => domain_bool($body['is_active'] ?? null, true),
    ];
    app_success(['id' => domain_upsert($pdo, 'tourist_attractions', $fields, $id)], $id === null ? 201 : 200);
}
if ($op === 'delete') {
    domain_delete($pdo, 'tourist_attractions', $body['id'] ?? null);
    app_success(['ok' => true]);
}
app_error('invalid_op', 'Operação inválida.', 400);
