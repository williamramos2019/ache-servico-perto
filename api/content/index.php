<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/persist.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();

$pdo = db_pdo(false);
$method = app_request_method();
$op = isset($_GET['op']) ? (string) $_GET['op'] : '';

function persist_show_row(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'event_id' => (string) $row['event_id'],
        'artist_name' => (string) $row['artist_name'],
        'description' => $row['description'] !== null ? (string) $row['description'] : null,
        'start_at' => (string) $row['start_at'],
        'end_at' => $row['end_at'] !== null ? (string) $row['end_at'] : null,
        'stage' => $row['stage'] !== null ? (string) $row['stage'] : null,
        'cover_image' => $row['cover_image'] !== null ? (string) $row['cover_image'] : null,
        'ticket_url' => $row['ticket_url'] !== null ? (string) $row['ticket_url'] : null,
        'ticket_price' => $row['ticket_price'] !== null ? (float) $row['ticket_price'] : null,
        'sort' => (int) $row['sort'],
    ];
}

function persist_blog_public(array $row): array
{
    $tags = persist_json_decode($row['tags'] ?? null);

    return [
        'id' => (string) $row['id'],
        'slug' => (string) $row['slug'],
        'title' => (string) $row['title'],
        'excerpt' => $row['excerpt'] !== null ? (string) $row['excerpt'] : null,
        'content' => $row['content'] !== null ? (string) $row['content'] : null,
        'cover_url' => $row['featured_image'] !== null ? (string) $row['featured_image'] : null,
        'author_name' => $row['author_name'] !== null ? (string) $row['author_name'] : null,
        'published_at' => $row['published_at'] !== null ? (string) $row['published_at'] : null,
        'meta_description' => $row['meta_description'] !== null ? (string) $row['meta_description'] : null,
        'keywords' => is_array($tags) ? $tags : [],
        'meta_title' => $row['meta_title'] !== null ? (string) $row['meta_title'] : null,
        'og_image' => $row['og_image'] !== null ? (string) $row['og_image'] : null,
    ];
}

function persist_service_row(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'name' => (string) $row['name'],
        'subtype' => $row['subtype'] !== null ? (string) $row['subtype'] : null,
        'description' => $row['description'] !== null ? (string) $row['description'] : null,
        'category' => (string) $row['category'],
        'address' => $row['address'] !== null ? (string) $row['address'] : null,
        'neighborhood' => $row['neighborhood'] !== null ? (string) $row['neighborhood'] : null,
        'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
        'phone_secondary' => $row['phone_secondary'] !== null ? (string) $row['phone_secondary'] : null,
        'whatsapp' => $row['whatsapp'] !== null ? (string) $row['whatsapp'] : null,
        'email' => $row['email'] !== null ? (string) $row['email'] : null,
        'website' => $row['website'] !== null ? (string) $row['website'] : null,
        'hours' => $row['hours'] !== null ? (string) $row['hours'] : null,
        'is_24h' => persist_bool($row['is_24h'] ?? 0),
        'lat' => $row['lat'] !== null ? (float) $row['lat'] : null,
        'lng' => $row['lng'] !== null ? (float) $row['lng'] : null,
        'active' => persist_bool($row['active'] ?? 0),
        'featured' => persist_bool($row['featured'] ?? 0),
        'city_id' => (string) $row['city_id'],
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
        'cities' => persist_city_embed($row),
    ];
}

function persist_emergency_row(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'name' => (string) $row['name'],
        'phone' => (string) $row['phone'],
        'description' => $row['description'] !== null ? (string) $row['description'] : null,
        'icon' => $row['icon'] !== null ? (string) $row['icon'] : null,
        'sort_order' => (int) $row['sort_order'],
        'active' => persist_bool($row['active'] ?? 0),
        'city_id' => $row['city_id'] !== null ? (string) $row['city_id'] : null,
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
        'cities' => persist_city_embed($row),
    ];
}

