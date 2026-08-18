<?php

declare(strict_types=1);

require_once __DIR__ . '/domains.php';

const REPRESENTATIVE_ROLES = ['vereador', 'prefeito', 'vice_prefeito', 'deputado', 'senador', 'outro'];
const REPRESENTATIVE_IMPORT_SOURCES = ['camara-vespasiano', 'camara-sao-jose-da-lapa', 'manual'];

/**
 * @return list<string>
 */
function representatives_feed_columns(): array
{
    return [
        'id', 'representative_id', 'city_id', 'kind', 'title', 'description', 'status',
        'source_url', 'source_name', 'occurred_at', 'created_at', 'updated_at',
    ];
}

function representatives_ranking_sql(): string
{
    return "SELECT r.*, COALESCE(a.activities_count, 0) AS activities_count,
                   COALESCE(atn.sessions_count, 0) AS sessions_count,
                   COALESCE(atn.absences_count, 0) AS absences_count,
                   COALESCE(atn.attendance_rate, 0) AS attendance_rate
            FROM representatives r
            JOIN cities c ON c.id = r.city_id
            LEFT JOIN (
                SELECT representative_id, COUNT(*) AS activities_count
                FROM representative_activities
                WHERE occurred_at >= DATE_FORMAT(UTC_DATE(), '%Y-%m-01')
                  AND representative_id IS NOT NULL
                GROUP BY representative_id
            ) a ON a.representative_id = r.id
            LEFT JOIN (
                SELECT representative_id, COUNT(*) AS sessions_count,
                       SUM(CASE WHEN present = 0 THEN 1 ELSE 0 END) AS absences_count,
                       ROUND(100 * SUM(CASE WHEN present = 1 THEN 1 ELSE 0 END) / COUNT(*)) AS attendance_rate
                FROM representative_attendance
                WHERE session_date >= DATE_FORMAT(UTC_DATE(), '%Y-%m-01')
                GROUP BY representative_id
            ) atn ON atn.representative_id = r.id
            WHERE r.is_active = 1 AND c.slug = :city
            ORDER BY activities_count DESC, r.name ASC LIMIT 100";
}

function representatives_row(array $row): array
{
    return domain_decode_row($row, ['social_links'], ['is_active']);
}

/**
 * @return array<string, mixed>
 */
function representatives_validated(array $body): array
{
    $name = domain_string($body['name'] ?? null, 'name', 2, 255, false);
    $slug = domain_string($body['slug'] ?? null, 'slug', 0, 191) ?? domain_slug($name);
    if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        app_error('invalid_slug', 'slug is invalid.', 422);
    }

    return [
        'name' => $name,
        'slug' => $slug,
        'role' => domain_enum($body['role'] ?? 'outro', 'role', REPRESENTATIVE_ROLES, 'outro'),
        'city_id' => domain_uuid($body['city_id'] ?? null, 'city_id', false),
        'party' => domain_string($body['party'] ?? null, 'party', 0, 64),
        'photo_url' => domain_optional_url($body['photo_url'] ?? null, 'photo_url'),
        'email' => companies_validate_optional_email($body['email'] ?? null),
        'phone' => domain_string($body['phone'] ?? null, 'phone', 0, 64),
        'social_links' => json_encode(is_array($body['social_links'] ?? null) ? $body['social_links'] : [], JSON_UNESCAPED_SLASHES),
        'mandate_start' => domain_date($body['mandate_start'] ?? null, 'mandate_start', true),
        'mandate_end' => domain_date($body['mandate_end'] ?? null, 'mandate_end', true),
        'is_active' => domain_bool($body['is_active'] ?? null, true),
        'bio' => domain_string($body['bio'] ?? null, 'bio', 0, 10000),
    ];
}

/**
 * @return array<string, mixed>
 */
