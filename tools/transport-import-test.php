<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Forbidden\n";
    exit(1);
}

require dirname(__DIR__) . '/api/importer/bootstrap.php';
require_once dirname(__DIR__) . '/api/importer/transport.php';

$failed = 0;
function t_ok(bool $ok, string $msg): void
{
    global $failed;
    if ($ok) {
        echo "ok  $msg\n";
        return;
    }
    $failed++;
    echo "FAIL  $msg\n";
}

t_ok(transport_validate_time('08:00'), 'Horário 08:00 válido');
t_ok(transport_validate_time('23:59'), 'Horário 23:59 válido');
t_ok(!transport_validate_time('24:00'), 'Horário 24:00 inválido');
t_ok(!transport_validate_time('8:00'), 'Horário sem zero à esquerda inválido');

t_ok(transport_is_uuid('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'), 'UUID válido');
t_ok(!transport_is_uuid("1' OR 1=1 --"), 'UUID malicioso rejeitado');

$inj = "' OR 1=1 --";
$like = transport_like($inj);
t_ok(str_contains($like, $inj) && str_starts_with($like, '%'), 'LIKE recebe o texto como valor, não como SQL');
$built = transport_list_where('', '', '', $inj);
$sql = implode(' AND ', $built['where']);
t_ok(str_contains($sql, ':q_code') && str_contains($sql, 'EXISTS'), 'Busca usa placeholders e EXISTS em pontos');
t_ok(!str_contains($sql, $inj), 'SQL injection em q não entra no SQL');
t_ok(($built['params'][':q_code'] ?? '') === $like, 'Parâmetro bound carrega o LIKE escapado');

$badType = transport_normalize_list_query(['type' => 'hack', 'page' => '0', 'limit' => '999']);
t_ok($badType['ok'] === false, 'type inválido é rejeitado');
$okPage = transport_normalize_list_query(['page' => '2', 'limit' => '999', 'q' => 'centro']);
t_ok($okPage['ok'] === true && $okPage['page'] === 2 && $okPage['limit'] === TRANSPORT_PAGE_MAX, 'page/limit validados e limit tetado');

$cityBad = transport_normalize_list_query(['city' => 'belo-horizonte']);
t_ok($cityBad['ok'] === false, 'cidade fora do escopo é rejeitada');

$parsed = transport_parse_argv([
    'transport-import.php',
    '--file', 'linhas.json',
    '--source-name', 'Prefeitura',
    '--source-url', 'https://exemplo.gov.br',
    '--dry-run',
    '--limit', '10',
]);
t_ok($parsed['dry_run'] === true && $parsed['file'] === 'linhas.json' && $parsed['limit'] === 10, 'CLI aceita flags com espaço');
$parsedEq = transport_parse_argv(['transport-import.php', '--source=prefeitura', '--file=x.json']);
t_ok($parsedEq['source_type'] === 'prefeitura', 'CLI aceita --source=tipo');
$parsedSp = transport_parse_argv(['transport-import.php', '--source', 'receita', '--file', 'x.json']);
t_ok($parsedSp['source_type'] === 'receita', 'CLI aceita --source receita (espaço)');

$file = dirname(__DIR__) . '/tools/fixtures/transport-sample.json';
$decoded = json_decode((string) file_get_contents($file), true);
t_ok(is_array($decoded) && isset($decoded['lines'][0]['code']) && $decoded['lines'][0]['code'] === 'TEST-01', 'Fixture de transporte carrega');
t_ok(($decoded['lines'][0]['name'] ?? '') === 'Linha fictícia de teste AgendaAqui', 'Fixture é claramente de teste');

$csv = dirname(__DIR__) . '/tools/fixtures/transport-sample.csv';
t_ok(is_file($csv), 'Fixture CSV técnico existe');
$csvRows = importer_read_csv($csv);
t_ok(isset($csvRows[0]['code']) && $csvRows[0]['code'] === 'TEST-CSV-01', 'CSV de teste lê code');

$pathDenied = false;
try {
    importer_safe_file('/etc/passwd');
} catch (InvalidArgumentException $e) {
    $pathDenied = true;
}
t_ok($pathDenied, 'Arquivo absoluto fora de storage/imports é recusado');

$cliSrc = (string) file_get_contents(dirname(__DIR__) . '/tools/transport-import.php');
t_ok(
    str_contains($cliSrc, 'http_response_code(403)')
    && !preg_match('/\b(shell_exec|passthru|system|exec)\s*\(/', $cliSrc),
    'CLI HTTP 403 e sem shell_exec/exec/system/passthru'
);

$apiSrc = (string) file_get_contents(dirname(__DIR__) . '/api/transport/index.php');
t_ok(str_contains($apiSrc, 'require_csrf()') && str_contains($apiSrc, 'persist_require_admin()'), 'POST exige CSRF e admin');
t_ok(str_contains($apiSrc, 'schedule_count') && !str_contains($apiSrc, 'FROM transport_schedules WHERE line_id IN'), 'list não hidrata todos os horários');
t_ok(str_contains($apiSrc, 'op === \'schedules\'') && str_contains($apiSrc, 'op === \'stops\''), 'detalhe busca horários e pontos sob demanda');

$frontList = (string) file_get_contents(dirname(__DIR__) . '/src/routes/transporte.tsx');
t_ok(str_contains($frontList, 'Busque por linha, destino, bairro ou ponto'), 'placeholder de busca genérico');
t_ok(!str_contains($frontList, '5130'), 'listagem não cita linha 5130 como se existisse');
t_ok(str_contains($frontList, 'Sheet'), 'filtros mobile usam Sheet');

$frontDetail = (string) file_get_contents(dirname(__DIR__) . '/src/routes/transporte.$slug.tsx');
t_ok(str_contains($frontDetail, 'Horário não informado pela fonte'), 'empty state de horário');
t_ok(str_contains($frontDetail, 'shareTransportLine') && str_contains($frontDetail, 'Ida'), 'compartilhar e ida/volta');

$favSrc = (string) file_get_contents(dirname(__DIR__) . '/src/lib/transport.ts');
t_ok(str_contains($favSrc, 'transporte_favoritos') && str_contains($favSrc, 'line.code'), 'favoritos localStorage com compatibilidade de code');

if ($failed > 0) {
    fwrite(STDERR, "$failed teste(s) falharam.\n");
    exit(1);
}
echo "Todos os testes de transporte passaram.\n";
exit(0);
