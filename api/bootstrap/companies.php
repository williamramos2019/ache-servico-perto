<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

const COMPANIES_MAX_BODY_BYTES = 65536;
const COMPANIES_MAX_TEXT = 65535;
const COMPANIES_MAX_URL = 2048;
const COMPANIES_LIST_LIMIT = 100;
const COMPANIES_UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
const COMPANIES_SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
const COMPANIES_STATUSES = ['active', 'inactive'];

const COMPANIES_ALLOWED_FIELDS = [
    'name',
    'slug',
    'tagline',
    'description',
    'phone',
    'whatsapp',
    'email',
    'address',
    'zip',
    'city_id',
    'lat',
    'lng',
    'website',
    'instagram',
    'facebook',
    'tiktok',
    'youtube',
    'hours',
    'logo_url',
    'banner_url',
    'video_url',
    'tour_360_url',
    'catalog_url',
    'pricebook_url',
    'portfolio_pdf_url',
    'status',
    'founded_year',
    'years_experience',
    'response_time_minutes',
    'response_rate',
    'services_completed',
    'clients_served',
    'certifications',
    'coverage_cities',
    'quality_scores',
    'badges',
    'price_range',
    'promotions',
    'financing_info',
    'differentials',
    'services_offered',
    'category_ids',
];

const COMPANIES_URL_FIELDS = [
    'website',
    'instagram',
    'facebook',
    'tiktok',
    'youtube',
    'logo_url',
    'banner_url',
    'video_url',
    'tour_360_url',
    'catalog_url',
    'pricebook_url',
    'portfolio_pdf_url',
];

const COMPANIES_TEXT_FIELDS = [
    'tagline',
    'description',
    'address',
];

const COMPANIES_SHORT_FIELDS = [
    'phone' => 64,
    'whatsapp' => 64,
    'zip' => 16,
];

const COMPANIES_JSON_OBJECT_OR_ARRAY = [
    'hours',
    'certifications',
    'quality_scores',
    'promotions',
    'financing_info',
];

const COMPANIES_JSON_STRING_ARRAY = [
    'coverage_cities',
    'badges',
    'differentials',
    'services_offered',
];

const COMPANIES_INT_FIELDS = [
    'founded_year',
    'years_experience',
    'response_time_minutes',
    'services_completed',
    'clients_served',
    'price_range',
];

/**
 * @return array<string, mixed>
 */
function companies_read_json(): array
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > COMPANIES_MAX_BODY_BYTES) {
        app_error('payload_too_large', 'Request body is too large.', 422);
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        app_error('invalid_json', 'JSON body is required.', 422);
    }
    if (strlen($raw) > COMPANIES_MAX_BODY_BYTES) {
        app_error('payload_too_large', 'Request body is too large.', 422);
    }

    $body = json_decode($raw, true);
    if (!is_array($body) || ($body !== [] && array_keys($body) === range(0, count($body) - 1))) {
        app_error('invalid_json', 'JSON body is invalid.', 422);
    }

    return $body;
}

/**
 * @param array<string, mixed> $body
 */
function companies_reject_unknown_fields(array $body): void
{
    $unknown = array_values(array_diff(array_keys($body), COMPANIES_ALLOWED_FIELDS));
    if ($unknown !== []) {
        app_error('unexpected_fields', 'One or more fields are not allowed.', 422);
    }
}

function companies_is_uuid(string $value): bool
{
    return preg_match(COMPANIES_UUID_PATTERN, $value) === 1;
}

function companies_require_uuid(mixed $value, string $code, string $message): string
{
    if (!is_string($value) || !companies_is_uuid($value)) {
        app_error($code, $message, 422);
    }

    return strtolower($value);
}

/**
 * @return array<string, mixed>|null
 */
