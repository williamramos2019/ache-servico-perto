<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/catalog.php';

app_start(['GET', 'OPTIONS']);
auth_start_session();

$op = isset($_GET['op']) ? (string) $_GET['op'] : '';
$pdo = db_pdo(false);

if ($op === 'cities') {
    $all = isset($_GET['all']) && (string) $_GET['all'] === '1';
    if ($all) {
        $stmt = $pdo->query('SELECT id, name, slug, state FROM cities WHERE is_active = 1 ORDER BY name ASC');
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, slug, state FROM cities WHERE slug IN (:a, :b) ORDER BY name ASC'
        );
        $stmt->execute([
            ':a' => CATALOG_APP_CITY_SLUGS[0],
            ':b' => CATALOG_APP_CITY_SLUGS[1],
        ]);
    }
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'state' => (string) $row['state'],
        ];
    }
    app_success(['cities' => $rows]);
}

if ($op === 'categories') {
    $stmt = $pdo->query(
        'SELECT id, name, slug, icon, description, sort FROM categories ORDER BY sort ASC, name ASC'
    );
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'icon' => $row['icon'] !== null ? (string) $row['icon'] : null,
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'sort' => (int) $row['sort'],
        ];
    }
    app_success(['categories' => $rows]);
}

if ($op === 'featured') {
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 6;
    if ($limit < 1) {
        $limit = 6;
    }
    if ($limit > 24) {
        $limit = 24;
    }
    $pool = max($limit * 4, 24);
    $where = ['c.status = :status'];
    $params = [':status' => 'active'];
    $citySlug = isset($_GET['city']) ? trim((string) $_GET['city']) : '';
    if ($citySlug !== '') {
        $cityStmt = $pdo->prepare('SELECT id FROM cities WHERE slug = :slug LIMIT 1');
        $cityStmt->execute([':slug' => $citySlug]);
        $city = $cityStmt->fetch();
        if ($city === false) {
            app_success(['companies' => []]);
        }
        $where[] = 'c.city_id = :city_id';
        $params[':city_id'] = (string) $city['id'];
    }
    $stmt = $pdo->prepare(
        'SELECT ' . catalog_company_select() . '
         FROM companies c
         LEFT JOIN cities ci ON ci.id = c.city_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.featured DESC, c.rating DESC, c.review_count DESC
         LIMIT ' . $pool
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    usort($rows, static function (array $a, array $b): int {
        $score = static function (array $c): float {
            $s = 0.0;
            if (!empty($c['banner_url'])) {
                $s += 100;
            }
            $plan = (string) ($c['plan'] ?? '');
            if ($plan === 'featured') {
                $s += 20;
            } elseif ($plan === 'premium') {
                $s += 10;
            }
            if ((int) ($c['featured'] ?? 0) === 1) {
                $s += 5;
            }
            $s += (float) ($c['rating'] ?? 0);

            return $s;
        };

        return $score($b) <=> $score($a);
    });
    $items = catalog_items_from_rows($pdo, array_slice($rows, 0, $limit));
    app_success(['companies' => $items]);
}

if ($op === 'search') {
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 0;
    if ($limit < 1) {
        $limit = 200;
    }
    if ($limit > 200) {
        $limit = 200;
    }
    if ($page < 0) {
        $page = 0;
    }
    $offset = $page * $limit;

    $where = ['c.status = :status'];
    $params = [':status' => 'active'];

    $citySlug = isset($_GET['city']) ? trim((string) $_GET['city']) : '';
    if ($citySlug !== '') {
        $cityStmt = $pdo->prepare('SELECT id FROM cities WHERE slug = :slug LIMIT 1');
        $cityStmt->execute([':slug' => $citySlug]);
        $city = $cityStmt->fetch();
        if ($city === false) {
            app_success(['items' => [], 'hasMore' => false, 'total' => 0]);
        }
        $where[] = 'c.city_id = :city_id';
        $params[':city_id'] = (string) $city['id'];
    }

    $categorySlug = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
    if ($categorySlug !== '') {
        $catStmt = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
        $catStmt->execute([':slug' => $categorySlug]);
        $cat = $catStmt->fetch();
        if ($cat === false) {
            app_success(['items' => [], 'hasMore' => false, 'total' => 0]);
        }
        $where[] = 'c.id IN (SELECT company_id FROM company_categories WHERE category_id = :category_id)';
        $params[':category_id'] = (string) $cat['id'];
    }

    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $q = str_replace(['%', '_'], ' ', $q);
    if ($q !== '') {
        $text = catalog_text_search_clause($q);
        $where[] = $text['sql'];
        $params = array_merge($params, $text['params']);
    }

    $plan = isset($_GET['plan']) ? (string) $_GET['plan'] : '';
    if ($plan === 'premium' || $plan === 'featured' || $plan === 'free') {
        $where[] = 'c.plan = :plan';
        $params[':plan'] = $plan;
    }
    if (isset($_GET['premiumOnly']) && (string) $_GET['premiumOnly'] === '1') {
        $where[] = "c.plan IN ('premium', 'featured')";
    }
    if (isset($_GET['verified']) && (string) $_GET['verified'] === '1') {
        $where[] = 'c.is_verified = 1';
    }
    if (isset($_GET['hasWhatsapp']) && (string) $_GET['hasWhatsapp'] === '1') {
        $where[] = "(c.whatsapp IS NOT NULL AND TRIM(c.whatsapp) <> '')";
    }
    if (isset($_GET['hasReviews']) && (string) $_GET['hasReviews'] === '1') {
        $where[] = 'c.review_count > 0';
    }

    $minRating = isset($_GET['minRating']) ? (float) $_GET['minRating'] : 0;
    if ($minRating > 0) {
        $where[] = 'c.rating >= :min_rating';
        $params[':min_rating'] = $minRating;
    }

    $sort = isset($_GET['sort']) ? (string) $_GET['sort'] : 'relevance';
    // relevance: paid visibility first, then rating. Other keys match the UI labels.
    $order = 'c.featured DESC, c.rating DESC, c.review_count DESC';
    if ($sort === 'name') {
        $order = 'c.name ASC';
    } elseif ($sort === 'newest') {
        $order = 'c.created_at DESC';
    } elseif ($sort === 'rating') {
        $order = 'c.rating DESC, c.review_count DESC';
    } elseif ($sort === 'reviews') {
        $order = 'c.review_count DESC, c.rating DESC';
    }

    $openNow = isset($_GET['openNow']) && (string) $_GET['openNow'] === '1';
    $sqlWhere = implode(' AND ', $where);

    if ($openNow) {
        $stmt = $pdo->prepare(
            'SELECT ' . catalog_company_select() . '
             FROM companies c
             LEFT JOIN cities ci ON ci.id = c.city_id
             WHERE ' . $sqlWhere . '
             ORDER BY ' . $order . '
             LIMIT 500'
        );
        $stmt->execute($params);
        $filtered = [];
        foreach ($stmt->fetchAll() as $row) {
            if (catalog_is_open_now(catalog_decode_hours($row['hours'] ?? null)) === true) {
                $filtered[] = $row;
            }
        }
        $total = count($filtered);
        $pageRows = array_slice($filtered, $offset, $limit);
        $items = catalog_items_from_rows($pdo, $pageRows);
        app_success([
            'items' => $items,
            'hasMore' => ($offset + $limit) < $total,
            'total' => $total,
        ]);
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM companies c WHERE ' . $sqlWhere);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT ' . catalog_company_select() . '
         FROM companies c
         LEFT JOIN cities ci ON ci.id = c.city_id
         WHERE ' . $sqlWhere . '
         ORDER BY ' . $order . '
         LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $stmt->execute($params);
    $items = catalog_items_from_rows($pdo, $stmt->fetchAll());
    app_success([
        'items' => $items,
        'hasMore' => ($offset + $limit) < $total,
        'total' => $total,
    ]);
}

if ($op === 'suggest') {
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $q = str_replace(['%', '_'], ' ', $q);
    if (strlen($q) < 2) {
        app_success(['companies' => []]);
    }
    $text = catalog_text_search_clause($q);
    $params = array_merge([':status' => 'active'], $text['params']);
    $citySql = '';
    $citySlug = isset($_GET['city']) ? trim((string) $_GET['city']) : '';
    if ($citySlug !== '') {
        $cityStmt = $pdo->prepare('SELECT id FROM cities WHERE slug = :slug LIMIT 1');
        $cityStmt->execute([':slug' => $citySlug]);
        $city = $cityStmt->fetch();
        if ($city !== false) {
            $citySql = ' AND c.city_id = :city_id';
            $params[':city_id'] = (string) $city['id'];
        }
    }
    $stmt = $pdo->prepare(
        'SELECT c.id, c.name, c.slug, c.logo_url, ci.name AS city_name
         FROM companies c
         LEFT JOIN cities ci ON ci.id = c.city_id
         WHERE c.status = :status AND ' . $text['sql'] . $citySql . '
         ORDER BY c.featured DESC, c.rating DESC
         LIMIT 6'
    );
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'logo_url' => $row['logo_url'] !== null ? (string) $row['logo_url'] : null,
            'city_name' => $row['city_name'] !== null ? (string) $row['city_name'] : null,
        ];
    }
    app_success(['companies' => $rows]);
}

