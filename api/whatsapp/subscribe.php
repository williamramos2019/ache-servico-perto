<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/whatsapp.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['whatsapp_subscribers', 'system_settings']);
$body = domain_read_json();
require_csrf();
$optOutSecret = whatsapp_optout_secret();
if ($optOutSecret === '') {
    app_error('service_unavailable', 'WhatsApp opt-out signing is not configured.', 503);
}

if (($body['consent'] ?? null) !== true) {
    app_error('consent_required', 'Explicit consent is required.', 422);
}
$name = domain_string($body['name'] ?? null, 'name', 2, 80, false);
$phone = domain_whatsapp_e164($body['phone'] ?? null);
if ($phone === null) {
    app_error('invalid_phone', 'Phone must be a valid Brazilian number.', 422);
}
$city = whatsapp_city($pdo, $body['citySlug'] ?? ($body['city_slug'] ?? null));
rate_limit_hit('whatsapp_subscribe_ip', security_client_ip(), 5, 3600);
rate_limit_hit('whatsapp_subscribe_phone', security_client_ip() . "\0" . $phone, 3, 86400);
$manageToken = is_string($body['manage_token'] ?? null) ? trim($body['manage_token']) : '';

$pdo->beginTransaction();
try {
    $transition = whatsapp_subscription_transition(
        $pdo,
        $phone,
        auth_user_id(),
        $manageToken,
        $optOutSecret,
        true
    );
    $existing = $transition['subscriber'] ?? false;
    $alreadyActive = $existing !== false && persist_bool($existing['is_active']);
    $id = $existing !== false ? (string) $existing['id'] : auth_uuid();
    $now = auth_now();
    $ownerId = $transition['owner_id'];
    $optOutToken = null;
    if ($transition['exists'] && !$transition['can_mutate']) {
        $pdo->commit();
        app_success([
            'ok' => true,
            'phone' => $phone,
            'city' => $city['name'],
            'idempotent' => true,
            'authorization_required' => true,
            'welcome_status' => 'unchanged',
            'opt_out_token' => null,
        ]);
    }
    if ($alreadyActive) {
        $pdo->prepare(
            'UPDATE whatsapp_subscribers
             SET name = :name, city_id = :city, updated_at = :updated
             WHERE id = :id'
        )->execute([
            ':name' => $name, ':city' => $city['id'], ':updated' => $now, ':id' => $id,
        ]);
    } else {
        $optOutToken = whatsapp_optout_sign($optOutSecret, $id, $phone, time());
        $tokenHash = hash('sha256', $optOutToken);
        if ($transition['exists']) {
            $pdo->prepare(
                'UPDATE whatsapp_subscribers
                 SET name = :name, city_id = :city, is_active = 1, opted_in_at = :opted_in,
                     opted_out_at = NULL, opt_out_token_hash = :token_hash, updated_at = :updated
                 WHERE id = :id'
            )->execute([
                ':name' => $name, ':city' => $city['id'], ':opted_in' => $now,
                ':token_hash' => $tokenHash, ':updated' => $now, ':id' => $id,
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO whatsapp_subscribers
                 (id, phone, name, city_id, user_id, is_active, opted_in_at, opted_out_at,
                  opt_out_token_hash, created_at, updated_at)
                 VALUES (:id, :phone, :name, :city, :user, 1, :opted_in, NULL,
                         :token_hash, :created, :updated)'
            )->execute([
                ':id' => $id, ':phone' => $phone, ':name' => $name, ':city' => $city['id'],
                ':user' => $ownerId, ':opted_in' => $now, ':token_hash' => $tokenHash,
                ':created' => $now, ':updated' => $now,
            ]);
        }
    }
    $welcome = $alreadyActive ? 'already_sent_or_queued' : whatsapp_welcome_status();
    whatsapp_store_audit($pdo, $id, [
        'consent' => true,
        'consent_text_version' => 'representatives-weekly-v1',
        'consented_at' => $now,
        'ip_hash' => hash('sha256', security_client_ip()),
        'city_slug' => $city['slug'],
        'welcome_status' => $welcome,
    ]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
        app_error('subscription_conflict', 'Subscription changed concurrently; retry with manage proof.', 409);
    }
    throw $e;
}

app_success([
    'ok' => true,
    'phone' => $phone,
    'city' => $city['name'],
    'idempotent' => $alreadyActive,
    'welcome_status' => $welcome,
    'opt_out_token' => $optOutToken,
], $alreadyActive ? 200 : 201);
