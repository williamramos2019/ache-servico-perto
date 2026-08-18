<?php

declare(strict_types=1);

/**
 * Copie este arquivo no servidor para:
 *   /home/USUARIO/agendaqui_secure/load-env.php
 * (instalações antigas: /home/USUARIO/agendaqui/load-env.php)
 * Permissão recomendada: 0600
 *
 * Preencha os placeholders. NUNCA coloque o arquivo real (com senha)
 * em public_html, no ZIP de deploy, nem no Git.
 *
 * Não use este arquivo como auto_prepend_file: ele recusa HTTP a menos
 * que AGENDAQUI_ENV_OK esteja definido (api/bootstrap/env.php faz isso).
 *
 * O PHP lê DB_DATABASE e DB_USERNAME (não DB_NAME / DB_USER).
 */

if (!defined('AGENDAQUI_ENV_OK')) {
    http_response_code(403);
    exit;
}

if (!function_exists('agendaqui_putenv')) {
    function agendaqui_putenv(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

agendaqui_putenv('APP_ENV', 'production');
agendaqui_putenv('APP_PUBLIC_URL', 'https://SEU-DOMINIO.com');
agendaqui_putenv('SESSION_NAME', 'agendaqui_sid');
// Same-origin. Deixe vazio. Só preencha se a SPA estiver em outro domínio.
agendaqui_putenv('APP_ALLOWED_ORIGINS', '');

// Idle da sessão em segundos (padrão 28800 = 8h) se AUTH_IDLE_SECONDS não for lido.
agendaqui_putenv('AUTH_IDLE_SECONDS', '28800');

agendaqui_putenv('DB_HOST', 'localhost');
agendaqui_putenv('DB_PORT', '3306');
agendaqui_putenv('DB_DATABASE', 'SEU_BANCO');
agendaqui_putenv('DB_USERNAME', 'SEU_USUARIO');
agendaqui_putenv('DB_PASSWORD', 'SUA_SENHA');
// Opcional no cPanel. Se o host exigir socket, descomente e ajuste:
// agendaqui_putenv('DB_SOCKET', '/tmp/mysql.sock');

agendaqui_putenv('RATE_LIMIT_DIR', '/home/USUARIO/agendaqui_secure/storage/rate-limit');
agendaqui_putenv('UPLOAD_MAX_BYTES', '5242880');

agendaqui_putenv('MAIL_HOST', 'smtp.exemplo.com');
agendaqui_putenv('MAIL_PORT', '465');
agendaqui_putenv('MAIL_ENCRYPTION', 'ssl');
agendaqui_putenv('MAIL_USERNAME', 'SEU_EMAIL@dominio.com');
agendaqui_putenv('MAIL_PASSWORD', 'SUA_SENHA_DE_EMAIL');
agendaqui_putenv('MAIL_FROM', 'SEU_EMAIL@dominio.com');
agendaqui_putenv('MAIL_FROM_NAME', 'AgendaAqui');

// Feeds CSV do Portal de Afiliados Shopee (opcional). Nunca commite URLs reais.
// agendaqui_putenv('SHOPEE_FEED_1_URL', '');
// agendaqui_putenv('SHOPEE_FEED_1_NAME', 'Datafeed 1');
// agendaqui_putenv('SHOPEE_FEED_2_URL', '');
// agendaqui_putenv('SHOPEE_FEED_2_NAME', 'Datafeed 2');

// HMAC curto enviado no payload Web Push (não use o segredo do cron).
agendaqui_putenv('PUSH_TRACK_SECRET', 'GERE_UM_SEGREDO_LONGO');

// Cron HTTP (POST /api/cron/index.php + header X-Cron-Secret). Vazio = 403.
agendaqui_putenv('CRON_SHARED_SECRET', 'GERE_OUTRO_SEGREDO_LONGO');

// Opt-out WhatsApp por link assinado (opcional).
// agendaqui_putenv('WHATSAPP_OPTOUT_SECRET', '');

// Disparo Web Push (VAPID privado) NÃO está ligado neste PHP compartilhado.
// agendaqui_putenv('VAPID_PRIVATE_KEY', '');
// agendaqui_putenv('VAPID_SUBJECT', 'mailto:contato@exemplo.com');

// IA editorial: sem provedor configurado, /admin/blog-ai permanece indisponível.
// agendaqui_putenv('EDITORIAL_AI_API_KEY', '');
