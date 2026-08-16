<?php

declare(strict_types=1);

function importer_only_digits(string $raw): string
{
    return preg_replace('/\D+/', '', $raw) ?? '';
}

function importer_cnpj_is_valid(string $cnpj): bool
{
    if (strlen($cnpj) !== 14 || !ctype_digit($cnpj)) {
        return false;
    }
    if (preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
        return false;
    }
    $calc = static function (string $base, array $weights): int {
        $sum = 0;
        foreach ($weights as $i => $w) {
            $sum += (int) $base[$i] * $w;
        }
        $mod = $sum % 11;

        return $mod < 2 ? 0 : 11 - $mod;
    };
    $d1 = $calc($cnpj, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    if ($d1 !== (int) $cnpj[12]) {
        return false;
    }
    $d2 = $calc($cnpj, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

    return $d2 === (int) $cnpj[13];
}

function importer_zip_normalize(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    $digits = importer_only_digits($raw);
    if (strlen($digits) !== 8) {
        return null;
    }

    return $digits;
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function importer_normalize_record(array $raw, string $sourceType): array
{
    $cnpj = importer_only_digits((string) ($raw['cnpj'] ?? $raw['cnpj_cpf'] ?? ''));
    if ($cnpj === '' && isset($raw['cnpj_basico'], $raw['cnpj_ordem'], $raw['cnpj_dv'])) {
        $cnpj = str_pad(importer_only_digits((string) $raw['cnpj_basico']), 8, '0', STR_PAD_LEFT)
            . str_pad(importer_only_digits((string) $raw['cnpj_ordem']), 4, '0', STR_PAD_LEFT)
            . str_pad(importer_only_digits((string) $raw['cnpj_dv']), 2, '0', STR_PAD_LEFT);
    }

    $trade = trim((string) ($raw['nome_fantasia'] ?? $raw['trade_name'] ?? $raw['name'] ?? $raw['nome'] ?? ''));
    $legal = trim((string) ($raw['razao_social'] ?? $raw['legal_name'] ?? $raw['razao'] ?? ''));
    $name = $trade !== '' ? $trade : $legal;

    $street = trim((string) ($raw['logradouro'] ?? $raw['street'] ?? $raw['address'] ?? ''));
    $number = trim((string) ($raw['numero'] ?? $raw['number'] ?? ''));
    $complement = trim((string) ($raw['complemento'] ?? $raw['complement'] ?? ''));
    $addressParts = array_values(array_filter([$street, $number, $complement], static fn ($p): bool => $p !== ''));
    $address = $addressParts === [] ? null : implode(', ', $addressParts);

    $ibge = importer_only_digits((string) ($raw['codigo_municipio'] ?? $raw['municipio_ibge'] ?? $raw['ibge'] ?? $raw['codigo_ibge'] ?? ''));
    $situacao = strtoupper(trim((string) ($raw['situacao_cadastral'] ?? $raw['descricao_situacao_cadastral'] ?? $raw['situacao'] ?? '')));
    $active = $situacao === '' || $situacao === 'ATIVA' || $situacao === '02' || $situacao === '2';

    $cnae = importer_only_digits((string) ($raw['cnae_fiscal'] ?? $raw['cnae'] ?? $raw['cnae_primary'] ?? $raw['cnae_principal'] ?? ''));
    if (strlen($cnae) > 7) {
        $cnae = substr($cnae, 0, 7);
    }
    $cnaeDesc = trim((string) ($raw['cnae_fiscal_descricao'] ?? $raw['cnae_descricao'] ?? $raw['atividade'] ?? ''));

    $externalId = trim((string) ($raw['external_id'] ?? ''));
    if ($externalId === '' && $cnpj !== '') {
        $externalId = $cnpj;
    }

    return [
        'cnpj' => $cnpj !== '' ? $cnpj : null,
        'name' => $name,
        'legal_name' => $legal !== '' ? $legal : null,
        'cnae_primary' => $cnae !== '' ? $cnae : null,
        'cnae_description' => $cnaeDesc !== '' ? $cnaeDesc : null,
        'neighborhood' => trim((string) ($raw['bairro'] ?? $raw['neighborhood'] ?? '')) ?: null,
        'address' => $address,
        'zip' => importer_zip_normalize(isset($raw['cep']) ? (string) $raw['cep'] : (isset($raw['zip']) ? (string) $raw['zip'] : null)),
        'ibge' => $ibge !== '' ? $ibge : null,
        'uf' => strtoupper(trim((string) ($raw['uf'] ?? $raw['estado'] ?? 'MG'))) ?: 'MG',
        'phone' => trim((string) ($raw['telefone'] ?? $raw['phone'] ?? $raw['ddd_telefone_1'] ?? '')) ?: null,
        'email' => trim((string) ($raw['email'] ?? '')) ?: null,
        'active' => $active,
        'source_type' => $sourceType,
        'external_id' => $externalId !== '' ? $externalId : null,
        'source_url' => isset($raw['source_url']) ? trim((string) $raw['source_url']) : null,
        'raw' => $raw,
    ];
}
