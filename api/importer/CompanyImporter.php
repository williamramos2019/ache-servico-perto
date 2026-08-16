<?php

declare(strict_types=1);

const IMPORTER_PROTECTED_COLUMNS = [
    'owner_id', 'plan', 'plan_expires_at', 'featured', 'is_verified',
    'rating', 'review_count', 'views_count', 'whatsapp', 'logo_url', 'banner_url',
    'video_url', 'hours', 'status', 'origin', 'description', 'tagline',
];

/**
 * Public fields that --update may fill only when the current value is empty.
 *
 * @param array<string, mixed> $existing
 * @param array<string, mixed> $record
 * @return array<string, mixed>
 */
function importer_allowed_fill(array $existing, array $record): array
{
    $fillable = [
        'cnpj' => $record['cnpj'] ?? null,
        'legal_name' => $record['legal_name'] ?? null,
        'cnae_primary' => $record['cnae_primary'] ?? null,
        'neighborhood' => $record['neighborhood'] ?? null,
        'address' => $record['address'] ?? null,
        'zip' => $record['zip'] ?? null,
        'email' => $record['email'] ?? null,
        'phone' => $record['phone'] ?? null,
    ];
    $out = [];
    foreach ($fillable as $col => $value) {
        if (in_array($col, IMPORTER_PROTECTED_COLUMNS, true)) {
            continue;
        }
        if ($value === null || $value === '') {
            continue;
        }
        $current = $existing[$col] ?? null;
        if ($current !== null && trim((string) $current) !== '') {
            continue;
        }
        $out[$col] = $value;
    }

    return $out;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function importer_protected_snapshot(array $row): array
{
    $snap = [];
    foreach (IMPORTER_PROTECTED_COLUMNS as $col) {
        $snap[$col] = $row[$col] ?? null;
    }

    return $snap;
}

/**
 * @param array<string, mixed> $record
 * @param array<string, mixed>|null $existing
 * @return array{action: string, company_id: ?string}
 */
function importer_persist_company(
    PDO $pdo,
    array $record,
    string $cityId,
    string $citySlug,
    ?string $categoryId,
    bool $allowUpdate,
    bool $dryRun,
    ?array $existing,
    string $match = 'none'
): array {
    if ($existing !== null) {
        if (($match ?? '') === 'name_city_candidate') {
            return ['action' => 'candidate', 'company_id' => (string) $existing['id']];
        }
        if (!$allowUpdate) {
            return ['action' => 'duplicate', 'company_id' => (string) $existing['id']];
        }
        if ($dryRun) {
            return ['action' => 'update', 'company_id' => (string) $existing['id']];
        }
        importer_fill_empty_fields($pdo, $existing, $record, $categoryId);

        return ['action' => 'update', 'company_id' => (string) $existing['id']];
    }

    $id = auth_uuid();
    $now = auth_now();
    if ($dryRun) {
        return ['action' => 'insert', 'company_id' => $id];
    }
    $slug = importer_unique_slug($pdo, importer_slugify((string) $record['name'], $citySlug));

    $stmt = $pdo->prepare(
        'INSERT INTO companies (
            id, owner_id, slug, name, legal_name, cnpj, cnae_primary, tagline, description,
            phone, whatsapp, email, address, neighborhood, zip, city_id, lat, lng,
            website, instagram, facebook, tiktok, youtube, hours, logo_url, banner_url,
            video_url, tour_360_url, catalog_url, pricebook_url, portfolio_pdf_url,
            plan, plan_expires_at, featured, status, origin, is_verified,
            rating, review_count, views_count, created_at, updated_at
        ) VALUES (
            :id, NULL, :slug, :name, :legal_name, :cnpj, :cnae_primary, NULL, NULL,
            :phone, NULL, :email, :address, :neighborhood, :zip, :city_id, NULL, NULL,
            NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
            NULL, NULL, NULL, NULL, NULL,
            :plan, NULL, 0, :status, :origin, 0,
            0, 0, 0, :created_at, :updated_at
        )'
    );
    $stmt->execute([
        ':id' => $id,
        ':slug' => $slug,
        ':name' => (string) $record['name'],
        ':legal_name' => $record['legal_name'] ?? null,
        ':cnpj' => $record['cnpj'] ?? null,
        ':cnae_primary' => $record['cnae_primary'] ?? null,
        ':phone' => $record['phone'] ?? null,
        ':email' => $record['email'] ?? null,
        ':address' => $record['address'] ?? null,
        ':neighborhood' => $record['neighborhood'] ?? null,
        ':zip' => $record['zip'] ?? null,
        ':city_id' => $cityId,
        ':plan' => 'free',
        ':status' => 'active',
        ':origin' => 'imported',
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    if ($categoryId !== null) {
        $link = $pdo->prepare(
            'INSERT IGNORE INTO company_categories (company_id, category_id) VALUES (:company_id, :category_id)'
        );
        $link->execute([':company_id' => $id, ':category_id' => $categoryId]);
    }

    return ['action' => 'insert', 'company_id' => $id];
}

/**
 * @param array<string, mixed> $existing
 * @param array<string, mixed> $record
 */
function importer_fill_empty_fields(PDO $pdo, array $existing, array $record, ?string $categoryId): void
{
    $set = [];
    $params = [':id' => (string) $existing['id'], ':updated_at' => auth_now()];
    $fillable = importer_allowed_fill($existing, $record);
    foreach ($fillable as $col => $value) {
        $set[] = "`$col` = :$col";
        $params[":$col"] = $value;
    }
    if ($set !== []) {
        $sql = 'UPDATE companies SET ' . implode(', ', $set) . ', updated_at = :updated_at WHERE id = :id';
        $pdo->prepare($sql)->execute($params);
    }
    if ($categoryId !== null) {
        $exists = $pdo->prepare(
            'SELECT 1 FROM company_categories WHERE company_id = :c AND category_id = :cat LIMIT 1'
        );
        $exists->execute([':c' => (string) $existing['id'], ':cat' => $categoryId]);
        if ($exists->fetchColumn() === false) {
            $count = $pdo->prepare('SELECT COUNT(*) FROM company_categories WHERE company_id = :c');
            $count->execute([':c' => (string) $existing['id']]);
            if ((int) $count->fetchColumn() === 0) {
                $pdo->prepare(
                    'INSERT INTO company_categories (company_id, category_id) VALUES (:company_id, :category_id)'
                )->execute([':company_id' => (string) $existing['id'], ':category_id' => $categoryId]);
            }
        }
    }
}

function importer_record_source(
    PDO $pdo,
    string $companyId,
    ?string $runId,
    string $sourceType,
    array $record,
    bool $dryRun
): void {
    if ($dryRun) {
        return;
    }
    $externalId = $record['external_id'] ?? $record['cnpj'] ?? null;
    $hash = hash('sha256', json_encode($record['raw'] ?? $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    $now = auth_now();
    $stmt = $pdo->prepare(
        'INSERT INTO company_sources
            (id, company_id, run_id, source_name, source_url, source_type, external_id, source_hash, collected_at, created_at, updated_at)
         VALUES
            (:id, :company_id, :run_id, :source_name, :source_url, :source_type, :external_id, :source_hash, :collected_at, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            company_id = VALUES(company_id),
            run_id = VALUES(run_id),
            source_hash = VALUES(source_hash),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        ':id' => auth_uuid(),
        ':company_id' => $companyId,
        ':run_id' => $runId,
        ':source_name' => $sourceType === 'receita' ? 'Receita Federal / dados abertos CNPJ' : 'Cadastro municipal',
        ':source_url' => $record['source_url'] ?? null,
        ':source_type' => $sourceType,
        ':external_id' => is_string($externalId) ? $externalId : null,
        ':source_hash' => $hash,
        ':collected_at' => $now,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}
