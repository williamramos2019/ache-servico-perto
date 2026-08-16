<?php

declare(strict_types=1);

/**
 * @return list<array<string, mixed>>
 */
function importer_read_json_or_csv(string $file): array
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'csv') {
        return importer_read_csv($file);
    }
    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('JSON inválido.');
    }
    if (isset($decoded['empresas']) && is_array($decoded['empresas'])) {
        $decoded = $decoded['empresas'];
    } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
        $decoded = $decoded['data'];
    }
    if ($decoded !== [] && array_keys($decoded) !== range(0, count($decoded) - 1)) {
        $decoded = [$decoded];
    }
    $out = [];
    foreach ($decoded as $row) {
        if (is_array($row)) {
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function importer_read_csv(string $file): array
{
    $fh = fopen($file, 'rb');
    if ($fh === false) {
        throw new RuntimeException('Não foi possível ler o CSV.');
    }
    $header = fgetcsv($fh, 0, ',');
    if ($header === false) {
        fclose($fh);
        return [];
    }
    $header = array_map(static fn ($h): string => strtolower(trim((string) $h)), $header);
    $rows = [];
    while (($cols = fgetcsv($fh, 0, ',')) !== false) {
        $row = [];
        foreach ($header as $i => $key) {
            $row[$key] = $cols[$i] ?? '';
        }
        $rows[] = $row;
    }
    fclose($fh);

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function importer_collect_cnpj_file(string $file, string $cityIbge): array
{
    $out = [];
    foreach (importer_read_json_or_csv($file) as $raw) {
        $record = importer_normalize_record($raw, 'receita');
        if (($record['ibge'] ?? null) !== $cityIbge) {
            continue;
        }
        $out[] = $record;
    }

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function importer_collect_municipal_file(string $file, string $cityIbge): array
{
    $out = [];
    foreach (importer_read_json_or_csv($file) as $raw) {
        $record = importer_normalize_record($raw, 'municipal');
        $ibge = $record['ibge'] ?? null;
        if (is_string($ibge) && $ibge !== '' && $ibge !== $cityIbge) {
            continue;
        }
        if (($record['external_id'] ?? null) === null) {
            $record['external_id'] = hash('sha256', importer_name_key((string) $record['name']) . '|' . $cityIbge . '|' . (string) ($record['address'] ?? ''));
        }
        $out[] = $record;
    }

    return $out;
}
