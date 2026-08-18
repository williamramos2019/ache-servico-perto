<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/auth.php';
require dirname(__DIR__) . '/bootstrap/backup.php';

app_start(['GET', 'POST', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
require_role('admin');

$method = app_request_method();
$op = isset($_GET['op']) ? (string) $_GET['op'] : '';

if ($method === 'GET' && $op === 'download') {
    $filename = isset($_GET['file']) ? (string) $_GET['file'] : '';
    try {
        $path = backup_resolve_download($filename);
    } catch (InvalidArgumentException $e) {
        app_error('invalid_backup', 'Backup file is unavailable.', 404);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if ($method !== 'POST') {
    app_error('invalid_op', 'Operation is not available.', 400);
}

require_csrf();

if ($op === 'export') {
    try {
        $result = backup_export($pdo);
    } catch (LengthException $e) {
        app_error('backup_too_large', 'Backup exceeds the size limit.', 413);
    }
    app_success([
        'filename' => $result['filename'],
        'manifest' => $result['manifest'],
        'download_url' => '/api/admin/backup.php?op=download&file=' . rawurlencode($result['filename']),
    ], 201);
}

if ($op === 'import') {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > backup_max_bytes()) {
        app_error('backup_too_large', 'Backup exceeds the size limit.', 413);
    }
    $upload = $_FILES['backup'] ?? null;
    if (
        !is_array($upload)
        || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_string($upload['tmp_name'] ?? null)
        || !is_uploaded_file($upload['tmp_name'])
    ) {
        app_error('invalid_backup', 'A valid backup upload is required.', 422);
    }
    try {
        $document = backup_read_file($upload['tmp_name']);
        $validated = backup_validate_document($document, backup_max_bytes());
        if ((int) $validated['schema_version'] > (int) BACKUP_SCHEMA_VERSION) {
            throw new InvalidArgumentException('Backup schema is newer than this application.');
        }
        $counts = backup_import_tables($pdo, $validated['tables']);
    } catch (JsonException | InvalidArgumentException | LengthException $e) {
        app_error('invalid_backup', 'Backup validation failed.', 422);
    }
    app_success(['imported' => $counts]);
}

app_error('invalid_op', 'Operation is not available.', 400);
