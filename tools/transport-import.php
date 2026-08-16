<?php

declare(strict_types=1);

/**
 * Importador de linhas de transporte (CLI only).
 *
 *   php tools/transport-import.php --help
 *   php tools/transport-import.php --file=linhas.json --source-name="Prefeitura SJL" --source-url="https://..." --dry-run
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este importador só pode ser executado pela linha de comando.\n");
    exit(1);
}

require dirname(__DIR__) . '/api/importer/bootstrap.php';
require_once dirname(__DIR__) . '/api/importer/transport.php';

function transport_import_help(): void
{
    echo <<<TXT
Importador de transporte público — AgendaAqui

Uso:
  php tools/transport-import.php --file=linhas.json --source-name="Prefeitura" --source-url="https://exemplo.gov.br" --dry-run

Opções:
  --file=nome.json             Arquivo dentro de storage/imports
  --source-name=...            Nome da fonte (obrigatório)
  --source-url=https://...     URL da fonte (obrigatório)
  --source-type=prefeitura|der|consorcio|other
  --dry-run                    Simula, não grava
  --help

Não há scraping. Sem arquivo sourced, o catálogo de linhas permanece vazio.

TXT;
}

$opts = [
    'file' => '',
    'source_name' => '',
    'source_url' => '',
    'source_type' => 'other',
    'dry_run' => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        transport_import_help();
        exit(0);
    }
    if ($arg === '--dry-run') {
        $opts['dry_run'] = true;
        continue;
    }
    if (str_starts_with($arg, '--file=')) {
        $opts['file'] = substr($arg, 7);
        continue;
    }
    if (str_starts_with($arg, '--source-name=')) {
        $opts['source_name'] = substr($arg, 14);
        continue;
    }
    if (str_starts_with($arg, '--source-url=')) {
        $opts['source_url'] = substr($arg, 13);
        continue;
    }
    if (str_starts_with($arg, '--source-type=')) {
        $opts['source_type'] = substr($arg, 14);
        continue;
    }
    fwrite(STDERR, "Opção desconhecida: $arg\n");
    exit(1);
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
