<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/persist.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();

$pdo = db_pdo(false);
$method = app_request_method();
$op = isset($_GET['op']) ? (string) $_GET['op'] : '';

if ($method === 'GET' && $op === 'nearest') {
    $lat = isset($_GET['lat']) ? (float) $_GET['lat'] : 0.0;
    $lng = isset($_GET['lng']) ? (float) $_GET['lng'] : 0.0;
    if ($lat === 0.0 && $lng === 0.0) {
        app_success(['slug' => null, 'name' => null]);
    }
    $stmt = $pdo->prepare(
        'SELECT name, slug,
                (6371 * ACOS(LEAST(1, COS(RADIANS(:lat1)) * COS(RADIANS(lat)) * COS(RADIANS(lng) - RADIANS(:lng))
                    + SIN(RADIANS(:lat2)) * SIN(RADIANS(lat))))) AS distance_km
         FROM cities
         WHERE is_active = 1 AND lat IS NOT NULL AND lng IS NOT NULL
         ORDER BY distance_km ASC
         LIMIT 1'
    );
    $stmt->execute([':lat1' => $lat, ':lng' => $lng, ':lat2' => $lat]);
    $row = $stmt->fetch();
    if ($row === false) {
        app_success(['slug' => null, 'name' => null]);
    }
    app_success([
        'slug' => (string) $row['slug'],
        'name' => (string) $row['name'],
        'distance_km' => $row['distance_km'] !== null ? (float) $row['distance_km'] : null,
    ]);
}

if ($method === 'GET' && $op === 'ranking') {
    require_auth();
    $stmt = $pdo->query(
        "SELECT c.id AS company_id, c.name, c.slug, c.logo_url, c.city_id,
                c.views_count AS visits, c.review_count AS reviews, c.rating AS avg_rating,
                (SELECT COUNT(*) FROM leads l WHERE l.company_id = c.id
                   AND l.created_at >= DATE_SUB(UTC_TIMESTAMP(3), INTERVAL 7 DAY)) AS activity
         FROM companies c
         WHERE c.status = 'active'
         ORDER BY (c.views_count + c.review_count * 10 + c.rating * 20) DESC
         LIMIT 20"
    );
    $rows = [];
    $pos = 1;
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'rank_position' => $pos,
            'company_id' => (string) $row['company_id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'logo_url' => $row['logo_url'] !== null ? (string) $row['logo_url'] : null,
            'city_id' => $row['city_id'] !== null ? (string) $row['city_id'] : null,
            'visits' => (int) $row['visits'],
            'activity' => (int) $row['activity'],
            'reviews' => (int) $row['reviews'],
            'avg_rating' => (float) $row['avg_rating'],
            'score' => (int) $row['visits'] + (int) $row['reviews'] * 10 + (float) $row['avg_rating'] * 20,
            'is_self' => false,
        ];
        $pos++;
    }
    app_success(['ok' => true, 'rows' => $rows]);
}

