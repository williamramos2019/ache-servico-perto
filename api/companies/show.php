<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/companies.php';

app_start(['GET', 'OPTIONS']);
auth_start_session();

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if ($id === '' || !companies_is_uuid($id)) {
    app_error('invalid_id', 'A valid company id is required.', 422);
}

$pdo = db_pdo(false);
$row = companies_find($pdo, strtolower($id));
if ($row === null || !companies_can_view($row, auth_user_id())) {
    app_error('not_found', 'Company was not found.', 404);
}

app_success([
    'company' => companies_public_row($pdo, $row),
]);
