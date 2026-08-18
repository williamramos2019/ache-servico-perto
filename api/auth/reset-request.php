<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/auth.php';
require_once dirname(__DIR__) . '/bootstrap/mail.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
$body = auth_read_json();
auth_reject_unknown_fields($body, ['email'], 'Only email is accepted.');
require_csrf();
$email = auth_validate_email((string) ($body['email'] ?? ''));
rate_limit_hit('password_reset_ip', security_client_ip(), 5, 3600);
rate_limit_hit('password_reset_email', $email, 3, 3600);
$pdo = db_pdo(false);
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$userId = $stmt->fetchColumn();
if (is_string($userId)) {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $now = auth_now();
    $expires = gmdate('Y-m-d H:i:s.000', time() + 3600);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id')->execute([
            ':user_id' => $userId,
        ]);
        $pdo->prepare(
            'INSERT INTO password_reset_tokens (id, user_id, token_hash, expires_at, created_at)
             VALUES (:id, :user_id, :token_hash, :expires_at, :created_at)'
        )->execute([
            ':id' => auth_uuid(), ':user_id' => $userId, ':token_hash' => $tokenHash,
            ':expires_at' => $expires, ':created_at' => $now,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    $base = rtrim((string) getenv('APP_PUBLIC_URL'), '/');
    $url = $base !== '' ? $base . '/reset-password?token=' . rawurlencode($token) : $token;
    mail_try_send(
        $email,
        'Redefinição de senha — AgendaAqui',
        "Use este link para redefinir sua senha. Ele expira em 1 hora:\n\n" . $url
    );
}

app_success(['ok' => true, 'message' => 'If the account exists, reset instructions will be sent.']);
