<?php

declare(strict_types=1);

/**
 * One-time HostGator installer. Delete this file after success.
 */

@ini_set('display_errors', '0');
@set_time_limit(180);
@ini_set('max_execution_time', '180');

$root = __DIR__;

$lockFile = $root . '/installed.lock';
$envFile = $root . '/load-env.php';
$migrateFile = $root . '/tools/migrate.php';
$migrationsDir = $root . '/database/migrations';

const INSTALL_DB_HOST = 'localhost';
const INSTALL_DB_PORT = '3306';

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
      body{font-family:Arial,sans-serif;max-width:520px;margin:40px auto;padding:0 16px;color:#111}
      input,button{font-size:18px;width:100%;box-sizing:border-box;padding:12px;margin:8px 0}
      button{background:#16a34a;color:#fff;border:0;cursor:pointer}
      .err{background:#fee2e2;padding:12px;margin:12px 0}
      .ok{background:#dcfce7;padding:12px;margin:12px 0}
      .log{background:#111;color:#d1fae5;padding:12px;white-space:pre-wrap;font-size:12px;overflow:auto;max-height:240px}
      a{color:#166534}
    </style></head><body>';
    echo $body;
    echo '</body></html>';
    exit;
}

function install_write_env(string $path, string $database, string $username, string $password, string $rateDir): void
{
    $php = "<?php\n\ndeclare(strict_types=1);\n\n"
        . "if (!defined('AGENDAQUI_ENV_OK')) {\n    http_response_code(403);\n    exit;\n}\n\n"
        . "function agendaqui_putenv(string \$name, string \$value): void\n"
        . "{\n    putenv(\$name . '=' . \$value);\n    \$_ENV[\$name] = \$value;\n}\n\n"
        . "agendaqui_putenv('APP_ENV', 'production');\n"
        . "agendaqui_putenv('DB_HOST', " . var_export(INSTALL_DB_HOST, true) . ");\n"
        . "agendaqui_putenv('DB_PORT', " . var_export(INSTALL_DB_PORT, true) . ");\n"
        . "agendaqui_putenv('DB_DATABASE', " . var_export($database, true) . ");\n"
        . "agendaqui_putenv('DB_USERNAME', " . var_export($username, true) . ");\n"
        . "agendaqui_putenv('DB_PASSWORD', " . var_export($password, true) . ");\n"
        . "agendaqui_putenv('RATE_LIMIT_DIR', " . var_export($rateDir, true) . ");\n";

    if (file_put_contents($path, $php) === false) {
        throw new RuntimeException('Nao foi possivel gravar load-env.php.');
    }
    @chmod($path, 0600);
}

if (is_file($lockFile) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    install_page('Banco ja instalado', '<h1>Banco ja foi instalado</h1>'
        . '<p>Apague o arquivo <strong>instalar.php</strong> nesta pasta.</p>'
        . '<p><a href="/api/health.php">Testar health</a> · <a href="/api/companies/index.php">Ver empresas</a></p>');
}

if (!is_file($migrateFile) || !is_dir($migrationsDir)) {
    install_page(
        'Faltam arquivos',
        '<h1>Faltam arquivos do banco</h1><p>Envie tambem as pastas <code>database</code> e <code>tools</code> para a mesma pasta do <code>index.html</code>.</p>'
    );
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $database = trim((string) ($_POST['database'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    if ($password === '' || $database === '' || $username === '') {
        $error = 'Preencha banco, usuário e senha do MySQL.';
    } else {
        try {
            $rateDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate-limit';
            if (!is_dir($rateDir) && !mkdir($rateDir, 0700, true) && !is_dir($rateDir)) {
                throw new RuntimeException('Nao foi possivel criar storage/rate-limit.');
            }

            install_write_env($envFile, $database, $username, $password, $rateDir);

            if (!defined('AGENDAQUI_ENV_OK')) {
                define('AGENDAQUI_ENV_OK', true);
            }
            require $envFile;

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
                throw new RuntimeException('Migration falhou. Log:\n' . $log);
            }

            file_put_contents($lockFile, gmdate('c') . "\n");
            @chmod($lockFile, 0600);
            @unlink(__FILE__);

            $deleted = !is_file(__FILE__);
            install_page(
                'Banco instalado',
                '<div class="ok"><h1>Banco instalado</h1><p>Tabelas e dados publicos foram criados.</p></div>'
                . ($deleted
                    ? '<p>O arquivo instalar.php foi apagado automaticamente.</p>'
                    : '<p><strong>Apague agora o arquivo instalar.php</strong> no File Manager.</p>')
                . '<p><a href="/api/health.php">Abrir health</a><br>'
                . '<a href="/api/companies/index.php">Abrir empresas</a><br>'
                . '<a href="/">Abrir o site</a></p>'
                . '<div class="log">' . install_h($log) . '</div>'
            );
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Migration') !== false) {
                $error = 'A senha conectou, mas a criacao das tabelas falhou. Envie a pasta database/migrations.';
            } else {
                $error = 'Nao conectou. Use a senha do MySQL em cPanel → Bancos de Dados MySQL, nao a senha do cPanel.';
            }
        }
    }
}

$form = '<h1>Instalar banco</h1>'
    . '<p>Este formulário fica público até ser apagado. Preencha os nomes do MySQL do cPanel. Não use a senha de login da hospedagem.</p>';
if ($error !== '') {
    $form .= '<div class="err">' . install_h($error) . '</div>';
}
$form .= '<form method="post">'
    . '<label>Banco<br><input name="database" value="" required autocomplete="off"></label>'
    . '<label>Usuário MySQL<br><input name="username" value="" required autocomplete="off"></label>'
    . '<label>Senha do MySQL<br><input type="password" name="password" required autofocus></label>'
    . '<button type="submit">Instalar banco</button>'
    . '</form>'
    . '<p>Depois apague o arquivo <code>instalar.php</code>.</p>';

install_page('Instalar banco', $form);
