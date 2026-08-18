<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/auth.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
require_csrf();

$body = auth_read_json();
auth_reject_unknown_fields($body, AUTH_LOGIN_FIELDS, 'Only email and password are allowed.');

$email = auth_normalize_email(is_string($body['email'] ?? null) ? $body['email'] : '');
$password = is_string($body['password'] ?? null) ? $body['password'] : '';
rate_limit_login($email);

$pdo = db_pdo(false);
$stmt = $pdo->prepare(
    'SELECT id, password_hash, session_version FROM users WHERE email = :email LIMIT 1'
);
$stmt->execute([':email' => $email]);
$row = $stmt->fetch();

$dummyHash = '$2y$10$usesomesillystringfore7hnbNJHxfd0q2A64j2MC6MqF47i6a';
$hash = is_array($row) ? (string) $row['password_hash'] : $dummyHash;

if ($password === '' || !password_verify($password, $hash) || $row === false) {
    app_error('invalid_credentials', 'Invalid email or password.', 401);
}

session_regenerate_id(true);
$_SESSION = [];
$_SESSION[AUTH_SESSION_KEY] = (string) $row['id'];
$_SESSION[AUTH_LAST_SEEN_KEY] = time();
$_SESSION[AUTH_SESSION_VERSION_KEY] = (int) $row['session_version'];
$csrf = csrf_rotate();

app_success([
    'user' => auth_public_user($pdo, (string) $row['id']),
    'csrf_token' => $csrf,
]);
