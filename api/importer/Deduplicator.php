<?php

declare(strict_types=1);

/**
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
            return ['company' => $row, 'match' => 'name_city'];
        }
    }

    return ['company' => null, 'match' => 'none'];
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
