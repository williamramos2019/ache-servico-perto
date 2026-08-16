<?php

declare(strict_types=1);

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
        ':raw_data' => $raw === null ? null : json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':created_at' => auth_now(),
    ]);
}
