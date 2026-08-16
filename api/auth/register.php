<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/auth.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
require_csrf();

$body = auth_read_json();
auth_reject_unknown_fields($body, AUTH_REGISTER_FIELDS, 'Only email, password and name are allowed.');

$email = auth_validate_email(is_string($body['email'] ?? null) ? $body['email'] : '');
$password = auth_validate_password(is_string($body['password'] ?? null) ? $body['password'] : '');
$name = auth_validate_name($body['name'] ?? null);
rate_limit_register();

$pdo = db_pdo(false);

$exists = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
$exists->execute([':email' => $email]);
if ($exists->fetchColumn() !== false) {
    app_error('email_taken', 'An account with this email already exists.', 409);
}

$userId = auth_uuid();
$roleId = auth_uuid();
$now = auth_now();
$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    app_error('internal_error', 'Unable to create account.', 500);
}

try {
    $pdo->beginTransaction();

    $insertUser = $pdo->prepare(
        'INSERT INTO users (id, email, password_hash, email_verified_at, created_at)
         VALUES (:id, :email, :password_hash, NULL, :created_at)'
    );
    $insertUser->execute([
        ':id' => $userId,
        ':email' => $email,
        ':password_hash' => $hash,
        ':created_at' => $now,
    ]);

    $insertProfile = $pdo->prepare(
        'INSERT INTO profiles (id, name, avatar_url, created_at, updated_at)
         VALUES (:id, :name, NULL, :created_at, :updated_at)'
    );
    $insertProfile->execute([
        ':id' => $userId,
        ':name' => $name,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $insertRole = $pdo->prepare(
        'INSERT INTO user_roles (id, user_id, role, created_at)
         VALUES (:id, :user_id, :role, :created_at)'
    );
    $insertRole->execute([
        ':id' => $roleId,
        ':user_id' => $userId,
        ':role' => 'user',
        ':created_at' => $now,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

app_success([
    'user' => auth_public_user($pdo, $userId),
    'csrf_token' => csrf_ensure(),
], 201);
