<?php

declare(strict_types=1);

require_once __DIR__ . '/persist.php';

const DOMAIN_MAX_BODY_BYTES = 131072;
const DOMAIN_AD_EVENTS = ['impression', 'click'];

/**
 * @param list<string> $allowedMethods
 * @return array{allowed:bool,status:int,code:string}
 */
function domain_endpoint_policy(
    string $method,
    array $allowedMethods,
    string $authorizationMode = 'public',
    bool $authorized = false
): array {
    if (!in_array(strtoupper($method), $allowedMethods, true)) {
        return ['allowed' => false, 'status' => 405, 'code' => 'method_not_allowed'];
    }
    if ($authorizationMode === 'public' || $authorized) {
        return ['allowed' => true, 'status' => 200, 'code' => 'ok'];
    }

    return ['allowed' => false, 'status' => 403, 'code' => 'forbidden'];
}

/**
 * @param array{allowed:bool,status:int,code:string} $policy
 */
function domain_enforce_endpoint_policy(array $policy): void
{
    if (!$policy['allowed']) {
        app_error($policy['code'], 'Endpoint authorization failed.', $policy['status']);
    }
}

function domain_page(array $query): int
{
    $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);

    return is_int($page) && $page > 0 ? min($page, 10000) : 1;
}

function domain_limit(array $query, int $default = 20, int $max = 100): int
{
    $limit = filter_var($query['limit'] ?? ($query['pageSize'] ?? $default), FILTER_VALIDATE_INT);
    if (!is_int($limit) || $limit < 1) {
        return $default;
    }

    return min($limit, $max);
}

function domain_cursor(mixed $value): ?string
{
    if (!is_string($value) || trim($value) === '' || strlen($value) > 64) {
        return null;
    }
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable) {
        return null;
    }

    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
}

function domain_whatsapp_e164(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $digits = preg_replace('/\D+/', '', $value);
    if (!is_string($digits) || strlen($digits) < 10 || strlen($digits) > 13) {
        return null;
    }
    if (!str_starts_with($digits, '55') && (strlen($digits) === 10 || strlen($digits) === 11)) {
        $digits = '55' . $digits;
    }
    if (strlen($digits) < 12 || strlen($digits) > 13 || !str_starts_with($digits, '55')) {
        return null;
    }

    return '+' . $digits;
}

function domain_allowed_http_url(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $url = trim($value);
    if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        return null;
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
        return null;
    }
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $public = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if ($public === false) {
            return null;
        }
    }

    return $url;
}

function domain_ad_event(mixed $value): ?string
{
    return is_string($value) && in_array($value, DOMAIN_AD_EVENTS, true) ? $value : null;
}

function domain_cron_secret_valid(string $expected, string $provided): bool
{
    return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
}

function domain_slug(string $value): string
{
    $value = strtr($value, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ç' => 'c', 'Ç' => 'C',
    ]);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $ascii = is_string($ascii) ? strtolower($ascii) : strtolower($value);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $ascii);

    return trim(is_string($slug) ? $slug : '', '-');
}

/**
 * @return array<string, mixed>
 */
function domain_read_json(): array
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > DOMAIN_MAX_BODY_BYTES) {
        app_error('payload_too_large', 'Request body is too large.', 422);
    }
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '' || strlen($raw) > DOMAIN_MAX_BODY_BYTES) {
        app_error('invalid_json', 'JSON body is required.', 422);
    }
    $body = json_decode($raw, true);
    if (!is_array($body) || ($body !== [] && array_is_list($body))) {
        app_error('invalid_json', 'JSON body is invalid.', 422);
    }

    return $body;
}

function domain_string(mixed $value, string $field, int $min = 0, int $max = 255, bool $nullable = true): ?string
{
    if ($value === null || $value === '') {
        if ($nullable && $min === 0) {
            return null;
        }
        app_error('invalid_' . $field, $field . ' is required.', 422);
    }
    if (!is_string($value)) {
        app_error('invalid_' . $field, $field . ' must be a string.', 422);
    }
    $text = trim($value);
    $length = strlen($text);
    if ($length < $min || $length > $max) {
        app_error('invalid_' . $field, $field . ' has an invalid length.', 422);
    }

    return $text;
}

function domain_enum(mixed $value, string $field, array $allowed, string $default): string
{
    $selected = is_string($value) && $value !== '' ? $value : $default;
    if (!in_array($selected, $allowed, true)) {
        app_error('invalid_' . $field, $field . ' is invalid.', 422);
    }

    return $selected;
}

function domain_bool(mixed $value, bool $default = false): int
{
    if ($value === null) {
        return $default ? 1 : 0;
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if ($value === 1 || $value === 0 || $value === '1' || $value === '0') {
        return (int) $value;
    }
    app_error('invalid_boolean', 'Boolean value is invalid.', 422);
}

function domain_int(mixed $value, string $field, int $min, int $max, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if (!is_int($number) || $number < $min || $number > $max) {
        app_error('invalid_' . $field, $field . ' is invalid.', 422);
    }

    return $number;
}

/**
 * Builds an OR LIKE clause with unique named placeholders (PDO native prepares).
 *
 * @param list<string> $expressions
 * @return array{sql: string, params: array<string, string>}
 */
function domain_or_like(array $expressions, string $like, string $prefix = 'like'): array
{
    $clauses = [];
    $params = [];
    foreach (array_values($expressions) as $i => $expression) {
        $key = ':' . $prefix . $i;
        $clauses[] = $expression . ' LIKE ' . $key;
        $params[$key] = $like;
    }

    return ['sql' => '(' . implode(' OR ', $clauses) . ')', 'params' => $params];
}

function domain_decimal(mixed $value, string $field, float $min = 0, float $max = 999999999999.99): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_bool($value) || !is_numeric($value)) {
        app_error('invalid_' . $field, $field . ' must be numeric.', 422);
    }
    $number = (float) $value;
    if ($number < $min || $number > $max) {
        app_error('invalid_' . $field, $field . ' is out of range.', 422);
    }

    return number_format($number, 2, '.', '');
}

