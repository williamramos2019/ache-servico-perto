<?php

declare(strict_types=1);

const TRANSPORT_TYPES = ['municipal', 'metropolitana', 'intermunicipal', 'tarifa-zero'];
const TRANSPORT_STATUSES = ['active', 'inactive', 'unknown', 'suspended', 'temporary'];
const TRANSPORT_DAY_TYPES = ['weekday', 'saturday', 'sunday', 'holiday', 'vacation', 'atypical'];
const TRANSPORT_DIRECTIONS = ['ida', 'volta', 'circular'];
const TRANSPORT_SOURCE_TYPES = ['prefeitura', 'der', 'consorcio', 'operador', 'dados-abertos', 'other'];
const TRANSPORT_PAGE_DEFAULT = 24;
const TRANSPORT_PAGE_MAX = 50;

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
        'schedule_count' => isset($row['schedule_count']) ? (int) $row['schedule_count'] : count($schedules),
        'stop_count' => isset($row['stop_count']) ? (int) $row['stop_count'] : count($stops),
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

function transport_like(string $raw): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $raw) . '%';
}

function transport_is_uuid(string $id): bool
{
    return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $id) === 1;
}

/**
 * @param array<string, mixed> $get
 * @return array{ok: bool, error: ?string, city: string, type: string, status: string, q: string, page: int, limit: int}
 */
function transport_normalize_list_query(array $get): array
{
    $city = trim((string) ($get['city'] ?? ''));
    $type = trim((string) ($get['type'] ?? ''));
    $status = trim((string) ($get['status'] ?? ''));
    $q = trim((string) ($get['q'] ?? ''));
    if (function_exists('mb_substr')) {
        $q = mb_substr($q, 0, 80);
    } else {
        $q = substr($q, 0, 80);
    }
    $page = isset($get['page']) ? (int) $get['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }
    $limit = isset($get['limit']) ? (int) $get['limit'] : TRANSPORT_PAGE_DEFAULT;
    if ($limit < 1) {
        $limit = TRANSPORT_PAGE_DEFAULT;
    }
    if ($limit > TRANSPORT_PAGE_MAX) {
        $limit = TRANSPORT_PAGE_MAX;
    }
    if ($type !== '' && !in_array($type, TRANSPORT_TYPES, true)) {
        return ['ok' => false, 'error' => 'type inválido.', 'city' => '', 'type' => '', 'status' => '', 'q' => '', 'page' => 1, 'limit' => TRANSPORT_PAGE_DEFAULT];
    }
    if ($status !== '' && !in_array($status, TRANSPORT_STATUSES, true)) {
        return ['ok' => false, 'error' => 'status inválido.', 'city' => '', 'type' => '', 'status' => '', 'q' => '', 'page' => 1, 'limit' => TRANSPORT_PAGE_DEFAULT];
    }
    if ($city !== '' && !in_array($city, ['todas', 'intermunicipal', 'sjl', 'vesp', 'vespasiano', 'sao-jose-da-lapa', 'sao_jose_da_lapa'], true)) {
        try {
            importer_resolve_city_slug($city);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => 'cidade inválida.', 'city' => '', 'type' => '', 'status' => '', 'q' => '', 'page' => 1, 'limit' => TRANSPORT_PAGE_DEFAULT];
        }
    }

    return ['ok' => true, 'error' => null, 'city' => $city, 'type' => $type, 'status' => $status, 'q' => $q, 'page' => $page, 'limit' => $limit];
}

/**
 * @return array{where: list<string>, params: array<string, mixed>}
 */