function companies_find(PDO $pdo, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * @return array<string, mixed>|null
 */
function companies_find_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function companies_is_owner(array $row, ?string $userId): bool
{
    if ($userId === null) {
        return false;
    }

    return isset($row['owner_id']) && is_string($row['owner_id']) && $row['owner_id'] === $userId;
}

function companies_can_view(array $row, ?string $userId): bool
{
    if ((string) $row['status'] === 'active') {
        return true;
    }

    return companies_is_owner($row, $userId);
}

function companies_require_owner(array $row, string $userId): void
{
    if (!companies_is_owner($row, $userId)) {
        app_error('forbidden', 'You do not have permission to perform this action.', 403);
    }
}

/**
 * @return list<string>
 */
function companies_category_ids(PDO $pdo, string $companyId): array
{
    $stmt = $pdo->prepare(
        'SELECT category_id FROM company_categories WHERE company_id = :company_id ORDER BY category_id'
    );
    $stmt->execute([':company_id' => $companyId]);
    $ids = [];
    foreach ($stmt->fetchAll() as $item) {
        $ids[] = (string) $item['category_id'];
    }

    return $ids;
}

function companies_decode_json(mixed $value): mixed
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_array($value)) {
        return $value;
    }

    $decoded = json_decode((string) $value, true);

    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function companies_public_row(PDO $pdo, array $row): array
{
    return [
        'id' => (string) $row['id'],
        'owner_id' => $row['owner_id'] !== null ? (string) $row['owner_id'] : null,
        'slug' => (string) $row['slug'],
        'name' => (string) $row['name'],
        'tagline' => $row['tagline'] !== null ? (string) $row['tagline'] : null,
        'description' => $row['description'] !== null ? (string) $row['description'] : null,
        'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
        'whatsapp' => $row['whatsapp'] !== null ? (string) $row['whatsapp'] : null,
        'email' => $row['email'] !== null ? (string) $row['email'] : null,
        'address' => $row['address'] !== null ? (string) $row['address'] : null,
        'neighborhood' => isset($row['neighborhood']) && $row['neighborhood'] !== null ? (string) $row['neighborhood'] : null,
        'zip' => $row['zip'] !== null ? (string) $row['zip'] : null,
        'city_id' => $row['city_id'] !== null ? (string) $row['city_id'] : null,
        'lat' => $row['lat'] !== null ? (float) $row['lat'] : null,
        'lng' => $row['lng'] !== null ? (float) $row['lng'] : null,
        'website' => $row['website'] !== null ? (string) $row['website'] : null,
        'instagram' => $row['instagram'] !== null ? (string) $row['instagram'] : null,
        'facebook' => $row['facebook'] !== null ? (string) $row['facebook'] : null,
        'tiktok' => $row['tiktok'] !== null ? (string) $row['tiktok'] : null,
        'youtube' => $row['youtube'] !== null ? (string) $row['youtube'] : null,
        'hours' => companies_decode_json($row['hours'] ?? null),
        'logo_url' => $row['logo_url'] !== null ? (string) $row['logo_url'] : null,
        'banner_url' => $row['banner_url'] !== null ? (string) $row['banner_url'] : null,
        'video_url' => $row['video_url'] !== null ? (string) $row['video_url'] : null,
        'tour_360_url' => $row['tour_360_url'] !== null ? (string) $row['tour_360_url'] : null,
        'catalog_url' => $row['catalog_url'] !== null ? (string) $row['catalog_url'] : null,
        'pricebook_url' => $row['pricebook_url'] !== null ? (string) $row['pricebook_url'] : null,
        'portfolio_pdf_url' => $row['portfolio_pdf_url'] !== null ? (string) $row['portfolio_pdf_url'] : null,
        'plan' => (string) $row['plan'],
        'plan_expires_at' => $row['plan_expires_at'] !== null ? (string) $row['plan_expires_at'] : null,
        'featured' => (int) $row['featured'] === 1,
        'status' => (string) $row['status'],
        'origin' => isset($row['origin']) && $row['origin'] !== null && $row['origin'] !== ''
            ? (string) $row['origin']
            : 'manual',
        'is_verified' => (int) $row['is_verified'] === 1,
        'rating' => (float) $row['rating'],
        'review_count' => (int) $row['review_count'],
        'views_count' => (int) $row['views_count'],
        'founded_year' => $row['founded_year'] !== null ? (int) $row['founded_year'] : null,
        'years_experience' => $row['years_experience'] !== null ? (int) $row['years_experience'] : null,
        'response_time_minutes' => $row['response_time_minutes'] !== null ? (int) $row['response_time_minutes'] : null,
        'response_rate' => $row['response_rate'] !== null ? (float) $row['response_rate'] : null,
        'services_completed' => $row['services_completed'] !== null ? (int) $row['services_completed'] : null,
        'clients_served' => $row['clients_served'] !== null ? (int) $row['clients_served'] : null,
        'certifications' => companies_decode_json($row['certifications'] ?? null),
        'coverage_cities' => companies_decode_json($row['coverage_cities'] ?? null),
        'quality_scores' => companies_decode_json($row['quality_scores'] ?? null),
        'reputation_score' => $row['reputation_score'] !== null ? (int) $row['reputation_score'] : null,
        'badges' => companies_decode_json($row['badges'] ?? null),
        'price_range' => $row['price_range'] !== null ? (int) $row['price_range'] : null,
        'promotions' => companies_decode_json($row['promotions'] ?? null),
        'financing_info' => companies_decode_json($row['financing_info'] ?? null),
        'differentials' => companies_decode_json($row['differentials'] ?? null),
        'services_offered' => companies_decode_json($row['services_offered'] ?? null) ?? [],
        'category_ids' => companies_category_ids($pdo, (string) $row['id']),
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
    ];
}