if ($op === 'company') {
    $slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
    if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
        app_error('invalid_slug', 'A valid slug is required.', 422);
    }
    $stmt = $pdo->prepare(
        'SELECT ' . catalog_company_select() . '
         FROM companies c
         LEFT JOIN cities ci ON ci.id = c.city_id
         WHERE c.slug = :slug LIMIT 1'
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    if ($row === false || !companies_can_view($row, auth_user_id())) {
        app_success(['company' => null]);
    }
    app_success(['company' => catalog_company_detail($pdo, $row)]);
}

if ($op === 'reviews') {
    $companyId = isset($_GET['company_id']) ? (string) $_GET['company_id'] : '';
    if (!companies_is_uuid($companyId)) {
        app_error('invalid_id', 'A valid company id is required.', 422);
    }
    $stmt = $pdo->prepare(
        'SELECT id, rating, comment, created_at, user_id, author_name, source, review_date
         FROM reviews WHERE company_id = :id
         ORDER BY (review_date IS NULL), review_date DESC, created_at DESC'
    );
    $stmt->execute([':id' => strtolower($companyId)]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (string) $row['id'],
            'rating' => (int) $row['rating'],
            'comment' => $row['comment'] !== null ? (string) $row['comment'] : null,
            'created_at' => (string) $row['created_at'],
            'user_id' => $row['user_id'] !== null ? (string) $row['user_id'] : null,
            'author_name' => $row['author_name'] !== null ? (string) $row['author_name'] : null,
            'source' => (string) $row['source'],
            'review_date' => $row['review_date'] !== null ? (string) $row['review_date'] : null,
        ];
    }
    app_success(['reviews' => $rows]);
}

