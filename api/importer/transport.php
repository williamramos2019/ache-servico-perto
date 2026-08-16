<?php

declare(strict_types=1);

const TRANSPORT_TYPES = ['municipal', 'metropolitana', 'intermunicipal', 'tarifa-zero'];
const TRANSPORT_STATUSES = ['active', 'inactive', 'unknown'];
const TRANSPORT_DAY_TYPES = ['weekday', 'saturday', 'sunday', 'holiday'];
const TRANSPORT_DIRECTIONS = ['ida', 'volta', 'circular'];

/**
 * @return array<string, mixed>
 */
function transport_line_row(array $row, ?array $source = null, array $schedules = [], array $stops = []): array
{
    $out = [
        'id' => (string) $row['id'],
        'city_id' => $row['city_id'] !== null ? (string) $row['city_id'] : null,
        'city_slug' => isset($row['city_slug']) && $row['city_slug'] !== null ? (string) $row['city_slug'] : null,
        'city_name' => isset($row['city_name']) && $row['city_name'] !== null ? (string) $row['city_name'] : null,
        'code' => (string) $row['code'],
        'slug' => (string) $row['slug'],
        'name' => (string) $row['name'],
        'type' => (string) $row['type'],
        'status' => (string) $row['status'],
        'fare' => $row['fare'] !== null ? (string) $row['fare'] : null,
        'operator_name' => $row['operator_name'] !== null ? (string) $row['operator_name'] : null,
        'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
        'source' => null,
        'schedules' => $schedules,
        'stops' => $stops,
        'updated_at' => (string) $row['updated_at'],
    ];
    if ($source !== null) {
        $out['source'] = [
            'name' => (string) $source['name'],
            'url' => $source['url'] !== null ? (string) $source['url'] : null,
            'type' => (string) $source['type'],
            'collected_at' => $source['collected_at'] !== null ? (string) $source['collected_at'] : null,
        ];
    }

    return $out;
}

function transport_slugify(string $code, string $name): string
{
    $base = importer_slugify(trim($code . ' ' . $name), 'linha');
    return preg_replace('/-linha$/', '', $base) ?: ('linha-' . strtolower($code));
}

function transport_validate_time(string $time): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
}

/**
 * @param array<string, mixed> $opts
 * @return array<string, mixed>
 */