function companies_city_exists(PDO $pdo, string $cityId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM cities WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $cityId]);

    return $stmt->fetchColumn() !== false;
}

/**
 * @param list<string> $categoryIds
 */
function companies_categories_exist(PDO $pdo, array $categoryIds): bool
{
    if ($categoryIds === []) {
        return true;
    }

    $placeholders = [];
    $params = [];
    foreach ($categoryIds as $index => $id) {
        $key = ':id' . $index;
        $placeholders[] = $key;
        $params[$key] = $id;
    }

    $sql = 'SELECT COUNT(*) FROM categories WHERE id IN (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() === count($categoryIds);
}

/**
 * @param list<string> $categoryIds
 */
function companies_replace_categories(PDO $pdo, string $companyId, array $categoryIds): void
{
    $delete = $pdo->prepare('DELETE FROM company_categories WHERE company_id = :company_id');
    $delete->execute([':company_id' => $companyId]);

    if ($categoryIds === []) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO company_categories (company_id, category_id) VALUES (:company_id, :category_id)'
    );
    foreach ($categoryIds as $categoryId) {
        $insert->execute([
            ':company_id' => $companyId,
            ':category_id' => $categoryId,
        ]);
    }
}

function companies_validate_name(mixed $value): string
{
    if (!is_string($value)) {
        app_error('invalid_name', 'Name must be a string.', 422);
    }

    $name = trim($value);
    if ($name === '' || strlen($name) > 255) {
        app_error('invalid_name', 'Name must be between 1 and 255 characters.', 422);
    }

    return $name;
}

function companies_validate_slug(mixed $value): string
{
    if (!is_string($value)) {
        app_error('invalid_slug', 'Slug must be a string.', 422);
    }

    $slug = strtolower(trim($value));
    if ($slug === '' || strlen($slug) > 255 || preg_match(COMPANIES_SLUG_PATTERN, $slug) !== 1) {
        app_error('invalid_slug', 'Slug must use lowercase letters, numbers and hyphens.', 422);
    }

    return $slug;
}

function companies_validate_optional_string(mixed $value, string $code, int $max): ?string
{
    if ($value === null) {
        return null;
    }
    if (!is_string($value)) {
        app_error($code, 'Value must be a string or null.', 422);
    }

    $text = trim($value);
    if ($text === '') {
        return null;
    }
    if (strlen($text) > $max) {
        app_error($code, 'Value is too long.', 422);
    }

    return $text;
}

function companies_validate_optional_email(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    if (!is_string($value)) {
        app_error('invalid_email', 'Email must be a string or null.', 422);
    }

    $email = auth_normalize_email($value);
    if ($email === '') {
        return null;
    }
    if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        app_error('invalid_email', 'A valid email is required.', 422);
    }

    return $email;
}

