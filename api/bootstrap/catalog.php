<?php

declare(strict_types=1);

require_once __DIR__ . '/companies.php';
require_once __DIR__ . '/mail.php';

const CATALOG_APP_CITY_SLUGS = ['vespasiano', 'sao-jose-da-lapa'];

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
/**
 * @param mixed $raw
 * @return array<string, string>|null
 */
function catalog_decode_hours(mixed $raw): ?array
{
    if (is_array($raw)) {
        $out = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && is_string($value) && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out === [] ? null : $out;
    }
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    return catalog_decode_hours($decoded);
}

/**
 * @return list<string>
 */
function catalog_expand_hour_key(string $key): array
{
    $order = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'];
    if (str_contains($key, '-')) {
        $parts = explode('-', $key, 2);
        $ai = array_search($parts[0], $order, true);
        $bi = array_search($parts[1], $order, true);
        if ($ai !== false && $bi !== false && $ai <= $bi) {
            return array_slice($order, (int) $ai, ((int) $bi - (int) $ai) + 1);
        }
    }

    return [$key];
}

/**
 * true = aberto agora, false = fechado, null = horário ausente ou ilegível.
 */
function catalog_is_open_now(?array $hours): ?bool
{
    if ($hours === null || $hours === []) {
        return null;
    }
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    } catch (Exception $e) {
        return null;
    }
    $map = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];
    $today = $map[(int) $now->format('w')];
    $minutes = ((int) $now->format('G')) * 60 + (int) $now->format('i');
    $intervals = [];
    $hasParseable = false;
    foreach ($hours as $key => $value) {
        if (!is_string($key) || !is_string($value)) {
            continue;
        }
        foreach (preg_split('/\s*,\s*/', $value) ?: [] as $part) {
            if (preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', trim($part), $m) !== 1) {
                continue;
            }
            $hasParseable = true;
            if (!in_array($today, catalog_expand_hour_key($key), true)) {
                continue;
            }
            $intervals[] = [
                ((int) $m[1]) * 60 + (int) $m[2],
                ((int) $m[3]) * 60 + (int) $m[4],
            ];
        }
    }
    if (!$hasParseable) {
        return null;
    }
    if ($intervals === []) {
        return false;
    }
    foreach ($intervals as $pair) {
        if ($minutes >= $pair[0] && $minutes <= $pair[1]) {
            return true;
        }
    }

    return false;
}

