<?php

declare(strict_types=1);

/**
 * Locate and load load-env.php from outside the document root when possible.
 *
 * Typical HostGator layout:
 *   /home/USUARIO/public_html/api/bootstrap/env.php
 *   /home/USUARIO/agendaqui_secure/load-env.php
 *
 * Existing installs that still use /home/USUARIO/agendaqui/ remain valid.
 */

/**
 * @return list<string>
 */
function db_env_candidates(): array
{
    $candidates = [];
    $explicit = getenv('AGENDAQUI_ENV_FILE');
    if (is_string($explicit) && $explicit !== '') {
        $candidates[] = $explicit;
    }

    $bootstrapDir = __DIR__;
    $webRoot = dirname($bootstrapDir, 2);
    $accountRoot = dirname($bootstrapDir, 3);
    $docRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/')
        : '';

    $candidates[] = $accountRoot . DIRECTORY_SEPARATOR . 'agendaqui_secure' . DIRECTORY_SEPARATOR . 'load-env.php';
    $candidates[] = $accountRoot . DIRECTORY_SEPARATOR . 'agendaqui' . DIRECTORY_SEPARATOR . 'load-env.php';
    $candidates[] = $accountRoot . DIRECTORY_SEPARATOR . 'load-env.php';
    $candidates[] = $webRoot . DIRECTORY_SEPARATOR . 'agendaqui_secure' . DIRECTORY_SEPARATOR . 'load-env.php';
    $candidates[] = $webRoot . DIRECTORY_SEPARATOR . 'load-env.php';
    $candidates[] = $bootstrapDir . DIRECTORY_SEPARATOR . 'load-env.php';

    if ($docRoot !== '') {
        $docParent = dirname($docRoot);
        $candidates[] = $docParent . DIRECTORY_SEPARATOR . 'agendaqui_secure' . DIRECTORY_SEPARATOR . 'load-env.php';
        $candidates[] = $docParent . DIRECTORY_SEPARATOR . 'agendaqui' . DIRECTORY_SEPARATOR . 'load-env.php';
        $candidates[] = $docParent . DIRECTORY_SEPARATOR . 'load-env.php';
        $candidates[] = $docRoot . DIRECTORY_SEPARATOR . 'load-env.php';
        $candidates[] = $docRoot . DIRECTORY_SEPARATOR . 'agendaqui_secure' . DIRECTORY_SEPARATOR . 'load-env.php';
        $candidates[] = $docRoot . DIRECTORY_SEPARATOR . 'agendaqui' . DIRECTORY_SEPARATOR . 'load-env.php';
        foreach (glob($docRoot . DIRECTORY_SEPARATOR . 'AgendaAqui-hostgator-*' . DIRECTORY_SEPARATOR . 'agendaqui_secure' . DIRECTORY_SEPARATOR . 'load-env.php') ?: [] as $nested) {
            $candidates[] = $nested;
        }
        foreach (glob($docRoot . DIRECTORY_SEPARATOR . 'AgendaAqui-hostgator-*' . DIRECTORY_SEPARATOR . 'agendaqui' . DIRECTORY_SEPARATOR . 'load-env.php') ?: [] as $nested) {
            $candidates[] = $nested;
        }
    }

    $unique = [];
    foreach ($candidates as $file) {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
        if ($normalized !== '' && !in_array($normalized, $unique, true)) {
            $unique[] = $normalized;
        }
    }

    return $unique;
}

function db_load_optional_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    foreach (db_env_candidates() as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }
        if (!defined('AGENDAQUI_ENV_OK')) {
            define('AGENDAQUI_ENV_OK', true);
        }
        require_once $file;
        return;
    }
}

function env_upload_max_bytes(): int
{
    db_load_optional_env();
    $raw = getenv('UPLOAD_MAX_BYTES');
    if ($raw === false || $raw === '') {
        $fromEnv = $_ENV['UPLOAD_MAX_BYTES'] ?? '';
        $raw = is_string($fromEnv) ? $fromEnv : '';
    }
    $bytes = (int) $raw;
    $default = 5 * 1024 * 1024;
    $max = 20 * 1024 * 1024;
    if ($bytes < 1024 || $bytes > $max) {
        return $default;
    }

    return $bytes;
}
