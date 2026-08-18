<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/auth.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
$body = auth_read_json();
auth_reject_unknown_fields($body, ['token', 'password'], 'Only token and password are accepted.');
require_csrf();
rate_limit_hit('password_reset_confirm_ip', security_client_ip(), 10, 3600);
$token = is_string($body['token'] ?? null) ? trim($body['token']) : '';
if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
    app_error('invalid_token', 'Reset token is invalid or expired.', 422);
}
$password = auth_validate_password((string) ($body['password'] ?? ''));
$tokenHash = hash('sha256', $token);
$pdo = db_pdo(false);
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'SELECT t.user_id, t.token_hash, t.expires_at, u.session_version
         FROM password_reset_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = :token_hash LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([':token_hash' => $tokenHash]);
    $data = $stmt->fetch();
    $valid = $data !== false && auth_reset_token_valid(
        $token,
        (string) $data['token_hash'],
        (int) strtotime((string) $data['expires_at']),
        time()
    );
    if (!$valid) {
        if ($data !== false) {
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash = :token_hash')->execute([
                ':token_hash' => $tokenHash,
            ]);
        }
        $pdo->commit();
        app_error('invalid_token', 'Reset token is invalid or expired.', 422);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash)) {
        throw new RuntimeException('Password hashing failed.');
    }
    $plan = auth_reset_plan((string) $data['user_id'], (int) $data['session_version']);
    $pdo->prepare(
        'UPDATE users SET password_hash = :hash, session_version = session_version + 1 WHERE id = :id'
    )->execute([
        ':hash' => $hash,
        ':id' => $plan['user_id'],
    ]);
    $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id')->execute([
        ':user_id' => $plan['user_id'],
    ]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
$_SESSION = [];
session_regenerate_id(true);
app_success(['ok' => true]);