function representatives_activity_validated(array $body): array
{
    $title = domain_string($body['title'] ?? null, 'title', 2, 500, false);
    $occurred = domain_date($body['occurred_at'] ?? null, 'occurred_at') ?? auth_now();
    $source = domain_string($body['source_name'] ?? 'manual', 'source_name', 1, 255, false);
    $repId = domain_uuid($body['representative_id'] ?? null, 'representative_id');
    $cityId = domain_uuid($body['city_id'] ?? null, 'city_id', false);
    $dedupe = trim((string) ($body['dedupe_hash'] ?? ''));
    if ($dedupe === '') {
        $dedupe = hash('sha256', implode('|', [$repId ?? '', $cityId, $title, $occurred, $source]));
    }

    return [
        'representative_id' => $repId,
        'city_id' => $cityId,
        'kind' => domain_string($body['kind'] ?? 'atividade', 'kind', 1, 64, false),
        'title' => $title,
        'description' => domain_string($body['description'] ?? null, 'description', 0, 10000),
        'status' => domain_string($body['status'] ?? null, 'status', 0, 32),
        'source_url' => domain_optional_url($body['source_url'] ?? null, 'source_url'),
        'source_name' => $source,
        'occurred_at' => $occurred,
        'raw_payload' => null,
        'dedupe_hash' => substr($dedupe, 0, 191),
    ];
}

/**
 * Atomically classifies or acquires an idempotent representative sync run.
 *
 * @return array{
 *   state:string,
 *   log_id:string,
 *   idempotent:bool,
 *   retry:bool,
 *   recovered:bool,
 *   found:int,
 *   inserted:int,
 *   updated:int
 * }
 */
