<?php

declare(strict_types=1);

require_once __DIR__ . '/domains.php';

/**
 * @return list<array{id: string, name: string, description: string, url: string}>
 */
function shopee_feeds_from_env(): array
{
    $feeds = [];
    for ($i = 1; $i <= 5; $i++) {
        $url = getenv('SHOPEE_FEED_' . $i . '_URL');
        if (!is_string($url) || trim($url) === '') {
            continue;
        }
        $safe = domain_allowed_http_url(trim($url));
        if ($safe === null) {
            continue;
        }
        $name = getenv('SHOPEE_FEED_' . $i . '_NAME');
        $feeds[] = [
            'id' => 'feed-' . $i,
            'name' => is_string($name) && trim($name) !== '' ? trim($name) : ('Datafeed Shopee ' . $i),
            'description' => 'CSV do Portal de Afiliados Shopee. Configure SHOPEE_FEED_' . $i . '_URL no servidor.',
            'url' => $safe,
        ];
    }

    return $feeds;
}

function shopee_sort_sql(string $sort): string
{
    return match ($sort) {
        'rating' => 'item_rating DESC',
        'price_asc' => 'sale_price ASC',
        'price_desc' => 'sale_price DESC',
        default => 'discount_percentage DESC',
    };
}

/**
 * @return list<string>
 */
function shopee_public_columns(): array
{
    return [
        'id', 'itemid', 'title', 'description', 'image_link', 'product_link', 'product_short_link',
        'price', 'sale_price', 'discount_percentage', 'item_rating', 'global_category1', 'global_category2',
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function shopee_public_row(array $row): array
{
    return domain_decode_row(
        $row,
        [],
        ['is_active', 'is_featured'],
        ['itemid'],
        ['price', 'sale_price', 'discount_percentage', 'item_rating']
    );
}

function shopee_optional_decimal(mixed $value, float $min, float $max): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $raw = is_string($value) ? str_replace(['R$', ' ', ','], ['', '', '.'], $value) : $value;
    if (!is_numeric($raw)) {
        return null;
    }
    $number = (float) $raw;
    if ($number < $min || $number > $max) {
        return null;
    }

    return number_format($number, 2, '.', '');
}

function shopee_csv_header_key(string $header): string
{
    $normalized = strtolower(trim($header));
    $normalized = str_replace([' ', '-'], '_', $normalized);

    return match ($normalized) {
        'item_id', 'itemid' => 'itemid',
        'product_name', 'name' => 'title',
        'product_description' => 'description',
        'image', 'image_url', 'image_link' => 'image_link',
        'image_link_3' => 'image_link_3',
        'product_url', 'url', 'product_link' => 'product_link',
        'short_link', 'affiliate_link', 'product_short_link' => 'product_short_link',
        'original_price', 'price' => 'price',
        'price_sale', 'sale_price' => 'sale_price',
        'discount', 'discount_percent', 'discount_percentage' => 'discount_percentage',
        'rating', 'item_rating' => 'item_rating',
        'category', 'global_category1' => 'global_category1',
        'subcategory', 'global_category2' => 'global_category2',
        default => $normalized,
    };
}

/**
 * @param array<string, string> $mapped
 * @return array<string, mixed>|null
 */
function shopee_mapped_product(array $mapped): ?array
{
    $itemid = isset($mapped['itemid']) ? (int) preg_replace('/\D+/', '', $mapped['itemid']) : 0;
    $title = trim((string) ($mapped['title'] ?? ''));
    $link = domain_allowed_http_url($mapped['product_link'] ?? ($mapped['product_short_link'] ?? null));
    if ($itemid < 1 || $title === '' || $link === null) {
        return null;
    }
    $short = domain_allowed_http_url($mapped['product_short_link'] ?? null);
    $image = domain_allowed_http_url($mapped['image_link'] ?? null);
    $image3 = domain_allowed_http_url($mapped['image_link_3'] ?? null);

    return [
        'itemid' => $itemid,
        'title' => mb_substr($title, 0, 512),
        'description' => isset($mapped['description']) ? mb_substr(trim($mapped['description']), 0, 8000) : null,
        'image_link' => $image,
        'image_link_3' => $image3,
        'product_link' => $link,
        'product_short_link' => $short,
        'price' => shopee_optional_decimal($mapped['price'] ?? null, 0, 9999999999.99),
        'sale_price' => shopee_optional_decimal($mapped['sale_price'] ?? null, 0, 9999999999.99),
        'discount_percentage' => shopee_optional_decimal($mapped['discount_percentage'] ?? null, 0, 100),
        'item_rating' => shopee_optional_decimal($mapped['item_rating'] ?? null, 0, 5),
        'global_category1' => domain_string($mapped['global_category1'] ?? null, 'global_category1', 0, 191),
        'global_category2' => domain_string($mapped['global_category2'] ?? null, 'global_category2', 0, 191),
        'global_catid1' => domain_string($mapped['global_catid1'] ?? null, 'global_catid1', 0, 64),
        'global_catid2' => domain_string($mapped['global_catid2'] ?? null, 'global_catid2', 0, 64),
        'global_item_attributes' => domain_string($mapped['global_item_attributes'] ?? null, 'global_item_attributes', 0, 4000),
    ];
}

/**
 * @return array{imported: int, skipped: int}
 */
function shopee_import_csv(PDO $pdo, string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new InvalidArgumentException('Não foi possível ler o CSV.');
    }
    $header = fgetcsv($handle);
    if (!is_array($header) || $header === []) {
        fclose($handle);
        throw new InvalidArgumentException('CSV sem cabeçalho.');
    }
    $keys = array_map('shopee_csv_header_key', $header);
    $imported = 0;
    $skipped = 0;
    $sql = 'INSERT INTO shopee_products (
                id, itemid, title, description, image_link, image_link_3, product_link, product_short_link,
                price, sale_price, discount_percentage, item_rating, global_category1, global_category2,
                global_catid1, global_catid2, global_item_attributes, is_active, is_featured, imported_at, created_at, updated_at
            ) VALUES (
                :id, :itemid, :title, :description, :image_link, :image_link_3, :product_link, :product_short_link,
                :price, :sale_price, :discount_percentage, :item_rating, :global_category1, :global_category2,
                :global_catid1, :global_catid2, :global_item_attributes, 1, 0, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)
            ) ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                image_link = VALUES(image_link),
                image_link_3 = VALUES(image_link_3),
                product_link = VALUES(product_link),
                product_short_link = VALUES(product_short_link),
                price = VALUES(price),
                sale_price = VALUES(sale_price),
                discount_percentage = VALUES(discount_percentage),
                item_rating = VALUES(item_rating),
                global_category1 = VALUES(global_category1),
                global_category2 = VALUES(global_category2),
                global_catid1 = VALUES(global_catid1),
                global_catid2 = VALUES(global_catid2),
                global_item_attributes = VALUES(global_item_attributes),
                is_active = 1,
                imported_at = UTC_TIMESTAMP(3),
                updated_at = UTC_TIMESTAMP(3)';
    $stmt = $pdo->prepare($sql);
    while (($cols = fgetcsv($handle)) !== false) {
        if ($cols === [null] || $cols === false) {
            continue;
        }
        $mapped = [];
        foreach ($keys as $i => $key) {
            $mapped[$key] = isset($cols[$i]) ? (string) $cols[$i] : '';
        }
        $product = shopee_mapped_product($mapped);
        if ($product === null) {
            $skipped++;
            continue;
        }
        $product['id'] = auth_uuid();
        $stmt->execute($product);
        $imported++;
    }
    fclose($handle);

    return ['imported' => $imported, 'skipped' => $skipped];
}