function companies_validate_optional_url(mixed $value, string $code): ?string
{
    if ($value === null) {
        return null;
    }
    if (!is_string($value)) {
        app_error($code, 'URL must be a string or null.', 422);
    }

    $url = trim($value);
    if ($url === '') {
        return null;
    }
    if (strlen($url) > COMPANIES_MAX_URL || filter_var($url, FILTER_VALIDATE_URL) === false) {
        app_error($code, 'URL is invalid.', 422);
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if ($scheme !== 'http' && $scheme !== 'https') {
        app_error($code, 'URL is invalid.', 422);
    }

    return $url;
}

function companies_validate_optional_uuid(mixed $value, string $code): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value) || !companies_is_uuid($value)) {
        app_error($code, 'A valid UUID is required.', 422);
    }

    return strtolower($value);
}

function companies_validate_optional_decimal(mixed $value, string $code, float $min, float $max): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_bool($value) || !is_numeric($value)) {
        app_error($code, 'Value must be numeric or null.', 422);
    }

    $number = (float) $value;
    if ($number < $min || $number > $max) {
        app_error($code, 'Value is out of range.', 422);
    }

    return number_format($number, 6, '.', '');
}

function companies_validate_optional_int(mixed $value, string $code, int $min, int $max): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_bool($value) || !is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
        app_error($code, 'Value must be an integer or null.', 422);
    }

    $number = (int) $value;
    if ($number < $min || $number > $max) {
        app_error($code, 'Value is out of range.', 422);
    }

    return $number;
}

function companies_validate_status(mixed $value): string
{
    if (!is_string($value) || !in_array($value, COMPANIES_STATUSES, true)) {
        app_error('invalid_status', 'Status must be active or inactive.', 422);
    }

    return $value;
}

function companies_validate_json_value(mixed $value, string $code, bool $stringArray): mixed
{
    if ($value === null) {
        return null;
    }
    if (!is_array($value)) {
        app_error($code, 'Value must be JSON or null.', 422);
    }
    if ($stringArray) {
        foreach ($value as $item) {
            if (!is_string($item)) {
                app_error($code, 'Value must be an array of strings or null.', 422);
            }
        }
    }

    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || strlen($encoded) > COMPANIES_MAX_TEXT) {
        app_error($code, 'Value is invalid.', 422);
    }

    return $value;
}

/**
 * @return list<string>
 */
function companies_validate_category_ids(mixed $value): array
{
    if ($value === null) {
        return [];
    }
    if (!is_array($value) || ($value !== [] && array_keys($value) !== range(0, count($value) - 1))) {
        app_error('invalid_category_ids', 'category_ids must be an array of UUIDs.', 422);
    }

    $ids = [];
    foreach ($value as $item) {
        $id = companies_require_uuid($item, 'invalid_category_ids', 'category_ids must be an array of UUIDs.');
        $ids[$id] = $id;
    }

    return array_values($ids);
}

/**
 * @param array<string, mixed> $body
 * @return array{fields: array<string, mixed>, category_ids: list<string>|null}
 */
