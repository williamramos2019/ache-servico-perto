<?php

declare(strict_types=1);

require_once __DIR__ . '/domains.php';

const WHATSAPP_CITY_SLUGS = ['vespasiano', 'sao-jose-da-lapa'];

function whatsapp_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function whatsapp_base64url_decode(string $value): ?string
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
        return null;
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);

    return is_string($decoded) ? $decoded : null;
}

function whatsapp_optout_sign(string $secret, string $subscriberId, string $phone, int $issuedAt): string
{
    if ($secret === '') {
        throw new InvalidArgumentException('Opt-out signing secret is required.');
    }
    $payload = whatsapp_base64url_encode(json_encode([
        'sub' => $subscriberId,
        'phone' => $phone,
        'iat' => $issuedAt,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $signature = whatsapp_base64url_encode(hash_hmac('sha256', $payload, $secret, true));

    return $payload . '.' . $signature;
}

function whatsapp_optout_token_valid(
    string $secret,
    string $token,
    string $subscriberId,
    string $phone,
    string $storedHash
): bool {
    if ($secret === '' || $storedHash === '' || !hash_equals($storedHash, hash('sha256', $token))) {
        return false;
    }
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return false;
    }
    [$payload, $providedSignature] = $parts;
    $expectedSignature = whatsapp_base64url_encode(hash_hmac('sha256', $payload, $secret, true));
    if (!hash_equals($expectedSignature, $providedSignature)) {
        return false;
    }
    $decoded = whatsapp_base64url_decode($payload);
    $claims = is_string($decoded) ? json_decode($decoded, true) : null;

    return is_array($claims)
        && is_string($claims['sub'] ?? null)
        && is_string($claims['phone'] ?? null)
        && hash_equals($subscriberId, $claims['sub'])
        && hash_equals($phone, $claims['phone']);
}

function whatsapp_optout_secret(): string
{
    $dedicated = trim((string) getenv('WHATSAPP_OPTOUT_SECRET'));
    if ($dedicated !== '') {
        return $dedicated;
    }

    return trim((string) getenv('CRON_SHARED_SECRET'));
}

/**
 * Resolves whether a subscription may be mutated without ever assigning an
 * authenticated user to a pre-existing anonymous phone.
 *
 * @return array{
 *   exists:bool,
 *   can_mutate:bool,
 *   owner_id:?string,
 *   owner_matches:bool,
 *   token_valid:bool,
 *   subscriber:array<string,mixed>|null
 * }
 */
function whatsapp_subscription_transition(
    PDO $pdo,
    string $phone,
    ?string $authenticatedUserId,
    string $manageToken,
    string $secret,
    bool $lockRow = true
): array {
    $sql = 'SELECT id, phone, is_active, user_id, opt_out_token_hash
            FROM whatsapp_subscribers WHERE phone = :phone LIMIT 1';
    if ($lockRow) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':phone' => $phone]);
    $existing = $stmt->fetch();
    if ($existing === false) {
        return [
            'exists' => false,
            'can_mutate' => true,
            'owner_id' => $authenticatedUserId,
            'owner_matches' => false,
            'token_valid' => false,
            'subscriber' => null,
        ];
    }
    $ownerId = is_string($existing['user_id'] ?? null) && $existing['user_id'] !== ''
        ? (string) $existing['user_id']
        : null;
    $ownerMatches = $ownerId !== null
        && $authenticatedUserId !== null
        && hash_equals($ownerId, $authenticatedUserId);
    $tokenValid = whatsapp_optout_token_valid(
        $secret,
        $manageToken,
        (string) $existing['id'],
        (string) $existing['phone'],
        (string) ($existing['opt_out_token_hash'] ?? '')
    );

    return [
        'exists' => true,
        'can_mutate' => $ownerMatches || $tokenValid,
        'owner_id' => $ownerId,
        'owner_matches' => $ownerMatches,
        'token_valid' => $tokenValid,
        'subscriber' => $existing,
    ];
}

/**
 * @return array{id:string,name:string,slug:string}
 */
function whatsapp_city(PDO $pdo, mixed $slug): array
{
    $slug = domain_enum($slug, 'citySlug', WHATSAPP_CITY_SLUGS, '');
    $stmt = $pdo->prepare('SELECT id, name, slug FROM cities WHERE slug = :slug AND is_active = 1 LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    if ($row === false) {
        app_error('invalid_city', 'City is not available.', 422);
    }

    return ['id' => (string) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug']];
}

function whatsapp_audit_key(string $subscriberId): string
{
    return 'whatsapp_consent_' . str_replace('-', '', $subscriberId);
}

/**
 * Stores explicit consent and welcome delivery state without exposing provider credentials.
 */
function whatsapp_store_audit(PDO $pdo, string $subscriberId, array $audit): void
{
    $pdo->prepare(
        'INSERT INTO system_settings (`key`, `value`, is_public, updated_at)
         VALUES (:key, :value, 0, :updated)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)'
    )->execute([
        ':key' => whatsapp_audit_key($subscriberId),
        ':value' => json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':updated' => auth_now(),
    ]);
}

function whatsapp_welcome_status(): string
{
    $url = trim((string) getenv('WHATSAPP_BOT_URL'));
    $token = trim((string) getenv('WHATSAPP_BOT_TOKEN'));

    return $url !== '' && $token !== '' ? 'queued' : 'unavailable';
}
