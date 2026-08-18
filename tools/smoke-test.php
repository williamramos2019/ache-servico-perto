<?php

declare(strict_types=1);

/**
 * Smoke test HTTP pós-deploy HostGator (somente CLI).
 *
 *   php tools/smoke-test.php https://seu-dominio.com.br
 *
 * Não imprime senhas. Não altera o banco.
 */

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Forbidden\n";
    exit(1);
}

$failures = [];
$passes = 0;

function smoke_fail(string $name, string $detail): void
{
    global $failures;
    $failures[] = $name . ': ' . $detail;
    fwrite(STDOUT, '[FAIL] ' . $name . ' — ' . $detail . PHP_EOL);
}

function smoke_ok(string $name, string $detail = ''): void
{
    global $passes;
    $passes++;
    fwrite(STDOUT, '[OK]   ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL);
}

function smoke_usage(): never
{
    fwrite(STDERR, "Uso: php tools/smoke-test.php https://seu-dominio.com.br\n");
    exit(2);
}

function smoke_base_url(string $raw): string
{
    $trimmed = rtrim(trim($raw), '/');
    if ($trimmed === '' || filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
        fwrite(STDERR, "URL inválida.\n");
        smoke_usage();
    }
    $parts = parse_url($trimmed);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        fwrite(STDERR, "Use http:// ou https://\n");
        smoke_usage();
    }
    if ($scheme === 'http') {
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($host, ['localhost', '127.0.0.1'], true)) {
            fwrite(STDERR, "Produção deve usar https://\n");
            exit(2);
        }
    }

    return $trimmed;
}

/**
 * @return array{status:int,body:string,headers:list<string>,content_type:string}
 */
function smoke_get(string $url, int $timeout = 25): array
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 5,
            'header' => "Accept: */*\r\nUser-Agent: AgendaAqui-smoke-test/1.1\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    $headers = [];
    $contentType = '';
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            $headers[] = $line;
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
            if (stripos($line, 'Content-Type:') === 0) {
                $contentType = trim(substr($line, strlen('Content-Type:')));
            }
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'headers' => $headers,
        'content_type' => $contentType,
    ];
}

function smoke_json(string $body): ?array
{
    $decoded = json_decode($body, true);

    return is_array($decoded) ? $decoded : null;
}

function smoke_blocked(int $status): bool
{
    return in_array($status, [401, 403, 404, 405], true);
}

$argvList = $argv ?? [];
if (count($argvList) < 2 || in_array($argvList[1], ['-h', '--help'], true)) {
    smoke_usage();
}

$base = smoke_base_url((string) $argvList[1]);
fwrite(STDOUT, 'Smoke test ' . $base . PHP_EOL);

$health = smoke_get($base . '/api/health.php');
$healthJson = smoke_json($health['body']);
$database = is_array($healthJson['data'] ?? null) ? ($healthJson['data']['database'] ?? null) : null;
if ($health['status'] === 200 && ($healthJson['success'] ?? false) === true && $database === 'ok') {
    smoke_ok('GET /api/health.php', 'HTTP 200, database=ok');
} else {
    smoke_fail(
        'GET /api/health.php',
        'esperado HTTP 200 + database=ok, obtido HTTP ' . $health['status']
    );
}

$csrf = smoke_get($base . '/api/auth/csrf.php');
$csrfJson = smoke_json($csrf['body']);
$token = is_array($csrfJson['data'] ?? null) ? ($csrfJson['data']['csrf_token'] ?? '') : '';
$tokenOk = is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1;
if ($csrf['status'] === 200 && ($csrfJson['success'] ?? false) === true && $tokenOk) {
    smoke_ok('GET /api/auth/csrf.php', 'token CSRF de 64 hex');
} else {
    smoke_fail('GET /api/auth/csrf.php', 'token CSRF ausente ou HTTP ' . $csrf['status']);
}

$sitemap = smoke_get($base . '/sitemap.php');
$xmlOk = $sitemap['status'] === 200
    && str_contains($sitemap['body'], '<urlset')
    && str_contains($sitemap['body'], '</urlset>')
    && (@simplexml_load_string($sitemap['body']) !== false);
