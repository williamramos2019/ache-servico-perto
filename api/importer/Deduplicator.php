<?php

declare(strict_types=1);

/**
 * Exact matches may update empty public fields (--update).
 * name+city is only a candidate: never merge, never alter the existing row.
 *
 * @return array{company: ?array<string, mixed>, match: string}
 */
function importer_find_duplicate(PDO $pdo, array $record, string $cityId): array
{
    $cnpj = $record['cnpj'] ?? null;
    if (is_string($cnpj) && $cnpj !== '') {
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE cnpj = :cnpj LIMIT 1');
        $stmt->execute([':cnpj' => $cnpj]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return ['company' => $row, 'match' => 'cnpj'];
        }
    }

    $externalId = $record['external_id'] ?? null;
    if (is_string($externalId) && $externalId !== '') {
        $stmt = $pdo->prepare(
            'SELECT c.*
             FROM company_sources s
             INNER JOIN companies c ON c.id = s.company_id
             WHERE s.source_type = :source_type AND s.external_id = :external_id
             LIMIT 1'
        );
        $stmt->execute([
            ':source_type' => (string) ($record['source_type'] ?? ''),
            ':external_id' => $externalId,
        ]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return ['company' => $row, 'match' => 'external_id'];
        }
    }

    // Name+city is a heuristic only when the incoming row has no CNPJ.
    if (is_string($cnpj) && $cnpj !== '') {
        return ['company' => null, 'match' => 'none'];
    }

    $nameKey = importer_name_key((string) $record['name']);
    if ($nameKey === '') {
        return ['company' => null, 'match' => 'none'];
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM companies WHERE city_id = :city_id AND status = :status'
    );
    $stmt->execute([':city_id' => $cityId, ':status' => 'active']);
    foreach ($stmt->fetchAll() as $row) {
        if (importer_name_key((string) $row['name']) === $nameKey) {
            return ['company' => $row, 'match' => 'name_city_candidate'];
        }
    }

    return ['company' => null, 'match' => 'none'];
}

function importer_already_imported(PDO $pdo, string $sourceType, array $record): bool
{
    $cnpj = $record['cnpj'] ?? null;
    if (is_string($cnpj) && $cnpj !== '') {
        $stmt = $pdo->prepare('SELECT 1 FROM companies WHERE cnpj = :cnpj LIMIT 1');
        $stmt->execute([':cnpj' => $cnpj]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }
    }
    $externalId = $record['external_id'] ?? (is_string($cnpj) ? $cnpj : null);
    if (is_string($externalId) && $externalId !== '') {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM company_sources WHERE source_type = :source_type AND external_id = :external_id LIMIT 1'
        );
        $stmt->execute([':source_type' => $sourceType, ':external_id' => $externalId]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Heuristic only. Same normalized trade name + same city, without a CNPJ/external hit.
 * Legal name is never used as a merge key.
 */
function importer_is_name_city_candidate(string $incomingName, string $existingName, string $incomingCityId, string $existingCityId): bool
{
    if ($incomingCityId === '' || $incomingCityId !== $existingCityId) {
        return false;
    }
    $a = importer_name_key($incomingName);
    $b = importer_name_key($existingName);

    return $a !== '' && $a === $b;
}

function importer_unique_slug(PDO $pdo, string $base): string
{
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt = $pdo->prepare('SELECT 1 FROM companies WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        if ($stmt->fetchColumn() === false) {
            return $slug;
        }
        $slug = $base . '-' . $n;
        $n++;
        if ($n > 500) {
            return $base . '-' . bin2hex(random_bytes(3));
        }
    }
}
