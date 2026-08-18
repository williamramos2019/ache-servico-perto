<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/shopee.php';

app_start(['GET', 'POST', 'PATCH', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['shopee_products']);
$method = app_request_method();
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';
$columns = implode(', ', array_map(static fn (string $col): string => '`' . $col . '`', shopee_public_columns()));

if ($method === 'GET') {
    if ($op === 'feeds') {
        persist_require_admin();
        app_success(['feeds' => shopee_feeds_from_env()]);
    }

    if ($op === 'categories') {
        $stmt = $pdo->query(
            "SELECT DISTINCT global_category1 AS category
             FROM shopee_products
             WHERE is_active = 1 AND global_category1 IS NOT NULL AND global_category1 <> ''
             ORDER BY global_category1 ASC
             LIMIT 500"
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll();
        app_success([
            'categories' => array_values(array_filter(array_map(
                static fn (array $row): string => (string) $row['category'],
                $rows
            ))),
        ]);
    }

    if ($op === 'featured' || $op === 'strip') {
        $limit = domain_limit($_GET, $op === 'strip' ? 3 : 12, 24);
        $hint = domain_string($_GET['hint'] ?? null, 'hint', 0, 80);
        $where = ['is_active = 1'];
        $params = [];
        if ($op === 'featured' || $hint === null) {
            $where[] = 'is_featured = 1';
        }
        if ($hint !== null) {
            $where[] = '(title LIKE :hint_title OR global_category1 LIKE :hint_cat)';
            $like = '%' . $hint . '%';
            $params[':hint_title'] = $like;
            $params[':hint_cat'] = $like;
        }
        $stmt = $pdo->prepare(
            'SELECT ' . $columns . ' FROM shopee_products WHERE ' . implode(' AND ', $where) .
            ' ORDER BY discount_percentage DESC LIMIT ' . $limit
        );
        $stmt->execute($params);
        $rows = array_map('shopee_public_row', $stmt->fetchAll());
        if ($op === 'strip' && count($rows) < 2) {
            $fallback = $pdo->query(
                'SELECT ' . $columns . ' FROM shopee_products
                 WHERE is_active = 1 AND is_featured = 1
                 ORDER BY discount_percentage DESC LIMIT ' . $limit
            );
            $rows = $fallback === false ? [] : array_map('shopee_public_row', $fallback->fetchAll());
        }
        app_success(['items' => $rows]);
    }

    if ($op === 'admin') {
        persist_require_admin();
    } elseif ($op !== 'list') {
        app_error('invalid_op', 'Operação inválida.', 400);
    }

    $where = [$op === 'admin' ? '1=1' : 'is_active = 1'];
    $params = [];
    $q = domain_string($_GET['q'] ?? null, 'q', 0, 120);
    if ($q !== null) {
        $where[] = 'title LIKE :q';
        $params[':q'] = '%' . $q . '%';
    }
    $category = domain_string($_GET['category'] ?? null, 'category', 0, 191);
    if ($category !== null) {
        $where[] = 'global_category1 = :category';
        $params[':category'] = $category;
    }
    $minDiscount = domain_decimal($_GET['minDiscount'] ?? ($_GET['min_discount'] ?? null), 'minDiscount', 0, 100);
    if ($minDiscount !== null) {
        $where[] = 'discount_percentage >= :min_discount';
        $params[':min_discount'] = $minDiscount;
    }
    $minRating = domain_decimal($_GET['minRating'] ?? ($_GET['min_rating'] ?? null), 'minRating', 0, 5);
    if ($minRating !== null) {
        $where[] = 'item_rating >= :min_rating';
        $params[':min_rating'] = $minRating;
    }
    $sort = domain_enum($_GET['sort'] ?? 'discount', 'sort', ['discount', 'rating', 'price_asc', 'price_desc'], 'discount');
    $page = domain_page($_GET);
    $limit = domain_limit($_GET, 24, $op === 'admin' ? 100 : 48);
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*) FROM shopee_products WHERE ' . $whereSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $offset = ($page - 1) * $limit;
    $select = $op === 'admin' ? '*' : $columns;
    $stmt = $pdo->prepare(
        'SELECT ' . $select . ' FROM shopee_products WHERE ' . $whereSql .
        ' ORDER BY ' . shopee_sort_sql($sort) . ' LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $stmt->execute($params);
    $rows = array_map('shopee_public_row', $stmt->fetchAll());
    app_success(['items' => $rows, 'total' => $total, 'page' => $page, 'pageSize' => $limit]);
}

$body = domain_read_json();
$op = is_string($body['op'] ?? null) ? $body['op'] : $op;
domain_require_admin_write();
if ($op === 'toggle') {
    $id = domain_uuid($body['id'] ?? null, 'id', false);
    $fields = ['updated_at' => gmdate('Y-m-d H:i:s.v')];
    if (array_key_exists('is_featured', $body)) {
        $fields['is_featured'] = domain_bool($body['is_featured']);
    }
    if (array_key_exists('is_active', $body)) {
        $fields['is_active'] = domain_bool($body['is_active']);
    }
    if (count($fields) === 1) {
        app_error('empty_patch', 'Informe is_featured ou is_active.', 422);
    }
    $sets = [];
    $params = [':id' => $id];
    foreach ($fields as $column => $value) {
        $sets[] = '`' . $column . '` = :' . $column;
        $params[':' . $column] = $value;
    }
    $update = $pdo->prepare('UPDATE shopee_products SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $update->execute($params);
    if ($update->rowCount() !== 1) {
        app_error('not_found', 'Produto não encontrado.', 404);
    }
    app_success(['ok' => true]);
}

app_error('invalid_op', 'Operação inválida.', 400);
