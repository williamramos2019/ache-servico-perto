<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

require dirname(__DIR__) . '/api/importer/bootstrap.php';
require_once dirname(__DIR__) . '/api/importer/transport.php';
require_once dirname(__DIR__) . '/api/bootstrap/cron.php';

$citySlug = '';
$transportArgv = [$argv[0]];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--city-slug=')) {
        $citySlug = substr($arg, 12);
        continue;
    }
    $transportArgv[] = $arg;
}
if ($citySlug === '' || !in_array($citySlug, WHATSAPP_CITY_SLUGS, true)) {
    fwrite(STDERR, "Informe --city-slug=vespasiano ou --city-slug=sao-jose-da-lapa.\n");
    exit(1);
}
try {
    $options = transport_parse_argv($transportArgv);
    if ($options['help']) {
        echo "Uso: php tools/bus-sync.php --city-slug=vespasiano --file=linhas.json --source-name=Prefeitura --source-url=https://...\n";
        exit(0);
    }
    if ($options['file'] === '' || $options['source_name'] === '' || $options['source_url'] === '') {
        throw new InvalidArgumentException('file, source-name e source-url são obrigatórios.');
    }
    $started = microtime(true);
    $pdo = db_pdo(false);
    $stats = transport_import_run($pdo, $options);
    $logId = cron_log_bus($pdo, $citySlug, $stats, $started);
    echo json_encode(['log_id' => $logId, 'stats' => $stats], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