function domain_uuid(mixed $value, string $field, bool $nullable = true): ?string
{
    if (($value === null || $value === '') && $nullable) {
        return null;
    }
    if (!is_string($value) || !companies_is_uuid($value)) {
        app_error('invalid_' . $field, $field . ' must be a UUID.', 422);
    }

    return strtolower($value);
}

function domain_date(mixed $value, string $field, bool $dateOnly = false): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value) || strlen($value) > 64) {
        app_error('invalid_' . $field, $field . ' is invalid.', 422);
    }
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable) {
        app_error('invalid_' . $field, $field . ' is invalid.', 422);
    }

    return $dateOnly
        ? $date->format('Y-m-d')
        : $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
}

function domain_json_array(mixed $value, string $field, int $maxItems = 100): string
{
    if ($value === null) {
        $value = [];
    }
    if (!is_array($value) || !array_is_list($value) || count($value) > $maxItems) {
        app_error('invalid_' . $field, $field . ' must be an array.', 422);
    }
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || strlen($encoded) > 65535) {
        app_error('invalid_' . $field, $field . ' is too large.', 422);
    }

    return $encoded;
}

function domain_json_value(mixed $value, string $field): string
{
    if (!is_array($value)) {
        app_error('invalid_' . $field, $field . ' must be JSON.', 422);
    }
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || strlen($encoded) > 65535) {
        app_error('invalid_' . $field, $field . ' is too large.', 422);
    }

    return $encoded;
}

function domain_optional_url(mixed $value, string $field): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $url = domain_allowed_http_url($value);
    if ($url === null) {
        app_error('invalid_' . $field, $field . ' must be a public HTTP URL.', 422);
    }

    return $url;
}

function domain_require_admin_write(): string
{
    $userId = persist_require_admin();
    require_csrf();
    rate_limit_authenticated_write($userId);

    return $userId;
}

function domain_require_tables(PDO $pdo, array $tables): void
{
    foreach ($tables as $table) {
        if (!preg_match('/^[a-z0-9_]+$/', $table)) {
            app_error('internal_error', 'Invalid table contract.', 500);
        }
        try {
            $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
        } catch (Throwable) {
            app_error('not_migrated', 'Required database migration is not installed.', 503);
        }
    }
}

/**
 * @param array<string, mixed> $fields
 */
function domain_upsert(PDO $pdo, string $table, array $fields, ?string $id): string
{
    if (!preg_match('/^[a-z0-9_]+$/', $table) || $fields === []) {
        app_error('internal_error', 'Invalid persistence contract.', 500);
    }
    $now = auth_now();
    $fields['updated_at'] = $now;
    if ($id !== null) {
        $sets = [];
        $params = [':id' => $id];
        foreach ($fields as $column => $value) {
            if (!preg_match('/^[a-z0-9_]+$/', $column)) {
                app_error('internal_error', 'Invalid field contract.', 500);
            }
            $sets[] = '`' . $column . '` = :' . $column;
            $params[':' . $column] = $value;
        }
        $pdo->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        return $id;
    }
    $id = auth_uuid();
    $fields = ['id' => $id, ...$fields, 'created_at' => $now];
    $columns = array_keys($fields);
    $params = [];
    foreach ($fields as $column => $value) {
        $params[':' . $column] = $value;
    }
    $pdo->prepare(
        'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES (:' . implode(', :', $columns) . ')'
    )->execute($params);

    return $id;
}

function domain_delete(PDO $pdo, string $table, mixed $id): void
{
    if (!preg_match('/^[a-z0-9_]+$/', $table)) {
        app_error('internal_error', 'Invalid persistence contract.', 500);
    }
    $uuid = domain_uuid($id, 'id', false);
    $pdo->prepare('DELETE FROM `' . $table . '` WHERE id = :id')->execute([':id' => $uuid]);
}

/**
 * @return array<string, mixed>
 */
function domain_decode_row(array $row, array $json = [], array $bools = [], array $ints = [], array $decimals = []): array
{
    foreach ($json as $field) {
        $row[$field] = persist_json_decode($row[$field] ?? null) ?? [];
    }
    foreach ($bools as $field) {
        $row[$field] = persist_bool($row[$field] ?? false);
    }
    foreach ($ints as $field) {
        $row[$field] = (int) ($row[$field] ?? 0);
    }
    foreach ($decimals as $field) {
        $row[$field] = isset($row[$field]) ? (float) $row[$field] : null;
    }

    return $row;
}

function domain_cron_require_secret(): void
{
    $expected = trim((string) getenv('CRON_SHARED_SECRET'));
    $provided = trim((string) ($_SERVER['HTTP_X_CRON_SECRET'] ?? ''));
    if (!domain_cron_secret_valid($expected, $provided)) {
        app_error('forbidden', 'Cron authorization failed.', 403);
    }
}