if ($xmlOk) {
    smoke_ok('GET /sitemap.php', 'XML urlset válido');
} else {
    smoke_fail('GET /sitemap.php', 'XML inválido ou HTTP ' . $sitemap['status']);
}

$cities = smoke_get($base . '/api/catalog/index.php?op=cities');
$citiesJson = smoke_json($cities['body']);
$cityRows = is_array($citiesJson['data'] ?? null) ? ($citiesJson['data']['cities'] ?? null) : null;
$cityOk = $cities['status'] === 200
    && ($citiesJson['success'] ?? false) === true
    && is_array($cityRows)
    && $cityRows !== []
    && isset($cityRows[0]['slug']);
if ($cityOk) {
    smoke_ok('GET /api/catalog/index.php?op=cities', count($cityRows) . ' cidade(s)');
} else {
    smoke_fail('GET /api/catalog/index.php?op=cities', 'JSON de cidades ausente ou HTTP ' . $cities['status']);
}

$htaccess = smoke_get($base . '/uploads/.htaccess');
$htaccessLeaked = $htaccess['status'] === 200 && (
    str_contains($htaccess['body'], 'php_flag')
    || str_contains($htaccess['body'], 'SetHandler')
    || str_contains($htaccess['body'], 'RewriteEngine')
);
if (smoke_blocked($htaccess['status']) && !$htaccessLeaked) {
    smoke_ok('GET /uploads/.htaccess', 'HTTP ' . $htaccess['status']);
} else {
    smoke_fail('GET /uploads/.htaccess', 'arquivo sensível não deve ser servido (HTTP ' . $htaccess['status'] . ')');
}

$probe = smoke_get($base . '/uploads/__agendaqui_smoke.php');
$phpExecuted = $probe['status'] === 200 && (
    str_contains($probe['body'], 'phpinfo')
    || str_contains($probe['body'], 'PHP Version')
    || str_contains($probe['body'], 'AGENDAQUI_ENV_OK')
);
$phpSource = $probe['status'] === 200 && str_contains($probe['body'], '<?php');
if (smoke_blocked($probe['status']) || ($probe['status'] >= 400 && $probe['status'] < 600 && !$phpExecuted && !$phpSource)) {
    smoke_ok('GET /uploads/__agendaqui_smoke.php', 'PHP não executa (HTTP ' . $probe['status'] . ')');
} else {
    smoke_fail('GET /uploads/__agendaqui_smoke.php', 'uploads não deve executar PHP (HTTP ' . $probe['status'] . ')');
}

$env = smoke_get($base . '/load-env.php');
$envLeaked = $env['status'] === 200 && (
    str_contains($env['body'], 'DB_PASSWORD')
    || str_contains($env['body'], 'CRON_SHARED_SECRET')
    || str_contains($env['body'], 'agendaqui_putenv')
);
if (smoke_blocked($env['status']) && !$envLeaked) {
    smoke_ok('GET /load-env.php', 'HTTP ' . $env['status']);
} else {
    smoke_fail('GET /load-env.php', 'esperado 403/404, obtido HTTP ' . $env['status']);
}

foreach (['/buscar', '/vespasiano'] as $path) {
    $page = smoke_get($base . $path);
    $isSpa = $page['status'] === 200
        && str_contains($page['body'], 'id="root"')
        && (str_contains($page['body'], '<!doctype html') || str_contains($page['body'], '<!DOCTYPE html'));
    $looksJson404 = str_starts_with(ltrim($page['body']), '{') && str_contains($page['body'], '"success"');
    if ($isSpa && !$looksJson404) {
        smoke_ok('GET ' . $path, 'SPA index.html HTTP 200');
    } else {
        smoke_fail('GET ' . $path, 'fallback SPA ausente (HTTP ' . $page['status'] . ')');
    }
}

fwrite(STDOUT, PHP_EOL);
if ($failures !== []) {
    fwrite(STDERR, 'FAIL (' . $passes . ' ok, ' . count($failures) . ' falha(s))' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'PASS (' . $passes . ' checks)' . PHP_EOL);
exit(0);
