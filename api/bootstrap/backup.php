<?php

declare(strict_types=1);

const BACKUP_FORMAT = 'agendaqui-backup';
const BACKUP_SCHEMA_VERSION = '018';
const BACKUP_MAX_BYTES = 52428800;
const BACKUP_FILENAME_PATTERN = '/^agendaqui-backup-\d{8}T\d{6}Z-[a-f0-9]{12}\.json$/';

/**
 * Fixed dependency order: parents precede children for import.
 *
 * @return list<string>
 */
function backup_table_allowlist(): array
{
    return [
        'users', 'cities', 'categories', 'event_categories', 'listing_categories',
        'blog_categories', 'job_sources', 'system_settings', 'plans_config',
        'notification_templates', 'transport_sources',
        'profiles', 'user_roles', 'companies', 'company_categories', 'company_media',
        'company_projects', 'company_faqs', 'company_views', 'company_claims',
        'reviews', 'leads', 'leads_planos', 'favorites', 'newsletter_subscribers',
        'posts', 'post_categories', 'blog_posts_legacy', 'editorial_posts',
        'events', 'shows', 'event_sync_logs', 'public_services', 'emergency_contacts',
        'tourist_attractions', 'procurements', 'listings', 'listing_messages',
        'listing_reports', 'media', 'marketplace_items', 'promotions', 'coupons',
        'appointments', 'banners', 'ad_campaigns', 'analytics_events',
        'notifications', 'notification_preferences', 'push_subscriptions',
        'push_notifications', 'push_deliveries', 'push_inbox', 'qa_tickets',
        'qa_ticket_comments', 'qa_ticket_events', 'user_requests', 'live_feed_hidden',
        'jobs', 'job_sync_logs', 'representatives', 'representative_activities',
        'representative_attendance', 'representative_sync_logs', 'whatsapp_subscribers',
        'company_import_runs', 'company_sources', 'company_import_errors',
        'transport_lines', 'transport_schedules', 'transport_stops', 'bus_sync_logs',
        'shopee_products',
    ];
}

function backup_max_bytes(): int
{
    $raw = getenv('BACKUP_MAX_BYTES');
    if ($raw === false || !ctype_digit($raw)) {
        return BACKUP_MAX_BYTES;
    }

    return min(BACKUP_MAX_BYTES, max(1048576, (int) $raw));
}

function backup_storage_dir(): string
{
    $override = getenv('BACKUP_DIR');
    if (is_string($override) && trim($override) !== '') {
        return rtrim(trim($override), '/\\');
    }

    $documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/')
        : '';
    if ($documentRoot !== '') {
        return dirname($documentRoot) . DIRECTORY_SEPARATOR . 'agendaqui'
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
    }

    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
}

function backup_ensure_storage_dir(): string
{
    $dir = backup_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create private backup storage.');
    }
    @chmod($dir, 0700);

    return $dir;
}

function backup_filename(): string
{
    return 'agendaqui-backup-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6)) . '.json';
}

function backup_schema_version(PDO $pdo): string
{
    try {
        $value = $pdo->query(
            "SELECT MAX(version) FROM migrations WHERE success = 1 AND version REGEXP '^[0-9]{3}$'"
        )->fetchColumn();
        if (is_string($value) && preg_match('/^\d{3}$/', $value) === 1) {
            return $value;
        }
    } catch (Throwable $e) {
        // A pre-ledger database is exported with the code's expected schema marker.
    }

    return BACKUP_SCHEMA_VERSION;
}

/**
 * @return array{filename: string, path: string, manifest: array<string, mixed>}
 */