if ($method === 'GET') {
    persist_require_admin();

    if ($op === 'stats') {
        $since = (new DateTimeImmutable('-7 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
        $count = static function (PDO $pdo, string $sql, array $params = []): int {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        };
        app_success([
            'total' => $count($pdo, 'SELECT COUNT(*) FROM companies'),
            'free' => $count($pdo, 'SELECT COUNT(*) FROM companies WHERE plan = :p', [':p' => 'free']),
            'premium' => $count($pdo, 'SELECT COUNT(*) FROM companies WHERE plan = :p', [':p' => 'premium']),
            'featured' => $count($pdo, 'SELECT COUNT(*) FROM companies WHERE featured = 1'),
            'recent7d' => $count($pdo, 'SELECT COUNT(*) FROM companies WHERE created_at >= :s', [':s' => $since]),
            'views' => $count($pdo, 'SELECT COUNT(*) FROM company_views'),
        ]);
    }

    if ($op === 'companies') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = min(200, max(10, (int) ($_GET['pageSize'] ?? 50)));
        $offset = ($page - 1) * $pageSize;
        $where = ['1=1'];
        $params = [];
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        if ($q !== '') {
            $where[] = 'c.name LIKE :q';
            $params[':q'] = persist_like($q);
        }
        $plan = isset($_GET['plan']) ? (string) $_GET['plan'] : '';
        if ($plan !== '' && $plan !== 'all') {
            $where[] = 'c.plan = :plan';
            $params[':plan'] = $plan;
        }
        $featured = isset($_GET['featured']) ? (string) $_GET['featured'] : 'all';
        if ($featured === 'yes') {
            $where[] = 'c.featured = 1';
        } elseif ($featured === 'no') {
            $where[] = 'c.featured = 0';
        }
        $sqlWhere = implode(' AND ', $where);
        $total = $pdo->prepare("SELECT COUNT(*) FROM companies c WHERE $sqlWhere");
        $total->execute($params);
        $stmt = $pdo->prepare(
            "SELECT c.id, c.name, c.slug, c.plan, c.featured, c.is_verified, c.status, c.city_id, c.created_at, c.email, c.phone, ci.name AS city_name
             FROM companies c LEFT JOIN cities ci ON ci.id = c.city_id
             WHERE $sqlWhere
             ORDER BY c.created_at DESC
             LIMIT $pageSize OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'plan' => $row['plan'] !== null ? (string) $row['plan'] : null,
                'featured' => persist_bool($row['featured']),
                'is_verified' => persist_bool($row['is_verified']),
                'status' => $row['status'] !== null ? (string) $row['status'] : null,
                'city_id' => $row['city_id'] !== null ? (string) $row['city_id'] : null,
                'cities' => $row['city_name'] !== null ? ['name' => (string) $row['city_name']] : null,
                'created_at' => (string) $row['created_at'],
                'email' => $row['email'] !== null ? (string) $row['email'] : null,
                'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
            ];
        }
        app_success(['rows' => $rows, 'total' => (int) $total->fetchColumn(), 'page' => $page, 'pageSize' => $pageSize]);
    }

    if ($op === 'imports') {
        try {
            $stmt = $pdo->query(
                'SELECT id, city_slug, source, dry_run, status, started_at, finished_at,
                        total_collected, total_valid, total_inserted, total_updated,
                        total_duplicates, total_rejected, total_skipped, error_message, importer_version
                 FROM company_import_runs
                 ORDER BY started_at DESC
                 LIMIT 50'
            );
            $runs = [];
            foreach ($stmt->fetchAll() as $row) {
                $errStmt = $pdo->prepare(
                    'SELECT error_type, error_message, company_name, created_at
                     FROM company_import_errors WHERE run_id = :id
                     ORDER BY created_at DESC LIMIT 20'
                );
                $errStmt->execute([':id' => (string) $row['id']]);
                $errors = [];
                foreach ($errStmt->fetchAll() as $err) {
                    $errors[] = [
                        'error_type' => (string) $err['error_type'],
                        'error_message' => (string) $err['error_message'],
                        'company_name' => $err['company_name'] !== null ? (string) $err['company_name'] : null,
                        'created_at' => (string) $err['created_at'],
                    ];
                }
                $runs[] = [
                    'id' => (string) $row['id'],
                    'city_slug' => (string) $row['city_slug'],
                    'source' => (string) $row['source'],
                    'dry_run' => persist_bool($row['dry_run']),
                    'status' => (string) $row['status'],
                    'started_at' => (string) $row['started_at'],
                    'finished_at' => $row['finished_at'] !== null ? (string) $row['finished_at'] : null,
                    'total_collected' => (int) $row['total_collected'],
                    'total_valid' => (int) $row['total_valid'],
                    'total_inserted' => (int) $row['total_inserted'],
                    'total_updated' => (int) $row['total_updated'],
                    'total_duplicates' => (int) $row['total_duplicates'],
                    'total_rejected' => (int) $row['total_rejected'],
                    'total_skipped' => (int) $row['total_skipped'],
                    'error_message' => $row['error_message'] !== null ? (string) $row['error_message'] : null,
                    'importer_version' => (string) $row['importer_version'],
                    'errors' => $errors,
                ];
            }
            app_success(['runs' => $runs]);
        } catch (Throwable $e) {
            app_success(['runs' => [], 'not_migrated' => true]);
        }
    }

    if ($op === 'cities') {
        $stmt = $pdo->query('SELECT * FROM cities ORDER BY name ASC');
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['featured_category_ids'] = persist_json_decode($row['featured_category_ids']);
            $row['is_active'] = persist_bool($row['is_active']);
            $row['lat'] = $row['lat'] !== null ? (float) $row['lat'] : null;
            $row['lng'] = $row['lng'] !== null ? (float) $row['lng'] : null;
            $rows[] = $row;
        }
        app_success(['cities' => $rows]);
    }

    if ($op === 'plans') {
        $stmt = $pdo->query('SELECT * FROM plans_config ORDER BY sort ASC');
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['features'] = persist_json_decode($row['features']);
            $rows[] = $row;
        }
        app_success(['plans' => $rows]);
    }

    if ($op === 'settings') {
        $stmt = $pdo->query('SELECT `key`, `value`, is_public, updated_at FROM system_settings ORDER BY `key` ASC');
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'key' => (string) $row['key'],
                'value' => persist_json_decode($row['value']),
                'is_public' => persist_bool($row['is_public']),
                'updated_at' => (string) $row['updated_at'],
            ];
        }
        app_success(['settings' => $rows]);
    }

    if ($op === 'leads') {
        $stmt = $pdo->query(
            'SELECT l.*, c.name AS company_name
             FROM leads l LEFT JOIN companies c ON c.id = l.company_id
             ORDER BY l.created_at DESC LIMIT 200'
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['companies'] = $row['company_name'] !== null ? ['name' => (string) $row['company_name']] : null;
            unset($row['company_name']);
            $rows[] = $row;
        }
        app_success(['leads' => $rows]);
    }

    if ($op === 'plan_leads') {
        $stmt = $pdo->query('SELECT * FROM leads_planos ORDER BY created_at DESC LIMIT 200');
        app_success(['leads' => $stmt->fetchAll()]);
    }

    if ($op === 'scan_texts') {
        $posts = $pdo->query("SELECT id, slug, title, excerpt, content, status FROM posts WHERE type = 'blog'")->fetchAll();
        $companies = $pdo->query('SELECT id, slug, name, description FROM companies WHERE description IS NOT NULL')->fetchAll();
        $events = $pdo->query('SELECT id, slug, title, description FROM events WHERE description IS NOT NULL')->fetchAll();
        app_success(['posts' => $posts, 'companies' => $companies, 'events' => $events]);
    }

    app_error('invalid_op', 'Operação inválida.', 400);
}

