<?php

declare(strict_types=1);

require_once __DIR__ . '/jobs.php';
require_once __DIR__ . '/representatives.php';
require_once __DIR__ . '/whatsapp.php';
require_once dirname(__DIR__) . '/importer/bootstrap.php';
require_once dirname(__DIR__) . '/importer/transport.php';

function sync_idempotency_key(string $kind, string $source, string $requestKey): string
{
    return hash('sha256', $kind . "\0" . $source . "\0" . $requestKey);
}

function cron_digest_is_due(
    DateTimeImmutable $now,
    ?DateTimeImmutable $lastSentAt,
    int $weekday = 5,
    int $startHour = 8,
    int $endHour = 18
): bool {
    $utc = $now->setTimezone(new DateTimeZone('UTC'));
    if ((int) $utc->format('N') !== $weekday) {
        return false;
    }
    $hour = (int) $utc->format('G');
    if ($hour < $startHour || $hour >= $endHour) {
        return false;
    }

    $weekStart = $utc->modify('monday this week')->setTime(0, 0);

    return $lastSentAt === null || $lastSentAt < $weekStart;
}

function cron_digest_run_key(DateTimeImmutable $now): string
{
    return 'whatsapp-digest:' . $now->setTimezone(new DateTimeZone('UTC'))->format('o-\WW');
}

function cron_claim_is_stale(
    ?DateTimeImmutable $claimedAt,
    DateTimeImmutable $now,
    int $ttlSeconds = 1800
): bool {
    return $claimedAt === null || $claimedAt->getTimestamp() <= $now->getTimestamp() - $ttlSeconds;
}

/**
 * @param array<mixed> $items
 * @return list<array<string, mixed>>
 */
function cron_normalize_bus_items(string $citySlug, array $items): array
{
    $normalized = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $item['city_slug'] = $citySlug;
            $normalized[] = $item;
        }
    }

    return $normalized;
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, mixed>
 */
