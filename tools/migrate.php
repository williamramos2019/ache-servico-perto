<?php

declare(strict_types=1);

/**
 * AgendaAqui MySQL/MariaDB migration runner (CLI only).
 *
 * Usage:
 *   php tools/migrate.php
 *   php tools/migrate.php --status
 *   php tools/migrate.php --dry-run
 *   php tools/migrate.php --help
 */

if (!defined('STDOUT')) {
    define('STDOUT', fopen('php://output', 'w'));
}
if (!defined('STDERR')) {
    define('STDERR', fopen('php://output', 'w'));
}

if (PHP_SAPI !== 'cli' && !(defined('AGENDAQUI_INSTALLER') && AGENDAQUI_INSTALLER === true)) {
    fwrite(STDERR, "This administrative tool can only be run from the command line.\n");
    exit(1);
}

require dirname(__DIR__) . '/api/bootstrap/database.php';

const MIGRATION_LOCK_NAME = 'migration_runner_lock';
const MIGRATION_FILENAME_PATTERN = '/^(\d{3})_([a-z0-9_]+)\.sql$/';

function migrate_write(string $text, bool $error = false): void
{
    if (PHP_SAPI === 'cli') {
        fwrite($error ? STDERR : STDOUT, $text);
        return;
    }
    echo $text;
}

function emit_error(string $message): void
{
    log_line('ERROR', $message);
}

function log_line(string $level, string $message): void
{
    $prefix = '[' . $level . ']';
    $line = $prefix . ' ' . $message . PHP_EOL;
    migrate_write($line, $level === 'ERROR');
}

function repo_root(): string
{
    return dirname(__DIR__);
}

function migrations_dir(): string
{
    $override = getenv('MIGRATIONS_DIR');
    if ($override !== false && $override !== '') {
        return $override;
    }

    return repo_root() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
}

function lock_timeout_seconds(): int
{
    $raw = getenv('MIGRATION_LOCK_TIMEOUT');
    if ($raw === false || $raw === '') {
        return 30;
    }

    $timeout = (int) $raw;
    return $timeout >= 0 ? $timeout : 30;
}

function utc_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
}

function print_help(): void
{
    $help = <<<TXT
AgendaAqui migration runner

Usage:
  php tools/migrate.php           Apply pending migrations
  php tools/migrate.php --status  Show applied / pending / checksum state
  php tools/migrate.php --dry-run Show pending migrations without changing the database
  php tools/migrate.php --help    Show this help

Environment:
  DB_HOST                 required
  DB_PORT                 optional, default 3306
  DB_DATABASE             required
  DB_USERNAME             required
  DB_PASSWORD             optional
  MIGRATIONS_DIR          optional override of database/migrations
  MIGRATION_LOCK_TIMEOUT  optional seconds, default 30

Rules:
  - CLI only. Not available over HTTP.
  - Migrations are immutable after a successful apply (SHA-256 checksum).
  - A failed migration is never treated as applied.
  - Destructive SQL (DROP DATABASE/TABLE/COLUMN, TRUNCATE) is blocked.
  - There is no --force flag.

TXT;
    migrate_write($help);
}

/**
 * @return array{status: bool, dry_run: bool, help: bool}
 */
function parse_args(array $argv): array
{
    $flags = [
        'status' => false,
        'dry_run' => false,
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--status') {
            $flags['status'] = true;
            continue;
        }
        if ($arg === '--dry-run') {
            $flags['dry_run'] = true;
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            $flags['help'] = true;
            continue;
        }
        if ($arg === '--force') {
            throw new RuntimeException('--force is not supported. Changed migrations must be fixed with a new file.');
        }
        throw new RuntimeException('Unknown argument: ' . $arg . '. Use --help.');
    }

    return $flags;
}

/**
 * @return list<array{version: string, name: string, path: string, checksum: string}>
 */
function discover_migrations(string $dir): array
{
    if (!is_dir($dir)) {
        throw new RuntimeException('Migrations directory not found: ' . $dir);
    }

    $paths = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
    if ($paths === false) {
        throw new RuntimeException('Unable to read migrations directory: ' . $dir);
    }

    $migrations = [];
    $seen = [];

    foreach ($paths as $path) {
        $name = basename($path);
        if (!preg_match(MIGRATION_FILENAME_PATTERN, $name, $match)) {
            throw new RuntimeException(
                'Invalid migration filename: ' . $name . '. Expected NNN_lowercase_name.sql'
            );
        }

        $version = $match[1];
        if (isset($seen[$version])) {
            throw new RuntimeException(
                'Duplicate migration version ' . $version . ': ' . $seen[$version] . ' and ' . $name
            );
        }
        $seen[$version] = $name;

        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw new RuntimeException('Unable to checksum migration: ' . $name);
        }

        $migrations[] = [
            'version' => $version,
            'name' => $name,
            'path' => $path,
            'checksum' => $checksum,
        ];
    }

    usort($migrations, static function (array $a, array $b): int {
        return ((int) $a['version']) <=> ((int) $b['version']);
    });

    return $migrations;
}

