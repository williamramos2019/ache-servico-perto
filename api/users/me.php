<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/auth.php';

const USERS_ME_MAX_BODY_BYTES = 8192;
const USERS_ME_MAX_AVATAR_URL = 2048;
const USERS_ME_ALLOWED_FIELDS = ['name', 'avatar_url'];

app_start(['GET', 'PATCH', 'OPTIONS']);
auth_start_session();

$userId = require_auth();
$pdo = db_pdo(false);
$method = app_request_method();

if ($method === 'GET') {
    app_success([
        'user' => auth_public_user($pdo, $userId),
        'csrf_token' => csrf_ensure(),
    ]);
}

auth_protect_write($userId);

$length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > USERS_ME_MAX_BODY_BYTES) {
    app_error('payload_too_large', 'Request body is too large.', 422);
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    app_error('invalid_json', 'JSON body is required.', 422);
}
if (strlen($raw) > USERS_ME_MAX_BODY_BYTES) {
    app_error('payload_too_large', 'Request body is too large.', 422);
}

$body = json_decode($raw, true);
if (!is_array($body) || ($body !== [] && array_keys($body) === range(0, count($body) - 1))) {
    app_error('invalid_json', 'JSON body is invalid.', 422);
}

$unknown = array_values(array_diff(array_keys($body), USERS_ME_ALLOWED_FIELDS));
if ($unknown !== []) {
    app_error('unexpected_fields', 'Only name and avatar_url can be updated.', 422);
}

if (!array_key_exists('name', $body) && !array_key_exists('avatar_url', $body)) {
    app_error('invalid_payload', 'At least one of name or avatar_url is required.', 422);
}

$sets = ['updated_at = :updated_at'];
$params = [
    ':id' => $userId,
    ':updated_at' => auth_now(),
];

if (array_key_exists('name', $body)) {
    if (!is_string($body['name'])) {
        app_error('invalid_name', 'Name must be a string.', 422);
    }
    $name = trim($body['name']);
    if ($name === '' || strlen($name) > 255) {
        app_error('invalid_name', 'Name must be between 1 and 255 characters.', 422);
    }
    $sets[] = 'name = :name';
    $params[':name'] = $name;
}

if (array_key_exists('avatar_url', $body)) {
    if ($body['avatar_url'] === null) {
        $sets[] = 'avatar_url = NULL';
    } elseif (!is_string($body['avatar_url'])) {
        app_error('invalid_avatar_url', 'avatar_url must be a string or null.', 422);
    } else {
        $avatar = trim($body['avatar_url']);
        if ($avatar === '' || strlen($avatar) > USERS_ME_MAX_AVATAR_URL) {
            app_error('invalid_avatar_url', 'avatar_url is invalid.', 422);
        }
        if (filter_var($avatar, FILTER_VALIDATE_URL) === false) {
            app_error('invalid_avatar_url', 'avatar_url is invalid.', 422);
        }
        $scheme = strtolower((string) parse_url($avatar, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            app_error('invalid_avatar_url', 'avatar_url is invalid.', 422);
        }
        $sets[] = 'avatar_url = :avatar_url';
        $params[':avatar_url'] = $avatar;
    }
}

$exists = $pdo->prepare('SELECT 1 FROM profiles WHERE id = :id LIMIT 1');
$exists->execute([':id' => $userId]);
if ($exists->fetchColumn() === false) {
    app_error('not_found', 'Profile was not found.', 404);
}

$sql = 'UPDATE profiles SET ' . implode(', ', $sets) . ' WHERE id = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

app_success([
    'user' => auth_public_user($pdo, $userId),
]);
