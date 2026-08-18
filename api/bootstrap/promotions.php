<?php

declare(strict_types=1);

require_once __DIR__ . '/domains.php';

function promotions_sponsored_value(bool $isAdmin, mixed $requested, bool $existing): bool
{
    if (!$isAdmin) {
        return $existing;
    }

    return domain_bool($requested, $existing) === 1;
}

/**
 * @return array{user_id:string,is_admin:bool}
 */
function promotions_authorize(PDO $pdo, ?string $companyId): array
{
    $userId = require_auth();
    require_csrf();
    rate_limit_authenticated_write($userId);
    $isAdmin = has_role($pdo, $userId, 'admin');
    if ($isAdmin) {
        return ['user_id' => $userId, 'is_admin' => true];
    }
    if ($companyId === null) {
        app_error('forbidden', 'Administrator access is required.', 403);
    }
    $stmt = $pdo->prepare('SELECT 1 FROM companies WHERE id = :id AND owner_id = :owner LIMIT 1');
    $stmt->execute([':id' => $companyId, ':owner' => $userId]);
    if ($stmt->fetchColumn() === false) {
        app_error('forbidden', 'You do not own this company.', 403);
    }

    return ['user_id' => $userId, 'is_admin' => false];
}