function backup_export(PDO $pdo): array
{
    $counts = [];
    $existingTables = [];
    foreach (backup_table_allowlist() as $table) {
        try {
            $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 0')->closeCursor();
        } catch (PDOException $e) {
            // Allows exports during additive rollout while retaining a fixed allowlist.
            if ((string) $e->getCode() === '42S02') {
                continue;
            }
            throw $e;
        }
        $existingTables[] = $table;
    }

    $createdAt = gmdate('Y-m-d\TH:i:s.000\Z');

    $dir = backup_ensure_storage_dir();
    $filename = backup_filename();
    $path = $dir . DIRECTORY_SEPARATOR . $filename;
    $temporary = $path . '.tmp';
    $handle = fopen($temporary, 'xb');
    if ($handle === false) {
        throw new RuntimeException('Unable to create backup.');
    }

    $bytes = 0;
    $write = static function (string $chunk) use ($handle, &$bytes): void {
        $bytes += strlen($chunk);
        if ($bytes > backup_max_bytes()) {
            throw new LengthException('Backup exceeds the configured size limit.');
        }
        $offset = 0;
        while ($offset < strlen($chunk)) {
            $written = fwrite($handle, substr($chunk, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write backup.');
            }
            $offset += $written;
        }
    };

    $transactionStarted = false;
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock backup file.');
        }
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->beginTransaction();
        $transactionStarted = true;
        $schemaVersion = backup_schema_version($pdo);
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $write('{"tables":{');
        $firstTable = true;
        foreach ($existingTables as $table) {
            $write(($firstTable ? '' : ',') . json_encode($table, JSON_THROW_ON_ERROR) . ':[');
            $firstTable = false;
            $firstRow = true;
            $rowCount = 0;
            $columns = backup_table_columns($pdo, $table);
            $columnSql = implode(
                ', ',
                array_map(static fn (string $column): string => "`$column`", $columns)
            );
            $statement = $pdo->query('SELECT ' . $columnSql . ' FROM `' . $table . '`');
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                $rowJson = json_encode(
                    $row,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
                );
                $write(($firstRow ? '' : ',') . $rowJson);
                $firstRow = false;
                $rowCount++;
            }
            $statement->closeCursor();
            $write(']');
            $counts[$table] = $rowCount;
        }
        $manifest = [
            'format' => BACKUP_FORMAT,
            'schema_version' => $schemaVersion,
            'created_at' => $createdAt,
            'counts' => $counts,
        ];
        $manifestJson = json_encode(
            $manifest,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
        $write('},"manifest":' . $manifestJson . '}');
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $pdo->commit();
        $transactionStarted = false;
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        @chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Unable to finalize backup.');
        }
    } catch (Throwable $e) {
        try {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        } catch (Throwable $ignored) {
        }
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_resource($handle)) {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
        @unlink($temporary);
        throw $e;
    }
    @chmod($path, 0600);

    return ['filename' => $filename, 'path' => $path, 'manifest' => $manifest];
}

/**
 * @param array<string, mixed> $row
 */
function backup_validate_row_constraints(string $table, array $row): void
{
    if (!in_array($table, ['coupons', 'promotions'], true) || !array_key_exists('discount_percent', $row)) {
        return;
    }
    $discount = $row['discount_percent'];
    if ($discount === null) {
        return;
    }
    if (!is_int($discount) && !is_float($discount) && !(is_string($discount) && is_numeric($discount))) {
        throw new InvalidArgumentException('Backup contains an invalid discount.');
    }
    $numeric = (float) $discount;
    if ($numeric < 0 || $numeric > 100 || floor($numeric) !== $numeric) {
        throw new InvalidArgumentException('Backup contains an invalid discount.');
    }
}

/**
 * @param array<string, mixed> $document
 * @return array{schema_version: string, tables: array<string, list<array<string, mixed>>>}
 */
function backup_validate_document(array $document, int $maxBytes): array
{
    $encoded = json_encode($document);
    if (!is_string($encoded) || strlen($encoded) > $maxBytes) {
        throw new InvalidArgumentException('Backup is too large or cannot be encoded.');
    }
    $manifest = $document['manifest'] ?? null;
    $tables = $document['tables'] ?? null;
    if (!is_array($manifest) || !is_array($tables)) {
        throw new InvalidArgumentException('Backup manifest and tables are required.');
    }
    if (($manifest['format'] ?? null) !== BACKUP_FORMAT) {
        throw new InvalidArgumentException('Unsupported backup format.');
    }
    $schemaVersion = $manifest['schema_version'] ?? null;
    if (!is_string($schemaVersion) || preg_match('/^\d{3}$/', $schemaVersion) !== 1) {
        throw new InvalidArgumentException('Invalid backup schema version.');
    }
    $createdAt = $manifest['created_at'] ?? null;
    if (!is_string($createdAt) || strtotime($createdAt) === false) {
        throw new InvalidArgumentException('Invalid backup creation timestamp.');
    }
    $counts = $manifest['counts'] ?? null;
    if (!is_array($counts)) {
        throw new InvalidArgumentException('Backup table counts are required.');
    }

    $allowed = array_flip(backup_table_allowlist());
    $validated = [];
    foreach ($tables as $table => $rows) {
        if (!is_string($table) || !isset($allowed[$table]) || !is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('Backup contains an invalid table.');
        }
        foreach ($rows as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new InvalidArgumentException('Backup contains an invalid row.');
            }
            backup_validate_row_constraints($table, $row);
        }
        if (!isset($counts[$table]) || !is_int($counts[$table]) || $counts[$table] !== count($rows)) {
            throw new InvalidArgumentException('Backup table count does not match manifest.');
        }
        $validated[$table] = $rows;
    }
    foreach ($counts as $table => $count) {
        if (!array_key_exists((string) $table, $validated) || !is_int($count)) {
            throw new InvalidArgumentException('Backup manifest references an invalid table.');
        }
    }

    return ['schema_version' => $schemaVersion, 'tables' => $validated];
}

/**
 * @return array<string, mixed>
 */