function strip_sql_comments(string $sql): string
{
    $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql) ?? $sql;
    $sql = preg_replace('/--[^\n]*/', ' ', $sql) ?? $sql;
    $sql = preg_replace('/#[^\n]*/', ' ', $sql) ?? $sql;

    return $sql;
}

function assert_sql_not_destructive(string $sql, string $name): void
{
    $body = strip_sql_comments($sql);
    $rules = [
        '/\bDROP\s+DATABASE\b/i' => 'DROP DATABASE',
        '/\bDROP\s+SCHEMA\b/i' => 'DROP SCHEMA',
        '/\bDROP\s+TABLE\b/i' => 'DROP TABLE',
        '/\bDROP\s+COLUMN\b/i' => 'DROP COLUMN',
        '/\bTRUNCATE\b/i' => 'TRUNCATE',
        '/\bALTER\s+TABLE\b(?:(?!;).)*\bDROP\b/is' => 'ALTER TABLE ... DROP',
    ];

    foreach ($rules as $pattern => $label) {
        if (preg_match($pattern, $body) === 1) {
            throw new RuntimeException(
                'Destructive SQL blocked in ' . $name . ': ' . $label
                . '. The runner will not execute this automatically. Write a reviewed follow-up process if it is truly required.'
            );
        }
    }
}

function migrations_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
    if ($stmt === false) {
        return false;
    }

    return $stmt->fetch() !== false;
}

/**
 * @return array<string, array{version: string, name: string, checksum: string, success: int}>
 */
function load_applied(PDO $pdo): array
{
    if (!migrations_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        'SELECT version, name, checksum, success FROM migrations ORDER BY version ASC'
    );
    if ($stmt === false) {
        throw new RuntimeException('Unable to read migrations table.');
    }

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(string) $row['version']] = [
            'version' => (string) $row['version'],
            'name' => (string) $row['name'],
            'checksum' => (string) $row['checksum'],
            'success' => (int) $row['success'],
        ];
    }

    return $rows;
}

function record_migration(
    PDO $pdo,
    string $version,
    string $name,
    string $checksum,
    int $elapsedMs,
    bool $success
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO migrations (version, name, checksum, executed_at, execution_time_ms, success)
         VALUES (:version, :name, :checksum, :executed_at, :execution_time_ms, :success)'
    );
    $stmt->execute([
        ':version' => $version,
        ':name' => $name,
        ':checksum' => $checksum,
        ':executed_at' => utc_now(),
        ':execution_time_ms' => $elapsedMs,
        ':success' => $success ? 1 : 0,
    ]);
}

function acquire_lock(PDO $pdo): void
{
    $timeout = lock_timeout_seconds();
    log_line('INFO', 'Acquiring migration lock');

    $stmt = $pdo->prepare('SELECT GET_LOCK(:name, :timeout)');
    $stmt->execute([
        ':name' => MIGRATION_LOCK_NAME,
        ':timeout' => $timeout,
    ]);
    $result = $stmt->fetchColumn();

    if ((int) $result !== 1) {
        throw new RuntimeException(
            'Unable to acquire migration lock "' . MIGRATION_LOCK_NAME . '". Another migration may be running.'
        );
    }

    log_line('OK', 'Lock acquired');
}

function release_lock(PDO $pdo): void
{
    log_line('INFO', 'Releasing migration lock');
    try {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
        $stmt->execute([':name' => MIGRATION_LOCK_NAME]);
        log_line('OK', 'Lock released');
    } catch (Throwable $e) {
        log_line('WARNING', 'Unable to release migration lock cleanly.');
    }
}

/**
 * @param list<array{version: string, name: string, path: string, checksum: string}> $files
 * @param array<string, array{version: string, name: string, checksum: string, success: int}> $applied
 * @return array{pending: list<array{version: string, name: string, path: string, checksum: string}>, errors: list<string>}
 */
function classify_migrations(array $files, array $applied): array
{
    $pending = [];
    $errors = [];

    foreach ($applied as $row) {
        if ((int) $row['success'] !== 1) {
            $errors[] = 'Migration ' . $row['name'] . ' previously failed (success=0). '
                . 'It is not applied. Inspect the database, then DELETE the failed row before retrying.';
        }
    }

    foreach ($files as $file) {
        $row = $applied[$file['version']] ?? null;
        if ($row === null) {
            $pending[] = $file;
            continue;
        }

        if ((int) $row['success'] !== 1) {
            continue;
        }

        if (!hash_equals($row['checksum'], $file['checksum'])) {
            $errors[] = 'MIGRATION CHECKSUM MISMATCH'
                . PHP_EOL . 'Migration ' . $file['name'] . ' already applied, but its checksum changed.'
                . PHP_EOL . 'Registered: ' . $row['checksum']
                . PHP_EOL . 'Current:    ' . $file['checksum']
                . PHP_EOL . 'Do not edit applied migrations. Add a new migration instead.';
        }
    }

    return ['pending' => $pending, 'errors' => $errors];
}

