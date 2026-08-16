<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
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

$file = dirname(__DIR__) . '/tools/fixtures/transport-sample.json';
$decoded = json_decode((string) file_get_contents($file), true);
t_ok(is_array($decoded) && isset($decoded['lines'][0]['code']) && $decoded['lines'][0]['code'] === 'TEST-01', 'Fixture de transporte carrega');
t_ok(($decoded['lines'][0]['name'] ?? '') === 'Linha fictícia de teste AgendaAqui', 'Fixture é claramente de teste');

$pathDenied = false;
try {
    importer_safe_file('/etc/passwd');
} catch (InvalidArgumentException $e) {
    $pathDenied = true;
}
t_ok($pathDenied, 'Arquivo absoluto fora de storage/imports é recusado');

if ($failed > 0) {
    fwrite(STDERR, "$failed teste(s) falharam.\n");
    exit(1);
}
echo "Todos os testes de transporte passaram.\n";
exit(0);
