<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

require dirname(__DIR__) . '/api/bootstrap/cron.php';

$sourceId = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help') {
        echo "Uso: php tools/jobs-sync.php [--source-id=UUID]\n";
        exit(0);
    }
    if (str_starts_with($arg, '--source-id=')) {
        $candidate = substr($arg, 12);
        if (!companies_is_uuid($candidate)) {
            fwrite(STDERR, "source-id inválido.\n");
            exit(1);
        }
        $sourceId = strtolower($candidate);
    }
}

try {
    $result = cron_jobs_due(db_pdo(false), $sourceId);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
