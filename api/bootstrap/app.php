<?php

declare(strict_types=1);

/**
 * HTTP bootstrap for the future HostGator PHP API.
 * Reuses api/bootstrap/database.php. No framework, no Composer.
 */

require_once __DIR__ . '/database.php';

const APP_SUPPORTED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

function app_env_name(): string
{
    $raw = getenv('APP_ENV');
    if ($raw === false || $raw === '') {
        return 'production';
    }

    $raw = strtolower(trim($raw));

    return $raw === 'development' ? 'development' : 'production';
}

function app_is_development(): bool
{
    return app_env_name() === 'development';
}

/**
 * @return list<string>
 */
function app_allowed_origins(): array
{
    $raw = getenv('APP_ALLOWED_ORIGINS');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $origins = [];
    foreach (explode(',', $raw) as $item) {
        $item = trim($item);
        if ($item !== '') {
            $origins[] = $item;
        }
    }

    return $origins;
}

function app_request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function app_send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
}

function app_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_success(mixed $data, int $status = 200): void
{
    app_json($status, [
        'success' => true,
        'data' => $data,
    ]);
}

function app_error(string $code, string $message, int $status = 400): void
{
    app_json($status, [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ]);
}

function app_safe_public_message(Throwable $e): string
{
    if (!app_is_development()) {
        return 'Unexpected error.';
    }

    $message = $e->getMessage();
    if (
        stripos($message, 'password') !== false
        || stripos($message, 'DB_PASSWORD') !== false
        || stripos($message, 'MAIL_PASSWORD') !== false
        || stripos($message, 'mysql:') !== false
        || stripos($message, 'using password') !== false
        || stripos($message, 'csrf') !== false
        || stripos($message, 'session') !== false
        || stripos($message, 'token') !== false
    ) {
        return 'A server error occurred.';
    }

    return $message;
}

function app_apply_cors(string $method): void
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowed = app_allowed_origins();

    if ($origin === '') {
        return;
    }

    if ($allowed === []) {
        if ($method === 'OPTIONS') {
            app_error('cors_not_configured', 'Cross-origin requests are not allowed.', 403);
        }

        return;
    }

    if (!in_array($origin, $allowed, true)) {
        if ($method === 'OPTIONS') {
            app_error('cors_forbidden', 'Origin is not allowed.', 403);
        }

        return;
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-CSRF-Token');
    header('Access-Control-Max-Age: 600');
    header('Access-Control-Allow-Credentials: true');
}

/**
 * @param list<string> $allowedMethods
 */
function app_start(array $allowedMethods): void
{
    date_default_timezone_set('UTC');
    app_send_security_headers();

    set_exception_handler(static function (Throwable $e): void {
        error_log('API exception');
        app_error('internal_error', app_safe_public_message($e), 500);
    });

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    $method = app_request_method();
    app_apply_cors($method);

    if ($method === 'OPTIONS') {
        http_response_code(204);
        header('Content-Type: application/json; charset=utf-8');
        exit;
    }

    if (!in_array($method, APP_SUPPORTED_METHODS, true) || !in_array($method, $allowedMethods, true)) {
        header('Allow: ' . implode(', ', $allowedMethods));
        app_error('method_not_allowed', 'HTTP method is not allowed.', 405);
    }
}

function app_database_status(): string
{
    try {
        $pdo = db_pdo(false);
        $pdo->query('SELECT 1');

        return 'ok';
    } catch (Throwable $e) {
        return 'unavailable';
    }
}