function cron_representatives_sync(
    PDO $pdo,
    string $source,
    ?string $cityId,
    array $items,
    string $requestKey
): array {
    if ($requestKey === '' || strlen($requestKey) > 191) {
        throw new InvalidArgumentException('A bounded idempotency key is required.');
    }
    $key = sync_idempotency_key('representatives', $source . ':' . ($cityId ?? ''), $requestKey);

    return representatives_import($pdo, $source, $cityId, $items, $key);
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, mixed>
 */
function cron_bus_sync(
    PDO $pdo,
    string $citySlug,
    string $sourceName,
    string $sourceUrl,
    array $items,
    string $requestKey
): array {
    if (!in_array($citySlug, WHATSAPP_CITY_SLUGS, true)) {
        throw new InvalidArgumentException('City is not allowlisted.');
    }
    if ($requestKey === '' || strlen($requestKey) > 191 || count($items) > 500) {
        throw new InvalidArgumentException('A bounded idempotency key and at most 500 items are required.');
    }
    $sourceName = trim($sourceName);
    $sourceUrl = domain_allowed_http_url($sourceUrl);
    if ($sourceName === '' || strlen($sourceName) > 255 || $sourceUrl === null) {
        throw new InvalidArgumentException('Source metadata is invalid.');
    }
    $idempotencyKey = sync_idempotency_key('bus', $citySlug, $requestKey);
    $logId = auth_uuid();
    $startedAt = auth_now();
    $insert = $pdo->prepare(
        "INSERT IGNORE INTO bus_sync_logs
         (id, city_slug, lines_found, lines_updated, errors, status, idempotency_key, started_at)
         VALUES (:id, :city, 0, 0, '[]', 'running', :key, :started)"
    );
    $insert->execute([
        ':id' => $logId, ':city' => $citySlug, ':key' => $idempotencyKey, ':started' => $startedAt,
    ]);
    if ($insert->rowCount() !== 1) {
        $existing = $pdo->prepare(
            'SELECT id, status, lines_found, lines_updated, errors
             FROM bus_sync_logs WHERE idempotency_key = :key LIMIT 1'
        );
        $existing->execute([':key' => $idempotencyKey]);

        return ['idempotent' => true, 'log' => $existing->fetch() ?: null];
    }
    $normalized = cron_normalize_bus_items($citySlug, $items);
    try {
        $stats = transport_import_run($pdo, [
            'file' => '',
            'lines' => $normalized,
            'source_name' => $sourceName,
            'source_url' => $sourceUrl,
            'source_type' => 'dados-abertos',
            'dry_run' => false,
            'update' => true,
            'resume' => false,
            'limit' => 500,
        ]);
        $errors = is_array($stats['errors'] ?? null) ? $stats['errors'] : [];
        if ((int) ($stats['rejected'] ?? 0) > 0) {
            $errors[] = (int) $stats['rejected'] . ' transport item(s) rejected by validation.';
        }
        $status = $errors === [] ? 'ok' : 'partial';
        $pdo->prepare(
            'UPDATE bus_sync_logs
             SET lines_found = :found, lines_updated = :updated, errors = :errors,
                 status = :status, finished_at = :finished WHERE id = :id'
        )->execute([
            ':found' => (int) ($stats['collected'] ?? 0),
            ':updated' => (int) ($stats['updated'] ?? 0) + (int) ($stats['inserted'] ?? 0),
            ':errors' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status' => $status, ':finished' => auth_now(), ':id' => $logId,
        ]);

        return ['idempotent' => false, 'log_id' => $logId, 'stats' => $stats];
    } catch (Throwable $e) {
        $pdo->prepare(
            "UPDATE bus_sync_logs
             SET errors = :errors, status = 'error', finished_at = :finished WHERE id = :id"
        )->execute([
            ':errors' => json_encode([substr($e->getMessage(), 0, 2000)], JSON_UNESCAPED_UNICODE),
            ':finished' => auth_now(), ':id' => $logId,
        ]);
        throw $e;
    }
}

/**
 * @return array<string, mixed>
 */
function cron_jobs_due(PDO $pdo, ?string $sourceId = null): array
{
    $sql = "SELECT id FROM job_sources WHERE is_active = 1 AND kind <> 'manual'";
    $params = [];
    if ($sourceId !== null) {
        $sql .= ' AND id = :id';
        $params[':id'] = $sourceId;
    } else {
        $sql .= ' AND (next_sync_at IS NULL OR next_sync_at <= UTC_TIMESTAMP(3))
                  AND (sync_locked_until IS NULL OR sync_locked_until <= UTC_TIMESTAMP(3))';
    }
    $sql .= ' ORDER BY next_sync_at ASC, last_sync_at ASC LIMIT 20';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $runs = [];
    foreach ($stmt->fetchAll() as $row) {
        try {
            $runs[] = ['source_id' => $row['id'], 'ok' => true, 'result' => jobs_sync_source($pdo, (string) $row['id'])];
        } catch (Throwable $e) {
            $runs[] = ['source_id' => $row['id'], 'ok' => false, 'error' => substr($e->getMessage(), 0, 500)];
        }
    }

    return ['runs' => $runs, 'count' => count($runs)];
}

function cron_bot_config(): ?array
{
    $url = trim((string) getenv('WHATSAPP_BOT_URL'));
    $token = trim((string) getenv('WHATSAPP_BOT_TOKEN'));
    if ($url === '' || $token === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    return ['url' => $url, 'token' => $token];
}

function cron_bot_send(string $phone, string $message): bool
{
    $config = cron_bot_config();
    if ($config === null) {
        return false;
    }
    $payload = json_encode(['to' => $phone, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'timeout' => 10,
        'ignore_errors' => true,
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $config['token'] . "\r\n",
        'content' => $payload,
    ]]);
    $result = @file_get_contents($config['url'], false, $context);
    $statusLine = is_array($http_response_header ?? null) ? (string) ($http_response_header[0] ?? '') : '';

    return is_string($result) && preg_match('/\s2\d\d\s/', $statusLine) === 1;
}

/**
 * @return array<string, int|string>
 */
function cron_whatsapp_welcome(PDO $pdo, int $limit = 50): array
{
    if (cron_bot_config() === null) {
        return ['status' => 'unavailable', 'sent' => 0, 'failed' => 0];
    }
    $stmt = $pdo->query(
        "SELECT s.id, s.phone, s.name, c.name AS city_name, ss.value AS audit
         FROM whatsapp_subscribers s JOIN cities c ON c.id = s.city_id
         JOIN system_settings ss ON ss.`key` = CONCAT('whatsapp_consent_', REPLACE(s.id, '-', ''))
         WHERE s.is_active = 1 AND JSON_UNQUOTE(JSON_EXTRACT(ss.value, '$.welcome_status')) = 'queued'
         ORDER BY s.opted_in_at LIMIT " . max(1, min($limit, 100))
    );
    $sent = 0;
    $failed = 0;
    foreach ($stmt->fetchAll() as $row) {
        $message = 'Olá, ' . ($row['name'] ?: 'vizinho(a)') . "! Você se inscreveu no resumo semanal do AgendaAqui sobre "
            . $row['city_name'] . ". Para cancelar, responda SAIR.";
        $ok = cron_bot_send((string) $row['phone'], $message);
        $audit = json_decode((string) $row['audit'], true);
        $audit = is_array($audit) ? $audit : [];
        $audit['welcome_status'] = $ok ? 'sent' : 'failed';
        $audit['welcome_updated_at'] = auth_now();
        whatsapp_store_audit($pdo, (string) $row['id'], $audit);
        $ok ? $sent++ : $failed++;
    }

    return ['status' => 'ok', 'sent' => $sent, 'failed' => $failed];
}

/**
 * @return array<string, int|string>
 */
function cron_whatsapp_digest(PDO $pdo, int $limit = 200): array
{
    $nowDate = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $weekday = max(1, min(7, (int) (getenv('WHATSAPP_DIGEST_WEEKDAY') ?: 5)));
    $startHour = max(0, min(23, (int) (getenv('WHATSAPP_DIGEST_START_HOUR') ?: 8)));
    $endHour = max($startHour + 1, min(24, (int) (getenv('WHATSAPP_DIGEST_END_HOUR') ?: 18)));
    if (!cron_digest_is_due($nowDate, null, $weekday, $startHour, $endHour)) {
        return ['status' => 'not_due', 'sent' => 0, 'failed' => 0];
    }
    if (cron_bot_config() === null) {
        return ['status' => 'unavailable', 'sent' => 0, 'failed' => 0];
    }
    $runKey = cron_digest_run_key($nowDate);
    $runId = auth_uuid();
    $claimToken = auth_uuid();
    $startedAt = auth_now();
    $insertRun = $pdo->prepare(
        "INSERT IGNORE INTO scheduled_hook_runs
         (id, hook_name, run_key, claim_token, status, started_at)
         VALUES (:id, 'whatsapp-digest', :run_key, :claim, 'running', :started)"
    );
    $insertRun->execute([
        ':id' => $runId, ':run_key' => $runKey, ':claim' => $claimToken, ':started' => $startedAt,
    ]);
    if ($insertRun->rowCount() !== 1) {
        $takeover = $pdo->prepare(
            "UPDATE scheduled_hook_runs
             SET claim_token = :claim, status = 'running', started_at = :started, finished_at = NULL, message = NULL
             WHERE hook_name = 'whatsapp-digest' AND run_key = :run_key
               AND (status IN ('failed', 'partial')
                    OR (status = 'running' AND started_at <= DATE_SUB(UTC_TIMESTAMP(3), INTERVAL 30 MINUTE)))"
        );
        $takeover->execute([':claim' => $claimToken, ':started' => $startedAt, ':run_key' => $runKey]);
        if ($takeover->rowCount() !== 1) {
            return ['status' => 'idempotent', 'sent' => 0, 'failed' => 0];
        }
        $lookup = $pdo->prepare(
            "SELECT id FROM scheduled_hook_runs WHERE hook_name = 'whatsapp-digest' AND run_key = :run_key LIMIT 1"
        );
        $lookup->execute([':run_key' => $runKey]);
        $runId = (string) $lookup->fetchColumn();
    }
    $candidateIds = $pdo->query(
        'SELECT id FROM whatsapp_subscribers
         WHERE is_active = 1
           AND (last_sent_at IS NULL OR last_sent_at < DATE_SUB(UTC_DATE(), INTERVAL WEEKDAY(UTC_DATE()) DAY))
           AND (digest_claimed_at IS NULL OR digest_claimed_at <= DATE_SUB(UTC_TIMESTAMP(3), INTERVAL 30 MINUTE))
         ORDER BY last_sent_at ASC LIMIT ' . max(1, min($limit, 500))
    )->fetchAll(PDO::FETCH_COLUMN);
    $claim = $pdo->prepare(
        'UPDATE whatsapp_subscribers
         SET digest_claim_token = :claim, digest_claimed_at = :claimed, updated_at = :updated
         WHERE id = :id AND is_active = 1
           AND (last_sent_at IS NULL OR last_sent_at < DATE_SUB(UTC_DATE(), INTERVAL WEEKDAY(UTC_DATE()) DAY))
           AND (digest_claimed_at IS NULL OR digest_claimed_at <= DATE_SUB(UTC_TIMESTAMP(3), INTERVAL 30 MINUTE))'
    );
    $claimed = 0;
    foreach ($candidateIds as $candidateId) {
        $claim->execute([
            ':claim' => $claimToken, ':claimed' => $startedAt, ':updated' => $startedAt, ':id' => $candidateId,
        ]);
        $claimed += $claim->rowCount();
    }
    $stmt = $pdo->prepare(
        'SELECT s.id, s.phone, s.name, s.city_id, c.name AS city_name
         FROM whatsapp_subscribers s JOIN cities c ON c.id = s.city_id
         WHERE s.digest_claim_token = :claim ORDER BY s.last_sent_at ASC'
    );
    $stmt->execute([':claim' => $claimToken]);
    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM representative_activities
         WHERE city_id = :city AND occurred_at >= DATE_SUB(UTC_TIMESTAMP(3), INTERVAL 7 DAY)'
    );
    $sent = 0;
    $failed = 0;
    foreach ($stmt->fetchAll() as $row) {
        $count->execute([':city' => $row['city_id']]);
        $activities = (int) $count->fetchColumn();
        $message = 'Resumo semanal AgendaAqui — ' . $row['city_name'] . ': ' . $activities
            . ' atividades públicas registradas nos últimos 7 dias. Para cancelar, responda SAIR.';
        if (cron_bot_send((string) $row['phone'], $message)) {
            $now = auth_now();
            $pdo->prepare(
                'UPDATE whatsapp_subscribers
                 SET last_sent_at = :sent, digest_claim_token = NULL, digest_claimed_at = NULL, updated_at = :updated
                 WHERE id = :id AND digest_claim_token = :claim'
            )->execute([':sent' => $now, ':updated' => $now, ':id' => $row['id'], ':claim' => $claimToken]);
            $sent++;
        } else {
            $pdo->prepare(
                'UPDATE whatsapp_subscribers
                 SET digest_claim_token = NULL, digest_claimed_at = NULL, updated_at = :updated
                 WHERE id = :id AND digest_claim_token = :claim'
            )->execute([':updated' => auth_now(), ':id' => $row['id'], ':claim' => $claimToken]);
            $failed++;
        }
    }

    $status = $failed === 0 ? 'completed' : 'partial';
    $pdo->prepare(
        'UPDATE scheduled_hook_runs
         SET status = :status, claimed = :claimed, sent = :sent, failed = :failed, finished_at = :finished
         WHERE id = :id AND claim_token = :claim'
    )->execute([
        ':status' => $status, ':claimed' => $claimed, ':sent' => $sent, ':failed' => $failed,
        ':finished' => auth_now(), ':id' => $runId, ':claim' => $claimToken,
    ]);

    return ['status' => $status, 'sent' => $sent, 'failed' => $failed];
}

function cron_log_bus(PDO $pdo, string $citySlug, array $stats, float $startedAt): string
{
    $id = auth_uuid();
    $errors = $stats['errors'] ?? [];
    if (!is_array($errors)) {
        $errors = [(string) $errors];
    }
    $pdo->prepare(
        'INSERT INTO bus_sync_logs
         (id, city_slug, lines_found, lines_updated, errors, status, started_at, finished_at)
         VALUES (:id, :city, :found, :updated, :errors, :status, :started, :finished)'
    )->execute([
        ':id' => $id,
        ':city' => $citySlug,
        ':found' => (int) ($stats['processed'] ?? $stats['lines_found'] ?? 0),
        ':updated' => (int) ($stats['updated'] ?? $stats['inserted'] ?? 0),
        ':errors' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':status' => $errors === [] ? 'ok' : 'partial',
        ':started' => gmdate('Y-m-d H:i:s.000', (int) $startedAt),
        ':finished' => auth_now(),
    ]);

    return $id;
}