if ($op === 'similar') {
    $exclude = isset($_GET['exclude_id']) ? (string) $_GET['exclude_id'] : '';
    $cityId = isset($_GET['city_id']) ? (string) $_GET['city_id'] : '';
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 6;
    if ($limit < 1) {
        $limit = 6;
    }
    if ($limit > 12) {
        $limit = 12;
    }
    $categoryIds = [];
    if (isset($_GET['category_ids']) && is_string($_GET['category_ids']) && $_GET['category_ids'] !== '') {
        foreach (explode(',', $_GET['category_ids']) as $id) {
            $id = trim($id);
            if (companies_is_uuid($id)) {
                $categoryIds[] = strtolower($id);
            }
        }
    }
    $params = [':status' => 'active'];
    $where = ['c.status = :status'];
    if (companies_is_uuid($exclude)) {
        $where[] = 'c.id != :exclude';
        $params[':exclude'] = strtolower($exclude);
    }
    if ($categoryIds !== []) {
        $in = [];
        foreach ($categoryIds as $i => $id) {
            $key = ':cat' . $i;
            $in[] = $key;
            $params[$key] = $id;
        }
        $where[] = 'c.id IN (SELECT company_id FROM company_categories WHERE category_id IN (' . implode(',', $in) . '))';
    } elseif (companies_is_uuid($cityId)) {
        $where[] = 'c.city_id = :city_id';
        $params[':city_id'] = strtolower($cityId);
    }
    if (companies_is_uuid($cityId) && $categoryIds !== []) {
        $where[] = 'c.city_id = :city_id';
        $params[':city_id'] = strtolower($cityId);
    }
    $stmt = $pdo->prepare(
        'SELECT ' . catalog_company_select() . '
         FROM companies c
         LEFT JOIN cities ci ON ci.id = c.city_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.featured DESC, c.rating DESC
         LIMIT ' . $limit
    );
    $stmt->execute($params);
    $items = catalog_items_from_rows($pdo, $stmt->fetchAll());
    app_success(['companies' => $items]);
}

if ($op === 'cities_by_ids') {
    $ids = [];
    if (isset($_GET['ids']) && is_string($_GET['ids'])) {
        foreach (explode(',', $_GET['ids']) as $id) {
            $id = trim($id);
            if (companies_is_uuid($id)) {
                $ids[] = strtolower($id);
            }
        }
    }
    if ($ids === []) {
        app_success(['cities' => []]);
    }
    $in = [];
    $params = [];
    foreach ($ids as $i => $id) {
        $key = ':id' . $i;
        $in[] = $key;
        $params[$key] = $id;
    }
    $stmt = $pdo->prepare(
        'SELECT id, name, state, slug FROM cities WHERE id IN (' . implode(',', $in) . ')'
    );
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'state' => (string) $row['state'],
            'slug' => (string) $row['slug'],
        ];
    }
    app_success(['cities' => $rows]);
}

if ($op === 'nearest') {
    $lat = isset($_GET['lat']) ? (float) $_GET['lat'] : 0.0;
    $lng = isset($_GET['lng']) ? (float) $_GET['lng'] : 0.0;
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

app_error('invalid_op', 'Unknown catalog operation.', 422);
