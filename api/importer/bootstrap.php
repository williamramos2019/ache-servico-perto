<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/auth.php';

const IMPORTER_VERSION = '2';
const IMPORTER_BATCH_SIZE = 100;
const IMPORTER_BRASILAPI_MAX = 50;
const IMPORTER_BRASILAPI_SLEEP_US = 400000;
const IMPORTER_BRASILAPI_TIMEOUT = 10;
const IMPORTER_BRASILAPI_RETRIES = 2;
const IMPORTER_ALLOWED_CITIES = [
    'sao-jose-da-lapa' => '3162955',
    'vespasiano' => '3171204',
];
const IMPORTER_CITY_ALIASES = [
    'sjl' => 'sao-jose-da-lapa',
    'sao_jose_da_lapa' => 'sao-jose-da-lapa',
    'sao-jose-da-lapa' => 'sao-jose-da-lapa',
    'vesp' => 'vespasiano',
    'vespasiano' => 'vespasiano',
];
const IMPORTER_ALLOWED_SOURCES = ['receita', 'municipal', 'brasilapi'];

function importer_repo_root(): string
{
    return dirname(__DIR__, 2);
}

function importer_imports_dir(): string
{
    return importer_repo_root() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'imports';
}

function importer_resolve_city_slug(string $raw): string
{
    $key = strtolower(trim($raw));
    if (!isset(IMPORTER_CITY_ALIASES[$key])) {
        throw new InvalidArgumentException('Cidade inválida. Use sjl ou vespasiano.');
    }

    return IMPORTER_CITY_ALIASES[$key];
}

function importer_city_ibge(string $citySlug): string
{
    if (!isset(IMPORTER_ALLOWED_CITIES[$citySlug])) {
        throw new InvalidArgumentException('Cidade fora do escopo do importador.');
    }

    return IMPORTER_ALLOWED_CITIES[$citySlug];
}

function importer_ibge_is_allowed(string $ibge): bool
{
    return in_array($ibge, array_values(IMPORTER_ALLOWED_CITIES), true);
}

function importer_city_slug_from_ibge(string $ibge): ?string
{
    $map = array_flip(IMPORTER_ALLOWED_CITIES);

    return $map[$ibge] ?? null;
}

function importer_abort_if_not_cli(): void
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

/**
 * Only files inside storage/imports (basename or relative, no "..").
 */
function importer_safe_file(string $userPath): string
{
    $userPath = trim(str_replace('\\', '/', $userPath));
    if ($userPath === '' || str_contains($userPath, '..') || str_starts_with($userPath, '/')) {
        throw new InvalidArgumentException('Caminho de arquivo inválido. Use um arquivo em storage/imports.');
    }
    $base = realpath(importer_imports_dir());
    if ($base === false) {
        throw new RuntimeException('Pasta storage/imports não encontrada.');
    }
    $candidate = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $userPath);
    $real = realpath($candidate);
    if ($real === false || !is_file($real)) {
        throw new InvalidArgumentException('Arquivo não encontrado em storage/imports.');
    }
    $prefix = $base . DIRECTORY_SEPARATOR;
    if (!str_starts_with($real, $prefix) && $real !== $base) {
        throw new InvalidArgumentException('Arquivo fora de storage/imports.');
    }

    return $real;
}

function importer_slugify(string $name, string $citySlug): string
{
    $map = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'ê' => 'e', 'è' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a',
        'É' => 'e', 'Ê' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o',
        'Ú' => 'u', 'Ç' => 'c',
    ];
    $s = strtr($name, $map);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    if ($s === '') {
        $s = 'empresa';
    }
    if (strlen($s) > 80) {
        $s = rtrim(substr($s, 0, 80), '-');
    }

    return $s . '-' . $citySlug;
}

function importer_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

function importer_lower(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
}

function importer_name_key(string $name): string
{
    $map = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'ê' => 'e', 'è' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
    ];
    $s = strtolower(strtr($name, $map));
    $s = preg_replace('/[^a-z0-9]+/', '', $s) ?? '';

    return $s;
}

require_once __DIR__ . '/Normalizer.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Classifier.php';
require_once __DIR__ . '/Deduplicator.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/ImportLogger.php';
require_once __DIR__ . '/adapters.php';
require_once __DIR__ . '/CompanyImporter.php';
require_once __DIR__ . '/ImportManager.php';
