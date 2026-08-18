<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/promotions.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['promotions', 'coupons']);
$method = app_request_method();
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';

if ($method === 'GET') {
    $entity = domain_enum($_GET['entity'] ?? 'promotions', 'entity', ['promotions', 'coupons'], 'promotions');
    $admin = $op === 'admin';
    $owner = $op === 'owner';
    if ($admin) {
        persist_require_admin();
    } elseif ($owner) {
        $ownerId = require_auth();
    } elseif (!in_array($op, ['list', 'show'], true)) {
        app_error('invalid_op', 'Operação inválida.', 400);
    }
    $table = $entity;
    $alias = $entity === 'promotions' ? 'p' : 'p';
    $where = [($admin || $owner) ? '1=1' : "p.status = 'published'"];
    if ($owner) {
        $where[] = 'EXISTS (SELECT 1 FROM companies owned WHERE owned.id = p.company_id AND owned.owner_id = :owner_id)';
        $params = [':owner_id' => $ownerId];
    } else {
        $params = [];
    }
    if (!$admin && !$owner) {
        $where[] = '(p.valid_from IS NULL OR p.valid_from <= UTC_TIMESTAMP(3))';
        $where[] = '(p.valid_to IS NULL OR p.valid_to >= UTC_TIMESTAMP(3))';
    }
    foreach (['city_id', 'company_id', 'category'] as $field) {
        $value = domain_string($_GET[$field] ?? null, $field, 0, 191);
        if ($value !== null) {
            $where[] = "p.`$field` = :$field";
            $params[':' . $field] = $value;
        }
    }
    if ($op === 'show') {
        $where[] = 'p.id = :id';
        $params[':id'] = domain_uuid($_GET['id'] ?? null, 'id', false);
    }
    $page = domain_page($_GET);
    $limit = $op === 'show' ? 1 : domain_limit($_GET, 30, ($admin || $owner) ? 100 : 50);
    $whereSql = implode(' AND ', $where);
    $total = 1;
    if ($op !== 'show') {
        $count = $pdo->prepare("SELECT COUNT(*) FROM `$table` p WHERE " . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
    }
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        "SELECT $alias.*, c.name AS company_name, c.slug AS company_slug, ci.name AS city_name, ci.slug AS city_slug
         FROM `$table` $alias LEFT JOIN companies c ON c.id = p.company_id LEFT JOIN cities ci ON ci.id = p.city_id
         WHERE " . $whereSql . '
         ORDER BY ' . ($entity === 'coupons' ? 'p.is_sponsored DESC, ' : '') . 'p.created_at DESC LIMIT ' . $limit .
         ' OFFSET ' . $offset
    );
    $stmt->execute($params);
    $rows = array_map(
        static fn (array $r): array => domain_decode_row($r, [], ['is_sponsored'], ['discount_percent'], ['price_from', 'price_to']),
        $stmt->fetchAll()
    );
    app_success($op === 'show'
        ? ['item' => $rows[0] ?? null]
        : ['rows' => $rows, 'total' => $total, 'page' => $page, 'pageSize' => $limit]);
}

$body = domain_read_json();
$op = is_string($body['op'] ?? null) ? $body['op'] : $op;
$entity = domain_enum($body['entity'] ?? 'promotions', 'entity', ['promotions', 'coupons'], 'promotions');
$id = domain_uuid($body['id'] ?? null, 'id');
$companyId = domain_uuid($body['company_id'] ?? null, 'company_id');
$existingSponsored = false;
if ($id !== null) {
    $select = $entity === 'coupons' ? 'company_id, is_sponsored' : 'company_id';
    $stmt = $pdo->prepare("SELECT $select FROM `$entity` WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();
    if ($existing === false) {
        app_error('not_found', 'Record not found.', 404);
    }
    $companyId = is_string($existing['company_id'] ?? null) ? $existing['company_id'] : null;
    $existingSponsored = persist_bool($existing['is_sponsored'] ?? false);
}
$authorization = promotions_authorize($pdo, $companyId);

if ($op === 'delete') {
    domain_delete($pdo, $entity, $id);
    app_success(['ok' => true]);
}
if ($op !== 'save') {
    app_error('invalid_op', 'Operação inválida.', 400);
}
$discount = isset($body['discount_percent']) && $body['discount_percent'] !== null
    ? domain_int($body['discount_percent'], 'discount_percent', 0, 100)
    : null;
$status = domain_enum($body['status'] ?? 'published', 'status', ['draft', 'published', 'archived'], 'published');
if ($entity === 'promotions') {
    if ($companyId === null) {
        app_error('invalid_company_id', 'company_id is required.', 422);
    }
    $title = domain_string($body['title'] ?? null, 'title', 2, 255, false);
    $fields = [
        'company_id' => $companyId,
        'city_id' => domain_uuid($body['city_id'] ?? null, 'city_id'),
        'slug' => domain_string($body['slug'] ?? null, 'slug', 0, 255) ?? domain_slug($title),
        'title' => $title,
        'description' => domain_string($body['description'] ?? null, 'description', 0, 10000),
        'cover_image' => domain_optional_url($body['cover_image'] ?? null, 'cover_image'),
        'image_url' => domain_optional_url($body['image_url'] ?? null, 'image_url'),
        'link_url' => domain_optional_url($body['link_url'] ?? null, 'link_url'),
        'category' => domain_string($body['category'] ?? null, 'category', 0, 128),
        'discount_percent' => $discount,
        'price_from' => domain_decimal($body['price_from'] ?? null, 'price_from'),
        'price_to' => domain_decimal($body['price_to'] ?? null, 'price_to'),
        'status' => $status,
        'valid_from' => domain_date($body['valid_from'] ?? null, 'valid_from'),
        'valid_to' => domain_date($body['valid_to'] ?? null, 'valid_to'),
    ];
} else {
    $fields = [
        'company_id' => $companyId,
        'city_id' => domain_uuid($body['city_id'] ?? null, 'city_id'),
        'title' => domain_string($body['title'] ?? null, 'title', 2, 255, false),
        'description' => domain_string($body['description'] ?? null, 'description', 0, 10000),
        'code' => strtoupper(domain_string($body['code'] ?? null, 'code', 2, 191, false)),
        'discount_percent' => $discount,
        'discount_label' => domain_string($body['discount_label'] ?? null, 'discount_label', 0, 255),
        'category' => domain_string($body['category'] ?? null, 'category', 0, 128),
        'image_url' => domain_optional_url($body['image_url'] ?? null, 'image_url'),
        'link_url' => domain_optional_url($body['link_url'] ?? null, 'link_url'),
        'terms' => domain_string($body['terms'] ?? null, 'terms', 0, 10000),
        'valid_from' => domain_date($body['valid_from'] ?? null, 'valid_from'),
        'valid_to' => domain_date($body['valid_to'] ?? null, 'valid_to'),
        'is_sponsored' => promotions_sponsored_value(
            $authorization['is_admin'],
            $body['is_sponsored'] ?? null,
            $existingSponsored
        ) ? 1 : 0,
        'status' => $status,
    ];
}
app_success(['id' => domain_upsert($pdo, $entity, $fields, $id)], $id === null ? 201 : 200);
