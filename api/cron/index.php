<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/cron.php';

app_start(['POST', 'OPTIONS']);
$cronAuthorized = domain_cron_secret_valid(
    trim((string) getenv('CRON_SHARED_SECRET')),
    trim((string) ($_SERVER['HTTP_X_CRON_SECRET'] ?? ''))
);
domain_enforce_endpoint_policy(
    domain_endpoint_policy(app_request_method(), ['POST'], 'cron', $cronAuthorized)
);
$pdo = db_pdo(false);
$body = domain_read_json();
$op = domain_enum(
    $body['op'] ?? '',
    'op',
    ['jobs-sync', 'representatives-sync', 'bus-sync', 'whatsapp-welcome', 'whatsapp-digest', 'scheduled-hooks'],
    ''
);

if ($op === 'jobs-sync') {
    $sourceId = domain_uuid($body['source_id'] ?? null, 'source_id');
    app_success(cron_jobs_due($pdo, $sourceId));
}
if ($op === 'whatsapp-welcome') {
    app_success(cron_whatsapp_welcome($pdo, domain_int($body['limit'] ?? 50, 'limit', 1, 100, 50)));
}
if ($op === 'whatsapp-digest') {
    app_success(cron_whatsapp_digest($pdo, domain_int($body['limit'] ?? 200, 'limit', 1, 500, 200)));
}
if ($op === 'scheduled-hooks') {
    app_success([
        'welcome' => cron_whatsapp_welcome($pdo, 50),
        'digest' => cron_whatsapp_digest($pdo, 200),
    ]);
}
if ($op === 'representatives-sync') {
    $source = domain_enum($body['source'] ?? '', 'source', REPRESENTATIVE_IMPORT_SOURCES, '');
    $cityId = domain_uuid($body['city_id'] ?? null, 'city_id');
    $items = $body['items'] ?? null;
    if (!is_array($items) || !array_is_list($items) || count($items) > 500) {
        app_error('invalid_items', 'items must be a list with at most 500 entries.', 422);
    }
    $requestKey = domain_string($body['idempotency_key'] ?? null, 'idempotency_key', 1, 191, false);
    app_success(cron_representatives_sync($pdo, $source, $cityId, $items, $requestKey));
}
if ($op === 'bus-sync') {
    $citySlug = domain_enum($body['city_slug'] ?? '', 'city_slug', WHATSAPP_CITY_SLUGS, '');
    $sourceName = domain_string($body['source_name'] ?? null, 'source_name', 1, 255, false);
    $sourceUrl = domain_optional_url($body['source_url'] ?? null, 'source_url');
    $items = $body['items'] ?? null;
    if ($sourceUrl === null || !is_array($items) || !array_is_list($items) || count($items) > 500) {
        app_error('invalid_bus_sync', 'source_url and up to 500 items are required.', 422);
    }
    $requestKey = domain_string($body['idempotency_key'] ?? null, 'idempotency_key', 1, 191, false);
    app_success(cron_bus_sync($pdo, $citySlug, $sourceName, $sourceUrl, $items, $requestKey));
}
app_error('invalid_op', 'Operação inválida.', 400);