function transport_list_where(string $city, string $type, string $status, string $q): array
{
    $where = ['1=1'];
    $params = [];
    if ($city !== '' && $city !== 'todas' && $city !== 'intermunicipal') {
        try {
            $city = importer_resolve_city_slug($city);
        } catch (InvalidArgumentException $e) {
            $city = '';
        }
        if ($city !== '') {
            $where[] = 'ci.slug = :city';
            $params[':city'] = $city;
        }
    } elseif ($city === 'intermunicipal') {
        $where[] = 'l.type = :icity';
        $params[':icity'] = 'intermunicipal';
    }
    if ($type !== '' && in_array($type, TRANSPORT_TYPES, true)) {
        $where[] = 'l.type = :type';
        $params[':type'] = $type;
    }
    if ($status !== '' && in_array($status, TRANSPORT_STATUSES, true)) {
        $where[] = 'l.status = :status';
        $params[':status'] = $status;
    }
    if ($q !== '') {
        $like = transport_like($q);
        $where[] = '(l.code LIKE :q_code OR l.name LIKE :q_name OR l.operator_name LIKE :q_op
            OR ci.name LIKE :q_city OR ci.slug LIKE :q_cslug
            OR EXISTS (
                SELECT 1 FROM transport_stops ts
                WHERE ts.line_id = l.id
                  AND (ts.name LIKE :q_stop OR IFNULL(ts.address, \'\') LIKE :q_addr)
            ))';
        $params[':q_code'] = $like;
        $params[':q_name'] = $like;
        $params[':q_op'] = $like;
        $params[':q_city'] = $like;
        $params[':q_cslug'] = $like;
        $params[':q_stop'] = $like;
        $params[':q_addr'] = $like;
    }

    return ['where' => $where, 'params' => $params];
}

/**
 * @param list<string> $argv
 * @return array<string, mixed>
 */
function transport_parse_argv(array $argv): array
{
    $opts = [
        'file' => '',
        'source_name' => '',
        'source_url' => '',
        'source_type' => 'other',
        'dry_run' => false,
        'update' => false,
        'resume' => false,
        'limit' => null,
        'help' => false,
    ];
    $args = array_values(array_slice($argv, 1));
    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
            continue;
        }
        if ($arg === '--dry-run') {
            $opts['dry_run'] = true;
            continue;
        }
        if ($arg === '--update') {
            $opts['update'] = true;
            continue;
        }
        if ($arg === '--resume') {
            $opts['resume'] = true;
            continue;
        }
        if ($arg === '--source') {
            $val = $args[$i + 1] ?? '';
            if ($val === '' || str_starts_with($val, '--')) {
                throw new InvalidArgumentException('Opção --source exige um valor.');
            }
            $opts['source_type'] = $val;
            $i++;
            continue;
        }
        if (str_starts_with($arg, '--source=')) {
            $opts['source_type'] = substr($arg, strlen('--source='));
            continue;
        }
        foreach (['file', 'source-name', 'source-url', 'source-type', 'limit'] as $key) {
            $flag = '--' . $key;
            $optKey = str_replace('-', '_', $key);
            if ($arg === $flag) {
                $val = $args[$i + 1] ?? '';
                if ($val === '' || str_starts_with($val, '--')) {
                    throw new InvalidArgumentException("Opção $flag exige um valor.");
                }
                $opts[$optKey] = $val;
                $i++;
                continue 2;
            }
            if (str_starts_with($arg, $flag . '=')) {
                $opts[$optKey] = substr($arg, strlen($flag) + 1);
                continue 2;
            }
        }
        throw new InvalidArgumentException('Opção desconhecida: ' . $arg);
    }
    if ($opts['limit'] !== null && $opts['limit'] !== '') {
        $lim = (int) $opts['limit'];
        $opts['limit'] = $lim > 0 ? $lim : null;
    } else {
        $opts['limit'] = null;
    }

    return $opts;
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
    $dryRun = (bool) $opts['dry_run'];
    $allowUpdate = (bool) ($opts['update'] ?? false);
    $resume = (bool) ($opts['resume'] ?? false);
    $limit = isset($opts['limit']) && is_int($opts['limit']) ? $opts['limit'] : null;

    $providedLines = $opts['lines'] ?? null;
    if (is_array($providedLines)) {
        $lines = $providedLines;
        $decoded = ['source_type' => $opts['source_type'] ?? 'other'];
    } else {
        $file = importer_safe_file((string) $opts['file']);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $lines = importer_read_csv($file);
            $decoded = ['source_type' => $opts['source_type'] ?? 'other'];
        } else {
            $raw = file_get_contents($file);
            if ($raw === false) {
                throw new RuntimeException('Não foi possível ler o arquivo.');
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('JSON inválido.');
            }
            $lines = $decoded['lines'] ?? $decoded['linhas'] ?? $decoded['data'] ?? $decoded;
        }
    }
    if (!is_array($lines)) {
        throw new InvalidArgumentException('Arquivo sem lista de linhas.');
    }
    if ($lines !== [] && array_keys($lines) !== range(0, count($lines) - 1)) {
        $lines = [$lines];
    }

    $sourceType = trim((string) ($decoded['source_type'] ?? $opts['source_type'] ?? 'other'));
    if (!in_array($sourceType, TRANSPORT_SOURCE_TYPES, true)) {
        $sourceType = 'other';
    }

    $stats = [
        'dry_run' => $dryRun,
        'collected' => 0,
        'inserted' => 0,
        'updated' => 0,
        'duplicates' => 0,
        'rejected' => 0,
        'skipped' => 0,
        'source_name' => $sourceName,
        'source_url' => $sourceUrl,
        'wrote_source' => false,
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
        $stats['wrote_source'] = true;
    }

    foreach ($lines as $rawLine) {
        if ($limit !== null && ($stats['inserted'] + $stats['updated'] + $stats['duplicates'] + $stats['skipped'] + $stats['rejected']) >= $limit) {
            break;
        }
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
            'suspended' => 'suspended',
            'suspensa' => 'suspended',
            'temporary' => 'temporary',
            'temporaria' => 'temporary',
            'temporária' => 'temporary',
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
            'SELECT id, source_id FROM transport_lines WHERE code = :code AND type = :type AND (city_id <=> :city_id) LIMIT 1'
        );
        $existing->execute([':code' => $code, ':type' => $type, ':city_id' => $cityId]);
        $existingRow = $existing->fetch();
        $existingId = $existingRow !== false ? (string) $existingRow['id'] : false;

        if ($dryRun) {
            if (is_string($existingId) && $existingId !== '') {
                if ($resume) {
                    $stats['skipped']++;
                } elseif ($allowUpdate) {
                    $stats['updated']++;
                } else {
                    $stats['duplicates']++;
                }
            } else {
                $stats['inserted']++;
            }
            continue;
        }

        if (is_string($existingId) && $existingId !== '' && $resume && !$allowUpdate) {
            $stats['skipped']++;
            continue;
        }

        $lineId = is_string($existingId) && $existingId !== '' ? $existingId : auth_uuid();
        $slug = transport_slugify($code, $name);
        $replaceChildren = false;
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
            $replaceChildren = true;
        } else {
            if (!$allowUpdate) {
                $stats['duplicates']++;
                continue;
            }
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
            // --update replaces imported schedules/stops. Without --update, existing children are kept
            // (admin may have edited them by hand; we cannot tell origin without a 013 column).
            $pdo->prepare('DELETE FROM transport_schedules WHERE line_id = :id')->execute([':id' => $lineId]);
            $pdo->prepare('DELETE FROM transport_stops WHERE line_id = :id')->execute([':id' => $lineId]);
            $stats['updated']++;
            $replaceChildren = true;
        }

        if (!$replaceChildren) {
            continue;
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
                $addr = trim((string) ($stop['address'] ?? ''));
                $nbhd = trim((string) ($stop['neighborhood'] ?? $stop['bairro'] ?? ''));
                if ($nbhd !== '') {
                    $addr = $addr === '' ? ('Bairro: ' . $nbhd) : ($addr . ' — ' . $nbhd);
                }
                $insStop->execute([
                    ':id' => auth_uuid(),
                    ':line_id' => $lineId,
                    ':sequence' => (int) ($stop['sequence'] ?? $seq),
                    ':name' => $stopName,
                    ':address' => $addr !== '' ? $addr : null,
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
