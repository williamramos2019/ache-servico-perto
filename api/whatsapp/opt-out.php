<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/whatsapp.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['whatsapp_subscribers']);
$body = domain_read_json();
rate_limit_hit('whatsapp_optout_ip', security_client_ip(), 10, 3600);
$phone = domain_whatsapp_e164($body['phone'] ?? null);
if ($phone === null) {
    app_error('invalid_phone', 'Phone must be a valid Brazilian number.', 422);
}
$lookup = $pdo->prepare(
    'SELECT id, phone, user_id, opt_out_token_hash
     FROM whatsapp_subscribers WHERE phone = :phone LIMIT 1'
);
$lookup->execute([':phone' => $phone]);
$subscriber = $lookup->fetch();
if ($subscriber === false) {
    app_success(['ok' => true, 'changed' => false]);
}
$providedCronSecret = trim((string) ($_SERVER['HTTP_X_CRON_SECRET'] ?? ''));
$cronAuthorized = domain_cron_secret_valid(trim((string) getenv('CRON_SHARED_SECRET')), $providedCronSecret);
if (!$cronAuthorized) {
    require_csrf();
}
$currentUserId = auth_user_id();
$owned = is_string($subscriber['user_id'] ?? null)
    && is_string($currentUserId)
    && hash_equals((string) $subscriber['user_id'], $currentUserId);
$token = is_string($body['token'] ?? null) ? trim($body['token']) : '';
$tokenAuthorized = whatsapp_optout_token_valid(
    whatsapp_optout_secret(),
    $token,
    (string) $subscriber['id'],
    (string) $subscriber['phone'],
    (string) ($subscriber['opt_out_token_hash'] ?? '')
);
domain_enforce_endpoint_policy(
    domain_endpoint_policy(
        app_request_method(),
        ['POST'],
        'ownership_or_token',
        $owned || $cronAuthorized || $tokenAuthorized
    )
);
$stmt = $pdo->prepare(
    'UPDATE whatsapp_subscribers SET is_active = 0, opted_out_at = :opted_out, updated_at = :updated
     WHERE phone = :phone AND is_active = 1'
);
$now = auth_now();
$stmt->execute([':opted_out' => $now, ':updated' => $now, ':phone' => $phone]);
app_success(['ok' => true, 'changed' => $stmt->rowCount() === 1]);