function apply_migration(PDO $pdo, array $file): void
{
    $sql = file_get_contents($file['path']);
    if ($sql === false) {
        throw new RuntimeException('Unable to read migration: ' . $file['name']);
    }

    assert_sql_not_destructive($sql, $file['name']);

    $started = hrtime(true);
    $inTransaction = false;

    try {
        $inTransaction = $pdo->beginTransaction();
        $pdo->exec($sql);
        if ($inTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
        $elapsedMs = (int) ((hrtime(true) - $started) / 1_000_000);
        record_migration($pdo, $file['version'], $file['name'], $file['checksum'], $elapsedMs, true);
        log_line('OK', $file['name'] . ' applied');
    } catch (Throwable $e) {
        $elapsedMs = (int) ((hrtime(true) - $started) / 1_000_000);
        if ($inTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        try {
            if (migrations_table_exists($pdo)) {
                record_migration($pdo, $file['version'], $file['name'], $file['checksum'], $elapsedMs, false);
            }
        } catch (Throwable $recordError) {
            log_line('WARNING', 'Could not record failed migration row for ' . $file['name']);
        }

        throw new RuntimeException(
            'Migration failed: ' . $file['name'] . ' — ' . $e->getMessage(),
            0,
            $e
        );
    }
}

function print_status(array $files, array $applied, array $pending, bool $tableReady): void
{
    log_line('INFO', 'Checking migrations');
    if (!$tableReady) {
        log_line('WARNING', 'Table migrations does not exist yet');
    }

    migrate_write( PHP_EOL . 'Found:' . PHP_EOL);
    if ($files === []) {
        migrate_write( '  (none)' . PHP_EOL);
    }
    foreach ($files as $file) {
        migrate_write( '  ' . $file['name'] . '  checksum=' . $file['checksum'] . PHP_EOL);
    }

    migrate_write( PHP_EOL . 'Applied:' . PHP_EOL);
    $successful = array_filter($applied, static fn(array $row): bool => (int) $row['success'] === 1);
    if ($successful === []) {
        migrate_write( '  (none)' . PHP_EOL);
    }
    foreach ($successful as $row) {
        migrate_write( '  ' . $row['name'] . '  checksum=' . $row['checksum'] . PHP_EOL);
    }

    migrate_write( PHP_EOL . 'Pending:' . PHP_EOL);
    if ($pending === []) {
        migrate_write( '  (none)' . PHP_EOL);
    }
    foreach ($pending as $file) {
        migrate_write( '  ' . $file['name'] . '  checksum=' . $file['checksum'] . PHP_EOL);
    }
    migrate_write( PHP_EOL);
}

function main(array $argv): int
{
    try {
        $flags = parse_args($argv);
    } catch (Throwable $e) {
        emit_error($e->getMessage());
        return 1;
    }

    if ($flags['help']) {
        print_help();
        return 0;
    }

    try {
        $files = discover_migrations(migrations_dir());
    } catch (Throwable $e) {
        emit_error($e->getMessage());
        return 1;
    }

    if (PHP_SAPI === 'cli') {
        log_line('INFO', 'Connecting to database');
    }
    try {
        $pdo = db_pdo(true);
    } catch (Throwable $e) {
        emit_error($e->getMessage());
        return 1;
    }
    log_line('OK', 'Database connection established');

    $lockHeld = false;
    $needsLock = !$flags['status'] && !$flags['dry_run'];

    try {
        if ($needsLock) {
            acquire_lock($pdo);
            $lockHeld = true;
        }

        $tableReady = migrations_table_exists($pdo);
        if ($tableReady) {
            log_line('OK', 'Migration table ready');
        } else {
            log_line('INFO', 'Migration table not created yet');
        }

        $applied = load_applied($pdo);
        $classified = classify_migrations($files, $applied);
        $pending = $classified['pending'];

        if ($flags['status'] || $flags['dry_run']) {
            print_status($files, $applied, $pending, $tableReady);
        }

        if ($classified['errors'] !== []) {
            foreach ($classified['errors'] as $error) {
                log_line('ERROR', $error);
            }
            return 1;
        }

        if ($flags['status']) {
            log_line('OK', 'Status completed');
            return 0;
        }

        if ($flags['dry_run']) {
            log_line('INFO', 'Dry-run: no database changes were made');
            if ($pending === []) {
                log_line('OK', 'No pending migrations');
            } else {
                log_line('INFO', count($pending) . ' migration(s) would be executed');
            }
            return 0;
        }

        log_line('INFO', 'Checking migrations');

        if ($pending === []) {
            log_line('OK', 'No pending migrations');
            log_line('OK', 'Migration process completed');
            return 0;
        }

        $executed = 0;
        foreach ($pending as $file) {
            log_line('INFO', $file['name'] . ' pending');
            apply_migration($pdo, $file);
            $executed++;
        }

        migrate_write( PHP_EOL . 'Result:' . PHP_EOL);
        migrate_write( '  ' . $executed . ' migration(s) executed successfully.' . PHP_EOL . PHP_EOL);
        log_line('OK', 'Migration process completed');
        return 0;
    } catch (Throwable $e) {
        emit_error($e->getMessage());
        return 1;
    } finally {
        if ($lockHeld) {
            release_lock($pdo);
        }
    }
}

if (PHP_SAPI === 'cli') {
    exit(main($argv));
}