function companies_validated_input(array $body, bool $creating): array
{
    companies_reject_unknown_fields($body);

    $fields = [];

    if ($creating || array_key_exists('name', $body)) {
        $fields['name'] = companies_validate_name($body['name'] ?? null);
    }
    if ($creating || array_key_exists('slug', $body)) {
        $fields['slug'] = companies_validate_slug($body['slug'] ?? null);
    }

    foreach (COMPANIES_TEXT_FIELDS as $field) {
        if (array_key_exists($field, $body)) {
            $fields[$field] = companies_validate_optional_string($body[$field], 'invalid_' . $field, COMPANIES_MAX_TEXT);
        }
    }

    foreach (COMPANIES_SHORT_FIELDS as $field => $max) {
        if (array_key_exists($field, $body)) {
            $fields[$field] = companies_validate_optional_string($body[$field], 'invalid_' . $field, $max);
        }
    }

    if (array_key_exists('email', $body)) {
        $fields['email'] = companies_validate_optional_email($body['email']);
    }
    if (array_key_exists('city_id', $body)) {
        $fields['city_id'] = companies_validate_optional_uuid($body['city_id'], 'invalid_city_id');
    }
    if (array_key_exists('lat', $body)) {
        $fields['lat'] = companies_validate_optional_decimal($body['lat'], 'invalid_lat', -90.0, 90.0);
    }
    if (array_key_exists('lng', $body)) {
        $fields['lng'] = companies_validate_optional_decimal($body['lng'], 'invalid_lng', -180.0, 180.0);
    }
    if (array_key_exists('status', $body)) {
        $fields['status'] = companies_validate_status($body['status']);
    }
    if (array_key_exists('response_rate', $body)) {
        $rate = companies_validate_optional_decimal($body['response_rate'], 'invalid_response_rate', 0.0, 100.0);
        $fields['response_rate'] = $rate === null ? null : number_format((float) $rate, 2, '.', '');
    }

    foreach (COMPANIES_URL_FIELDS as $field) {
        if (array_key_exists($field, $body)) {
            $fields[$field] = companies_validate_optional_url($body[$field], 'invalid_' . $field);
        }
    }

    $intRanges = [
        'founded_year' => [1800, 2100],
        'years_experience' => [0, 200],
        'response_time_minutes' => [0, 10080],
        'services_completed' => [0, 2147483647],
        'clients_served' => [0, 2147483647],
        'price_range' => [0, 32767],
    ];
    foreach (COMPANIES_INT_FIELDS as $field) {
        if (array_key_exists($field, $body)) {
            [$min, $max] = $intRanges[$field];
            $fields[$field] = companies_validate_optional_int($body[$field], 'invalid_' . $field, $min, $max);
        }
    }

    foreach (COMPANIES_JSON_OBJECT_OR_ARRAY as $field) {
        if (array_key_exists($field, $body)) {
            $fields[$field] = companies_validate_json_value($body[$field], 'invalid_' . $field, false);
        }
    }
    foreach (COMPANIES_JSON_STRING_ARRAY as $field) {
        if (array_key_exists($field, $body)) {
            $value = $body[$field];
            if ($field === 'services_offered' && $value === null) {
                $value = [];
            }
            $fields[$field] = companies_validate_json_value($value, 'invalid_' . $field, true);
        }
    }
    if ($creating && !array_key_exists('services_offered', $fields)) {
        $fields['services_offered'] = [];
    }

    $categoryIds = null;
    if ($creating) {
        $categoryIds = companies_validate_category_ids($body['category_ids'] ?? null);
    } elseif (array_key_exists('category_ids', $body)) {
        $categoryIds = companies_validate_category_ids($body['category_ids']);
    }

    if ($creating && $fields === []) {
        app_error('invalid_payload', 'name and slug are required.', 422);
    }

    return [
        'fields' => $fields,
        'category_ids' => $categoryIds,
    ];
}

function companies_assert_unique_slug(PDO $pdo, string $slug, ?string $ignoreId = null): void
{
    $sql = 'SELECT id FROM companies WHERE slug = :slug';
    $params = [':slug' => $slug];
    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $ignoreId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn() !== false) {
        app_error('slug_taken', 'This slug is already in use.', 409);
    }
}

function companies_assert_city(PDO $pdo, ?string $cityId): void
{
    if ($cityId === null) {
        return;
    }
    if (!companies_city_exists($pdo, $cityId)) {
        app_error('invalid_city_id', 'city_id does not exist.', 422);
    }
}

/**
 * @param list<string>|null $categoryIds
 */
function companies_assert_categories(PDO $pdo, ?array $categoryIds): void
{
    if ($categoryIds === null) {
        return;
    }
    if (!companies_categories_exist($pdo, $categoryIds)) {
        app_error('invalid_category_ids', 'One or more category_ids do not exist.', 422);
    }
}

/**
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function companies_bind_writable(array $fields): array
{
    $bound = [];
    foreach ($fields as $column => $value) {
        if (in_array($column, array_merge(COMPANIES_JSON_OBJECT_OR_ARRAY, COMPANIES_JSON_STRING_ARRAY), true)) {
            $bound[$column] = $value === null
                ? null
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            continue;
        }
        $bound[$column] = $value;
    }

    return $bound;
}
