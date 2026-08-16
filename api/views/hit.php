<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/catalog.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();

require_csrf();
rate_limit_hit('view_ip', security_client_ip(), 120, 3600);

$body = companies_read_json();
$companyId = companies_require_uuid($body['company_id'] ?? null, 'invalid_company_id', 'A valid company is required.');

$pdo = db_pdo(false);
$company = companies_find($pdo, $companyId);
if ($company === null) {
    app_success(['ok' => true]);
}

$ip = security_client_ip();
$stmt = $pdo->prepare(
    'INSERT INTO company_views (company_id, ip_hash, viewed_at) VALUES (:company_id, :ip_hash, :viewed_at)'
);
$stmt->execute([
    ':company_id' => $companyId,
    ':ip_hash' => hash('sha256', $ip),
    ':viewed_at' => auth_now(),
]);
$upd = $pdo->prepare('UPDATE companies SET views_count = views_count + 1 WHERE id = :id');
$upd->execute([':id' => $companyId]);

app_success(['ok' => true]);
