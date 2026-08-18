<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/push.php';

app_start(['POST', 'OPTIONS']);
$pdo = db_pdo(false);
domain_require_tables($pdo, ['push_deliveries', 'push_notifications', 'push_subscriptions']);
rate_limit_hit('push_track', (string) ($_SERVER['REMOTE_ADDR'] ?? '0'), 60, 60);

$body = domain_read_json();
$event = is_string($body['event'] ?? null) ? $body['event'] : '';
$allowedEvents = ['delivered', 'opened', 'clicked', 'unsubscribed', 'failed', 'resubscribe'];
if (!in_array($event, $allowedEvents, true)) {
    app_error('invalid_event', 'Evento inválido.', 400);
}

if ($event === 'resubscribe') {
    push_handle_resubscribe($pdo, $body);
}

if ($event === 'unsubscribed') {
    $endpoint = domain_allowed_http_url($body['old_endpoint'] ?? null);
    if ($endpoint !== null && push_endpoint_allowed($endpoint)) {
        $stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = :endpoint LIMIT 1');
        $stmt->execute([':endpoint' => $endpoint]);
    }
    app_success(['ok' => true]);
}

$deliveryId = domain_int($body['delivery_id'] ?? null, 'delivery_id', 1, PHP_INT_MAX, 0);
$token = is_string($body['token'] ?? null) ? $body['token'] : '';
if ($deliveryId < 1 || !push_verify_delivery_token($deliveryId, $token)) {
    app_error('invalid_token', 'Token de entrega inválido.', 401);
}

$stmt = $pdo->prepare(
    'SELECT id, notification_id, status, delivered_at, opened_at, clicked_at
     FROM push_deliveries WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $deliveryId]);
$deliv = $stmt->fetch();
if ($deliv === false) {
    app_error('not_found', 'Entrega não encontrada.', 404);
}

$now = gmdate('Y-m-d H:i:s.v');
$rank = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'opened' => 3, 'clicked' => 4, 'failed' => -1];
$currentRank = $rank[(string) $deliv['status']] ?? 0;
$sets = [];
$params = [':id' => $deliveryId];
$counter = null;
$nextStatus = null;

if ($event === 'delivered' && empty($deliv['delivered_at'])) {
    $sets[] = 'delivered_at = :ts';
    $params[':ts'] = $now;
    $nextStatus = 'delivered';
    $counter = 'delivered_count';
} elseif ($event === 'opened' && empty($deliv['opened_at'])) {
    $sets[] = 'opened_at = :ts';
    $params[':ts'] = $now;
    $nextStatus = 'opened';
    $counter = 'opened_count';
} elseif ($event === 'clicked' && empty($deliv['clicked_at'])) {
    $sets[] = 'clicked_at = :ts';
    $params[':ts'] = $now;
    $nextStatus = 'clicked';
    $counter = 'clicked_count';
} elseif ($event === 'failed') {
    $sets[] = 'status = :status';
    $params[':status'] = 'failed';
    $counter = 'failed_count';
}

if ($nextStatus !== null && ($rank[$nextStatus] ?? 0) > $currentRank) {
    $sets[] = 'status = :status';
    $params[':status'] = $nextStatus;
}

if ($sets !== []) {
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE push_deliveries SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $update->execute($params);
        $allowedCounters = ['delivered_count', 'opened_count', 'clicked_count', 'failed_count'];
        if ($counter !== null && is_string($deliv['notification_id']) && in_array($counter, $allowedCounters, true)) {
            $inc = $pdo->prepare(
                'UPDATE push_notifications SET `' . $counter . '` = `' . $counter . '` + 1, updated_at = :ts WHERE id = :nid'
            );
            $inc->execute([':nid' => $deliv['notification_id'], ':ts' => $now]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

app_success(['ok' => true]);
