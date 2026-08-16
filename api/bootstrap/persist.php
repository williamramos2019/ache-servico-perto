<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog.php';

/**
 * @return mixed
 */
function persist_json_decode(mixed $value): mixed
{
    if ($value === null) {
        return null;
    }
    if (is_array($value) || is_int($value) || is_float($value) || is_bool($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return null;
    }
    $decoded = json_decode($value, true);

    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
}

function persist_json_encode(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function persist_bool(mixed $value): bool
{
    return (int) $value === 1 || $value === true || $value === '1';
}

function persist_optional_string(mixed $value, int $max = 255): ?string
{
    if ($value === null) {
        return null;
    }
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (strlen($value) > $max) {
        $value = substr($value, 0, $max);
    }

    return $value;
}

function persist_optional_uuid(mixed $value, string $code = 'invalid_id'): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return companies_require_uuid($value, $code, 'ID inválido.');
}

function persist_like(string $raw): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $raw) . '%';
}

function persist_require_admin(): string
{
    return require_role('admin');
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function persist_listing_row(array $row): array
{
    $images = persist_json_decode($row['images'] ?? null);
    if (!is_array($images)) {
        $images = [];
    }
    $images = array_values(array_filter($images, static fn ($item): bool => is_string($item) && $item !== ''));

    return [
        'id' => (string) $row['id'],
        'slug' => (string) $row['slug'],
        'user_id' => (string) $row['user_id'],
        'city_id' => $row['city_id'] !== null ? (string) $row['city_id'] : null,
        'category_slug' => (string) $row['category_slug'],
        'title' => (string) $row['title'],
        'description' => $row['description'] !== null ? (string) $row['description'] : null,
        'price' => $row['price'] !== null ? (float) $row['price'] : null,
        'condition' => (string) $row['condition'],
        'neighborhood' => $row['neighborhood'] !== null ? (string) $row['neighborhood'] : null,
        'contact_phone' => $row['contact_phone'] !== null ? (string) $row['contact_phone'] : null,
        'images' => $images,
        'status' => (string) $row['status'],
        'views_count' => (int) ($row['views_count'] ?? 0),
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function persist_event_row(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'slug' => (string) $row['slug'],
        'title' => (string) $row['title'],
        'description' => $row['description'] !== null ? (string) $row['description'] : null,
        'cover_image' => $row['cover_image'] !== null ? (string) $row['cover_image'] : null,
        'location' => $row['location'] !== null ? (string) $row['location'] : null,
        'city_id' => $row['city_id'] !== null ? (string) $row['city_id'] : null,
        'start_at' => (string) $row['start_at'],
        'end_at' => $row['end_at'] !== null ? (string) $row['end_at'] : null,
        'status' => (string) $row['status'],
        'event_type' => $row['event_type'] !== null ? (string) $row['event_type'] : null,
        'category_id' => $row['category_id'] !== null ? (string) $row['category_id'] : null,
        'ticket_url' => $row['ticket_url'] !== null ? (string) $row['ticket_url'] : null,
        'price_min' => $row['price_min'] !== null ? (float) $row['price_min'] : null,
        'price_max' => $row['price_max'] !== null ? (float) $row['price_max'] : null,
        'created_by' => $row['created_by'] !== null ? (string) $row['created_by'] : null,
        'company_id' => $row['company_id'] !== null ? (string) $row['company_id'] : null,
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function persist_city_embed(array $row): ?array
{
    if (empty($row['city_name'])) {
        return null;
    }

    return [
        'name' => (string) $row['city_name'],
        'slug' => (string) ($row['city_slug'] ?? ''),
    ];
}

function persist_setting_get(PDO $pdo, string $key): mixed
{
    $stmt = $pdo->prepare('SELECT `value` FROM system_settings WHERE `key` = :k LIMIT 1');
    $stmt->execute([':k' => $key]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }

    return persist_json_decode($row['value']);
}

function persist_setting_upsert(PDO $pdo, string $key, mixed $value, bool $public = true): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO system_settings (`key`, `value`, is_public, updated_at)
         VALUES (:k, :v, :p, :u)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), is_public = VALUES(is_public), updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        ':k' => $key,
        ':v' => persist_json_encode($value),
        ':p' => $public ? 1 : 0,
        ':u' => auth_now(),
    ]);
}
