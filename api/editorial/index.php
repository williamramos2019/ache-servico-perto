<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/domains.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['editorial_posts']);
$method = app_request_method();
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';
$statuses = ['planejado', 'producao', 'agendado', 'publicado', 'cancelado'];

if ($method === 'GET') {
    persist_require_admin();
    if ($op === 'ai_status') {
        $configured = trim((string) getenv('EDITORIAL_AI_PROVIDER')) !== ''
            && trim((string) getenv('EDITORIAL_AI_API_KEY')) !== '';
        app_success([
            'available' => false,
            'configured' => $configured,
            'state' => $configured ? 'disabled' : 'unavailable',
            'message' => $configured
                ? 'AI generation is intentionally disabled until a provider adapter is approved.'
                : 'AI provider is not configured.',
        ]);
    }
    if ($op !== 'list') {
        app_error('invalid_op', 'Operação inválida.', 400);
    }
    $month = domain_string($_GET['month'] ?? gmdate('Y-m'), 'month', 7, 7, false);
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
        app_error('invalid_month', 'month must use YYYY-MM.', 422);
    }
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = $pdo->prepare(
        'SELECT * FROM editorial_posts WHERE publish_date BETWEEN :start AND :end
         ORDER BY publish_date, created_at LIMIT 500'
    );
    $stmt->execute([':start' => $start, ':end' => $end]);
    app_success(['rows' => array_map(
        static fn (array $r): array => domain_decode_row($r, ['tags']),
        $stmt->fetchAll()
    )]);
}

$body = domain_read_json();
$op = is_string($body['op'] ?? null) ? $body['op'] : $op;
domain_require_admin_write();
if ($op === 'ai_generate') {
    app_error('provider_unavailable', 'Editorial AI provider is unavailable.', 503);
}
if ($op === 'delete') {
    domain_delete($pdo, 'editorial_posts', $body['id'] ?? null);
    app_success(['ok' => true]);
}
if ($op !== 'save') {
    app_error('invalid_op', 'Operação inválida.', 400);
}
$id = domain_uuid($body['id'] ?? null, 'id');
$fields = [
    'publish_date' => domain_date($body['publish_date'] ?? null, 'publish_date', true),
    'theme' => domain_string($body['theme'] ?? null, 'theme', 2, 10000, false),
    'format' => domain_string($body['format'] ?? 'Reels', 'format', 1, 128, false),
    'caption' => domain_string($body['caption'] ?? null, 'caption', 2, 20000, false),
    'status' => domain_enum($body['status'] ?? 'planejado', 'status', $statuses, 'planejado'),
    'campaign' => domain_string($body['campaign'] ?? null, 'campaign', 0, 2000),
    'city' => domain_string($body['city'] ?? null, 'city', 0, 255),
    'company_id' => domain_uuid($body['company_id'] ?? null, 'company_id'),
    'tags' => domain_json_array($body['tags'] ?? [], 'tags', 50),
    'notes' => domain_string($body['notes'] ?? null, 'notes', 0, 10000),
];
app_success(['id' => domain_upsert($pdo, 'editorial_posts', $fields, $id)], $id === null ? 201 : 200);
