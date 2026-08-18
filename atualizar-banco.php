<?php

declare(strict_types=1);

/**
 * Instalação e atualização do banco em um clique (HostGator).
 *
 * Envie este arquivo para a MESMA pasta do index.html.
 * Abra: /atualizar-banco.php
 *
 * 1) Grava load-env.php (senha com $ via var_export)
 * 2) Aplica migrations pendentes (não apaga dados)
 * 3) Cria o admin se o e-mail for preenchido
 * 4) Apaga o instalador
 */

@ini_set('display_errors', '0');
@set_time_limit(300);

function install_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function install_page(string $title, string $body): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . install_h($title) . '</title>';
    echo '<style>
      body{font-family:Arial,sans-serif;max-width:560px;margin:40px auto;padding:0 16px;color:#111}
      input,button{font-size:18px;width:100%;box-sizing:border-box;padding:12px;margin:8px 0}
      button{background:#16a34a;color:#fff;border:0;cursor:pointer;font-weight:700}
      .err{background:#fee2e2;padding:12px;margin:12px 0}
      .ok{background:#dcfce7;padding:12px;margin:12px 0}
      .warn{background:#fef9c3;padding:12px;margin:12px 0}
      .log{background:#111;color:#d1fae5;padding:12px;white-space:pre-wrap;font-size:12px;overflow:auto;max-height:280px}
      code{background:#f1f5f9;padding:2px 6px}
      a{color:#166534}
    </style></head><body>';
    echo $body;
    echo '</body></html>';
    exit;
}

function install_norm(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}

function install_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function install_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
}

function install_safe_error(string $message, string $log = ''): string
{
    if (stripos($message, 'Access denied') !== false || stripos($message, '1045') !== false) {
        return 'Senha recusada. Use a senha do usuário MySQL no cPanel (Bancos de Dados), não a senha do cPanel.';
    }
    if (stripos($message, 'Unknown database') !== false || stripos($message, '1049') !== false) {
        return 'O banco MySQL não existe. Confira o nome no cPanel.';
    }
    $out = 'Falha na instalação. Confira a senha e se tools/ e database/ estão na pasta do site.';
    if ($log !== '') {
        $out .= '<div class="log">' . install_h($log) . '</div>';
    }

    return $out;
}

function install_write_env(string $path, string $password, string $rateDir, string $database, string $username): void
{
    $php = "<?php\n\ndeclare(strict_types=1);\n\n"
        . "if (!defined('AGENDAQUI_ENV_OK')) {\n    http_response_code(403);\n    exit;\n}\n\n"
        . "if (!function_exists('agendaqui_putenv')) {\n"
        . "    function agendaqui_putenv(string \$name, string \$value): void\n"
        . "    {\n        putenv(\$name . '=' . \$value);\n        \$_ENV[\$name] = \$value;\n    }\n"
        . "}\n\n"
        . "agendaqui_putenv('APP_ENV', 'production');\n"
        . "agendaqui_putenv('SESSION_NAME', 'agendaqui_sid');\n"
        . "agendaqui_putenv('AUTH_IDLE_SECONDS', '28800');\n"
        . "agendaqui_putenv('APP_ALLOWED_ORIGINS', '');\n"
        . "agendaqui_putenv('DB_HOST', 'localhost');\n"
        . "agendaqui_putenv('DB_PORT', '3306');\n"
        . "agendaqui_putenv('DB_DATABASE', " . var_export($database, true) . ");\n"
        . "agendaqui_putenv('DB_USERNAME', " . var_export($username, true) . ");\n"
        . "agendaqui_putenv('DB_PASSWORD', " . var_export($password, true) . ");\n"
        . "agendaqui_putenv('RATE_LIMIT_DIR', " . var_export($rateDir, true) . ");\n"
        . "agendaqui_putenv('UPLOAD_MAX_BYTES', '5242880');\n"
        . "agendaqui_putenv('CRON_SHARED_SECRET', " . var_export(bin2hex(random_bytes(24)), true) . ");\n"
        . "agendaqui_putenv('PUSH_TRACK_SECRET', " . var_export(bin2hex(random_bytes(24)), true) . ");\n";

    if (file_put_contents($path, $php) === false) {
        throw new RuntimeException('Não foi possível gravar load-env.php.');
    }
    @chmod($path, 0600);
}

function install_ensure_admin(PDO $pdo, string $email, string $password): void
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('E-mail de admin inválido.');
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('Senha do admin deve ter no mínimo 8 caracteres.');
    }

    $now = install_now();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $userId = $stmt->fetchColumn();

    if (!is_string($userId) || $userId === '') {
        $userId = install_uuid();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Não foi possível criar a senha do admin.');
        }
        $ins = $pdo->prepare(
            'INSERT INTO users (id, email, password_hash, email_verified_at, created_at)
             VALUES (:id, :email, :password_hash, :verified, :created_at)'
        );
        $ins->execute([
            ':id' => $userId,
            ':email' => $email,
            ':password_hash' => $hash,
            ':verified' => $now,
            ':created_at' => $now,
        ]);
        $prof = $pdo->prepare(
            'INSERT INTO profiles (id, name, avatar_url, created_at, updated_at)
             VALUES (:id, :name, NULL, :created_at, :updated_at)'
        );
        $prof->execute([
            ':id' => $userId,
            ':name' => 'Administrador',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    $role = $pdo->prepare(
        'INSERT IGNORE INTO user_roles (id, user_id, role, created_at)
         VALUES (:id, :user_id, :role, :created_at)'
    );
    $role->execute([
        ':id' => install_uuid(),
        ':user_id' => $userId,
        ':role' => 'admin',
        ':created_at' => $now,
    ]);
}

$here = install_norm(__DIR__);
$docRoot = install_norm((string) ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__));
$account = dirname($docRoot);
$roots = [
    $account . '/agendaqui_secure',
    $account . '/agendaqui',
    $docRoot . '/agendaqui_secure',
    $docRoot . '/agendaqui',
    $here . '/agendaqui_secure',
    $here . '/agendaqui',
];
foreach (glob($here . '/AgendaAqui-hostgator-*/agendaqui_secure') ?: [] as $nested) {
    $roots[] = install_norm($nested);
}
foreach (glob($here . '/AgendaAqui-hostgator-*/agendaqui') ?: [] as $nested) {
    $roots[] = install_norm($nested);
}
$roots[] = $here;
$roots[] = $docRoot;

$migrateFile = '';
$migrationsDir = '';
$private = '';
foreach ($roots as $root) {
    $root = install_norm($root);
    $migrate = $root . '/tools/migrate.php';
    $migrations = $root . '/database/migrations';
    if ($private === '' && is_file($migrate) && is_file($migrations . '/019_reference_seeds.sql')) {
        $private = $root;
        $migrateFile = $migrate;
        $migrationsDir = $migrations;
    }
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $database = trim((string) ($_POST['database'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $log = '';
    if ($password === '' || $database === '' || $username === '') {
        $error = 'Preencha banco, usuário e senha do MySQL.';
    } elseif ($migrateFile === '' || $migrationsDir === '') {
        $error = 'Não achei tools/migrate.php e database/migrations nesta pasta do site.';
    } else {
        try {
            $envDir = $account . '/agendaqui_secure';
            if ($private !== '' && $private !== $here && $private !== $docRoot) {
                $envDir = $private;
            }
            if ($envDir === $here || $envDir === $docRoot) {
                throw new RuntimeException(
                    'Envie a pasta agendaqui_secure/ do ZIP para /home/USUARIO/agendaqui_secure (fora do public_html) antes de instalar.'
                );
            }
            if (!is_dir($envDir) && !mkdir($envDir, 0755, true) && !is_dir($envDir)) {
                throw new RuntimeException('Não foi possível criar ' . $envDir . '. Crie /home/USUARIO/agendaqui_secure no File Manager.');
            }
            $rateDir = $envDir . '/storage/rate-limit';
            if (!is_dir($rateDir) && !mkdir($rateDir, 0700, true) && !is_dir($rateDir)) {
                throw new RuntimeException('Não foi possível criar storage/rate-limit.');
            }

            $pdo = new PDO(
                'mysql:host=localhost;dbname=' . $database . ';charset=utf8mb4',
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]
            );
            $pdo->query('SELECT 1');
            unset($pdo);

            install_write_env($envDir . '/load-env.php', $password, $rateDir, $database, $username);

            putenv('DB_HOST=localhost');
            $_ENV['DB_HOST'] = 'localhost';
            putenv('DB_DATABASE=' . $database);
            $_ENV['DB_DATABASE'] = $database;
            putenv('DB_USERNAME=' . $username);
            $_ENV['DB_USERNAME'] = $username;
            putenv('DB_PASSWORD=' . $password);
            $_ENV['DB_PASSWORD'] = $password;
            putenv('MIGRATIONS_DIR=' . $migrationsDir);
            $_ENV['MIGRATIONS_DIR'] = $migrationsDir;

            if (!defined('AGENDAQUI_INSTALLER')) {
                define('AGENDAQUI_INSTALLER', true);
            }
            if (!defined('STDOUT')) {
                define('STDOUT', fopen('php://output', 'w'));
            }
            if (!defined('STDERR')) {
                define('STDERR', fopen('php://output', 'w'));
            }

            require $migrateFile;
            ob_start();
            $code = main(['migrate.php']);
            $log = (string) ob_get_clean();
            if ($code !== 0) {
                throw new RuntimeException('Migration falhou.');
            }

            $adminNote = '';
            if ($adminEmail !== '') {
                install_ensure_admin(db_pdo(false), $adminEmail, $adminPassword);
                $adminNote = '<p>Admin criado/atualizado: <code>' . install_h($adminEmail) . '</code>. Entre em <a href="/auth">/auth</a> e abra <a href="/admin">/admin</a>.</p>';
            }

            @file_put_contents($here . '/installed.lock', gmdate('c') . "\n");
            @chmod($here . '/installed.lock', 0600);
            @unlink($here . '/atualizar-banco.php');
            @unlink($here . '/instalar-banco.php');
            @unlink($here . '/instalar.php');
            @unlink(__FILE__);
            $gone = !is_file(__FILE__);

            install_page(
                'Pronto',
                '<div class="ok"><h1>Instalação concluída</h1>'
                . '<p>Arquivos no ar. Banco atualizado (só pending). Dados antigos não foram apagados.</p></div>'
                . $adminNote
                . ($gone
                    ? '<p>O instalador foi apagado automaticamente.</p>'
                    : '<p class="warn">Apague agora <code>atualizar-banco.php</code> no File Manager.</p>')
                . '<p><a href="/api/health.php">Testar health</a> · <a href="/api/catalog/index.php?op=cities">Ver cidades</a> · <a href="/">Abrir o site</a></p>'
                . '<div class="log">' . install_h($log) . '</div>'
            );
        } catch (Throwable $e) {
            $error = install_safe_error($e->getMessage(), $log);
        }
    }
}

$missing = $migrateFile === '';
$form = '<h1>Instalar AgendaAqui</h1>'
    . '<ol>'
    . '<li>Envie o conteúdo de <code>public_html/</code> do ZIP para a pasta do site.</li>'
    . '<li>Cole a senha do MySQL (cPanel → Bancos de Dados).</li>'
    . '<li>Clique no botão. O banco é atualizado em seguida.</li>'
    . '</ol>'
    . '<p>Não apaga dados. Não use a senha de login do cPanel.</p>'
    . '<p>A pasta <code>agendaqui_secure/</code> do ZIP tem de estar em <code>/home/USUARIO/agendaqui_secure</code>, fora do site. Senha e cron vão para lá, não para o public_html. Instalações antigas em <code>/home/USUARIO/agendaqui</code> continuam válidas.</p>';
if ($missing) {
    $form .= '<div class="warn">Não achei <code>tools/migrate.php</code> e <code>database/migrations/019_reference_seeds.sql</code> nesta pasta. Envie o conteúdo de <code>public_html/</code> do ZIP atual, não a pasta pai do ZIP.</div>';
}
if ($error !== '') {
    $form .= '<div class="err">' . $error . '</div>';
}
$form .= '<form method="post">'
    . '<label>Banco<br><input name="database" value="" required autocomplete="off" placeholder="nome completo no cPanel"></label>'
    . '<label>Usuário MySQL<br><input name="username" value="" required autocomplete="off" placeholder="usuário MySQL no cPanel"></label>'
    . '<label>Senha do MySQL<br><input type="password" name="password" required autofocus autocomplete="current-password"></label>'
    . '<label>E-mail do admin do site (opcional)<br><input type="email" name="admin_email" autocomplete="username"></label>'
    . '<label>Senha do admin do site (mín. 8, se preencher o e-mail)<br><input type="password" name="admin_password" minlength="8" autocomplete="new-password"></label>'
    . '<button type="submit">Instalar e atualizar o banco</button>'
    . '</form>'
    . '<p>Runner: <code>' . install_h($migrateFile !== '' ? $migrateFile : '(não encontrado)') . '</code></p>';

install_page('Instalar AgendaAqui', $form);
