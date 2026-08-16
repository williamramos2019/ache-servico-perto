<?php

declare(strict_types=1);

/**
 * Importador controlado de empresas (CLI only).
 *
 *   php tools/import-companies.php --help
 *   php tools/import-companies.php --source receita --city sjl --file receita/arquivo.json --dry-run
 *   php tools/import-companies.php --source=receita --city=vespasiano --file=receita/vesp.json --limit=100
 *   php tools/import-companies.php --source receita --city sjl --file receita/arquivo.json --resume
 */

importer_cli_guard();

require dirname(__DIR__) . '/api/importer/bootstrap.php';

function importer_cli_guard(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Forbidden\n";
    exit(1);
}

function import_companies_help(): void
{
    echo <<<TXT
Importador de empresas — AgendaAqui

Uso:
  php tools/import-companies.php --source receita --city sjl --file receita/arquivo.json --dry-run
  php tools/import-companies.php --source municipal --city vespasiano --file municipal/vesp.csv --limit=50
  php tools/import-companies.php --source receita --city sjl --file receita/arquivo.json --resume

Opções:
  --source receita|municipal   Fonte do arquivo (BrasilAPI não coleta; use --enrich)
  --city sjl|vespasiano        Cidade alvo (IBGE 3162955 / 3171204)
  --file nome.json             Arquivo relativo a storage/imports/
  --dry-run                    Somente leitura: não grava empresas nem marca importação concluída
  --limit=N                    Processa no máximo N registros
  --resume                     Retoma pulando CNPJ/external_id já importados
  --resume=UUID                Continua o run_id indicado (não usar run dry-run)
  --update                     Preenche campos públicos vazios só em match exato de CNPJ/fonte
  --enrich                     Complementa vazios via BrasilAPI (timeout, retry, rate limit)
  --help                       Esta ajuda

Regras:
  - Somente CLI. Via navegador responde 403.
  - Arquivo em storage/imports (ex.: storage/imports/receita/). Caminhos com .. são recusados.
  - Município só pelos códigos IBGE 3162955 (SJL) e 3171204 (Vespasiano).
  - Empresas existentes: owner, plano, destaque, verificação, rating, WhatsApp, fotos e descrição não são sobrescritos.
  - origin das empresas já cadastradas não muda. Novas importadas recebem origin=imported.
  - Sem arquivo real da Receita, não inventamos empresas e não há carga real.

TXT;
}

try {
    $opts = importer_parse_argv($argv);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

if ($opts['help']) {
    import_companies_help();
    exit(0);
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
