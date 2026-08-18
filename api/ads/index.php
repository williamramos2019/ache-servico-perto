<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/domains.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['ad_campaigns', 'analytics_events']);
$method = app_request_method();
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';

if ($method === 'GET') {
    if ($op === 'admin' || $op === 'stats') {
        persist_require_admin();
        $rows = $pdo->query('SELECT * FROM ad_campaigns ORDER BY created_at DESC LIMIT 500')->fetchAll();
    } elseif ($op === 'list' || $op === 'show') {
        $where = [
            'a.active = 1',
            '(a.starts_at IS NULL OR a.starts_at <= UTC_TIMESTAMP(3))',
            '(a.ends_at IS NULL OR a.ends_at >= UTC_TIMESTAMP(3))',
        ];
        $params = [];
        $city = domain_string($_GET['city'] ?? null, 'city', 0, 191);
        if ($city !== null) {
            $where[] = '(a.city_slug IS NULL OR a.city_slug = :city)';
            $params[':city'] = $city;
        }
        if ($op === 'show') {
            $where[] = 'a.id = :id';
            $params[':id'] = domain_uuid($_GET['id'] ?? null, 'id', false);
        }
        $limit = $op === 'show' ? 1 : domain_limit($_GET, 20, 50);
        $stmt = $pdo->prepare(
            'SELECT a.* FROM ad_campaigns a WHERE ' . implode(' AND ', $where) .
            ' ORDER BY a.weight DESC, a.created_at DESC LIMIT ' . $limit
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } else {
        app_error('invalid_op', 'Operação inválida.', 400);
    }
    $rows = array_map(
        static fn (array $r): array => domain_decode_row(
            $r,
            ['route_patterns'],
            ['active'],
            ['delay_seconds', 'scroll_trigger_percent', 'display_seconds', 'weight', 'impressions', 'clicks']
        ),
        $rows
    );
    app_success($op === 'show' ? ['campaign' => $rows[0] ?? null] : ['rows' => $rows]);
}

$body = domain_read_json();
$op = is_string($body['op'] ?? null) ? $body['op'] : $op;
if ($op === 'track') {
    rate_limit_hit('ad_track_ip', security_client_ip(), 120, 60);
    $id = domain_uuid($body['id'] ?? ($body['campaign_id'] ?? null), 'campaign_id', false);
    $event = domain_ad_event($body['event'] ?? null);
    if ($event === null) {
        app_error('invalid_event', 'event must be impression or click.', 422);
    }
    $column = $event === 'click' ? 'clicks' : 'impressions';
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            "UPDATE ad_campaigns SET `$column` = `$column` + 1
             WHERE id = :id AND active = 1
               AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP(3))
               AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP(3))"
        );
        $update->execute([':id' => $id]);
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            app_error('not_found', 'Active campaign not found.', 404);
        }
        $pdo->prepare(
            'INSERT INTO analytics_events (name, entity_type, entity_id, user_id, meta, created_at)
             VALUES (:name, :type, :id, :user, :meta, :created)'
        )->execute([
            ':name' => 'ad_' . $event,
            ':type' => 'ad_campaign',
            ':id' => $id,
            ':user' => auth_user_id(),
            ':meta' => json_encode(['ip_hash' => hash('sha256', security_client_ip())]),
            ':created' => auth_now(),
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    app_success(['ok' => true, 'event' => $event]);
}

domain_require_admin_write();
$id = domain_uuid($body['id'] ?? null, 'id');
if ($op === 'delete') {
    domain_delete($pdo, 'ad_campaigns', $id);
    app_success(['ok' => true]);
}
if ($op !== 'save') {
    app_error('invalid_op', 'Operação inválida.', 400);
}
$image = domain_optional_url($body['image_url'] ?? null, 'image_url');
$link = domain_optional_url($body['link_url'] ?? null, 'link_url');
if ($image === null || $link === null) {
    app_error('invalid_url', 'image_url and link_url are required.', 422);
}
$fields = [
    'name' => domain_string($body['name'] ?? null, 'name', 2, 255, false),
    'image_url' => $image,
    'link_url' => $link,
    'city_slug' => domain_string($body['city_slug'] ?? null, 'city_slug', 0, 191),
    'placement' => domain_enum($body['placement'] ?? 'bottom-right', 'placement', ['bottom-right', 'bottom-center', 'center'], 'bottom-right'),
    'delay_seconds' => domain_int($body['delay_seconds'] ?? 5, 'delay_seconds', 0, 60, 5),
    'scroll_trigger_percent' => domain_int($body['scroll_trigger_percent'] ?? 0, 'scroll_trigger_percent', 0, 100),
    'display_seconds' => domain_int($body['display_seconds'] ?? 7, 'display_seconds', 3, 60, 7),
    'active' => domain_bool($body['active'] ?? null, true),
    'starts_at' => domain_date($body['starts_at'] ?? null, 'starts_at'),
    'ends_at' => domain_date($body['ends_at'] ?? null, 'ends_at'),
    'weight' => domain_int($body['weight'] ?? 1, 'weight', 1, 100, 1),
    'route_patterns' => domain_json_array($body['route_patterns'] ?? [], 'route_patterns', 100),
    'company_id' => domain_uuid($body['company_id'] ?? null, 'company_id'),
];
app_success(['id' => domain_upsert($pdo, 'ad_campaigns', $fields, $id)], $id === null ? 201 : 200);