function representatives_sync_claim(
    PDO $pdo,
    string $idempotencyKey,
    string $source,
    ?string $cityId,
    int $itemsFound,
    string $newLogId,
    string $now,
    string $staleBefore
): array {
    $select = $pdo->prepare(
        'SELECT id, status, items_found, items_new, items_updated, created_at
         FROM representative_sync_logs WHERE idempotency_key = :key LIMIT 1'
    );
    $select->execute([':key' => $idempotencyKey]);
    $existing = $select->fetch();
    if ($existing !== false) {
        $status = strtolower((string) $existing['status']);
        $result = [
            'state' => 'in_progress',
            'log_id' => (string) $existing['id'],
            'idempotent' => false,
            'retry' => false,
            'recovered' => false,
            'found' => (int) $existing['items_found'],
            'inserted' => (int) $existing['items_new'],
            'updated' => (int) $existing['items_updated'],
        ];
        if (in_array($status, ['ok', 'completed', 'success'], true)) {
            $result['state'] = 'completed';
            $result['idempotent'] = true;

            return $result;
        }
        $retry = in_array($status, ['error', 'failed'], true);
        $recover = $status === 'running' && (string) $existing['created_at'] <= $staleBefore;
        if (!$retry && !$recover) {
            return $result;
        }
        $expectedStatuses = $retry ? ['error', 'failed'] : ['running'];
        $placeholders = implode(', ', array_fill(0, count($expectedStatuses), '?'));
        $update = $pdo->prepare(
            "UPDATE representative_sync_logs
             SET source = ?, city_id = ?, status = 'running', items_found = ?,
                 items_new = 0, items_updated = 0, error = NULL, duration_ms = NULL, created_at = ?
             WHERE idempotency_key = ? AND status IN ($placeholders)"
             . ($recover ? ' AND created_at <= ?' : '')
        );
        $params = [$source, $cityId, $itemsFound, $now, $idempotencyKey, ...$expectedStatuses];
        if ($recover) {
            $params[] = $staleBefore;
        }
        $update->execute($params);
        if ($update->rowCount() !== 1) {
            return representatives_sync_claim(
                $pdo,
                $idempotencyKey,
                $source,
                $cityId,
                $itemsFound,
                $newLogId,
                $now,
                $staleBefore
            );
        }
        $result['state'] = 'acquired';
        $result['retry'] = $retry;
        $result['recovered'] = $recover;
        $result['found'] = $itemsFound;
        $result['inserted'] = 0;
        $result['updated'] = 0;

        return $result;
    }

    try {
        $insert = $pdo->prepare(
            "INSERT INTO representative_sync_logs
             (id, source, city_id, status, items_found, items_new, items_updated, idempotency_key, created_at)
             VALUES (:id, :source, :city, 'running', :found, 0, 0, :idempotency, :created)"
        );
        $insert->execute([
            ':id' => $newLogId, ':source' => $source, ':city' => $cityId, ':found' => $itemsFound,
            ':idempotency' => $idempotencyKey, ':created' => $now,
        ]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() !== '23000') {
            throw $e;
        }

        return representatives_sync_claim(
            $pdo,
            $idempotencyKey,
            $source,
            $cityId,
            $itemsFound,
            $newLogId,
            $now,
            $staleBefore
        );
    }

    return [
        'state' => 'acquired',
        'log_id' => $newLogId,
        'idempotent' => false,
        'retry' => false,
        'recovered' => false,
        'found' => $itemsFound,
        'inserted' => 0,
        'updated' => 0,
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, int|string>
 */
function representatives_import(
    PDO $pdo,
    string $source,
    ?string $cityId,
    array $items,
    ?string $idempotencyKey = null
): array
{
    if (!in_array($source, REPRESENTATIVE_IMPORT_SOURCES, true)) {
        throw new InvalidArgumentException('Import source is not allowlisted.');
    }
    $started = hrtime(true);
    $found = min(count($items), 500);
    $new = 0;
    $updated = 0;
    $logId = auth_uuid();
    if ($idempotencyKey !== null) {
        $now = auth_now();
        $claim = representatives_sync_claim(
            $pdo,
            $idempotencyKey,
            $source,
            $cityId,
            $found,
            $logId,
            $now,
            gmdate('Y-m-d H:i:s.000', time() - 1800)
        );
        if ($claim['state'] === 'completed') {
            return [
                'log_id' => $claim['log_id'],
                'found' => $claim['found'],
                'inserted' => $claim['inserted'],
                'updated' => $claim['updated'],
                'idempotent' => true,
            ];
        }
        if ($claim['state'] !== 'acquired') {
            throw new RuntimeException('Representative sync with this idempotency key is already in progress.');
        }
        $logId = $claim['log_id'];
    } else {
        $insertLog = $pdo->prepare(
            "INSERT INTO representative_sync_logs
             (id, source, city_id, status, items_found, items_new, items_updated, idempotency_key, created_at)
             VALUES (:id, :source, :city, 'running', :found, 0, 0, NULL, :created)"
        );
        $insertLog->execute([
            ':id' => $logId, ':source' => $source, ':city' => $cityId, ':found' => $found,
            ':created' => auth_now(),
        ]);
    }
    try {
        foreach (array_slice($items, 0, 500) as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ($cityId !== null) {
                $item['city_id'] = $cityId;
            }
            $fields = representatives_validated($item);
            $stmt = $pdo->prepare('SELECT id FROM representatives WHERE slug = :slug LIMIT 1');
            $stmt->execute([':slug' => $fields['slug']]);
            $existing = $stmt->fetchColumn();
            domain_upsert($pdo, 'representatives', $fields, is_string($existing) ? $existing : null);
            is_string($existing) ? $updated++ : $new++;
        }
        $pdo->prepare(
            "UPDATE representative_sync_logs
             SET status = 'ok', items_new = :new, items_updated = :updated, duration_ms = :duration
             WHERE id = :id"
        )->execute([
            ':new' => $new, ':updated' => $updated,
            ':duration' => (int) ((hrtime(true) - $started) / 1_000_000), ':id' => $logId,
        ]);
    } catch (Throwable $e) {
        $pdo->prepare(
            "UPDATE representative_sync_logs
             SET status = 'error', error = :error, duration_ms = :duration WHERE id = :id"
        )->execute([
            ':error' => substr($e->getMessage(), 0, 2000),
            ':duration' => (int) ((hrtime(true) - $started) / 1_000_000), ':id' => $logId,
        ]);
        throw $e;
    }

    return ['log_id' => $logId, 'found' => $found, 'inserted' => $new, 'updated' => $updated, 'idempotent' => false];
}
