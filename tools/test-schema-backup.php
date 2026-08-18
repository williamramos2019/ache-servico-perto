<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assertions = 0;

function test_expect(bool $condition, string $message): void
{
    global $failures, $assertions;
    $assertions++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, "[FAIL] $message\n");
    } else {
        fwrite(STDOUT, "[OK] $message\n");
    }
}

function test_migration_sql(string $root, string $version): string
{
    $paths = glob($root . '/database/migrations/' . $version . '_*.sql') ?: [];
    test_expect(count($paths) === 1, "migration $version exists exactly once");

    return count($paths) === 1 ? (string) file_get_contents($paths[0]) : '';
}

function test_has_json_column_default(string $sql): bool
{
    $withoutComments = preg_replace('/--[^\r\n]*/', '', $sql);

    return preg_match('/\bJSON\b(?:(?![,;]).)*\bDEFAULT\b/is', $withoutComments ?? $sql) === 1;
}

test_expect(
    test_has_json_column_default("CREATE TABLE sample (`payload` JSON NOT NULL\n DEFAULT ('{}'));"),
    'JSON default detector covers multiline declarations'
);

$expectedTables = [
    '013' => ['blog_categories', 'editorial_posts', 'event_sync_logs', 'tourist_attractions', 'procurements'],
    '014' => ['coupons', 'ad_campaigns', 'user_requests', 'live_feed_hidden'],
    '015' => ['job_sources', 'jobs', 'job_sync_logs'],
    '016' => ['representatives', 'representative_activities', 'representative_attendance', 'representative_sync_logs', 'whatsapp_subscribers'],
    '017' => ['bus_sync_logs'],
    '018' => ['shopee_products'],
    '019' => [],
];
$expectedColumns = [
    '013' => [
        'profiles' => ['city_id', 'state', 'onboarding_completed_at', 'onboarding_version'],
        'companies' => ['services_offered'],
        'posts' => ['category_id'],
        'events' => ['source', 'source_url', 'external_id', 'dedupe_hash'],
        'public_services' => ['verification_status', 'verified_at', 'verified_source', 'verified_by'],
    ],
    '014' => [
        'promotions' => ['city_id', 'category', 'discount_percent', 'image_url', 'link_url'],
        'push_deliveries' => ['retry_count', 'next_retry_at'],
        'ad_campaigns' => ['route_patterns', 'company_id'],
    ],
    '015' => [
        'jobs' => [
            'is_premium', 'company_id', 'company_logo_url', 'company_size', 'company_culture',
            'requirements', 'nice_to_have', 'benefits', 'responsibilities', 'workload',
            'apply_email', 'apply_whatsapp', 'application_deadline', 'featured_until',
        ],
    ],
    '018' => [
        'shopee_products' => [
            'itemid', 'title', 'product_link', 'sale_price', 'discount_percentage',
            'is_active', 'is_featured', 'global_category1',
        ],
    ],
];

