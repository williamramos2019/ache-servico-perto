<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/companies.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();

$userId = require_auth();
auth_protect_write($userId);
$body = companies_read_json();
$input = companies_validated_input($body, true);
$fields = $input['fields'];
$categoryIds = $input['category_ids'] ?? [];

$pdo = db_pdo(false);
companies_assert_unique_slug($pdo, (string) $fields['slug']);
companies_assert_city($pdo, $fields['city_id'] ?? null);
companies_assert_categories($pdo, $categoryIds);

$id = auth_uuid();
$now = auth_now();
$writable = companies_bind_writable($fields);

$columns = [
    'id',
    'owner_id',
    'plan',
    'featured',
    'is_verified',
    'rating',
    'review_count',
    'views_count',
    'created_at',
    'updated_at',
];
$params = [
    ':id' => $id,
    ':owner_id' => $userId,
    ':plan' => 'free',
    ':featured' => 0,
    ':is_verified' => 0,
    ':rating' => '0.00',
    ':review_count' => 0,
    ':views_count' => 0,
    ':created_at' => $now,
    ':updated_at' => $now,
];

foreach ($writable as $column => $value) {
    $columns[] = $column;
    $params[':' . $column] = $value;
}

if (!array_key_exists('status', $writable)) {
    $columns[] = 'status';
    $params[':status'] = 'active';
}

$placeholders = [];
foreach ($columns as $column) {
    $placeholders[] = ':' . $column;
}

$sql = 'INSERT INTO companies (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    companies_replace_categories($pdo, $id, $categoryIds);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$row = companies_find($pdo, $id);
if ($row === null) {
    app_error('internal_error', 'Unable to create company.', 500);
}

app_success([
    'company' => companies_public_row($pdo, $row),
], 201);
