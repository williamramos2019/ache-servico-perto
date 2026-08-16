<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/catalog.php';

app_start(['GET', 'POST', 'DELETE', 'OPTIONS']);
auth_start_session();

$userId = require_auth();
$pdo = db_pdo(false);
$method = app_request_method();

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT f.company_id, f.created_at,
                c.id, c.slug, c.name, c.tagline, c.banner_url, c.logo_url, c.plan, c.featured,
                ci.name AS city_name, ci.slug AS city_slug
         FROM favorites f
         INNER JOIN companies c ON c.id = f.company_id
         LEFT JOIN cities ci ON ci.id = c.city_id
         WHERE f.user_id = :user_id
         ORDER BY f.created_at DESC'
    );
    $stmt->execute([':user_id' => $userId]);
    $rows = [];
    $ids = [];
    foreach ($stmt->fetchAll() as $row) {
        $ids[] = (string) $row['company_id'];
        $city = null;
        if (!empty($row['city_name'])) {
            $city = ['name' => (string) $row['city_name'], 'slug' => (string) $row['city_slug']];
        }
        $rows[] = [
            'company_id' => (string) $row['company_id'],
            'created_at' => (string) $row['created_at'],
            'companies' => [
                'id' => (string) $row['id'],
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'tagline' => $row['tagline'] !== null ? (string) $row['tagline'] : null,
                'banner_url' => $row['banner_url'] !== null ? (string) $row['banner_url'] : null,
                'logo_url' => $row['logo_url'] !== null ? (string) $row['logo_url'] : null,
                'plan' => $row['plan'] !== null ? (string) $row['plan'] : null,
                'featured' => (int) $row['featured'] === 1,
                'cities' => $city,
            ],
        ];
    }
    app_success(['favorites' => $rows, 'company_ids' => $ids]);
}

require_csrf();
rate_limit_hit('fav_user', $userId, 60, 900);

$body = companies_read_json();
if (($body['op'] ?? '') === 'clear') {
    $del = $pdo->prepare('DELETE FROM favorites WHERE user_id = :user_id');
    $del->execute([':user_id' => $userId]);
    app_success(['cleared' => true]);
}
$companyId = companies_require_uuid($body['company_id'] ?? null, 'invalid_company_id', 'A valid company is required.');
$company = companies_find($pdo, $companyId);
if ($company === null) {
    app_error('not_found', 'Empresa não encontrada.', 404);
}

if ($method === 'DELETE' || (($body['op'] ?? '') === 'remove')) {
    $del = $pdo->prepare('DELETE FROM favorites WHERE user_id = :user_id AND company_id = :company_id');
    $del->execute([':user_id' => $userId, ':company_id' => $companyId]);
    app_success(['favorited' => false]);
}

$ins = $pdo->prepare(
    'INSERT IGNORE INTO favorites (user_id, company_id, created_at) VALUES (:user_id, :company_id, :created_at)'
);
$ins->execute([
    ':user_id' => $userId,
    ':company_id' => $companyId,
    ':created_at' => auth_now(),
]);
app_success(['favorited' => true]);