$migrationSql = [];
foreach ($expectedTables as $version => $tables) {
    $sql = test_migration_sql($root, $version);
    $migrationSql[$version] = $sql;
    test_expect(
        preg_match('/\b(?:DROP|TRUNCATE)\b/i', preg_replace('/--[^\r\n]*/', '', $sql)) !== 1,
        "migration $version has no destructive statements"
    );
    test_expect(
        !test_has_json_column_default($sql),
        "migration $version has no JSON column defaults"
    );
    foreach ($tables as $table) {
        test_expect(
            preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?' . preg_quote($table, '/') . '`?/i', $sql) === 1,
            "migration $version creates $table"
        );
    }
    foreach ($expectedColumns[$version] ?? [] as $table => $columns) {
        foreach ($columns as $column) {
            test_expect(
                preg_match('/`' . preg_quote($column, '/') . '`\s+/i', $sql) === 1,
                "migration $version defines $table.$column"
            );
        }
    }
}
test_expect(str_contains($migrationSql['013'], 'information_schema.columns'), 'migration 013 guards retryable deltas');
test_expect(str_contains($migrationSql['014'], 'information_schema.columns'), 'migration 014 guards retryable deltas');
test_expect(str_contains($migrationSql['013'], 'fallback_dedupe_hash'), 'procurements include fallback deduplication');
test_expect(str_contains($migrationSql['014'], 'chk_coupons_discount_percent'), 'coupon discounts enforce 0 to 100');
test_expect(str_contains($migrationSql['014'], 'chk_promotions_discount_percent'), 'promotion discounts enforce 0 to 100');
test_expect(str_contains($migrationSql['018'] ?? '', 'uq_shopee_itemid'), 'shopee products enforce unique itemid');
test_expect(str_contains($migrationSql['019'] ?? '', 'INSERT IGNORE'), 'migration 019 seeds are idempotent');
test_expect(str_contains($migrationSql['019'] ?? '', '7c1a9e20-4b3d-4f8a-9e11-0a1b2c3d4e01'), 'migration 019 uses stable attraction ids');
test_expect(!str_contains($migrationSql['019'] ?? '', 'INSERT INTO `companies`'), 'migration 019 does not insert companies');
test_expect(!str_contains($migrationSql['019'] ?? '', 'INSERT INTO `users`'), 'migration 019 does not insert users');

$backupFile = $root . '/api/bootstrap/backup.php';
test_expect(is_file($backupFile), 'backup service module exists');
if (is_file($backupFile)) {
    require_once $backupFile;
    $allowlist = backup_table_allowlist();
    test_expect(in_array('users', $allowlist, true), 'backup allowlist includes users');
    test_expect(in_array('shopee_products', $allowlist, true), 'backup allowlist includes shopee_products');
    test_expect(!in_array('migrations; DROP TABLE users', $allowlist, true), 'backup allowlist rejects arbitrary table input');

    $valid = [
        'manifest' => [
            'format' => 'agendaqui-backup',
            'schema_version' => '018',
            'created_at' => '2026-08-16T21:00:00.000Z',
            'counts' => ['cities' => 1],
        ],
        'tables' => ['cities' => [['id' => '00000000-0000-4000-8000-000000000001']]],
    ];
    test_expect(backup_validate_document($valid, 1024 * 1024)['schema_version'] === '018', 'backup manifest validates');

    $invalidTable = $valid;
    $invalidTable['tables']['not_allowed'] = [];
    try {
        backup_validate_document($invalidTable, 1024 * 1024);
        test_expect(false, 'backup rejects non-allowlisted tables');
    } catch (InvalidArgumentException $e) {
        test_expect(true, 'backup rejects non-allowlisted tables');
    }

    $invalidCount = $valid;
    $invalidCount['manifest']['counts']['cities'] = 2;
    try {
        backup_validate_document($invalidCount, 1024 * 1024);
        test_expect(false, 'backup rejects manifest count mismatch');
    } catch (InvalidArgumentException $e) {
        test_expect(true, 'backup rejects manifest count mismatch');
    }

    test_expect(function_exists('backup_column_is_generated'), 'backup can identify generated columns');
    if (function_exists('backup_column_is_generated')) {
        test_expect(
            backup_column_is_generated(['Extra' => 'STORED GENERATED']),
            'backup excludes generated columns from restore'
        );
    }

    $invalidDiscount = $valid;
    $invalidDiscount['manifest']['counts'] = ['coupons' => 1];
    $invalidDiscount['tables'] = ['coupons' => [['id' => 'coupon-1', 'discount_percent' => 101]]];
    try {
        backup_validate_document($invalidDiscount, 1024 * 1024);
        test_expect(false, 'backup rejects discounts outside 0 to 100');
    } catch (InvalidArgumentException $e) {
        test_expect(true, 'backup rejects discounts outside 0 to 100');
    }

    test_expect(function_exists('backup_upsert_plan'), 'backup provides primary-key upsert planning');
    if (function_exists('backup_upsert_plan')) {
        $plan = backup_upsert_plan(
            'users',
            ['id' => 'user-1', 'email' => 'admin@example.test', 'password_hash' => 'hash'],
            ['id']
        );
        test_expect(str_contains($plan['update_sql'], 'WHERE `id` = :pk_id'), 'backup updates rows by primary key');
        test_expect(!str_contains($plan['update_sql'], 'SET `id` ='), 'backup never updates primary keys');
    }
}

$companiesFile = $root . '/api/bootstrap/companies.php';
require_once $companiesFile;
test_expect(in_array('services_offered', COMPANIES_ALLOWED_FIELDS, true), 'company writes allow services_offered');
test_expect(in_array('services_offered', COMPANIES_JSON_STRING_ARRAY, true), 'company services are validated as a string array');
$boundServices = companies_bind_writable(['services_offered' => ['Instalação', 'Manutenção']]);
test_expect(
    $boundServices['services_offered'] === '["Instalação","Manutenção"]',
    'company services are encoded for MySQL JSON'
);
$normalizedServices = companies_validated_input(['services_offered' => null], false);
test_expect($normalizedServices['fields']['services_offered'] === [], 'null company services normalize to an empty array');
$newCompanyDefaults = companies_validated_input(['name' => 'Empresa', 'slug' => 'empresa'], true);
test_expect($newCompanyDefaults['fields']['services_offered'] === [], 'new companies default services to an empty array');

if ($failures !== []) {
    fwrite(STDERR, sprintf("\n%d/%d assertions failed.\n", count($failures), $assertions));
    exit(1);
}

fwrite(STDOUT, sprintf("\nAll %d assertions passed.\n", $assertions));
