<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/persist.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();

$pdo = db_pdo(false);
$method = app_request_method();
$op = isset($_GET['op']) ? (string) $_GET['op'] : '';

if ($method === 'GET') {
    if ($op === 'categories') {
        $stmt = $pdo->query(
            'SELECT slug, name, icon, sort_order FROM listing_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'icon' => $row['icon'] !== null ? (string) $row['icon'] : null,
                'sort_order' => (int) $row['sort_order'],
            ];
        }
        app_success(['categories' => $rows]);
    }

    if ($op === 'search') {
        $where = ['l.status = :status'];
        $params = [':status' => 'ativo'];
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        if ($q !== '') {
            $where[] = 'l.title LIKE :q';
            $params[':q'] = persist_like($q);
        }
        $category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
        if ($category !== '' && $category !== 'todas') {
            $where[] = 'l.category_slug = :category';
            $params[':category'] = $category;
        }
        $cityId = isset($_GET['city_id']) ? trim((string) $_GET['city_id']) : '';
        if ($cityId !== '' && $cityId !== 'todas' && companies_is_uuid($cityId)) {
            $where[] = 'l.city_id = :city_id';
            $params[':city_id'] = strtolower($cityId);
        }
        $condition = isset($_GET['condition']) ? trim((string) $_GET['condition']) : '';
        if (in_array($condition, ['novo', 'seminovo', 'usado'], true)) {
            $where[] = 'l.`condition` = :cond';
            $params[':cond'] = $condition;
        }
        $sort = isset($_GET['sort']) ? (string) $_GET['sort'] : 'recentes';
        $order = 'l.created_at DESC';
        if ($sort === 'preco-asc') {
            $order = 'l.price IS NULL, l.price ASC';
        } elseif ($sort === 'preco-desc') {
            $order = 'l.price IS NULL, l.price DESC';
        }
        $sql = 'SELECT l.* FROM listings l WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' LIMIT 60';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_listing_row($row);
        }
        app_success(['listings' => $rows]);
    }

    if ($op === 'show') {
        $slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
        if ($slug === '') {
            app_error('invalid_slug', 'Slug inválido.', 422);
        }
        $stmt = $pdo->prepare('SELECT * FROM listings WHERE slug = :slug AND status = :status LIMIT 1');
        $stmt->execute([':slug' => $slug, ':status' => 'ativo']);
        $row = $stmt->fetch();
        if ($row === false) {
            app_success(['listing' => null]);
        }
        $hit = $pdo->prepare('UPDATE listings SET views_count = views_count + 1 WHERE id = :id');
        $hit->execute([':id' => $row['id']]);
        $row['views_count'] = (int) $row['views_count'] + 1;
        app_success(['listing' => persist_listing_row($row)]);
    }

    if ($op === 'get') {
        $id = companies_require_uuid($_GET['id'] ?? null, 'invalid_id', 'ID inválido.');
        $stmt = $pdo->prepare('SELECT * FROM listings WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        app_success(['listing' => $row === false ? null : persist_listing_row($row)]);
    }

    if ($op === 'mine') {
        $userId = require_auth();
        $stmt = $pdo->prepare(
            'SELECT * FROM listings WHERE user_id = :user_id AND status <> :removed ORDER BY created_at DESC'
        );
        $stmt->execute([':user_id' => $userId, ':removed' => 'removido']);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_listing_row($row);
        }
        app_success(['listings' => $rows]);
    }

    if ($op === 'seller') {
        $userId = companies_require_uuid($_GET['user_id'] ?? null, 'invalid_id', 'ID inválido.');
        $stmt = $pdo->prepare('SELECT name, avatar_url FROM profiles WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        app_success([
            'profile' => $row === false
                ? null
                : [
                    'name' => $row['name'] !== null ? (string) $row['name'] : null,
                    'avatar_url' => $row['avatar_url'] !== null ? (string) $row['avatar_url'] : null,
                ],
        ]);
    }

    if ($op === 'other') {
        $userId = companies_require_uuid($_GET['user_id'] ?? null, 'invalid_id', 'ID inválido.');
        $exclude = persist_optional_uuid($_GET['exclude_id'] ?? null);
        $sql = 'SELECT * FROM listings WHERE user_id = :user_id AND status = :status';
        $params = [':user_id' => $userId, ':status' => 'ativo'];
        if ($exclude !== null) {
            $sql .= ' AND id <> :exclude';
            $params[':exclude'] = $exclude;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 4';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_listing_row($row);
        }
        app_success(['listings' => $rows]);
    }

    if ($op === 'messages') {
        $userId = require_auth();
        $stmt = $pdo->prepare(
            'SELECT * FROM listing_messages
             WHERE buyer_id = :a OR seller_id = :b
             ORDER BY created_at DESC LIMIT 500'
        );
        $stmt->execute([':a' => $userId, ':b' => $userId]);
        app_success(['messages' => persist_message_rows($stmt->fetchAll())]);
    }

    if ($op === 'thread') {
        $userId = require_auth();
        $listingId = companies_require_uuid($_GET['listing_id'] ?? null, 'invalid_id', 'ID inválido.');
        $buyerId = companies_require_uuid($_GET['buyer_id'] ?? null, 'invalid_buyer', 'ID inválido.');
        $stmt = $pdo->prepare(
            'SELECT * FROM listing_messages
             WHERE listing_id = :listing_id AND buyer_id = :buyer_id
             ORDER BY created_at ASC'
        );
        $stmt->execute([':listing_id' => $listingId, ':buyer_id' => $buyerId]);
        $rows = persist_message_rows($stmt->fetchAll());
        $unread = [];
        foreach ($rows as $row) {
            if ($row['sender_id'] !== $userId && $row['read_at'] === null) {
                $unread[] = $row['id'];
            }
        }
        if ($unread !== []) {
            $now = auth_now();
            $in = implode(',', array_fill(0, count($unread), '?'));
            $upd = $pdo->prepare("UPDATE listing_messages SET read_at = ? WHERE id IN ($in) AND sender_id <> ?");
            $upd->execute(array_merge([$now], $unread, [$userId]));
            foreach ($rows as &$row) {
                if (in_array($row['id'], $unread, true)) {
                    $row['read_at'] = $now;
                }
            }
            unset($row);
        }
        app_success(['messages' => $rows]);
    }

    app_error('invalid_op', 'Operação inválida.', 400);
}

