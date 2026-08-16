<?php

declare(strict_types=1);

function importer_http_get(string $url, int $timeoutSeconds = IMPORTER_BRASILAPI_TIMEOUT, int $retries = IMPORTER_BRASILAPI_RETRIES): ?string
{
    if (!str_starts_with($url, 'https://brasilapi.com.br/')) {
        return null;
    }
    $attempt = 0;
    while ($attempt <= $retries) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutSeconds,
                'header' => "Accept: application/json\r\nUser-Agent: AgendaAqui-Importer/1.0\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (is_string($body) && $body !== '') {
            return $body;
        }
        $attempt++;
        if ($attempt <= $retries) {
            usleep(250000);
        }
    }

    return null;
}

/**
 * Optional enrichment only. Never used as a collector. Failures are ignored.
 *
 * @param array<string, mixed> $record
 * @return array<string, mixed>
 */
function importer_brasilapi_enrich(array $record): array
{
    $cnpj = $record['cnpj'] ?? null;
    if (!is_string($cnpj) || !importer_cnpj_is_valid($cnpj)) {
        return $record;
    }
    try {
        $body = importer_http_get('https://brasilapi.com.br/api/cnpj/v1/' . $cnpj);
    } catch (Throwable $e) {
        return $record;
    }
    if ($body === null) {
        return $record;
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return $record;
    }
    if (($record['legal_name'] ?? null) === null && !empty($data['razao_social'])) {
        $record['legal_name'] = trim((string) $data['razao_social']);
    }
    if (($record['cnae_primary'] ?? null) === null && !empty($data['cnae_fiscal'])) {
        $record['cnae_primary'] = importer_only_digits((string) $data['cnae_fiscal']);
    }
    if (($record['cnae_description'] ?? null) === null && !empty($data['cnae_fiscal_descricao'])) {
        $record['cnae_description'] = trim((string) $data['cnae_fiscal_descricao']);
    }
    if (($record['neighborhood'] ?? null) === null && !empty($data['bairro'])) {
        $record['neighborhood'] = trim((string) $data['bairro']);
    }
    if (($record['zip'] ?? null) === null && !empty($data['cep'])) {
        $record['zip'] = importer_zip_normalize((string) $data['cep']);
    }
    if (($record['address'] ?? null) === null) {
        $parts = array_filter([
            trim((string) ($data['logradouro'] ?? '')),
            trim((string) ($data['numero'] ?? '')),
            trim((string) ($data['complemento'] ?? '')),
        ], static fn ($p): bool => $p !== '');
        if ($parts !== []) {
            $record['address'] = implode(', ', $parts);
        }
    }

    return $record;
}