function transport_import_run(PDO $pdo, array $opts): array
{
    $sourceName = trim((string) $opts['source_name']);
    $sourceUrl = trim((string) ($opts['source_url'] ?? ''));
    if ($sourceName === '' || $sourceUrl === '') {
        throw new InvalidArgumentException('--source-name e --source-url são obrigatórios.');
    }
    if (!preg_match('#^https?://#i', $sourceUrl)) {
        throw new InvalidArgumentException('--source-url deve ser http(s).');
    }
    $file = importer_safe_file((string) $opts['file']);
    $dryRun = (bool) $opts['dry_run'];
    $raw = file_get_contents($file);
    if ($raw === false) {
        throw new RuntimeException('Não foi possível ler o arquivo.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('JSON inválido.');
    }
    $lines = $decoded['lines'] ?? $decoded['linhas'] ?? $decoded;
    if (!is_array($lines)) {
        throw new InvalidArgumentException('Arquivo sem lista de linhas.');
    }

    $sourceType = trim((string) ($decoded['source_type'] ?? $opts['source_type'] ?? 'other'));
    if (!in_array($sourceType, ['prefeitura', 'der', 'consorcio', 'other'], true)) {
        $sourceType = 'other';
    }

    $stats = [
        'dry_run' => $dryRun,
        'collected' => 0,
        'inserted' => 0,
        'updated' => 0,
        'rejected' => 0,
        'source_name' => $sourceName,
        'source_url' => $sourceUrl,
    ];

    $sourceId = null;
    $now = auth_now();
    if (!$dryRun) {
        $sourceId = auth_uuid();
        $pdo->prepare(
            'INSERT INTO transport_sources (id, name, url, type, collected_at, created_at, updated_at)
             VALUES (:id, :name, :url, :type, :collected_at, :created_at, :updated_at)'
        )->execute([
            ':id' => $sourceId,
            ':name' => $sourceName,
            ':url' => $sourceUrl,
            ':type' => $sourceType,
            ':collected_at' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    foreach ($lines as $rawLine) {
        if (!is_array($rawLine)) {
            continue;
        }
        $stats['collected']++;
        $code = trim((string) ($rawLine['code'] ?? $rawLine['numero'] ?? ''));
        $name = trim((string) ($rawLine['name'] ?? $rawLine['nome'] ?? ''));
        $type = strtolower(trim((string) ($rawLine['type'] ?? $rawLine['tipo'] ?? 'municipal')));
        $cityRaw = strtolower(trim((string) ($rawLine['city_slug'] ?? $rawLine['cidade'] ?? '')));
        $citySlug = null;
        if ($cityRaw !== '' && $cityRaw !== 'intermunicipal') {
            try {
                $citySlug = importer_resolve_city_slug($cityRaw);
            } catch (InvalidArgumentException $e) {
                $citySlug = null;
            }
        }
        if ($code === '' || $name === '' || !in_array($type, TRANSPORT_TYPES, true)) {
            $stats['rejected']++;
            continue;
        }
        $statusRaw = strtolower(trim((string) ($rawLine['status'] ?? 'unknown')));
        $statusMap = [
            'em-operacao' => 'active',
            'em_operacao' => 'active',
            'active' => 'active',
            'atrasada' => 'active',
            'encerrada' => 'inactive',
            'inactive' => 'inactive',
        ];
        $status = $statusMap[$statusRaw] ?? 'unknown';
        $cityId = null;
        if ($citySlug !== null) {
            $st = $pdo->prepare('SELECT id FROM cities WHERE slug = :slug LIMIT 1');
            $st->execute([':slug' => $citySlug]);
            $found = $st->fetchColumn();
            $cityId = is_string($found) ? $found : null;
        }

        $existing = $pdo->prepare(
            'SELECT id FROM transport_lines WHERE code = :code AND type = :type AND (city_id <=> :city_id) LIMIT 1'
        );
        $existing->execute([':code' => $code, ':type' => $type, ':city_id' => $cityId]);
        $existingId = $existing->fetchColumn();

        if ($dryRun) {
            if (is_string($existingId) && $existingId !== '') {
                $stats['updated']++;
            } else {
                $stats['inserted']++;
            }
            continue;
        }

        $lineId = is_string($existingId) && $existingId !== '' ? $existingId : auth_uuid();
        $slug = transport_slugify($code, $name);
        if ($existingId === false) {
            $n = 2;
            $base = $slug;
            while (true) {
                $chk = $pdo->prepare('SELECT 1 FROM transport_lines WHERE slug = :slug LIMIT 1');
                $chk->execute([':slug' => $slug]);
                if ($chk->fetchColumn() === false) {
                    break;
                }
                $slug = $base . '-' . $n;
                $n++;
            }
            $pdo->prepare(
                'INSERT INTO transport_lines
                    (id, city_id, source_id, code, slug, name, type, status, fare, operator_name, notes, external_id, created_at, updated_at)
                 VALUES
                    (:id, :city_id, :source_id, :code, :slug, :name, :type, :status, :fare, :operator_name, :notes, :external_id, :created_at, :updated_at)'
            )->execute([
                ':id' => $lineId,
                ':city_id' => $cityId,
                ':source_id' => $sourceId,
                ':code' => $code,
                ':slug' => $slug,
                ':name' => $name,
                ':type' => $type,
                ':status' => $status,
                ':fare' => trim((string) ($rawLine['fare'] ?? $rawLine['tarifa'] ?? '')) ?: null,
                ':operator_name' => trim((string) ($rawLine['operator_name'] ?? $rawLine['operadora'] ?? '')) ?: null,
                ':notes' => trim((string) ($rawLine['notes'] ?? '')) ?: null,
                ':external_id' => trim((string) ($rawLine['external_id'] ?? $code)),
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $stats['inserted']++;
        } else {
            $pdo->prepare(
                'UPDATE transport_lines SET
                    source_id = :source_id, name = :name, status = :status, fare = :fare,
                    operator_name = :operator_name, notes = :notes, updated_at = :updated_at
                 WHERE id = :id'
            )->execute([
                ':source_id' => $sourceId,
                ':name' => $name,
                ':status' => $status,
                ':fare' => trim((string) ($rawLine['fare'] ?? $rawLine['tarifa'] ?? '')) ?: null,
                ':operator_name' => trim((string) ($rawLine['operator_name'] ?? $rawLine['operadora'] ?? '')) ?: null,
                ':notes' => trim((string) ($rawLine['notes'] ?? '')) ?: null,
                ':updated_at' => $now,
                ':id' => $lineId,
            ]);
            $pdo->prepare('DELETE FROM transport_schedules WHERE line_id = :id')->execute([':id' => $lineId]);
            $pdo->prepare('DELETE FROM transport_stops WHERE line_id = :id')->execute([':id' => $lineId]);
            $stats['updated']++;
        }

        $schedules = $rawLine['schedules'] ?? [];
        if (isset($rawLine['horarios']) && is_array($rawLine['horarios'])) {
            $map = ['util' => 'weekday', 'sabado' => 'saturday', 'domingo' => 'sunday'];
            foreach ($map as $key => $day) {
                foreach ($rawLine['horarios'][$key] ?? [] as $t) {
                    $schedules[] = ['direction' => 'ida', 'day_type' => $day, 'departure_time' => (string) $t];
                }
            }
        }
        if (is_array($schedules)) {
            $ins = $pdo->prepare(
                'INSERT INTO transport_schedules
                    (id, line_id, direction, day_type, departure_time, control_point, notes, created_at, updated_at)
                 VALUES
                    (:id, :line_id, :direction, :day_type, :departure_time, :control_point, :notes, :created_at, :updated_at)'
            );
            foreach ($schedules as $sch) {
                if (!is_array($sch)) {
                    continue;
                }
                $time = trim((string) ($sch['departure_time'] ?? $sch['time'] ?? ''));
                $day = strtolower(trim((string) ($sch['day_type'] ?? 'weekday')));
                $dir = strtolower(trim((string) ($sch['direction'] ?? 'ida')));
                if (!transport_validate_time($time) || !in_array($day, TRANSPORT_DAY_TYPES, true) || !in_array($dir, TRANSPORT_DIRECTIONS, true)) {
                    continue;
                }
                $ins->execute([
                    ':id' => auth_uuid(),
                    ':line_id' => $lineId,
                    ':direction' => $dir,
                    ':day_type' => $day,
                    ':departure_time' => $time,
                    ':control_point' => trim((string) ($sch['control_point'] ?? '')) ?: null,
                    ':notes' => trim((string) ($sch['notes'] ?? '')) ?: null,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
            }
        }

        $stops = $rawLine['stops'] ?? $rawLine['pontos'] ?? [];
        if (is_array($stops)) {
            $insStop = $pdo->prepare(
                'INSERT INTO transport_stops
                    (id, line_id, sequence, name, address, lat, lng, direction, created_at, updated_at)
                 VALUES
                    (:id, :line_id, :sequence, :name, :address, :lat, :lng, :direction, :created_at, :updated_at)'
            );
            $seq = 0;
            foreach ($stops as $stop) {
                $seq++;
                if (is_string($stop)) {
                    $stop = ['name' => $stop, 'sequence' => $seq];
                }
                if (!is_array($stop)) {
                    continue;
                }
                $stopName = trim((string) ($stop['name'] ?? ''));
                if ($stopName === '') {
                    continue;
                }
                $dir = strtolower(trim((string) ($stop['direction'] ?? 'ida')));
                if (!in_array($dir, TRANSPORT_DIRECTIONS, true)) {
                    $dir = 'ida';
                }
                $insStop->execute([
                    ':id' => auth_uuid(),
                    ':line_id' => $lineId,
                    ':sequence' => (int) ($stop['sequence'] ?? $seq),
                    ':name' => $stopName,
                    ':address' => trim((string) ($stop['address'] ?? '')) ?: null,
                    ':lat' => isset($stop['lat']) && is_numeric($stop['lat']) ? $stop['lat'] : null,
                    ':lng' => isset($stop['lng']) && is_numeric($stop['lng']) ? $stop['lng'] : null,
                    ':direction' => $dir,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
            }
        }
    }

    return $stats;
}
