<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/auth.php';

app_start(['GET', 'OPTIONS']);
auth_start_session();

$userId = require_auth();
$pdo = db_pdo(false);

app_success([
    'user' => auth_public_user($pdo, $userId),
    'csrf_token' => csrf_ensure(),
]);
