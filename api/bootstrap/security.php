<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

const CSRF_SESSION_KEY = 'csrf';
const CSRF_HEADER = 'HTTP_X_CSRF_TOKEN';

const RATE_LIMIT_LOGIN_EMAIL_MAX = 5;
const RATE_LIMIT_LOGIN_EMAIL_WINDOW = 900;
const RATE_LIMIT_LOGIN_IP_MAX = 20;
const RATE_LIMIT_LOGIN_IP_WINDOW = 900;
const RATE_LIMIT_REGISTER_IP_MAX = 5;
const RATE_LIMIT_REGISTER_IP_WINDOW = 3600;
const RATE_LIMIT_WRITE_USER_MAX = 60;
const RATE_LIMIT_WRITE_USER_WINDOW = 900;

function security_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!is_string($ip) || $ip === '') {
        return '0.0.0.0';
    }

    return $ip;
}

function rate_limit_dir(): string
{
    $override = getenv('RATE_LIMIT_DIR');
    if ($override !== false && trim($override) !== '') {
        return rtrim(trim($override), '/\\');
    }

    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate-limit';
}

function rate_limit_ensure_dir(): string
{
    $dir = rate_limit_dir();
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            app_error('internal_error', 'Unable to apply request limits.', 500);
        }
    }

    return $dir;
}

function rate_limit_path(string $bucket, string $subject): string
{
    $key = hash('sha256', $bucket . "\0" . $subject);

    return rate_limit_ensure_dir() . DIRECTORY_SEPARATOR . $key . '.json';
}

/**
 * @return array{count: int, reset: int}
 */
function rate_limit_read(mixed $handle): array
{
    $raw = stream_get_contents($handle);
    if (!is_string($raw) || trim($raw) === '') {
        return ['count' => 0, 'reset' => 0];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['count' => 0, 'reset' => 0];
    }

    return [
        'count' => isset($data['count']) ? (int) $data['count'] : 0,
        'reset' => isset($data['reset']) ? (int) $data['reset'] : 0,
    ];
}

function rate_limit_write(mixed $handle, int $count, int $reset): void
{
    $payload = json_encode([
        'count' => $count,
        'reset' => $reset,
    ], JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        app_error('internal_error', 'Unable to apply request limits.', 500);
    }

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, $payload);
}

function rate_limit_hit(string $bucket, string $subject, int $max, int $windowSeconds): void
{
    $path = rate_limit_path($bucket, $subject);
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        app_error('internal_error', 'Unable to apply request limits.', 500);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            app_error('internal_error', 'Unable to apply request limits.', 500);
        }
        @chmod($path, 0600);

        $now = time();
        $state = rate_limit_read($handle);
        if ($state['reset'] <= $now) {
            $state = ['count' => 0, 'reset' => $now + $windowSeconds];
        }

        if ($state['count'] >= $max) {
            $retry = max(1, $state['reset'] - $now);
            header('Retry-After: ' . $retry);
            app_error('rate_limited', 'Too many requests. Try again later.', 429);
        }

        $state['count']++;
        rate_limit_write($handle, $state['count'], $state['reset']);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function rate_limit_login(string $email): void
{
    $ip = security_client_ip();
    rate_limit_hit('login_ip', $ip, RATE_LIMIT_LOGIN_IP_MAX, RATE_LIMIT_LOGIN_IP_WINDOW);
    rate_limit_hit('login_email', $ip . "\0" . $email, RATE_LIMIT_LOGIN_EMAIL_MAX, RATE_LIMIT_LOGIN_EMAIL_WINDOW);
}

function rate_limit_register(): void
{
    rate_limit_hit('register_ip', security_client_ip(), RATE_LIMIT_REGISTER_IP_MAX, RATE_LIMIT_REGISTER_IP_WINDOW);
}

function rate_limit_authenticated_write(string $userId): void
{
    rate_limit_hit('write_user', $userId, RATE_LIMIT_WRITE_USER_MAX, RATE_LIMIT_WRITE_USER_WINDOW);
}

function csrf_generate(): string
{
    return bin2hex(random_bytes(32));
}

function csrf_ensure(): string
{
    $current = $_SESSION[CSRF_SESSION_KEY] ?? null;
    if (!is_string($current) || $current === '' || strlen($current) < 32) {
        $current = csrf_generate();
        $_SESSION[CSRF_SESSION_KEY] = $current;
    }

    return $current;
}

function csrf_rotate(): string
{
    $token = csrf_generate();
    $_SESSION[CSRF_SESSION_KEY] = $token;

    return $token;
}

function csrf_from_request(): string
{
    $header = $_SERVER[CSRF_HEADER] ?? '';
    if (is_string($header)) {
        return trim($header);
    }

    return '';
}

function csrf_session_has_token(): bool
{
    $token = $_SESSION[CSRF_SESSION_KEY] ?? null;

    return is_string($token) && $token !== '';
}

function require_csrf(): void
{
    $provided = csrf_from_request();
    $expected = $_SESSION[CSRF_SESSION_KEY] ?? null;
    if (
        !is_string($expected)
        || $expected === ''
        || $provided === ''
        || !hash_equals($expected, $provided)
    ) {
        app_error('csrf_invalid', 'Request could not be validated.', 403);
    }
}

function auth_protect_write(?string $userId = null): void
{
    require_csrf();
    if ($userId !== null && $userId !== '') {
        rate_limit_authenticated_write($userId);
    }
}