require_csrf();
$body = companies_read_json();
$op = is_string($body['op'] ?? null) ? (string) $body['op'] : $op;
$userId = require_auth();

if ($method === 'DELETE' || $op === 'delete') {
    $id = companies_require_uuid($body['id'] ?? ($_GET['id'] ?? null), 'invalid_id', 'ID inválido.');
    $stmt = $pdo->prepare('DELETE FROM listings WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':id' => $id, ':user_id' => $userId]);
    if ($stmt->rowCount() === 0) {
        app_error('not_found', 'Anúncio não encontrado.', 404);
    }
    app_success(['ok' => true]);
}

if ($op === 'create' || $op === 'update') {
    rate_limit_hit('listing_user', $userId, 20, 3600);
    $title = persist_optional_string($body['title'] ?? null, 120);
    if ($title === null || strlen($title) < 5) {
        app_error('invalid_title', 'Título muito curto.', 422);
    }
    $category = persist_optional_string($body['category_slug'] ?? null, 255);
    if ($category === null) {
        app_error('invalid_category', 'Selecione uma categoria.', 422);
    }
    $cat = $pdo->prepare('SELECT slug FROM listing_categories WHERE slug = :slug AND is_active = 1 LIMIT 1');
    $cat->execute([':slug' => $category]);
    if ($cat->fetch() === false) {
        app_error('invalid_category', 'Categoria inválida.', 422);
    }
    $cityId = persist_optional_uuid($body['city_id'] ?? null, 'invalid_city_id');
    if ($cityId === null) {
        app_error('invalid_city_id', 'Selecione a cidade.', 422);
    }
    $condition = is_string($body['condition'] ?? null) ? (string) $body['condition'] : 'usado';
    if (!in_array($condition, ['novo', 'seminovo', 'usado'], true)) {
        $condition = 'usado';
    }
    $images = $body['images'] ?? [];
    if (!is_array($images)) {
        $images = [];
    }
    $images = array_values(array_filter($images, static fn ($item): bool => is_string($item) && $item !== ''));
    if ($images === [] || count($images) > 6) {
        app_error('invalid_images', 'Adicione de 1 a 6 fotos.', 422);
    }
    $price = null;
    if (isset($body['price']) && $body['price'] !== null && $body['price'] !== '') {
        $price = is_numeric($body['price']) ? (float) $body['price'] : null;
    }
    $now = auth_now();
    $fields = [
        'title' => $title,
        'description' => persist_optional_string($body['description'] ?? null, 2000),
        'price' => $price,
        'condition' => $condition,
        'category_slug' => $category,
        'city_id' => $cityId,
        'neighborhood' => persist_optional_string($body['neighborhood'] ?? null, 80),
        'contact_phone' => persist_optional_string($body['contact_phone'] ?? null, 20),
        'images' => persist_json_encode($images),
        'updated_at' => $now,
    ];

    if ($op === 'update') {
        $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
        $own = $pdo->prepare('SELECT id FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1');
        $own->execute([':id' => $id, ':user_id' => $userId]);
        if ($own->fetch() === false) {
            app_error('not_found', 'Anúncio não encontrado.', 404);
        }
        $set = [];
        $params = [':id' => $id];
        foreach ($fields as $col => $val) {
            $set[] = "`$col` = :$col";
            $params[":$col"] = $val;
        }
        $upd = $pdo->prepare('UPDATE listings SET ' . implode(', ', $set) . ' WHERE id = :id');
        $upd->execute($params);
        app_success(['ok' => true]);
    }

    $base = preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?? 'anuncio';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'anuncio';
    }
    $id = auth_uuid();
    $slug = $base . '-' . substr($id, 0, 8);
    $ins = $pdo->prepare(
        'INSERT INTO listings (id, user_id, slug, title, description, category_slug, city_id, neighborhood, price, `condition`, status, images, contact_phone, views_count, created_at, updated_at)
         VALUES (:id, :user_id, :slug, :title, :description, :category_slug, :city_id, :neighborhood, :price, :condition, :status, :images, :contact_phone, 0, :created_at, :updated_at)'
    );
    $ins->execute([
        ':id' => $id,
        ':user_id' => $userId,
        ':slug' => $slug,
        ':title' => $fields['title'],
        ':description' => $fields['description'],
        ':category_slug' => $fields['category_slug'],
        ':city_id' => $fields['city_id'],
        ':neighborhood' => $fields['neighborhood'],
        ':price' => $fields['price'],
        ':condition' => $fields['condition'],
        ':status' => 'ativo',
        ':images' => $fields['images'],
        ':contact_phone' => $fields['contact_phone'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    app_success(['id' => $id, 'slug' => $slug], 201);
}

if ($op === 'status') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $status = is_string($body['status'] ?? null) ? (string) $body['status'] : '';
    if (!in_array($status, ['ativo', 'vendido', 'pausado', 'removido'], true)) {
        app_error('invalid_status', 'Status inválido.', 422);
    }
    $stmt = $pdo->prepare(
        'UPDATE listings SET status = :status, updated_at = :updated_at WHERE id = :id AND user_id = :user_id'
    );
    $stmt->execute([
        ':status' => $status,
        ':updated_at' => auth_now(),
        ':id' => $id,
        ':user_id' => $userId,
    ]);
    if ($stmt->rowCount() === 0) {
        app_error('not_found', 'Anúncio não encontrado.', 404);
    }
    app_success(['ok' => true]);
}

if ($op === 'message') {
    rate_limit_hit('listing_msg', $userId, 40, 3600);
    $listingId = companies_require_uuid($body['listing_id'] ?? null, 'invalid_id', 'ID inválido.');
    $text = persist_optional_string($body['body'] ?? null, 2000);
    if ($text === null || strlen($text) < 2) {
        app_error('invalid_body', 'Digite uma mensagem.', 422);
    }
    $listing = $pdo->prepare('SELECT id, user_id FROM listings WHERE id = :id LIMIT 1');
    $listing->execute([':id' => $listingId]);
    $row = $listing->fetch();
    if ($row === false) {
        app_error('not_found', 'Anúncio não encontrado.', 404);
    }
    $sellerId = (string) $row['user_id'];
    if ($sellerId === $userId && empty($body['buyer_id'])) {
        app_error('invalid_message', 'Você não pode mandar mensagem no próprio anúncio.', 422);
    }
    $buyerId = persist_optional_uuid($body['buyer_id'] ?? null) ?? $userId;
    if ($userId !== $buyerId && $userId !== $sellerId) {
        app_error('forbidden', 'Você não tem permissão para esta conversa.', 403);
    }
    $ins = $pdo->prepare(
        'INSERT INTO listing_messages (id, listing_id, buyer_id, seller_id, sender_id, body, read_at, created_at)
         VALUES (:id, :listing_id, :buyer_id, :seller_id, :sender_id, :body, NULL, :created_at)'
    );
    $ins->execute([
        ':id' => auth_uuid(),
        ':listing_id' => $listingId,
        ':buyer_id' => $buyerId,
        ':seller_id' => $sellerId,
        ':sender_id' => $userId,
        ':body' => $text,
        ':created_at' => auth_now(),
    ]);
    app_success(['ok' => true], 201);
}

if ($op === 'report') {
    rate_limit_hit('listing_report', $userId, 10, 3600);
    $listingId = companies_require_uuid($body['listing_id'] ?? null, 'invalid_id', 'ID inválido.');
    $reason = persist_optional_string($body['reason'] ?? null, 255);
    if ($reason === null || strlen($reason) < 2) {
        app_error('invalid_reason', 'Informe o motivo.', 422);
    }
    $ins = $pdo->prepare(
        'INSERT INTO listing_reports (id, listing_id, reporter_id, reason, notes, status, created_at)
         VALUES (:id, :listing_id, :reporter_id, :reason, :notes, :status, :created_at)'
    );
    $ins->execute([
        ':id' => auth_uuid(),
        ':listing_id' => $listingId,
        ':reporter_id' => $userId,
        ':reason' => $reason,
        ':notes' => persist_optional_string($body['notes'] ?? null, 2000),
        ':status' => 'aberto',
        ':created_at' => auth_now(),
    ]);
    app_success(['ok' => true], 201);
}

app_error('invalid_op', 'Operação inválida.', 400);

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function persist_message_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (string) $row['id'],
            'listing_id' => (string) $row['listing_id'],
            'buyer_id' => (string) $row['buyer_id'],
            'seller_id' => (string) $row['seller_id'],
            'sender_id' => (string) $row['sender_id'],
            'body' => (string) $row['body'],
            'read_at' => $row['read_at'] !== null ? (string) $row['read_at'] : null,
            'created_at' => (string) $row['created_at'],
        ];
    }

    return $out;
}
