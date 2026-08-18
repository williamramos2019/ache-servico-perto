<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/persist.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
require_auth();
require_csrf();
rate_limit_hit('upload_user', require_auth(), 30, 3600);

$userId = require_auth();
$kind = isset($_POST['kind']) ? (string) $_POST['kind'] : 'generic';
if (!in_array($kind, ['company', 'listing', 'qa', 'generic'], true)) {
    $kind = 'generic';
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    app_error('invalid_file', 'Envie um arquivo.', 422);
}
$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    app_error('invalid_file', 'Falha no envio do arquivo.', 422);
}
$size = (int) ($file['size'] ?? 0);
$maxBytes = env_upload_max_bytes();
if ($size < 1 || $size > $maxBytes) {
    app_error('invalid_file', 'Imagem acima do limite permitido.', 422);
}

$tmp = (string) ($file['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    app_error('invalid_file', 'Arquivo inválido.', 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($tmp);
$extMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
if ($kind === 'qa' && $mime === 'video/webm') {
    $extMap['video/webm'] = 'webm';
}
if (!isset($extMap[$mime])) {
    app_error('invalid_file', 'Formato não permitido. Use JPG, PNG ou WebP.', 422);
}

$root = dirname(__DIR__, 2);
$dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $kind;
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    app_error('internal_error', 'Não foi possível salvar o arquivo.', 500);
}

$name = auth_uuid() . '.' . $extMap[$mime];
$dest = $dir . DIRECTORY_SEPARATOR . $name;
if (!move_uploaded_file($tmp, $dest)) {
    app_error('internal_error', 'Não foi possível salvar o arquivo.', 500);
}

$url = '/uploads/' . $kind . '/' . $name;
$pdo = db_pdo(false);
$ins = $pdo->prepare(
    'INSERT INTO media (id, url, kind, meta, owner_id, company_id, created_at)
     VALUES (:id, :url, :kind, :meta, :owner_id, NULL, :created_at)'
);
$ins->execute([
    ':id' => auth_uuid(),
    ':url' => $url,
    ':kind' => $kind,
    ':meta' => persist_json_encode(['mime' => $mime, 'size' => $size]),
    ':owner_id' => $userId,
    ':created_at' => auth_now(),
]);

app_success(['url' => $url], 201);
