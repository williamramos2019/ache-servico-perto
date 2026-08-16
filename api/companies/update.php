<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/companies.php';

app_start(['PATCH', 'OPTIONS']);
auth_start_session();

$userId = require_auth();
auth_protect_write($userId);

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if ($id === '' || !companies_is_uuid($id)) {
    app_error('invalid_id', 'A valid company id is required.', 422);
}
$id = strtolower($id);

$body = companies_read_json();
$input = companies_validated_input($body, false);
$fields = $input['fields'];
$categoryIds = $input['category_ids'];

if ($fields === [] && $categoryIds === null) {
    app_error('invalid_payload', 'At least one allowed field is required.', 422);
}

$pdo = db_pdo(false);
$row = companies_find($pdo, $id);
if ($row === null) {
    app_error('not_found', 'Company was not found.', 404);
}
companies_require_owner($row, $userId);

if (isset($fields['slug'])) {
    companies_assert_unique_slug($pdo, (string) $fields['slug'], $id);
}
if (array_key_exists('city_id', $fields)) {
    companies_assert_city($pdo, $fields['city_id']);
}
companies_assert_categories($pdo, $categoryIds);

$writable = companies_bind_writable($fields);
$sets = ['updated_at = :updated_at'];
$params = [
    ':id' => $id,
    ':updated_at' => auth_now(),
];

foreach ($writable as $column => $value) {
    if ($value === null) {
        $sets[] = $column . ' = NULL';
        continue;
    }
    $sets[] = $column . ' = :' . $column;
    $params[':' . $column] = $value;
}

try {
    $pdo->beginTransaction();
    $sql = 'UPDATE companies SET ' . implode(', ', $sets) . ' WHERE id = :id AND owner_id = :owner_id';
    $params[':owner_id'] = $userId;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() === 0) {
        $current = companies_find($pdo, $id);
        if ($current === null) {
            app_error('not_found', 'Company was not found.', 404);
        }
        companies_require_owner($current, $userId);
    }
    if ($categoryIds !== null) {
        companies_replace_categories($pdo, $id, $categoryIds);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$updated = companies_find($pdo, $id);
if ($updated === null) {
    app_error('not_found', 'Company was not found.', 404);
}

app_success([
    'company' => companies_public_row($pdo, $updated),
]);
