<?php

declare(strict_types=1);

/**
 * Importador controlado de empresas (CLI only).
 *
 *   php tools/import-companies.php --help
 *   php tools/import-companies.php --source=receita --city=sjl --file=sjl.json --dry-run
 *   php tools/import-companies.php --source=municipal --city=vespasiano --file=vesp.csv --limit=100
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este importador só pode ser executado pela linha de comando.\n");
    exit(1);
}

require dirname(__DIR__) . '/api/importer/bootstrap.php';

function import_companies_help(): void
{
    echo <<<TXT
Importador de empresas — AgendaAqui

Uso:
  php tools/import-companies.php --source=receita --city=sjl --file=arquivo.json --dry-run
  php tools/import-companies.php --source=municipal --city=vespasiano --file=vesp.json --limit=50

Opções:
  --source=receita|municipal   Fonte do arquivo (BrasilAPI não coleta; use --enrich)
  --city=sjl|vespasiano        Cidade alvo (IBGE 3162955 / 3171204)
  --file=nome.json             Arquivo dentro de storage/imports
  --dry-run                    Simula, não grava no banco
  --limit=N                    Processa no máximo N registros
  --update                     Preenche campos vazios em duplicatas (nunca sobrescreve dono/plano)
  --enrich                     Complementa vazios via BrasilAPI (máx. 50, com pausa)
  --help                       Esta ajuda

O arquivo precisa estar em storage/imports. Caminhos com .. são recusados.
Sem arquivo real de CNPJ/cadastro municipal, não inventamos empresas.

TXT;
}

$opts = [
    'source' => '',
    'city' => '',
    'file' => '',
    'dry_run' => false,
    'limit' => null,
    'update' => false,
    'enrich' => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        import_companies_help();
        exit(0);
    }
    if ($arg === '--dry-run') {
        $opts['dry_run'] = true;
        continue;
    }
    if ($arg === '--update') {
        $opts['update'] = true;
        continue;
    }
    if ($arg === '--enrich') {
        $opts['enrich'] = true;
        continue;
    }
    if (str_starts_with($arg, '--source=')) {
        $opts['source'] = substr($arg, 9);
        continue;
    }
    if (str_starts_with($arg, '--city=')) {
        $opts['city'] = substr($arg, 7);
        continue;
    }
    if (str_starts_with($arg, '--file=')) {
        $opts['file'] = substr($arg, 7);
        continue;
    }
    if (str_starts_with($arg, '--limit=')) {
        $opts['limit'] = max(1, (int) substr($arg, 8));
        continue;
    }
    fwrite(STDERR, "Opção desconhecida: $arg\n");
    exit(1);
}

if ($opts['source'] === '' || $opts['city'] === '' || $opts['file'] === '') {
    fwrite(STDERR, "Informe --source, --city e --file. Use --help.\n");
    exit(1);
}

try {
    $pdo = db_pdo(false);
    $stats = importer_run($pdo, $opts);
    echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
