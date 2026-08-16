<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $record
 * @return list<string>
 */
function importer_validate_record(array $record, string $cityIbge, string $sourceType): array
{
    $errors = [];
    $name = trim((string) ($record['name'] ?? ''));
    if ($name === '' || importer_len($name) < 2) {
        $errors[] = 'Nome obrigatório.';
    }
    $cnpj = $record['cnpj'] ?? null;
    if ($sourceType === 'receita') {
        if (!is_string($cnpj) || $cnpj === '') {
            $errors[] = 'CNPJ obrigatório na fonte Receita.';
        } elseif (!importer_cnpj_is_valid($cnpj)) {
            $errors[] = 'CNPJ inválido.';
        }
    } elseif (is_string($cnpj) && $cnpj !== '' && !importer_cnpj_is_valid($cnpj)) {
        $errors[] = 'CNPJ inválido.';
    }
    $ibge = $record['ibge'] ?? null;
    if (is_string($ibge) && $ibge !== '') {
        if (!importer_ibge_is_allowed($ibge)) {
            $errors[] = 'Município fora do escopo (somente IBGE 3162955 e 3171204).';
        } elseif ($ibge !== $cityIbge) {
            $errors[] = 'Município fora da cidade alvo.';
        }
    }
    if ($sourceType === 'receita' && (!is_string($ibge) || $ibge === '')) {
        $errors[] = 'Código IBGE do município ausente.';
    }
    $uf = (string) ($record['uf'] ?? 'MG');
    if ($uf !== '' && $uf !== 'MG') {
        $errors[] = 'UF fora de Minas Gerais.';
    }
    if (empty($record['active'])) {
        $errors[] = 'Situação cadastral inativa.';
    }

    return $errors;
}
