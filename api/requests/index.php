<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/domains.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['user_requests']);
$method = app_request_method();
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : ($method === 'POST' ? 'create' : 'list');
$categories = ['duvida', 'sugestao', 'parceria', 'orcamento', 'cadastro_empresa', 'cadastro_evento', 'imprensa', 'elogio', 'reclamacao', 'outro'];
$statuses = ['novo', 'em_analise', 'respondido', 'resolvido', 'arquivado'];
$priorities = ['baixa', 'media', 'alta', 'critica'];

if ($method === 'GET') {
    persist_require_admin();
    $limit = domain_limit($_GET, 100, 200);
    $where = ['1=1'];
    $params = [];
    foreach (['status' => $statuses, 'category' => $categories, 'priority' => $priorities] as $field => $allowed) {
        $value = domain_string($_GET[$field] ?? null, $field, 0, 32);
        if ($value !== null) {
            if (!in_array($value, $allowed, true)) {
                app_error('invalid_' . $field, $field . ' is invalid.', 422);
            }
            $where[] = "`$field` = :$field";
            $params[':' . $field] = $value;
        }
    }
    $search = domain_string($_GET['search'] ?? null, 'search', 0, 200);
    if ($search !== null) {
        $like = domain_or_like(['subject', 'request_number'], persist_like($search), 'search');
        $where[] = $like['sql'];
        $params = array_merge($params, $like['params']);
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM user_requests WHERE ' . implode(' AND ', $where) .
        ' ORDER BY created_at DESC LIMIT ' . $limit
    );
    $stmt->execute($params);
    $stats = $pdo->query(
        "SELECT COUNT(*) AS total,
         SUM(status = 'novo') AS novos,
         SUM(status = 'resolvido') AS resolvidos,
         SUM(created_at >= UTC_DATE()) AS hoje FROM user_requests"
    )->fetch();
    app_success(['rows' => array_map(
        static fn (array $r): array => domain_decode_row($r, ['extra']),
        $stmt->fetchAll()
    ), 'stats' => domain_decode_row($stats ?: [], [], [], ['total', 'novos', 'resolvidos', 'hoje'])]);
}

$body = domain_read_json();
$op = is_string($body['op'] ?? null) ? $body['op'] : $op;
require_csrf();
if ($op === 'create') {
    rate_limit_hit('request_ip', security_client_ip(), 5, 3600);
    $email = companies_validate_optional_email($body['user_email'] ?? null);
    $pageUrl = domain_optional_url($body['page_url'] ?? null, 'page_url');
    $attachment = domain_optional_url($body['attachment_url'] ?? null, 'attachment_url');
    $extra = $body['extra'] ?? [];
    if (!is_array($extra) || array_is_list($extra)) {
        app_error('invalid_extra', 'extra must be an object.', 422);
    }
    $number = 'AQ-' . gmdate('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $id = auth_uuid();
    $now = auth_now();
    $pdo->prepare(
        'INSERT INTO user_requests
         (id, request_number, category, subject, description, page_url, attachment_url, user_id,
          user_name, user_email, user_phone, city_id, status, priority, ip, extra, created_at, updated_at)
         VALUES
         (:id, :number, :category, :subject, :description, :page_url, :attachment_url, :user_id,
          :user_name, :user_email, :user_phone, :city_id, :status, :priority, :ip, :extra, :created, :updated)'
    )->execute([
        ':id' => $id,
        ':number' => $number,
        ':category' => domain_enum($body['category'] ?? 'outro', 'category', $categories, 'outro'),
        ':subject' => domain_string($body['subject'] ?? null, 'subject', 3, 255, false),
        ':description' => domain_string($body['description'] ?? null, 'description', 5, 5000, false),
        ':page_url' => $pageUrl,
        ':attachment_url' => $attachment,
        ':user_id' => auth_user_id(),
        ':user_name' => domain_string($body['user_name'] ?? null, 'user_name', 0, 255),
        ':user_email' => $email,
        ':user_phone' => domain_string($body['user_phone'] ?? null, 'user_phone', 0, 64),
        ':city_id' => domain_uuid($body['city_id'] ?? null, 'city_id'),
        ':status' => 'novo',
        ':priority' => 'media',
        ':ip' => security_client_ip(),
        ':extra' => json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':created' => $now,
        ':updated' => $now,
    ]);
    app_success(['id' => $id, 'request_number' => $number], 201);
}

$userId = persist_require_admin();
rate_limit_authenticated_write($userId);
if ($op === 'update') {
    $id = domain_uuid($body['id'] ?? null, 'id', false);
    $status = domain_enum($body['status'] ?? 'novo', 'status', $statuses, 'novo');
    $priority = domain_enum($body['priority'] ?? 'media', 'priority', $priorities, 'media');
    $pdo->prepare(
        'UPDATE user_requests SET status = :status, priority = :priority, admin_response = :response,
         assigned_to = :assigned, resolved_at = :resolved, updated_at = :updated WHERE id = :id'
    )->execute([
        ':status' => $status,
        ':priority' => $priority,
        ':response' => domain_string($body['admin_response'] ?? null, 'admin_response', 0, 5000),
        ':assigned' => domain_uuid($body['assigned_to'] ?? null, 'assigned_to'),
        ':resolved' => $status === 'resolvido' ? auth_now() : null,
        ':updated' => auth_now(),
        ':id' => $id,
    ]);
    app_success(['ok' => true]);
}
if ($op === 'delete') {
    domain_delete($pdo, 'user_requests', $body['id'] ?? null);
    app_success(['ok' => true]);
}
app_error('invalid_op', 'Operação inválida.', 400);