if ($method === 'GET') {
    if ($op === 'setting') {
        $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
        if (!in_array($key, ['site_content', 'nav_items'], true)) {
            app_error('invalid_key', 'Chave inválida.', 422);
        }
        app_success(['value' => persist_setting_get($pdo, $key)]);
    }

    if ($op === 'events') {
        $sql = 'SELECT * FROM events WHERE status = :status';
        $params = [':status' => 'published'];
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        if ($q !== '') {
            $sql .= ' AND title LIKE :q';
            $params[':q'] = persist_like($q);
        }
        $catSlug = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
        if ($catSlug !== '') {
            $sql .= ' AND category_id = (SELECT id FROM event_categories WHERE slug = :cslug LIMIT 1)';
            $params[':cslug'] = $catSlug;
        }
        $sql .= ' ORDER BY start_at ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_event_row($row);
        }
        app_success(['events' => $rows]);
    }

    if ($op === 'event') {
        $slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
        $stmt = $pdo->prepare('SELECT * FROM events WHERE slug = :slug AND status = :status LIMIT 1');
        $stmt->execute([':slug' => $slug, ':status' => 'published']);
        $row = $stmt->fetch();
        app_success(['event' => $row === false ? null : persist_event_row($row)]);
    }

    if ($op === 'shows') {
        $eventId = companies_require_uuid($_GET['event_id'] ?? null, 'invalid_id', 'ID inválido.');
        $stmt = $pdo->prepare('SELECT * FROM shows WHERE event_id = :id ORDER BY start_at ASC, sort ASC');
        $stmt->execute([':id' => $eventId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_show_row($row);
        }
        app_success(['shows' => $rows]);
    }

    if ($op === 'event_categories') {
        $stmt = $pdo->query('SELECT id, slug, name, icon, sort FROM event_categories ORDER BY sort ASC');
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'icon' => $row['icon'] !== null ? (string) $row['icon'] : null,
                'sort' => (int) $row['sort'],
            ];
        }
        app_success(['categories' => $rows]);
    }

    if ($op === 'posts') {
        $stmt = $pdo->query(
            "SELECT id, slug, title, excerpt, featured_image, author_name, published_at, meta_description, tags, meta_title, og_image, content
             FROM posts WHERE type = 'blog' AND status = 'published' ORDER BY published_at DESC"
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_blog_public($row);
        }
        app_success(['posts' => $rows]);
    }

    if ($op === 'post') {
        $slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
        $stmt = $pdo->prepare(
            "SELECT * FROM posts WHERE slug = :slug AND type = 'blog' AND status = 'published' LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        app_success(['post' => $row === false ? null : persist_blog_public($row)]);
    }

    if ($op === 'public_services') {
        $sql = 'SELECT ps.*, ci.name AS city_name, ci.slug AS city_slug
                FROM public_services ps
                LEFT JOIN cities ci ON ci.id = ps.city_id
                WHERE ps.active = 1';
        $params = [];
        $citySlug = isset($_GET['city']) ? trim((string) $_GET['city']) : '';
        if ($citySlug !== '') {
            $sql .= ' AND ci.slug = :city';
            $params[':city'] = $citySlug;
        }
        $category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
        if ($category !== '') {
            $sql .= ' AND ps.category = :category';
            $params[':category'] = $category;
        }
        $sql .= ' ORDER BY ps.featured DESC, ps.name ASC';
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;
        if ($limit > 0) {
            $sql .= ' LIMIT ' . min(200, $limit);
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_service_row($row);
        }
        app_success(['services' => $rows]);
    }

    if ($op === 'public_service') {
        $id = companies_require_uuid($_GET['id'] ?? null, 'invalid_id', 'ID inválido.');
        $stmt = $pdo->prepare(
            'SELECT ps.*, ci.name AS city_name, ci.slug AS city_slug
             FROM public_services ps LEFT JOIN cities ci ON ci.id = ps.city_id
             WHERE ps.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        app_success(['service' => $row === false ? null : persist_service_row($row)]);
    }

    if ($op === 'emergency') {
        $citySlug = isset($_GET['city']) ? trim((string) $_GET['city']) : '';
        $sql = 'SELECT e.*, ci.name AS city_name, ci.slug AS city_slug
                FROM emergency_contacts e
                LEFT JOIN cities ci ON ci.id = e.city_id
                WHERE e.active = 1';
        $params = [];
        if ($citySlug !== '') {
            $sql .= ' AND (ci.slug = :city OR e.city_id IS NULL)';
            $params[':city'] = $citySlug;
        } else {
            $sql .= ' AND e.city_id IS NULL';
        }
        $sql .= ' ORDER BY e.sort_order ASC, e.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_emergency_row($row);
        }
        app_success(['contacts' => $rows]);
    }

    persist_require_admin();

    if ($op === 'events_admin') {
        $stmt = $pdo->query('SELECT * FROM events ORDER BY start_at DESC');
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_event_row($row);
        }
        app_success(['events' => $rows]);
    }

    if ($op === 'posts_admin') {
        $stmt = $pdo->query("SELECT * FROM posts WHERE type = 'blog' ORDER BY created_at DESC");
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $pub = persist_blog_public($row);
            $pub['published'] = (string) $row['status'] === 'published';
            $pub['created_at'] = (string) $row['created_at'];
            $pub['status'] = (string) $row['status'];
            $rows[] = $pub;
        }
        app_success(['posts' => $rows]);
    }

    if ($op === 'public_services_admin') {
        $stmt = $pdo->query(
            'SELECT ps.*, ci.name AS city_name, ci.slug AS city_slug
             FROM public_services ps LEFT JOIN cities ci ON ci.id = ps.city_id
             ORDER BY ps.created_at DESC'
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_service_row($row);
        }
        app_success(['services' => $rows]);
    }

    if ($op === 'emergency_admin') {
        $stmt = $pdo->query(
            'SELECT e.*, ci.name AS city_name, ci.slug AS city_slug
             FROM emergency_contacts e LEFT JOIN cities ci ON ci.id = e.city_id
             ORDER BY e.sort_order ASC, e.name ASC'
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = persist_emergency_row($row);
        }
        app_success(['contacts' => $rows]);
    }

    app_error('invalid_op', 'Operação inválida.', 400);
}

