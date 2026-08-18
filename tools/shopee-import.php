<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

require dirname(__DIR__) . '/api/bootstrap/shopee.php';

$path = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help') {
        echo "Uso: php tools/shopee-import.php --csv=arquivo.csv\n";
        exit(0);
    }
    if (str_starts_with($arg, '--csv=')) {
        $path = substr($arg, 6);
    }
}

if (!is_string($path) || $path === '' || !is_file($path)) {
    fwrite(STDERR, "Informe um CSV existente com --csv=arquivo.csv\n");
    exit(1);
}

try {
    $result = shopee_import_csv(db_pdo(false), $path);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
