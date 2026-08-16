<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/persist.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();

$pdo = db_pdo(false);
$method = app_request_method();
$op = isset($_GET['op']) ? (string) $_GET['op'] : '';

function ops_json_col(mixed $value): mixed
{
    return persist_json_decode($value);
}

if ($method === 'GET') {
    if ($op === 'prefs' || $op === 'inbox' || $op === 'inbox_count') {
        $userId = require_auth();
        if ($op === 'prefs') {
            $stmt = $pdo->prepare('SELECT * FROM notification_preferences WHERE user_id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch();
            app_success(['prefs' => $row === false ? null : $row]);
        }
        if ($op === 'inbox_count') {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM push_inbox WHERE user_id = :id AND read_at IS NULL AND archived_at IS NULL'
            );
            $stmt->execute([':id' => $userId]);
            app_success(['count' => (int) $stmt->fetchColumn()]);
        }
        $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'all';
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
        $sql = 'SELECT i.id, i.received_at, i.read_at, i.favorite_at, i.archived_at,
                       n.id AS n_id, n.title, n.body, n.icon_url, n.image_url, n.url, n.category, n.emoji, n.color, n.created_at AS n_created
                FROM push_inbox i
                INNER JOIN push_notifications n ON n.id = i.notification_id
                WHERE i.user_id = :user_id';
        if ($tab === 'unread') {
            $sql .= ' AND i.read_at IS NULL AND i.archived_at IS NULL';
        } elseif ($tab === 'read') {
            $sql .= ' AND i.read_at IS NOT NULL AND i.archived_at IS NULL';
        } elseif ($tab === 'favorites') {
            $sql .= ' AND i.favorite_at IS NOT NULL';
        } elseif ($tab === 'archived') {
            $sql .= ' AND i.archived_at IS NOT NULL';
        } else {
            $sql .= ' AND i.archived_at IS NULL';
        }
        $sql .= " ORDER BY i.received_at DESC LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $rows = [];
        $q = isset($_GET['q']) ? mb_strtolower(trim((string) $_GET['q'])) : '';
        foreach ($stmt->fetchAll() as $row) {
            if ($q !== '') {
                $hay = mb_strtolower((string) $row['title'] . ' ' . (string) $row['body']);
                if (!str_contains($hay, $q)) {
                    continue;
                }
            }
            $rows[] = [
                'id' => (int) $row['id'],
                'received_at' => (string) $row['received_at'],
                'read_at' => $row['read_at'] !== null ? (string) $row['read_at'] : null,
                'favorite_at' => $row['favorite_at'] !== null ? (string) $row['favorite_at'] : null,
                'archived_at' => $row['archived_at'] !== null ? (string) $row['archived_at'] : null,
                'notification' => [
                    'id' => (string) $row['n_id'],
                    'title' => (string) $row['title'],
                    'body' => (string) $row['body'],
                    'icon_url' => $row['icon_url'] !== null ? (string) $row['icon_url'] : null,
                    'image_url' => $row['image_url'] !== null ? (string) $row['image_url'] : null,
                    'url' => $row['url'] !== null ? (string) $row['url'] : null,
                    'category' => (string) $row['category'],
                    'emoji' => $row['emoji'] !== null ? (string) $row['emoji'] : null,
                    'color' => $row['color'] !== null ? (string) $row['color'] : null,
                    'created_at' => (string) $row['n_created'],
                ],
            ];
        }
        app_success(['inbox' => $rows]);
    }

    persist_require_admin();

    if ($op === 'push_list') {
        $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
        $stmt = $pdo->query(
            "SELECT id, title, body, category, status, sent_at, created_at, sent_count, delivered_count, opened_count, clicked_count, failed_count, audience
             FROM push_notifications ORDER BY created_at DESC LIMIT $limit"
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['audience'] = ops_json_col($row['audience']);
            $rows[] = $row;
        }
        app_success(['rows' => $rows]);
    }

    if ($op === 'push_get') {
        $id = companies_require_uuid($_GET['id'] ?? null, 'invalid_id', 'ID inválido.');
        $stmt = $pdo->prepare('SELECT * FROM push_notifications WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $notif = $stmt->fetch();
        if ($notif === false) {
            app_error('not_found', 'Envio não encontrado.', 404);
        }
        $notif['audience'] = ops_json_col($notif['audience']);
        $notif['buttons'] = ops_json_col($notif['buttons']);
        $del = $pdo->prepare('SELECT status, device, browser FROM push_deliveries WHERE notification_id = :id');
        $del->execute([':id' => $id]);
        $byDevice = [];
        $byBrowser = [];
        $deliveries = $del->fetchAll();
        foreach ($deliveries as $d) {
            $dev = $d['device'] !== null ? (string) $d['device'] : 'unknown';
            $br = $d['browser'] !== null ? (string) $d['browser'] : 'unknown';
            $byDevice[$dev] = ($byDevice[$dev] ?? 0) + 1;
            $byBrowser[$br] = ($byBrowser[$br] ?? 0) + 1;
        }
        app_success([
            'notification' => $notif,
            'byDevice' => $byDevice,
            'byBrowser' => $byBrowser,
            'totalDeliveries' => count($deliveries),
        ]);
    }

    if ($op === 'push_stats') {
        $subs = $pdo->query('SELECT user_id FROM push_subscriptions')->fetchAll();
        $unique = [];
        foreach ($subs as $s) {
            $unique[(string) $s['user_id']] = true;
        }
        $pwa = (int) $pdo->query('SELECT COUNT(*) FROM push_subscriptions WHERE is_pwa = 1')->fetchColumn();
        $companies = (int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn();
        $premium = (int) $pdo->query("SELECT COUNT(*) FROM companies WHERE plan = 'premium'")->fetchColumn();
        $free = (int) $pdo->query("SELECT COUNT(*) FROM companies WHERE plan = 'free'")->fetchColumn();
        $notifs = $pdo->query(
            'SELECT id, sent_at, sent_count, delivered_count, opened_count, clicked_count, failed_count, unsubscribed_count, created_at
             FROM push_notifications ORDER BY created_at DESC LIMIT 30'
        )->fetchAll();
        $last = $pdo->query(
            "SELECT id, title, sent_at FROM push_notifications WHERE status = 'sent' ORDER BY sent_at DESC LIMIT 1"
        )->fetch();
        $next = $pdo->query(
            "SELECT id, title, scheduled_at FROM push_notifications WHERE status = 'scheduled' ORDER BY scheduled_at ASC LIMIT 1"
        )->fetch();
        app_success([
            'uniqueSubscribers' => count($unique),
            'pwaSubscribers' => $pwa,
            'companiesTotal' => $companies,
            'companiesPremium' => $premium,
            'companiesFree' => $free,
            'notifications' => $notifs,
            'lastSent' => $last === false ? null : $last,
            'nextScheduled' => $next === false ? null : $next,
        ]);
    }

    if ($op === 'templates') {
        $stmt = $pdo->query('SELECT * FROM notification_templates ORDER BY sort ASC');
        app_success(['templates' => $stmt->fetchAll()]);
    }

    if ($op === 'qa_list') {
        $where = ['1=1'];
        $params = [];
        if (!empty($_GET['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = (string) $_GET['status'];
        }
        if (!empty($_GET['type'])) {
            $where[] = 'type = :type';
            $params[':type'] = (string) $_GET['type'];
        }
        if (!empty($_GET['priority'])) {
            $where[] = 'priority = :priority';
            $params[':priority'] = (string) $_GET['priority'];
        }
        if (!empty($_GET['search'])) {
            $where[] = 'description LIKE :search';
            $params[':search'] = persist_like((string) $_GET['search']);
        }
        $limit = min(200, max(1, (int) ($_GET['limit'] ?? 100)));
        $sql = 'SELECT id, ticket_number, type, priority, status, description, page_url, page_title, user_name, user_email, device, screenshot_url, assigned_to, created_at, resolved_at, ip, city_id
                FROM qa_tickets WHERE ' . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['device'] = ops_json_col($row['device']);
            $rows[] = $row;
        }
        $statRows = $pdo->query('SELECT status, priority, created_at FROM qa_tickets')->fetchAll();
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $stats = ['total' => count($statRows), 'pendentes' => 0, 'resolvidos' => 0, 'criticos' => 0, 'hoje' => 0];
        foreach ($statRows as $r) {
            $st = (string) $r['status'];
            if (!in_array($st, ['corrigido', 'publicado', 'fechado'], true)) {
                $stats['pendentes']++;
            }
            if (in_array($st, ['corrigido', 'publicado'], true)) {
                $stats['resolvidos']++;
            }
            if ((string) $r['priority'] === 'critica') {
                $stats['criticos']++;
            }
            if (str_starts_with((string) $r['created_at'], $today)) {
                $stats['hoje']++;
            }
        }
        app_success(['rows' => $rows, 'stats' => $stats]);
    }

    if ($op === 'qa_get') {
        $id = companies_require_uuid($_GET['id'] ?? null, 'invalid_id', 'ID inválido.');
        $stmt = $pdo->prepare('SELECT * FROM qa_tickets WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $ticket = $stmt->fetch();
        if ($ticket === false) {
            app_error('not_found', 'Ticket não encontrado.', 404);
        }
        foreach (['device', 'console_logs', 'network_logs', 'extra'] as $col) {
            $ticket[$col] = ops_json_col($ticket[$col] ?? null);
        }
        $comments = $pdo->prepare('SELECT * FROM qa_ticket_comments WHERE ticket_id = :id ORDER BY created_at ASC');
        $comments->execute([':id' => $id]);
        $events = $pdo->prepare('SELECT * FROM qa_ticket_events WHERE ticket_id = :id ORDER BY created_at ASC');
        $events->execute([':id' => $id]);
        app_success([
            'ticket' => $ticket,
            'comments' => $comments->fetchAll(),
            'events' => $events->fetchAll(),
            'screenshotSignedUrl' => $ticket['screenshot_url'] !== null ? (string) $ticket['screenshot_url'] : null,
        ]);
    }

    app_error('invalid_op', 'Operação inválida.', 400);
}

require_csrf();
$body = companies_read_json();
$op = is_string($body['op'] ?? null) ? (string) $body['op'] : $op;
$now = auth_now();

if ($op === 'qa_create') {
    $userId = auth_user_id();
    rate_limit_hit('qa_ip', security_client_ip(), 12, 3600);
    $type = persist_optional_string($body['type'] ?? null, 32);
    $description = persist_optional_string($body['description'] ?? null, 5000);
    if ($type === null || $description === null || strlen($description) < 3) {
        app_error('invalid_input', 'Descreva o problema.', 422);
    }
    $priority = in_array($type, ['erro', 'bug'], true) ? 'alta' : (in_array($type, ['lentidao', 'funcionalidade'], true) ? 'media' : ($type === 'sugestao' ? 'baixa' : 'media'));
    $id = auth_uuid();
    $ticketNumber = 'QA-' . strtoupper(substr(str_replace('-', '', $id), 0, 8));
    $ins = $pdo->prepare(
        'INSERT INTO qa_tickets (id, ticket_number, type, status, priority, description, page_url, page_title, screenshot_url, video_url, fingerprint, ip, user_id, user_email, user_name, city_id, device, console_logs, network_logs, extra, created_at, updated_at)
         VALUES (:id, :ticket_number, :type, :status, :priority, :description, :page_url, :page_title, :screenshot_url, :video_url, :fingerprint, :ip, :user_id, :user_email, :user_name, :city_id, :device, :console_logs, :network_logs, :extra, :created_at, :updated_at)'
    );
    $ins->execute([
        ':id' => $id,
        ':ticket_number' => $ticketNumber,
        ':type' => $type,
        ':status' => 'novo',
        ':priority' => $priority,
        ':description' => $description,
        ':page_url' => persist_optional_string($body['page_url'] ?? null, 2000),
        ':page_title' => persist_optional_string($body['page_title'] ?? null, 255),
        ':screenshot_url' => persist_optional_string($body['screenshot_url'] ?? null, 2048),
        ':video_url' => persist_optional_string($body['video_url'] ?? null, 2048),
        ':fingerprint' => persist_optional_string($body['fingerprint'] ?? null, 128),
        ':ip' => security_client_ip(),
        ':user_id' => $userId,
        ':user_email' => persist_optional_string($body['user_email'] ?? null, 255),
        ':user_name' => persist_optional_string($body['user_name'] ?? null, 255),
        ':city_id' => persist_optional_uuid($body['city_id'] ?? null),
        ':device' => persist_json_encode($body['device'] ?? new stdClass()),
        ':console_logs' => persist_json_encode($body['console_logs'] ?? []),
        ':network_logs' => persist_json_encode($body['network_logs'] ?? []),
        ':extra' => persist_json_encode($body['extra'] ?? new stdClass()),
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    app_success(['id' => $id, 'ticket_number' => $ticketNumber], 201);
}

if ($op === 'subscribe' || $op === 'unsubscribe' || $op === 'prefs_save' || $op === 'inbox_action' || $op === 'inbox_read_all') {
    $userId = require_auth();
    if ($op === 'subscribe') {
        $endpoint = persist_optional_string($body['endpoint'] ?? null, 2000);
        $p256dh = persist_optional_string($body['p256dh'] ?? null, 2000);
        $auth = persist_optional_string($body['auth'] ?? null, 2000);
        if ($endpoint === null || $p256dh === null || $auth === null) {
            app_error('invalid_input', 'Dados de inscrição inválidos.', 422);
        }
        $existing = $pdo->prepare('SELECT id FROM push_subscriptions WHERE endpoint = :e LIMIT 1');
        $existing->execute([':e' => $endpoint]);
        $found = $existing->fetch();
        if ($found !== false) {
            $upd = $pdo->prepare(
                'UPDATE push_subscriptions SET user_id = :user_id, p256dh = :p256dh, auth = :auth, is_pwa = :is_pwa, platform = :platform, user_agent = :user_agent, last_seen_at = :seen WHERE id = :id'
            );
            $upd->execute([
                ':user_id' => $userId,
                ':p256dh' => $p256dh,
                ':auth' => $auth,
                ':is_pwa' => !empty($body['is_pwa']) ? 1 : 0,
                ':platform' => persist_optional_string($body['platform'] ?? null, 64),
                ':user_agent' => persist_optional_string($body['user_agent'] ?? null, 1000),
                ':seen' => $now,
                ':id' => $found['id'],
            ]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO push_subscriptions (id, user_id, endpoint, p256dh, auth, is_pwa, platform, user_agent, last_seen_at, created_at)
                 VALUES (:id, :user_id, :endpoint, :p256dh, :auth, :is_pwa, :platform, :user_agent, :seen, :created_at)'
            );
            $ins->execute([
                ':id' => auth_uuid(),
                ':user_id' => $userId,
                ':endpoint' => $endpoint,
                ':p256dh' => $p256dh,
                ':auth' => $auth,
                ':is_pwa' => !empty($body['is_pwa']) ? 1 : 0,
                ':platform' => persist_optional_string($body['platform'] ?? null, 64),
                ':user_agent' => persist_optional_string($body['user_agent'] ?? null, 1000),
                ':seen' => $now,
                ':created_at' => $now,
            ]);
        }
        app_success(['ok' => true]);
    }
    if ($op === 'unsubscribe') {
        $endpoint = persist_optional_string($body['endpoint'] ?? null, 2000);
        $pdo->prepare('DELETE FROM push_subscriptions WHERE user_id = :u AND endpoint = :e')
            ->execute([':u' => $userId, ':e' => $endpoint]);
        app_success(['ok' => true]);
    }
    if ($op === 'prefs_save') {
        $cols = [
            'atualizacoes', 'blog', 'empresas', 'eventos', 'marketplace', 'novidades', 'promocoes',
            'som', 'vibracao', 'quiet_hours_enabled',
        ];
        $row = ['user_id' => $userId, 'updated_at' => $now];
        foreach ($cols as $col) {
            if (array_key_exists($col, $body)) {
                $row[$col] = !empty($body[$col]) ? 1 : 0;
            }
        }
        if (isset($body['quiet_start'])) {
            $row['quiet_start'] = (int) $body['quiet_start'];
        }
        if (isset($body['quiet_end'])) {
            $row['quiet_end'] = (int) $body['quiet_end'];
        }
        $names = array_keys($row);
        $update = [];
        foreach ($names as $n) {
            if ($n === 'user_id') {
                continue;
            }
            $update[] = "`$n` = VALUES(`$n`)";
        }
        $placeholders = [];
        $params = [];
        foreach ($row as $k => $v) {
            $placeholders[] = ":$k";
            $params[":$k"] = $v;
        }
        $sql = 'INSERT INTO notification_preferences (`' . implode('`, `', $names) . '`) VALUES (' . implode(', ', $placeholders) . ')
                ON DUPLICATE KEY UPDATE ' . implode(', ', $update);
        $pdo->prepare($sql)->execute($params);
        app_success(['ok' => true]);
    }
    if ($op === 'inbox_read_all') {
        $pdo->prepare('UPDATE push_inbox SET read_at = :t WHERE user_id = :u AND read_at IS NULL')
            ->execute([':t' => $now, ':u' => $userId]);
        app_success(['ok' => true]);
    }
    if ($op === 'inbox_action') {
        $id = (int) ($body['id'] ?? 0);
        $action = is_string($body['action'] ?? null) ? (string) $body['action'] : '';
        if ($id < 1) {
            app_error('invalid_id', 'ID inválido.', 422);
        }
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM push_inbox WHERE id = :id AND user_id = :u')->execute([':id' => $id, ':u' => $userId]);
            app_success(['ok' => true]);
        }
        $patch = [];
        if ($action === 'read') {
            $patch['read_at'] = $now;
        }
        if ($action === 'unread') {
            $patch['read_at'] = null;
        }
        if ($action === 'favorite') {
            $patch['favorite_at'] = $now;
        }
        if ($action === 'unfavorite') {
            $patch['favorite_at'] = null;
        }
        if ($action === 'archive') {
            $patch['archived_at'] = $now;
        }
        if ($action === 'unarchive') {
            $patch['archived_at'] = null;
        }
        if ($patch === []) {
            app_error('invalid_action', 'Ação inválida.', 422);
        }
        $set = [];
        $params = [':id' => $id, ':u' => $userId];
        foreach ($patch as $col => $val) {
            $set[] = "`$col` = :$col";
            $params[":$col"] = $val;
        }
        $pdo->prepare('UPDATE push_inbox SET ' . implode(', ', $set) . ' WHERE id = :id AND user_id = :u')
            ->execute($params);
        app_success(['ok' => true]);
    }
}

$adminId = persist_require_admin();

if ($op === 'push_delete') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $pdo->prepare('DELETE FROM push_notifications WHERE id = :id')->execute([':id' => $id]);
    app_success(['ok' => true]);
}

if ($op === 'qa_update') {
    $id = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
    $set = ['updated_at = :updated_at'];
    $params = [':id' => $id, ':updated_at' => $now];
    if (!empty($body['status'])) {
        $set[] = 'status = :status';
        $params[':status'] = (string) $body['status'];
        if (in_array((string) $body['status'], ['corrigido', 'publicado', 'fechado'], true)) {
            $set[] = 'resolved_at = :resolved_at';
            $params[':resolved_at'] = $now;
        }
    }
    if (!empty($body['priority'])) {
        $set[] = 'priority = :priority';
        $params[':priority'] = (string) $body['priority'];
    }
    if (array_key_exists('assigned_to', $body)) {
        $set[] = 'assigned_to = :assigned_to';
        $params[':assigned_to'] = persist_optional_uuid($body['assigned_to']);
    }
    $pdo->prepare('UPDATE qa_tickets SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
    app_success(['ok' => true]);
}

if ($op === 'qa_comment') {
    $ticketId = companies_require_uuid($body['ticket_id'] ?? null, 'invalid_id', 'ID inválido.');
    $text = persist_optional_string($body['body'] ?? null, 4000);
    if ($text === null) {
        app_error('invalid_body', 'Comentário vazio.', 422);
    }
    $ins = $pdo->prepare(
        'INSERT INTO qa_ticket_comments (id, ticket_id, author_id, body, created_at)
         VALUES (:id, :ticket_id, :author_id, :body, :created_at)'
    );
    $ins->execute([
        ':id' => auth_uuid(),
        ':ticket_id' => $ticketId,
        ':author_id' => $adminId,
        ':body' => $text,
        ':created_at' => $now,
    ]);
    app_success(['ok' => true], 201);
}

app_error('invalid_op', 'Operação inválida.', 400);
