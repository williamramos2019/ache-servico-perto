<?php

declare(strict_types=1);

/**
 * Copy this file on the server to:
 *   /home4/will3269/agendaqui/load-env.php
 * Permission: 0600
 *
 * NEVER put the real file in the document root.
 * NEVER commit the real file (with password) to Git.
 *
 * Do not use this file as auto_prepend_file: it refuses HTTP unless
 * AGENDAQUI_ENV_OK is defined (api/bootstrap/database.php does that).
 */

if (!defined('AGENDAQUI_ENV_OK')) {
    http_response_code(403);
    exit;
}

function agendaqui_putenv(string $name, string $value): void
{
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

agendaqui_putenv('APP_ENV', 'production');
agendaqui_putenv('DB_HOST', 'localhost');
agendaqui_putenv('DB_PORT', '3306');
agendaqui_putenv('DB_DATABASE', 'COLOCAR_NO_SERVIDOR');
agendaqui_putenv('DB_USERNAME', 'COLOCAR_NO_SERVIDOR');
agendaqui_putenv('DB_PASSWORD', 'COLOCAR_NO_SERVIDOR');
agendaqui_putenv('RATE_LIMIT_DIR', '/home4/will3269/agendaqui/storage/rate-limit');

agendaqui_putenv('MAIL_HOST', 'smtp.titan.email');
agendaqui_putenv('MAIL_PORT', '465');
agendaqui_putenv('MAIL_ENCRYPTION', 'ssl');
agendaqui_putenv('MAIL_USERNAME', 'suporte@blog.autolimpezapro.com.br');
agendaqui_putenv('MAIL_PASSWORD', '');
agendaqui_putenv('MAIL_FROM', 'suporte@blog.autolimpezapro.com.br');
agendaqui_putenv('MAIL_FROM_NAME', 'AgendaAqui');
