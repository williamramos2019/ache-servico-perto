<?php

declare(strict_types=1);

require_once __DIR__ . '/domains.php';

function push_track_secret(): string
{
    foreach ([getenv('PUSH_TRACK_SECRET'), $_ENV['PUSH_TRACK_SECRET'] ?? null, $_SERVER['PUSH_TRACK_SECRET'] ?? null] as $secret) {
        if (is_string($secret) && trim($secret) !== '') {
            return trim($secret);
        }
    }

    return '';
}

function push_sign_delivery_token(int $deliveryId): string
{
    $secret = push_track_secret();
    if ($secret === '') {
        return '';
    }
    $raw = hash_hmac('sha256', (string) $deliveryId, $secret, true);

    return substr(rtrim(strtr(base64_encode($raw), '+/', '-_'), '='), 0, 22);
}

function push_verify_delivery_token(int $deliveryId, string $token): bool
{
    $expected = push_sign_delivery_token($deliveryId);
    if ($expected === '' || $token === '' || strlen($expected) !== strlen($token)) {
        return false;
    }

    return hash_equals($expected, $token);
}

function push_endpoint_allowed(string $url): bool
{
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== 'https' || $host === '') {
        return false;
    }
    $allowed = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'web.push.apple.com',
        'wns2-am3p.notify.windows.com',
    ];
    foreach ($allowed as $suffix) {
        if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
            return true;
        }
    }

    return false;
}

function push_handle_resubscribe(PDO $pdo, array $body): void
{
    $old = domain_allowed_http_url($body['old_endpoint'] ?? null);
    $sub = is_array($body['new_subscription'] ?? null) ? $body['new_subscription'] : [];
    $newEndpoint = domain_allowed_http_url($sub['endpoint'] ?? null);
    $keys = is_array($sub['keys'] ?? null) ? $sub['keys'] : [];
    $p256dh = domain_string($keys['p256dh'] ?? null, 'p256dh', 20, 512, false);
    $auth = domain_string($keys['auth'] ?? null, 'auth', 8, 512, false);

    if ($old === null || $newEndpoint === null || !push_endpoint_allowed($old) || !push_endpoint_allowed($newEndpoint)) {
        app_error('invalid_endpoint', 'Endpoint de push inválido.', 400);
    }

    $find = $pdo->prepare('SELECT id, user_id, user_agent, platform, is_pwa FROM push_subscriptions WHERE endpoint = :endpoint LIMIT 1');
    $find->execute([':endpoint' => $old]);
    $existing = $find->fetch();
    if ($existing === false) {
        app_error('unknown_subscription', 'Inscrição não encontrada.', 404);
    }

    $now = gmdate('Y-m-d H:i:s.v');
    $pdo->beginTransaction();
    try {
        $same = $pdo->prepare('SELECT id FROM push_subscriptions WHERE endpoint = :endpoint LIMIT 1');
        $same->execute([':endpoint' => $newEndpoint]);
        $current = $same->fetch();
        if ($current !== false) {
            $upd = $pdo->prepare(
                'UPDATE push_subscriptions
                 SET p256dh = :p256dh, auth = :auth, user_id = :user_id, last_seen_at = :seen
                 WHERE id = :id'
            );
            $upd->execute([
                ':p256dh' => $p256dh,
                ':auth' => $auth,
                ':user_id' => $existing['user_id'],
                ':seen' => $now,
                ':id' => $current['id'],
            ]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO push_subscriptions
                 (id, user_id, endpoint, p256dh, auth, is_pwa, platform, user_agent, last_seen_at, created_at)
                 VALUES (:id, :user_id, :endpoint, :p256dh, :auth, :is_pwa, :platform, :ua, :seen, :created)'
            );
            $ins->execute([
                ':id' => auth_uuid(),
                ':user_id' => $existing['user_id'],
                ':endpoint' => $newEndpoint,
                ':p256dh' => $p256dh,
                ':auth' => $auth,
                ':is_pwa' => (int) $existing['is_pwa'],
                ':platform' => $existing['platform'],
                ':ua' => $existing['user_agent'],
                ':seen' => $now,
                ':created' => $now,
            ]);
        }
        if ($old !== $newEndpoint) {
            $del = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = :endpoint LIMIT 1');
            $del->execute([':endpoint' => $old]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    app_success(['ok' => true]);
}
