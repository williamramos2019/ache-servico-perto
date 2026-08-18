<?php

declare(strict_types=1);

/**
 * PDO bootstrap for MySQL/MariaDB.
 *
 * Reads configuration only from environment variables. Never hardcodes
 * credentials and never prints secrets.
 *
 * Required:
 *   DB_HOST
 *   DB_DATABASE
 *   DB_USERNAME
 *
 * Optional:
 *   DB_PORT     (default 3306)
 *   DB_PASSWORD (default empty)
 *   DB_SOCKET   (unix socket; preferred on cPanel when set)
 *
 * Environment file discovery lives in api/bootstrap/env.php.
 */
require_once __DIR__ . '/env.php';

function db_env(string $name, bool $required = true, string $default = ''): string
{
    db_load_optional_env();

    $value = getenv($name);
    if ($value === false || $value === '') {
        $fromEnv = $_ENV[$name] ?? '';
        $value = is_string($fromEnv) ? $fromEnv : '';
    }
    if ($value === '') {
        if ($required) {
            throw new RuntimeException('Missing required environment variable: ' . $name);
        }

        return $default;
    }

    return $value;
}

function db_config(): array
{
    return [
        'host' => db_env('DB_HOST'),
        'port' => db_env('DB_PORT', false, '3306'),
        'database' => db_env('DB_DATABASE'),
        'username' => db_env('DB_USERNAME'),
        'password' => db_env('DB_PASSWORD', false, ''),
        'socket' => db_env('DB_SOCKET', false, ''),
    ];
}

function db_pdo(bool $allowMultiStatements = false): PDO
{
    @ini_set('default_socket_timeout', '5');

    $config = db_config();

    if ($config['socket'] !== '') {
        $dsn = sprintf(
            'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
            $config['socket'],
            $config['database']
        );
    } elseif (strcasecmp($config['host'], 'localhost') === 0) {
        // Do not add port=3306 here: that forces TCP and can hang on cPanel.
        $dsn = sprintf(
            'mysql:host=localhost;dbname=%s;charset=utf8mb4',
            $config['database']
        );
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ];

    if ($allowMultiStatements) {
        $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
    }

    try {
        $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    } catch (PDOException $e) {
        throw new RuntimeException(
            sprintf(
                'Database connection failed (host=%s port=%s database=%s user=%s).',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['username']
            ),
            (int) $e->getCode(),
            $e
        );
    }

    $pdo->exec("SET time_zone = '+00:00'");
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

    return $pdo;
}
