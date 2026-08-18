<?php

declare(strict_types=1);

require_once __DIR__ . '/domains.php';

const JOB_SOURCE_ALLOWLIST = [
    'remoteok' => 'https://remoteok.com/api',
    'trampos-co' => 'https://trampos.co/oportunidades.json',
];

function jobs_sync_backoff_seconds(int $failureCount): int
{
    $attempt = max(1, min($failureCount, 10));

    return min(21600, 300 * (2 ** ($attempt - 1)));
}

function jobs_source_is_due(
    DateTimeImmutable $now,
    ?DateTimeImmutable $nextSyncAt,
    ?DateTimeImmutable $lockedUntil
): bool {
    return ($nextSyncAt === null || $nextSyncAt <= $now)
        && ($lockedUntil === null || $lockedUntil <= $now);
}

function jobs_public_row(array $row, bool $detail = false): array
{
    $out = domain_decode_row(
        $row,
        ['tags', 'requirements', 'nice_to_have', 'benefits', 'responsibilities'],
        ['is_remote', 'is_active', 'is_premium'],
        [],
        ['salary_min', 'salary_max']
    );
    unset($out['raw']);
    if (!$detail) {
        unset($out['description'], $out['company_culture'], $out['nice_to_have'], $out['responsibilities']);
    }

    return $out;
}

/**
 * @return array<string, mixed>
 */
function jobs_validated(array $body): array
{
    $salaryMin = domain_decimal($body['salary_min'] ?? null, 'salary_min');
    $salaryMax = domain_decimal($body['salary_max'] ?? null, 'salary_max');
    if ($salaryMin !== null && $salaryMax !== null && (float) $salaryMin > (float) $salaryMax) {
        app_error('invalid_salary', 'salary_min cannot exceed salary_max.', 422);
    }

    return [
        'source_id' => domain_uuid($body['source_id'] ?? null, 'source_id'),
        'external_id' => domain_string($body['external_id'] ?? null, 'external_id', 0, 255),
        'title' => domain_string($body['title'] ?? null, 'title', 3, 500, false),
        'company_name' => domain_string($body['company_name'] ?? null, 'company_name', 0, 255),
        'description' => domain_string($body['description'] ?? null, 'description', 0, 20000),
        'location_city' => domain_string($body['location_city'] ?? null, 'location_city', 0, 255),
        'location_state' => domain_string($body['location_state'] ?? null, 'location_state', 0, 8),
        'is_remote' => domain_bool($body['is_remote'] ?? null),
        'employment_type' => domain_string($body['employment_type'] ?? null, 'employment_type', 0, 64),
        'experience_level' => domain_string($body['experience_level'] ?? null, 'experience_level', 0, 64),
        'salary_min' => $salaryMin,
        'salary_max' => $salaryMax,
        'salary_currency' => domain_string($body['salary_currency'] ?? 'BRL', 'salary_currency', 3, 8, false),
        'apply_url' => domain_optional_url($body['apply_url'] ?? null, 'apply_url'),
        'category' => domain_string($body['category'] ?? null, 'category', 0, 128),
        'tags' => domain_json_array($body['tags'] ?? [], 'tags', 30),
        'posted_at' => domain_date($body['posted_at'] ?? null, 'posted_at') ?? auth_now(),
        'expires_at' => domain_date($body['expires_at'] ?? null, 'expires_at'),
        'is_active' => domain_bool($body['is_active'] ?? null, true),
        'is_premium' => domain_bool($body['is_premium'] ?? null),
        'company_id' => domain_uuid($body['company_id'] ?? null, 'company_id'),
        'company_logo_url' => domain_optional_url($body['company_logo_url'] ?? null, 'company_logo_url'),
        'company_size' => domain_string($body['company_size'] ?? null, 'company_size', 0, 128),
        'company_culture' => domain_string($body['company_culture'] ?? null, 'company_culture', 0, 8000),
        'requirements' => domain_json_array($body['requirements'] ?? [], 'requirements', 50),
        'nice_to_have' => domain_json_array($body['nice_to_have'] ?? [], 'nice_to_have', 50),
        'benefits' => domain_json_array($body['benefits'] ?? [], 'benefits', 50),
        'responsibilities' => domain_json_array($body['responsibilities'] ?? [], 'responsibilities', 50),
        'workload' => domain_string($body['workload'] ?? null, 'workload', 0, 128),
        'apply_email' => companies_validate_optional_email($body['apply_email'] ?? null),
        'apply_whatsapp' => domain_string($body['apply_whatsapp'] ?? null, 'apply_whatsapp', 0, 64),
        'application_deadline' => domain_date($body['application_deadline'] ?? null, 'application_deadline', true),
        'featured_until' => domain_date($body['featured_until'] ?? null, 'featured_until'),
    ];
}

/**
 * Runs only built-in adapters. A URL supplied by an HTTP caller is never fetched.
 *
 * @return array<string, int|string>
 */
