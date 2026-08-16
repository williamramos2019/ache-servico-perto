<?php

declare(strict_types=1);

function importer_sanitize_raw(?array $raw): ?array
{
    if ($raw === null) {
        return null;
    }
    $deny = ['password', 'senha', 'token', 'secret', 'api_key', 'apikey', 'authorization', 'cookie'];
    $out = [];
    foreach ($raw as $key => $value) {
        if (in_array(strtolower((string) $key), $deny, true)) {
            continue;
        }
        if (is_array($value)) {
            $out[$key] = importer_sanitize_raw($value);
        } else {
            $out[$key] = $value;
        }
    }

    return $out;
}

function importer_log_line(string $message): void
{
    fwrite(STDERR, '[' . gmdate('H:i:s') . '] ' . $message . PHP_EOL);
}

function importer_log_error(
    PDO $pdo,
    string $runId,
    ?string $externalId,
    ?string $name,
    string $type,
    string $message,
    ?array $raw
): void {
    $safe = importer_sanitize_raw($raw);
    $stmt = $pdo->prepare(
        'INSERT INTO company_import_errors
            (id, run_id, external_id, company_name, error_type, error_message, raw_data, created_at)
         VALUES
            (:id, :run_id, :external_id, :company_name, :error_type, :error_message, :raw_data, :created_at)'
    );
    $stmt->execute([
        ':id' => auth_uuid(),
        ':run_id' => $runId,
        ':external_id' => $externalId,
        ':company_name' => $name,
        ':error_type' => $type,
        ':error_message' => $message,
        ':raw_data' => $safe === null ? null : json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':created_at' => auth_now(),
    ]);
}