require_csrf();
$body = companies_read_json();
$op = is_string($body['op'] ?? null) ? (string) $body['op'] : $op;
$userId = persist_require_admin();
$now = auth_now();

if ($op === 'setting_save') {
    $key = is_string($body['key'] ?? null) ? (string) $body['key'] : '';
    if (!in_array($key, ['site_content', 'nav_items'], true)) {
        app_error('invalid_key', 'Chave inválida.', 422);
    }
    persist_setting_upsert($pdo, $key, $body['value'] ?? null, true);
    app_success(['ok' => true]);
}

if ($op === 'event_save') {
    $title = persist_optional_string($body['title'] ?? null, 255);
    if ($title === null) {
        app_error('invalid_title', 'Título é obrigatório.', 422);
    }
    $start = persist_optional_string($body['start_at'] ?? null, 40);
    if ($start === null) {
        app_error('invalid_start', 'Data/hora de início é obrigatória.', 422);
    }
    $slug = persist_optional_string($body['slug'] ?? null, 255);
    if ($slug === null) {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?: 'evento';
        $slug = trim((string) $slug, '-');
    }
    $fields = [
        'slug' => $slug,
        'title' => $title,
        'description' => persist_optional_string($body['description'] ?? null, 20000),
        'cover_image' => persist_optional_string($body['cover_image'] ?? null, 2048),
        'location' => persist_optional_string($body['location'] ?? null, 2000),
        'start_at' => $start,
        'end_at' => persist_optional_string($body['end_at'] ?? null, 40),
        'status' => persist_optional_string($body['status'] ?? 'draft', 16) ?? 'draft',
        'event_type' => persist_optional_string($body['event_type'] ?? null, 64),
        'category_id' => persist_optional_uuid($body['category_id'] ?? null),
        'ticket_url' => persist_optional_string($body['ticket_url'] ?? null, 2048),
        'price_min' => isset($body['price_min']) && $body['price_min'] !== null && $body['price_min'] !== '' ? (float) $body['price_min'] : null,
        'price_max' => isset($body['price_max']) && $body['price_max'] !== null && $body['price_max'] !== '' ? (float) $body['price_max'] : null,
        'updated_at' => $now,
    ];
    if (!empty($body['id'])) {
        $id = companies_require_uuid($body['id'], 'invalid_id', 'ID inválido.');
        $set = [];
        $params = [':id' => $id];
        foreach ($fields as $col => $val) {
            $set[] = "`$col` = :$col";
            $params[":$col"] = $val;
        }
        $pdo->prepare('UPDATE events SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
        app_success(['id' => $id]);
    }
    $id = auth_uuid();
    $ins = $pdo->prepare(
        'INSERT INTO events (id, slug, title, description, cover_image, start_at, end_at, location, category_id, event_type, ticket_url, price_min, price_max, status, created_by, created_at, updated_at)
         VALUES (:id, :slug, :title, :description, :cover_image, :start_at, :end_at, :location, :category_id, :event_type, :ticket_url, :price_min, :price_max, :status, :created_by, :created_at, :updated_at)'
    );
    $ins->execute([
        ':id' => $id,
        ':slug' => $fields['slug'],
        ':title' => $fields['title'],
        ':description' => $fields['description'],
        ':cover_image' => $fields['cover_image'],
        ':start_at' => $fields['start_at'],
        ':end_at' => $fields['end_at'],
        ':location' => $fields['location'],
        ':category_id' => $fields['category_id'],
        ':event_type' => $fields['event_type'],
        ':ticket_url' => $fields['ticket_url'],
        ':price_min' => $fields['price_min'],
        ':price_max' => $fields['price_max'],
        ':status' => $fields['status'],
        ':created_by' => $userId,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    app_success(['id' => $id], 201);
}

if ($op === 'event_delete') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $pdo->prepare('DELETE FROM events WHERE id = :id')->execute([':id' => $id]);
    app_success(['ok' => true]);
}

if ($op === 'show_save') {
    $eventId = companies_require_uuid($body['event_id'] ?? null, 'invalid_id', 'ID inválido.');
    $artist = persist_optional_string($body['artist_name'] ?? null, 255);
    $start = persist_optional_string($body['start_at'] ?? null, 40);
    if ($artist === null || $start === null) {
        app_error('invalid_input', 'Artista e horário são obrigatórios.', 422);
    }
    $fields = [
        'event_id' => $eventId,
        'artist_name' => $artist,
        'description' => persist_optional_string($body['description'] ?? null, 4000),
        'start_at' => $start,
        'end_at' => persist_optional_string($body['end_at'] ?? null, 40),
        'stage' => persist_optional_string($body['stage'] ?? null, 255),
        'cover_image' => persist_optional_string($body['cover_image'] ?? null, 2048),
        'ticket_url' => persist_optional_string($body['ticket_url'] ?? null, 2048),
        'ticket_price' => isset($body['ticket_price']) && $body['ticket_price'] !== null && $body['ticket_price'] !== '' ? (float) $body['ticket_price'] : null,
        'sort' => isset($body['sort']) ? (int) $body['sort'] : 0,
        'updated_at' => $now,
    ];
    if (!empty($body['id'])) {
        $id = companies_require_uuid($body['id'], 'invalid_id', 'ID inválido.');
        $set = [];
        $params = [':id' => $id];
        foreach ($fields as $col => $val) {
            $set[] = "`$col` = :$col";
            $params[":$col"] = $val;
        }
        $pdo->prepare('UPDATE shows SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
        app_success(['id' => $id]);
    }
    $id = auth_uuid();
    $ins = $pdo->prepare(
        'INSERT INTO shows (id, event_id, artist_name, cover_image, description, start_at, end_at, stage, sort, ticket_price, ticket_url, created_at, updated_at)
         VALUES (:id, :event_id, :artist_name, :cover_image, :description, :start_at, :end_at, :stage, :sort, :ticket_price, :ticket_url, :created_at, :updated_at)'
    );
    $ins->execute([
        ':id' => $id,
        ':event_id' => $fields['event_id'],
        ':artist_name' => $fields['artist_name'],
        ':cover_image' => $fields['cover_image'],
        ':description' => $fields['description'],
        ':start_at' => $fields['start_at'],
        ':end_at' => $fields['end_at'],
        ':stage' => $fields['stage'],
        ':sort' => $fields['sort'],
        ':ticket_price' => $fields['ticket_price'],
        ':ticket_url' => $fields['ticket_url'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    app_success(['id' => $id], 201);
}

if ($op === 'show_delete') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $pdo->prepare('DELETE FROM shows WHERE id = :id')->execute([':id' => $id]);
    app_success(['ok' => true]);
}

if ($op === 'post_save') {
    $title = persist_optional_string($body['title'] ?? null, 255);
    $slug = persist_optional_string($body['slug'] ?? null, 255);
    if ($title === null || $slug === null) {
        app_error('invalid_input', 'Título e slug são obrigatórios.', 422);
    }
    $published = !empty($body['published']) || (($body['status'] ?? '') === 'published');
    $fields = [
        'type' => 'blog',
        'slug' => $slug,
        'title' => $title,
        'excerpt' => persist_optional_string($body['excerpt'] ?? null, 4000),
        'content' => persist_optional_string($body['content'] ?? null, 500000),
        'featured_image' => persist_optional_string($body['cover_url'] ?? ($body['featured_image'] ?? null), 2048),
        'author_name' => persist_optional_string($body['author_name'] ?? null, 255) ?? 'Equipe AgendaAqui',
        'status' => $published ? 'published' : 'draft',
        'published_at' => $published ? (persist_optional_string($body['published_at'] ?? null, 40) ?? $now) : persist_optional_string($body['published_at'] ?? null, 40),
        'meta_title' => persist_optional_string($body['meta_title'] ?? null, 255),
        'meta_description' => persist_optional_string($body['meta_description'] ?? null, 400),
        'og_image' => persist_optional_string($body['og_image'] ?? ($body['cover_url'] ?? null), 2048),
        'tags' => persist_json_encode($body['keywords'] ?? ($body['tags'] ?? [])),
        'author_id' => $userId,
        'updated_at' => $now,
    ];
    if (!empty($body['id'])) {
        $id = companies_require_uuid($body['id'], 'invalid_id', 'ID inválido.');
        $set = [];
        $params = [':id' => $id];
        foreach ($fields as $col => $val) {
            $set[] = "`$col` = :$col";
            $params[":$col"] = $val;
        }
        $pdo->prepare('UPDATE posts SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
        app_success(['id' => $id]);
    }
    $id = auth_uuid();
    $ins = $pdo->prepare(
        'INSERT INTO posts (id, type, status, slug, title, excerpt, content, featured_image, tags, meta_title, meta_description, og_image, author_id, author_name, published_at, created_at, updated_at)
         VALUES (:id, :type, :status, :slug, :title, :excerpt, :content, :featured_image, :tags, :meta_title, :meta_description, :og_image, :author_id, :author_name, :published_at, :created_at, :updated_at)'
    );
    $ins->execute([
        ':id' => $id,
        ':type' => 'blog',
        ':status' => $fields['status'],
        ':slug' => $fields['slug'],
        ':title' => $fields['title'],
        ':excerpt' => $fields['excerpt'],
        ':content' => $fields['content'],
        ':featured_image' => $fields['featured_image'],
        ':tags' => $fields['tags'],
        ':meta_title' => $fields['meta_title'],
        ':meta_description' => $fields['meta_description'],
        ':og_image' => $fields['og_image'],
        ':author_id' => $userId,
        ':author_name' => $fields['author_name'],
        ':published_at' => $fields['published_at'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    app_success(['id' => $id], 201);
}

if ($op === 'post_delete') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $pdo->prepare('DELETE FROM posts WHERE id = :id')->execute([':id' => $id]);
    app_success(['ok' => true]);
}

if ($op === 'service_save' || $op === 'emergency_save') {
    $table = $op === 'service_save' ? 'public_services' : 'emergency_contacts';
    $id = persist_optional_uuid($body['id'] ?? null);
    unset($body['op'], $body['cities']);
    if ($table === 'public_services') {
        $allowed = [
            'city_id', 'name', 'category', 'subtype', 'description', 'address', 'neighborhood',
            'phone', 'phone_secondary', 'whatsapp', 'email', 'website', 'hours',
            'lat', 'lng', 'featured', 'is_24h', 'active',
        ];
        $name = persist_optional_string($body['name'] ?? null, 255);
        $cityId = persist_optional_uuid($body['city_id'] ?? null, 'invalid_city_id');
        $category = persist_optional_string($body['category'] ?? null, 32);
        if ($name === null || $cityId === null || $category === null) {
            app_error('invalid_input', 'Nome, cidade e categoria são obrigatórios.', 422);
        }
        $row = [
            'city_id' => $cityId,
            'name' => $name,
            'category' => $category,
            'subtype' => persist_optional_string($body['subtype'] ?? null, 64),
            'description' => persist_optional_string($body['description'] ?? null, 8000),
            'address' => persist_optional_string($body['address'] ?? null, 2000),
            'neighborhood' => persist_optional_string($body['neighborhood'] ?? null, 255),
            'phone' => persist_optional_string($body['phone'] ?? null, 64),
            'phone_secondary' => persist_optional_string($body['phone_secondary'] ?? null, 64),
            'whatsapp' => persist_optional_string($body['whatsapp'] ?? null, 64),
            'email' => persist_optional_string($body['email'] ?? null, 255),
            'website' => persist_optional_string($body['website'] ?? null, 2048),
            'hours' => persist_optional_string($body['hours'] ?? null, 2000),
            'lat' => isset($body['lat']) && $body['lat'] !== null && $body['lat'] !== '' ? (float) $body['lat'] : null,
            'lng' => isset($body['lng']) && $body['lng'] !== null && $body['lng'] !== '' ? (float) $body['lng'] : null,
            'featured' => !empty($body['featured']) ? 1 : 0,
            'is_24h' => !empty($body['is_24h']) ? 1 : 0,
            'active' => array_key_exists('active', $body) ? (!empty($body['active']) ? 1 : 0) : 1,
            'updated_at' => $now,
        ];
    } else {
        $name = persist_optional_string($body['name'] ?? null, 255);
        $phone = persist_optional_string($body['phone'] ?? null, 64);
        if ($name === null || $phone === null) {
            app_error('invalid_input', 'Nome e telefone são obrigatórios.', 422);
        }
        $row = [
            'city_id' => persist_optional_uuid($body['city_id'] ?? null),
            'name' => $name,
            'phone' => $phone,
            'description' => persist_optional_string($body['description'] ?? null, 2000),
            'icon' => persist_optional_string($body['icon'] ?? null, 2000),
            'sort_order' => isset($body['sort_order']) ? (int) $body['sort_order'] : 0,
            'active' => array_key_exists('active', $body) ? (!empty($body['active']) ? 1 : 0) : 1,
            'updated_at' => $now,
        ];
    }
    if ($id !== null) {
        $set = [];
        $params = [':id' => $id];
        foreach ($row as $col => $val) {
            $set[] = "`$col` = :$col";
            $params[":$col"] = $val;
        }
        $pdo->prepare("UPDATE `$table` SET " . implode(', ', $set) . ' WHERE id = :id')->execute($params);
        app_success(['id' => $id]);
    }
    $id = auth_uuid();
    $cols = array_keys($row);
    $cols[] = 'id';
    $cols[] = 'created_at';
    $row['id'] = $id;
    $row['created_at'] = $now;
    $placeholders = [];
    $params = [];
    foreach ($row as $col => $val) {
        $placeholders[] = ":$col";
        $params[":$col"] = $val;
    }
    $pdo->prepare(
        "INSERT INTO `$table` (`" . implode('`, `', array_keys($row)) . '`) VALUES (' . implode(', ', $placeholders) . ')'
    )->execute($params);
    app_success(['id' => $id], 201);
}

if ($op === 'service_delete' || $op === 'emergency_delete') {
    $table = $op === 'service_delete' ? 'public_services' : 'emergency_contacts';
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $pdo->prepare("DELETE FROM `$table` WHERE id = :id")->execute([':id' => $id]);
    app_success(['ok' => true]);
}

app_error('invalid_op', 'Operação inválida.', 400);