function jobs_sync_source(PDO $pdo, string $sourceId): array
{
    $stmt = $pdo->prepare('SELECT * FROM job_sources WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute([':id' => $sourceId]);
    $source = $stmt->fetch();
    if ($source === false) {
        throw new InvalidArgumentException('Active source not found.');
    }
    $slug = (string) $source['slug'];
    $url = JOB_SOURCE_ALLOWLIST[$slug] ?? null;
    if ($url === null || (string) $source['kind'] === 'manual') {
        throw new InvalidArgumentException('Source has no automated adapter.');
    }
    $lockToken = auth_uuid();
    $claimedAt = auth_now();
    $claim = $pdo->prepare(
        "UPDATE job_sources
         SET sync_lock_token = :token, sync_locked_until = DATE_ADD(UTC_TIMESTAMP(3), INTERVAL 5 MINUTE),
             updated_at = :updated
         WHERE id = :id AND is_active = 1 AND kind <> 'manual'
           AND (sync_locked_until IS NULL OR sync_locked_until <= UTC_TIMESTAMP(3))"
    );
    $claim->execute([':token' => $lockToken, ':updated' => $claimedAt, ':id' => $sourceId]);
    if ($claim->rowCount() !== 1) {
        throw new RuntimeException('Source sync is already locked or unavailable.');
    }
    $logId = auth_uuid();
    $started = auth_now();
    $pdo->prepare(
        "INSERT INTO job_sync_logs (id, source_id, started_at, status) VALUES (:id, :source, :started, 'running')"
    )->execute([':id' => $logId, ':source' => $sourceId, ':started' => $started]);
    $stats = ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'errors' => 0];
    try {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => 15,
            'header' => "Accept: application/json\r\nUser-Agent: AgendaAqui-Jobs/1.0\r\n",
            'ignore_errors' => false,
        ]]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || strlen($raw) > 5_000_000) {
            throw new RuntimeException('Source response unavailable or too large.');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Source returned invalid JSON.');
        }
        $items = array_is_list($decoded) ? $decoded : ($decoded['jobs'] ?? $decoded['data'] ?? []);
        if (!is_array($items)) {
            $items = [];
        }
        $stats['fetched'] = min(count($items), 500);
        foreach (array_slice($items, 0, 500) as $item) {
            if (!is_array($item)) {
                $stats['errors']++;
                continue;
            }
            $external = trim((string) ($item['id'] ?? $item['external_id'] ?? $item['slug'] ?? ''));
            $title = trim((string) ($item['position'] ?? $item['title'] ?? ''));
            if ($external === '' || $title === '') {
                $stats['errors']++;
                continue;
            }
            $existing = $pdo->prepare('SELECT id FROM jobs WHERE source_id = :source AND external_id = :external LIMIT 1');
            $existing->execute([':source' => $sourceId, ':external' => substr($external, 0, 255)]);
            $id = $existing->fetchColumn();
            $fields = [
                'source_id' => $sourceId,
                'external_id' => substr($external, 0, 255),
                'title' => substr($title, 0, 500),
                'company_name' => substr(trim((string) ($item['company'] ?? $item['company_name'] ?? '')), 0, 255) ?: null,
                'description' => isset($item['description']) ? substr((string) $item['description'], 0, 20000) : null,
                'location_city' => substr(trim((string) ($item['location'] ?? $item['location_city'] ?? '')), 0, 255) ?: null,
                'is_remote' => !empty($item['remote']) ? 1 : 0,
                'apply_url' => domain_allowed_http_url($item['url'] ?? $item['apply_url'] ?? null),
                'tags' => json_encode(is_array($item['tags'] ?? null) ? array_slice($item['tags'], 0, 30) : []),
                'requirements' => '[]',
                'nice_to_have' => '[]',
                'benefits' => '[]',
                'responsibilities' => '[]',
                'posted_at' => auth_now(),
                'is_active' => 1,
            ];
            domain_upsert($pdo, 'jobs', $fields, is_string($id) ? $id : null);
            $stats[is_string($id) ? 'updated' : 'inserted']++;
        }
        $pdo->prepare(
            "UPDATE job_sync_logs SET finished_at = :finished, status = 'ok', fetched = :fetched,
             inserted = :inserted, updated = :updated, errors = :errors WHERE id = :id"
        )->execute([
            ':finished' => auth_now(), ':fetched' => $stats['fetched'], ':inserted' => $stats['inserted'],
            ':updated' => $stats['updated'], ':errors' => $stats['errors'], ':id' => $logId,
        ]);
        $completedAt = auth_now();
        $pdo->prepare(
            "UPDATE job_sources
             SET last_sync_at = :synced, last_sync_status = 'ok', last_sync_message = NULL,
                 failure_count = 0,
                 next_sync_at = DATE_ADD(:synced_next, INTERVAL sync_frequency_minutes MINUTE),
                 sync_lock_token = NULL, sync_locked_until = NULL, updated_at = :updated
             WHERE id = :id AND sync_lock_token = :token"
        )->execute([
            ':synced' => $completedAt, ':synced_next' => $completedAt, ':updated' => $completedAt,
            ':id' => $sourceId, ':token' => $lockToken,
        ]);
    } catch (Throwable $e) {
        $failureCount = ((int) ($source['failure_count'] ?? 0)) + 1;
        $failedAt = auth_now();
        $nextSyncAt = gmdate('Y-m-d H:i:s.000', time() + jobs_sync_backoff_seconds($failureCount));
        $pdo->prepare(
            "UPDATE job_sources
             SET last_sync_at = :failed, last_sync_status = 'error', last_sync_message = :message,
                 failure_count = :failure_count, next_sync_at = :next_sync,
                 sync_lock_token = NULL, sync_locked_until = NULL, updated_at = :updated
             WHERE id = :id AND sync_lock_token = :token"
        )->execute([
            ':failed' => $failedAt, ':message' => substr($e->getMessage(), 0, 2000),
            ':failure_count' => $failureCount, ':next_sync' => $nextSyncAt,
            ':updated' => $failedAt, ':id' => $sourceId, ':token' => $lockToken,
        ]);
        $pdo->prepare(
            "UPDATE job_sync_logs SET finished_at = :finished, status = 'error', errors = errors + 1, message = :message WHERE id = :id"
        )->execute([':finished' => $failedAt, ':message' => substr($e->getMessage(), 0, 2000), ':id' => $logId]);
        throw $e;
    }

    return ['log_id' => $logId, ...$stats];
}
