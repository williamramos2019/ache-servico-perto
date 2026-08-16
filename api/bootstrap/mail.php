<?php

declare(strict_types=1);

/**
 * Titan SMTP sender. No Composer / PHPMailer.
 * Uses stream_socket_client + AUTH LOGIN (ssl:// :465 or STARTTLS :587).
 */

require_once __DIR__ . '/database.php';

function mail_header_safe(string $value): string
{
    return trim(str_replace(["\r", "\n", "\0"], '', $value));
}

function mail_valid_address(string $email): bool
{
    $email = trim($email);

    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && !preg_match('/[\r\n]/', $email);
}

function mail_encode_header(string $value): string
{
    $value = mail_header_safe($value);
    if ($value === '' || preg_match('/^[\x20-\x7E]+$/', $value) === 1) {
        return $value;
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function mail_dot_stuff(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    foreach ($lines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines);
}

/**
 * @return array{
 *   host: string,
 *   port: int,
 *   encryption: string,
 *   username: string,
 *   password: string,
 *   from: string,
 *   from_name: string
 * }
 */
function mail_config(): array
{
    $portRaw = db_env('MAIL_PORT', false, '465');
    $port = (int) $portRaw;
    if ($port <= 0) {
        $port = 465;
    }

    $encryption = strtolower(trim(db_env('MAIL_ENCRYPTION', false, 'ssl')));
    if ($encryption === '') {
        $encryption = $port === 587 ? 'tls' : 'ssl';
    }

    $from = trim(db_env('MAIL_FROM', false, ''));
    $username = trim(db_env('MAIL_USERNAME', false, $from));

    return [
        'host' => trim(db_env('MAIL_HOST', false, 'smtp.titan.email')),
        'port' => $port,
        'encryption' => $encryption,
        'username' => $username,
        'password' => db_env('MAIL_PASSWORD', false, ''),
        'from' => $from !== '' ? $from : $username,
        'from_name' => trim(db_env('MAIL_FROM_NAME', false, 'AgendaAqui')),
    ];
}

function mail_configured(): bool
{
    $config = mail_config();

    return $config['host'] !== ''
        && $config['username'] !== ''
        && $config['password'] !== ''
        && mail_valid_address($config['from']);
}

/**
 * @param resource $fp
 */
function mail_smtp_read($fp): string
{
    $data = '';
    while (!feof($fp)) {
        $line = fgets($fp, 2048);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (preg_match('/^\d{3} /', $line) === 1) {
            break;
        }
    }

    return $data;
}

/**
 * @param resource $fp
 * @param list<int> $ok
 */
function mail_smtp_cmd($fp, string $command, array $ok): int
{
    if ($command !== '') {
        fwrite($fp, $command . "\r\n");
    }
    $resp = mail_smtp_read($fp);
    $code = (int) substr($resp, 0, 3);
    if (!in_array($code, $ok, true)) {
        throw new RuntimeException('SMTP unexpected response: ' . $code);
    }

    return $code;
}

/**
 * @param array{reply_to?: string} $options
 */
function mail_send(string $to, string $subject, string $body, array $options = []): void
{
    if (!mail_configured()) {
        throw new RuntimeException('Mail is not configured.');
    }
    if (!mail_valid_address($to)) {
        throw new RuntimeException('Invalid recipient.');
    }

    $config = mail_config();
    $from = $config['from'];
    $replyTo = isset($options['reply_to']) && is_string($options['reply_to']) ? trim($options['reply_to']) : '';
    if ($replyTo !== '' && !mail_valid_address($replyTo)) {
        $replyTo = '';
    }

    $useSsl = in_array($config['encryption'], ['ssl', 'smtps'], true) || $config['port'] === 465;
    $useStartTls = in_array($config['encryption'], ['tls', 'starttls'], true) || (!$useSsl && $config['port'] === 587);
    $remote = ($useSsl ? 'ssl://' : 'tcp://') . $config['host'] . ':' . $config['port'];

    $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
        $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
    }

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'crypto_method' => $crypto,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if ($fp === false) {
        throw new RuntimeException('SMTP connect failed.');
    }

    stream_set_timeout($fp, 20);

    try {
        mail_smtp_cmd($fp, '', [220]);
        $ehloHost = mail_header_safe((string) ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if ($ehloHost === '') {
            $ehloHost = 'localhost';
        }
        mail_smtp_cmd($fp, 'EHLO ' . $ehloHost, [250]);

        if ($useStartTls) {
            mail_smtp_cmd($fp, 'STARTTLS', [220]);
            $cryptoOk = @stream_socket_enable_crypto($fp, true, $crypto);
            if ($cryptoOk !== true) {
                throw new RuntimeException('SMTP STARTTLS failed.');
            }
            mail_smtp_cmd($fp, 'EHLO ' . $ehloHost, [250]);
        }

        mail_smtp_cmd($fp, 'AUTH LOGIN', [334]);
        mail_smtp_cmd($fp, base64_encode($config['username']), [334]);
        mail_smtp_cmd($fp, base64_encode($config['password']), [235]);
        mail_smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        mail_smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        mail_smtp_cmd($fp, 'DATA', [354]);

        $fromHeader = $config['from_name'] !== ''
            ? mail_encode_header($config['from_name']) . ' <' . $from . '>'
            : $from;
        $date = gmdate('D, d M Y H:i:s') . ' +0000';
        $messageId = '<' . bin2hex(random_bytes(12)) . '@' . $ehloHost . '>';

        $headers = [
            'From: ' . $fromHeader,
            'To: ' . $to,
            'Subject: ' . mail_encode_header($subject),
            'Date: ' . $date,
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . mail_dot_stuff($body) . "\r\n.";
        mail_smtp_cmd($fp, $payload, [250]);
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

/**
 * @param array{reply_to?: string} $options
 */
function mail_try_send(string $to, string $subject, string $body, array $options = []): bool
{
    try {
        mail_send($to, $subject, $body, $options);
        return true;
    } catch (Throwable $e) {
        error_log('Mail send failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Notify the configured mailbox (MAIL_FROM).
 *
 * @param array{reply_to?: string} $options
 */
function mail_notify(string $subject, string $body, array $options = []): bool
{
    if (!mail_configured()) {
        return false;
    }
    $config = mail_config();

    return mail_try_send($config['from'], $subject, $body, $options);
}

/**
 * @param array<string, string|null> $fields
 */
function mail_format_fields(array $fields): string
{
    $lines = [];
    foreach ($fields as $label => $value) {
        $text = $value === null || $value === '' ? '-' : $value;
        $lines[] = $label . ': ' . $text;
    }

    return implode("\n", $lines) . "\n";
}
