<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/companies.php';

app_start(['GET', 'OPTIONS']);
auth_start_session();

$userId = require_auth();
$pdo = db_pdo(false);

$stmt = $pdo->prepare(
    'SELECT * FROM companies WHERE owner_id = :owner_id ORDER BY created_at DESC, id DESC LIMIT ' . COMPANIES_LIST_LIMIT
);
$stmt->execute([':owner_id' => $userId]);

$companies = [];
foreach ($stmt->fetchAll() as $row) {
    $companies[] = companies_public_row($pdo, $row);
}

app_success([
    'companies' => $companies,
]);
