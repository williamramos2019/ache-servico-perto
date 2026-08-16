<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/security.php';

const AUTH_KNOWN_ROLES = ['admin', 'company_owner', 'user', 'editor', 'publisher'];
const AUTH_SESSION_KEY = 'uid';
const AUTH_LAST_SEEN_KEY = 'last_seen';
const AUTH_MIN_PASSWORD_LENGTH = 8;
const AUTH_MAX_PASSWORD_LENGTH = 72;
const AUTH_IDLE_SECONDS = 28800;
const AUTH_REGISTER_FIELDS = ['email', 'password', 'name'];
const AUTH_LOGIN_FIELDS = ['email', 'password'];

function auth_cookie_secure(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';
    if ($https !== '' && strtolower((string) $https) !== 'off') {
        return true;
    }

    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function auth_session_name(): string
{
    $name = getenv('SESSION_NAME');
    if ($name === false || trim($name) === '') {
        return 'agendaqui_sid';
    }

    return trim($name);
}

function auth_configure_session(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string) AUTH_IDLE_SECONDS);

    session_name(auth_session_name());
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => auth_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    auth_configure_session();
    session_start();
    auth_enforce_idle();
}

function auth_enforce_idle(): void
{
    $seen = $_SESSION[AUTH_LAST_SEEN_KEY] ?? null;
    if ($seen !== null && (time() - (int) $seen) > AUTH_IDLE_SECONDS) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    $_SESSION[AUTH_LAST_SEEN_KEY] = time();
}

function auth_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function auth_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
}

function auth_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

/**
 * @return array<string, mixed>
 */
function auth_read_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        app_error('invalid_json', 'JSON body is required.', 400);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        app_error('invalid_json', 'JSON body is invalid.', 400);
    }

    return $data;
}

/**
 * @param list<string> $allowed
 * @param array<string, mixed> $body
 */
function auth_reject_unknown_fields(array $body, array $allowed, string $message): void
{
    $unknown = array_values(array_diff(array_keys($body), $allowed));
    if ($unknown !== []) {
        app_error('unexpected_fields', $message, 422);
    }
}

function auth_user_id(): ?string
{
    $id = $_SESSION[AUTH_SESSION_KEY] ?? null;
    if (!is_string($id) || $id === '') {
        return null;
    }

    return $id;
}

function require_auth(): string
{
    $id = auth_user_id();
    if ($id === null) {
        app_error('unauthenticated', 'Authentication is required.', 401);
    }

    return $id;
}

/**
 * @return list<string>
 */
function auth_roles(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare('SELECT role FROM user_roles WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    $roles = [];
    foreach ($stmt->fetchAll() as $row) {
        $role = (string) $row['role'];
        if (in_array($role, AUTH_KNOWN_ROLES, true)) {
            $roles[] = $role;
        }
    }

    return $roles;
}

function has_role(PDO $pdo, string $userId, string $role): bool
{
    if (!in_array($role, AUTH_KNOWN_ROLES, true)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM user_roles WHERE user_id = :user_id AND role = :role LIMIT 1'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':role' => $role,
    ]);

    return $stmt->fetchColumn() !== false;
}

function require_role(string $role): string
{
    $userId = require_auth();
    $pdo = db_pdo(false);
    if (!has_role($pdo, $userId, $role)) {
        app_error('forbidden', 'You do not have permission to perform this action.', 403);
    }

    return $userId;
}

/**
 * @return array{id: string, email: string, email_verified: bool, created_at: string, profile: array{name: ?string, avatar_url: ?string}, roles: list<string>}
 */
function auth_public_user(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.email, u.email_verified_at, u.created_at,
                p.name, p.avatar_url
         FROM users u
         LEFT JOIN profiles p ON p.id = u.id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
        app_error('unauthenticated', 'Authentication is required.', 401);
    }

    return [
        'id' => (string) $row['id'],
        'email' => (string) $row['email'],
        'email_verified' => $row['email_verified_at'] !== null,
        'created_at' => (string) $row['created_at'],
        'profile' => [
            'name' => $row['name'] !== null ? (string) $row['name'] : null,
            'avatar_url' => $row['avatar_url'] !== null ? (string) $row['avatar_url'] : null,
        ],
        'roles' => auth_roles($pdo, $userId),
    ];
}

function auth_validate_email(string $email): string
{
    $email = auth_normalize_email($email);
    if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        app_error('invalid_email', 'A valid email is required.', 400);
    }

    return $email;
}

function auth_validate_password(string $password): string
{
    $length = strlen($password);
    if ($length < AUTH_MIN_PASSWORD_LENGTH || $length > AUTH_MAX_PASSWORD_LENGTH) {
        app_error(
            'invalid_password',
            'Password must be between ' . AUTH_MIN_PASSWORD_LENGTH . ' and ' . AUTH_MAX_PASSWORD_LENGTH . ' characters.',
            400
        );
    }

    return $password;
}

function auth_validate_name(mixed $name): string
{
    if (!is_string($name)) {
        app_error('invalid_name', 'Name is required.', 400);
    }

    $name = trim($name);
    if ($name === '' || strlen($name) > 255) {
        app_error('invalid_name', 'Name is required.', 400);
    }

    return $name;
}

function auth_clear_session_cookie(): void
{
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'] ?: '/',
        'secure' => $params['secure'],
        'httponly' => true,
        'samesite' => $params['samesite'] ?: 'Lax',
    ]);
}
