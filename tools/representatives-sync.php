<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

require dirname(__DIR__) . '/api/bootstrap/representatives.php';

$options = ['file' => '', 'source' => 'manual', 'city_id' => null];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help') {
        echo "Uso: php tools/representatives-sync.php --file=arquivo.json --source=manual [--city-id=UUID]\n";
        exit(0);
    }
    foreach (['file' => 'file', 'source' => 'source', 'city-id' => 'city_id'] as $flag => $key) {
        if (str_starts_with($arg, '--' . $flag . '=')) {
            $options[$key] = substr($arg, strlen($flag) + 3);
        }
    }
}
if ($options['file'] === '' || basename($options['file']) !== $options['file'] || !str_ends_with(strtolower($options['file']), '.json')) {
    fwrite(STDERR, "Informe um arquivo .json sem caminho, localizado em storage/imports.\n");
    exit(1);
}
if (!in_array($options['source'], REPRESENTATIVE_IMPORT_SOURCES, true)) {
    fwrite(STDERR, "Fonte não permitida.\n");
    exit(1);
}
if ($options['city_id'] !== null && $options['city_id'] !== '' && !companies_is_uuid((string) $options['city_id'])) {
    fwrite(STDERR, "city-id inválido.\n");
    exit(1);
}
$path = dirname(__DIR__) . '/storage/imports/' . $options['file'];
if (!is_file($path) || filesize($path) > 5_000_000) {
    fwrite(STDERR, "Arquivo ausente ou muito grande.\n");
    exit(1);
}
$items = json_decode((string) file_get_contents($path), true);
if (!is_array($items) || !array_is_list($items)) {
    fwrite(STDERR, "JSON deve conter uma lista.\n");
    exit(1);
}
try {
    $result = representatives_import(
        db_pdo(false),
        (string) $options['source'],
        $options['city_id'] !== null && $options['city_id'] !== '' ? (string) $options['city_id'] : null,
        $items
    );
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