require_csrf();
$body = companies_read_json();
$op = is_string($body['op'] ?? null) ? (string) $body['op'] : $op;
persist_require_admin();
$now = auth_now();

if ($op === 'company_update') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $allowed = ['plan', 'featured', 'is_verified', 'status', 'name'];
    $set = ['updated_at = :updated_at'];
    $params = [':id' => $id, ':updated_at' => $now];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $body)) {
            continue;
        }
        $val = $body[$col];
        if ($col === 'featured' || $col === 'is_verified') {
            $val = !empty($val) ? 1 : 0;
        }
        $set[] = "`$col` = :$col";
        $params[":$col"] = $val;
    }
    if (count($set) === 1) {
        app_error('invalid_input', 'Nada para atualizar.', 422);
    }
    $pdo->prepare('UPDATE companies SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
    app_success(['ok' => true]);
}

if ($op === 'company_delete') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $pdo->prepare('DELETE FROM companies WHERE id = :id')->execute([':id' => $id]);
    app_success(['ok' => true]);
}

if ($op === 'city_update') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $allowed = [
        'name', 'slug', 'state', 'lat', 'lng', 'hero_title', 'hero_subtitle', 'hero_image_url',
        'banner_url', 'logo_url', 'video_url', 'primary_color', 'seo_title', 'seo_description',
        'og_image_url', 'is_active',
    ];
    $set = ['updated_at = :updated_at'];
    $params = [':id' => $id, ':updated_at' => $now];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $body)) {
            continue;
        }
        $val = $body[$col];
        if ($col === 'is_active') {
            $val = !empty($val) ? 1 : 0;
        }
        $set[] = "`$col` = :$col";
        $params[":$col"] = $val === '' ? null : $val;
    }
    $pdo->prepare('UPDATE cities SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
    app_success(['ok' => true]);
}

if ($op === 'plan_update') {
    $slug = persist_optional_string($body['slug'] ?? null, 64);
    if ($slug === null) {
        app_error('invalid_slug', 'Plano inválido.', 422);
    }
    $allowed = ['name', 'price_cents', 'duration_days', 'max_photos', 'features', 'sort'];
    $set = ['updated_at = :updated_at'];
    $params = [':slug' => $slug, ':updated_at' => $now];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $body)) {
            continue;
        }
        $val = $body[$col];
        if ($col === 'features') {
            $val = persist_json_encode($val);
        }
        $set[] = "`$col` = :$col";
        $params[":$col"] = $val;
    }
    $pdo->prepare('UPDATE plans_config SET ' . implode(', ', $set) . ' WHERE slug = :slug')->execute($params);
    app_success(['ok' => true]);
}

if ($op === 'setting_update') {
    $key = persist_optional_string($body['key'] ?? null, 191);
    if ($key === null) {
        app_error('invalid_key', 'Chave inválida.', 422);
    }
    persist_setting_upsert($pdo, $key, $body['value'] ?? null, true);
    app_success(['ok' => true]);
}

app_error('invalid_op', 'Operação inválida.', 400);
