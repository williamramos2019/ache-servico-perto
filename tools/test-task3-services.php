<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/api/bootstrap/whatsapp.php';
require_once $root . '/api/bootstrap/representatives.php';

$failures = [];
$checks = 0;

function service_check(bool $condition, string $message): void
{
    global $failures, $checks;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

foreach (['domain_endpoint_policy', 'whatsapp_subscription_transition', 'representatives_sync_claim'] as $function) {
    service_check(function_exists($function), $function . ' service must exist');
}

if (function_exists('domain_endpoint_policy')) {
    $wrongMethod = domain_endpoint_policy('GET', ['POST'], 'public', false);
    service_check($wrongMethod['allowed'] === false && $wrongMethod['status'] === 405, 'method dispatch rejects GET for POST endpoint');
    $cronDenied = domain_endpoint_policy('POST', ['POST'], 'cron', false);
    service_check($cronDenied['allowed'] === false && $cronDenied['status'] === 403, 'cron dispatch rejects missing secret');
    service_check(domain_endpoint_policy('POST', ['POST'], 'cron', true)['allowed'] === true, 'cron dispatch accepts verified secret');
    service_check(
        domain_endpoint_policy('POST', ['POST'], 'ownership_or_token', true)['allowed'] === true,
        'ownership/token dispatch accepts verified proof'
    );
}

if (in_array('sqlite', PDO::getAvailableDrivers(), true)
    && function_exists('whatsapp_subscription_transition')
    && function_exists('representatives_sync_claim')
) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE whatsapp_subscribers (
            id TEXT PRIMARY KEY,
            phone TEXT UNIQUE NOT NULL,
            user_id TEXT NULL,
            is_active INTEGER NOT NULL,
            opt_out_token_hash TEXT NULL
        )'
    );
    $secret = 'service-test-secret';
    $victimId = '11111111-1111-4111-8111-111111111111';
    $victimPhone = '+5531999990000';
    $victimToken = whatsapp_optout_sign($secret, $victimId, $victimPhone, 1_700_000_000);
    $insertSubscriber = $pdo->prepare(
        'INSERT INTO whatsapp_subscribers (id, phone, user_id, is_active, opt_out_token_hash)
         VALUES (:id, :phone, NULL, 1, :token_hash)'
    );
    $insertSubscriber->execute([
        ':id' => $victimId,
        ':phone' => $victimPhone,
        ':token_hash' => hash('sha256', $victimToken),
    ]);

    $attacker = whatsapp_subscription_transition(
        $pdo,
        $victimPhone,
        '22222222-2222-4222-8222-222222222222',
        '',
        $secret,
        false
    );
    service_check($attacker['can_mutate'] === false, 'attacker login cannot manage victim anonymous phone');
    service_check($attacker['owner_id'] === null, 'attacker login cannot claim victim anonymous phone');
    $storedOwner = $pdo->query("SELECT user_id FROM whatsapp_subscribers WHERE id = '$victimId'")->fetchColumn();
    service_check($storedOwner === null || $storedOwner === false, 'anonymous victim row remains unowned after attacker attempt');

    $tokenOwner = whatsapp_subscription_transition($pdo, $victimPhone, null, $victimToken, $secret, false);
    service_check($tokenOwner['can_mutate'] === true, 'existing anonymous subscription accepts its manage token');
    service_check($tokenOwner['owner_id'] === null, 'token management does not silently attach a user');

    $ownedId = '33333333-3333-4333-8333-333333333333';
    $ownedPhone = '+5531777770000';
    $insertOwned = $pdo->prepare(
        'INSERT INTO whatsapp_subscribers (id, phone, user_id, is_active, opt_out_token_hash)
         VALUES (:id, :phone, :user, 1, NULL)'
    );
    $insertOwned->execute([':id' => $ownedId, ':phone' => $ownedPhone, ':user' => $ownedId]);
    service_check(
        whatsapp_subscription_transition($pdo, $ownedPhone, $ownedId, '', $secret, false)['can_mutate'] === true,
        'original authenticated owner can manage owned phone'
    );
    service_check(
        whatsapp_subscription_transition(
            $pdo,
            $ownedPhone,
            '22222222-2222-4222-8222-222222222222',
            '',
            $secret,
            false
        )['can_mutate'] === false,
        'different authenticated user cannot manage owned phone'
    );

    $newPhone = whatsapp_subscription_transition(
        $pdo,
        '+5531888880000',
        '22222222-2222-4222-8222-222222222222',
        '',
        $secret,
        false
    );
    service_check($newPhone['exists'] === false, 'new phone follows creation path');
    service_check(
        $newPhone['owner_id'] === '22222222-2222-4222-8222-222222222222',
        'authenticated owner is attached only during original creation'
    );

    $injection = whatsapp_subscription_transition($pdo, "' OR 1=1 --", null, '', $secret, false);
    service_check($injection['exists'] === false, 'phone lookup is parameterized against injection input');

    $pdo->exec(
        'CREATE TABLE representative_sync_logs (
            id TEXT PRIMARY KEY,
            source TEXT NOT NULL,
            city_id TEXT NULL,
            status TEXT NOT NULL,
            items_found INTEGER NOT NULL DEFAULT 0,
            items_new INTEGER NOT NULL DEFAULT 0,
            items_updated INTEGER NOT NULL DEFAULT 0,
            error TEXT NULL,
            duration_ms INTEGER NULL,
            idempotency_key TEXT UNIQUE,
            created_at TEXT NOT NULL
        )'
    );
    $now = '2026-08-17 01:00:00.000';
    $staleBefore = '2026-08-17 00:30:00.000';
    $first = representatives_sync_claim($pdo, 'key-new', 'manual', null, 3, 'log-new', $now, $staleBefore);
    service_check($first['state'] === 'acquired' && $first['retry'] === false, 'new representative run is acquired');
    $pdo->exec("UPDATE representative_sync_logs SET status = 'ok' WHERE id = 'log-new'");
    $completed = representatives_sync_claim($pdo, 'key-new', 'manual', null, 3, 'unused', $now, $staleBefore);
    service_check($completed['state'] === 'completed' && $completed['idempotent'] === true, 'completed run returns idempotent success');

    $insertLog = $pdo->prepare(
        'INSERT INTO representative_sync_logs
         (id, source, city_id, status, items_found, idempotency_key, created_at, error)
         VALUES (:id, :source, NULL, :status, 1, :key, :created, :error)'
    );
    $insertLog->execute([
        ':id' => 'log-failed', ':source' => 'manual', ':status' => 'error',
        ':key' => 'key-failed', ':created' => $now, ':error' => 'previous failure',
    ]);
    $failedRetry = representatives_sync_claim(
        $pdo,
        'key-failed',
        'manual',
        null,
        2,
        'unused',
        '2026-08-17 01:01:00.000',
        $staleBefore
    );
    service_check($failedRetry['state'] === 'acquired' && $failedRetry['retry'] === true, 'failed run is explicitly retried');
    service_check(
        $pdo->query("SELECT status FROM representative_sync_logs WHERE id = 'log-failed'")->fetchColumn() === 'running',
        'failed retry transitions run back to running'
    );

    $insertLog->execute([
        ':id' => 'log-running', ':source' => 'manual', ':status' => 'running',
        ':key' => 'key-running', ':created' => $now, ':error' => null,
    ]);
    $busy = representatives_sync_claim(
        $pdo,
        'key-running',
        'manual',
        null,
        1,
        'unused',
        '2026-08-17 01:05:00.000',
        $staleBefore
    );
    service_check($busy['state'] === 'in_progress' && $busy['idempotent'] === false, 'fresh running run is not reported as success');

    $insertLog->execute([
        ':id' => 'log-stale', ':source' => 'manual', ':status' => 'running',
        ':key' => 'key-stale', ':created' => '2026-08-16 23:00:00.000', ':error' => null,
    ]);
    $stale = representatives_sync_claim(
        $pdo,
        'key-stale',
        'manual',
        null,
        1,
        'unused',
        $now,
        $staleBefore
    );
    service_check($stale['state'] === 'acquired' && $stale['recovered'] === true, 'stale running run is recovered');

    $importFirst = representatives_import($pdo, 'manual', null, [], 'key-full-import');
    $importSecond = representatives_import($pdo, 'manual', null, [], 'key-full-import');
    service_check($importFirst['idempotent'] === false, 'representative import executes newly acquired run');
    service_check($importSecond['idempotent'] === true, 'representative import wraps only completed run as idempotent success');

    $insertLog->execute([
        ':id' => 'log-full-failed', ':source' => 'manual', ':status' => 'error',
        ':key' => 'key-full-failed', ':created' => $now, ':error' => 'interrupted',
    ]);
    $retriedImport = representatives_import($pdo, 'manual', null, [], 'key-full-failed');
    service_check($retriedImport['idempotent'] === false, 'representative import retries failed run instead of wrapping success');
    service_check(
        $pdo->query("SELECT status FROM representative_sync_logs WHERE id = 'log-full-failed'")->fetchColumn() === 'ok',
        'retried representative import completes recovered run'
    );

    $insertLog->execute([
        ':id' => 'log-full-busy', ':source' => 'manual', ':status' => 'running',
        ':key' => 'key-full-busy', ':created' => auth_now(), ':error' => null,
    ]);
    $busyRejected = false;
    try {
        representatives_import($pdo, 'manual', null, [], 'key-full-busy');
    } catch (RuntimeException) {
        $busyRejected = true;
    }
    service_check($busyRejected, 'representative import rejects fresh in-progress run');
} else {
    service_check(false, 'pdo_sqlite is required for executable Task 3 service tests');
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL ($checks checks)\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK ($checks checks)\n");