function backup_read_file(string $path): array
{
    $size = filesize($path);
    if ($size === false || $size <= 0 || $size > backup_max_bytes()) {
        throw new InvalidArgumentException('Backup file size is invalid.');
    }
    $json = file_get_contents($path);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to read backup file.');
    }
    $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($document)) {
        throw new InvalidArgumentException('Backup JSON root must be an object.');
    }

    return $document;
}

/**
 * @param array<string, mixed> $column
 */
function backup_column_is_generated(array $column): bool
{
    $extra = isset($column['Extra']) ? (string) $column['Extra'] : '';

    return stripos($extra, 'GENERATED') !== false;
}

/**
 * @return list<string>
 */
function backup_table_columns(PDO $pdo, string $table): array
{
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (backup_column_is_generated($row)) {
            continue;
        }
        $columns[] = (string) $row['Field'];
    }

    return $columns;
}

/**
 * @return list<string>
 */
function backup_primary_columns(PDO $pdo, string $table): array
{
    $columns = [];
    $statement = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = 'PRIMARY' ORDER BY Seq_in_index");
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[] = (string) $row['Column_name'];
    }
    if ($columns === []) {
        throw new RuntimeException('Backup table has no primary key.');
    }

    return $columns;
}

/**
 * @param array<string, mixed> $row
 * @param list<string> $primaryColumns
 * @return array{exists_sql: string, insert_sql: string, update_sql: string}
 */
function backup_upsert_plan(string $table, array $row, array $primaryColumns): array
{
    $columns = array_keys($row);
    if ($columns === [] || array_diff($primaryColumns, $columns) !== []) {
        throw new InvalidArgumentException('Backup row is missing its primary key.');
    }
    $where = array_map(
        static fn (string $column): string => "`$column` = :pk_$column",
        $primaryColumns
    );
    $quoted = array_map(static fn (string $column): string => "`$column`", $columns);
    $values = array_map(static fn (string $column): string => ":value_$column", $columns);
    $mutable = array_values(array_diff($columns, $primaryColumns));
    $sets = array_map(
        static fn (string $column): string => "`$column` = :set_$column",
        $mutable
    );

    return [
        'exists_sql' => "SELECT 1 FROM `$table` WHERE " . implode(' AND ', $where) . ' LIMIT 1',
        'insert_sql' => "INSERT INTO `$table` (" . implode(', ', $quoted) . ') VALUES (' . implode(', ', $values) . ')',
        'update_sql' => $sets === []
            ? ''
            : "UPDATE `$table` SET " . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $where),
    ];
}

function backup_bind_value(mixed $value): mixed
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    return $value;
}

/**
 * @param array<string, list<array<string, mixed>>> $tables
 * @return array<string, int>
 */
function backup_import_tables(PDO $pdo, array $tables): array
{
    $result = [];
    $pdo->beginTransaction();
    try {
        foreach (backup_table_allowlist() as $table) {
            $rows = $tables[$table] ?? null;
            if ($rows === null || $rows === []) {
                continue;
            }
            $allowedColumns = array_flip(backup_table_columns($pdo, $table));
            $primaryColumns = backup_primary_columns($pdo, $table);
            $count = 0;
            foreach ($rows as $row) {
                $columns = array_keys($row);
                if ($columns === [] || array_diff_key($row, $allowedColumns) !== []) {
                    throw new InvalidArgumentException('Backup row contains an invalid column.');
                }
                $plan = backup_upsert_plan($table, $row, $primaryColumns);
                $primaryParams = [];
                foreach ($primaryColumns as $column) {
                    $primaryParams[":pk_$column"] = backup_bind_value($row[$column]);
                }
                $exists = $pdo->prepare($plan['exists_sql']);
                $exists->execute($primaryParams);
                if ($exists->fetchColumn() === false) {
                    $params = [];
                    foreach ($row as $column => $value) {
                        $params[":value_$column"] = backup_bind_value($value);
                    }
                    $statement = $pdo->prepare($plan['insert_sql']);
                    $statement->execute($params);
                } elseif ($plan['update_sql'] !== '') {
                    $params = $primaryParams;
                    foreach ($row as $column => $value) {
                        if (!in_array($column, $primaryColumns, true)) {
                            $params[":set_$column"] = backup_bind_value($value);
                        }
                    }
                    $statement = $pdo->prepare($plan['update_sql']);
                    $statement->execute($params);
                }
                $count++;
            }
            $result[$table] = $count;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $result;
}

function backup_resolve_download(string $filename): string
{
    if (preg_match(BACKUP_FILENAME_PATTERN, $filename) !== 1) {
        throw new InvalidArgumentException('Invalid backup filename.');
    }
    $dir = backup_ensure_storage_dir();
    $path = $dir . DIRECTORY_SEPARATOR . $filename;
    $realDir = realpath($dir);
    $realPath = realpath($path);
    if ($realDir === false || $realPath === false || dirname($realPath) !== $realDir || !is_file($realPath)) {
        throw new InvalidArgumentException('Backup file not found.');
    }

    return $realPath;
}