function catalog_list_item(array $row): array
{
    $city = null;
    if (!empty($row['city_name']) && !empty($row['city_slug'])) {
        $city = [
            'name' => (string) $row['city_name'],
            'slug' => (string) $row['city_slug'],
        ];
    }
    $hours = catalog_decode_hours($row['hours'] ?? null);
    $phone = isset($row['phone']) && $row['phone'] !== null && trim((string) $row['phone']) !== ''
        ? (string) $row['phone']
        : null;
    $whatsapp = isset($row['whatsapp']) && $row['whatsapp'] !== null && trim((string) $row['whatsapp']) !== ''
        ? (string) $row['whatsapp']
        : null;

    return [
        'id' => (string) $row['id'],
        'slug' => (string) $row['slug'],
        'name' => (string) $row['name'],
        'tagline' => $row['tagline'] !== null ? (string) $row['tagline'] : null,
        'banner_url' => $row['banner_url'] !== null ? (string) $row['banner_url'] : null,
        'logo_url' => $row['logo_url'] !== null ? (string) $row['logo_url'] : null,
        'plan' => $row['plan'] !== null ? (string) $row['plan'] : null,
        'featured' => (int) ($row['featured'] ?? 0) === 1,
        'is_verified' => (int) ($row['is_verified'] ?? 0) === 1,
        'city' => $city,
        'rating' => (float) ($row['rating'] ?? 0),
        'review_count' => (int) ($row['review_count'] ?? 0),
        'phone' => $phone,
        'whatsapp' => $whatsapp,
        'open_now' => catalog_is_open_now($hours),
        'origin' => isset($row['origin']) && $row['origin'] !== null && $row['origin'] !== ''
            ? (string) $row['origin']
            : 'manual',
        'categories' => [],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function catalog_attach_categories(PDO $pdo, array $items): array
{
    if ($items === []) {
        return $items;
    }
    $in = [];
    $params = [];
    foreach ($items as $i => $item) {
        $key = ':id' . $i;
        $in[] = $key;
        $params[$key] = (string) $item['id'];
    }
    $stmt = $pdo->prepare(
        'SELECT cc.company_id, cat.name, cat.slug
         FROM company_categories cc
         INNER JOIN categories cat ON cat.id = cc.category_id
         WHERE cc.company_id IN (' . implode(',', $in) . ')
         ORDER BY cat.sort ASC, cat.name ASC'
    );
    $stmt->execute($params);
    $by = [];
    foreach ($stmt->fetchAll() as $row) {
        $cid = (string) $row['company_id'];
        $by[$cid][] = [
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
        ];
    }
    foreach ($items as &$item) {
        $item['categories'] = $by[(string) $item['id']] ?? [];
    }
    unset($item);

    return $items;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function catalog_items_from_rows(PDO $pdo, array $rows): array
{
    $items = [];
    foreach ($rows as $row) {
        $items[] = catalog_list_item($row);
    }

    return catalog_attach_categories($pdo, $items);
}

function catalog_company_select(): string
{
    return 'c.*, ci.name AS city_name, ci.slug AS city_slug, ci.state AS city_state';
}

/**
 * Named placeholders must be unique: PDO native prepares reject repeating :q.
 *
 * @return array{sql: string, params: array<string, string>}
 */
function catalog_text_search_clause(string $q): array
{
    $like = '%' . $q . '%';

    return [
        'sql' => '(c.name LIKE :q_name OR c.tagline LIKE :q_tagline OR c.description LIKE :q_description
            OR EXISTS (
                SELECT 1 FROM company_categories cc
                INNER JOIN categories cat ON cat.id = cc.category_id
                WHERE cc.company_id = c.id
                  AND (cat.name LIKE :q_cat_name OR cat.slug LIKE :q_cat_slug)
            ))',
        'params' => [
            ':q_name' => $like,
            ':q_tagline' => $like,
            ':q_description' => $like,
            ':q_cat_name' => $like,
            ':q_cat_slug' => $like,
        ],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function catalog_company_categories(PDO $pdo, string $companyId): array
{
    $stmt = $pdo->prepare(
        'SELECT cat.id, cat.name, cat.slug, cat.icon
         FROM company_categories cc
         INNER JOIN categories cat ON cat.id = cc.category_id
         WHERE cc.company_id = :id
         ORDER BY cat.sort ASC, cat.name ASC'
    );
    $stmt->execute([':id' => $companyId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'categories' => [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'icon' => $row['icon'] !== null ? (string) $row['icon'] : null,
            ],
        ];
    }

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function catalog_company_media(PDO $pdo, string $companyId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, url, type, caption, sort FROM company_media
         WHERE company_id = :id ORDER BY sort ASC, created_at ASC'
    );
    $stmt->execute([':id' => $companyId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'id' => (string) $row['id'],
            'url' => (string) $row['url'],
            'type' => (string) $row['type'],
            'caption' => $row['caption'] !== null ? (string) $row['caption'] : null,
            'sort' => (int) $row['sort'],
        ];
    }

    return $out;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function catalog_company_detail(PDO $pdo, array $row): array
{
    $public = companies_public_row($pdo, $row);
    $public['cities'] = null;
    if (!empty($row['city_name'])) {
        $public['cities'] = [
            'name' => (string) $row['city_name'],
            'slug' => (string) $row['city_slug'],
            'state' => (string) ($row['city_state'] ?? ''),
        ];
    }
    $public['company_categories'] = catalog_company_categories($pdo, (string) $row['id']);
    $public['company_media'] = catalog_company_media($pdo, (string) $row['id']);

    return $public;
}

function catalog_refresh_company_rating(PDO $pdo, string $companyId): void
{
    $stmt = $pdo->prepare(
        'SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count
         FROM reviews WHERE company_id = :id'
    );
    $stmt->execute([':id' => $companyId]);
    $row = $stmt->fetch();
    $avg = $row && $row['avg_rating'] !== null ? round((float) $row['avg_rating'], 2) : 0.0;
    $count = $row ? (int) $row['review_count'] : 0;
    $upd = $pdo->prepare(
        'UPDATE companies SET rating = :rating, review_count = :review_count, updated_at = :updated_at
         WHERE id = :id'
    );
    $upd->execute([
        ':rating' => $avg,
        ':review_count' => $count,
        ':updated_at' => auth_now(),
        ':id' => $companyId,
    ]);
}
