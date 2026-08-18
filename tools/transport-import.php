<?php

declare(strict_types=1);

/**
 * Importador de linhas de transporte (CLI only).
 *
 *   php tools/transport-import.php --help
 *   php tools/transport-import.php --file linhas.json --source-name "Prefeitura" --source-url "https://exemplo.gov.br" --dry-run
 */

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

function transport_import_help(): void
{
    echo <<<TXT
Importador de transporte público — AgendaAqui

Uso:
  php tools/transport-import.php --file linhas.json --source-name "Prefeitura" --source-url "https://exemplo.gov.br" --dry-run
  php tools/transport-import.php --file=linhas.csv --source-name=Prefeitura --source-url=https://exemplo.gov.br --limit=50

Opções:
  --file nome.json|csv         Arquivo em storage/imports
  --source-name ...            Nome da fonte (obrigatório)
  --source-url https://...     URL da fonte (obrigatório)
  --source-type prefeitura|der|consorcio|operador|dados-abertos|other
  --source TYPE               Alias de --source-type (espaço ou --source=tipo)
  --dry-run                    Somente leitura: não grava linhas, horários, pontos nem fontes
  --update                     Atualiza linha existente e substitui horários/pontos
  --resume                     Ignora linhas já cadastradas (code+tipo+cidade)
  --limit=N                    Processa no máximo N registros
  --help

Sem --update, linha já existente é duplicata e não é alterada (preserva edição manual).
Não há scraping. Sem arquivo sourced, o catálogo permanece vazio.

TXT;
}

try {
    $opts = transport_parse_argv($argv);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

if ($opts['help']) {
    transport_import_help();
    exit(0);
}

if ($opts['file'] === '' || $opts['source_name'] === '' || $opts['source_url'] === '') {
    fwrite(STDERR, "Informe --file, --source-name e --source-url. Use --help.\n");
    exit(1);
}

try {
    $pdo = db_pdo(false);
    $stats = transport_import_run($pdo, $opts);
    echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
