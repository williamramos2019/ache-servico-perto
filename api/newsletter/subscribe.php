<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/catalog.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
require_csrf();

rate_limit_hit('newsletter_ip', security_client_ip(), 5, 3600);

$body = companies_read_json();
$email = isset($body['email']) && is_string($body['email']) ? auth_normalize_email($body['email']) : '';
$name = isset($body['name']) && is_string($body['name']) ? trim($body['name']) : '';
$citySlug = isset($body['city_slug']) && is_string($body['city_slug']) ? trim($body['city_slug']) : '';

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    app_error('invalid_email', 'E-mail inválido.', 422);
}
if (strlen($name) > 120) {
    app_error('invalid_name', 'Nome muito longo.', 422);
}

$pdo = db_pdo(false);
$stmt = $pdo->prepare(
    'INSERT IGNORE INTO newsletter_subscribers (id, email, name, city_slug, created_at)
     VALUES (:id, :email, :name, :city_slug, :created_at)'
);
$stmt->execute([
    ':id' => auth_uuid(),
    ':email' => $email,
    ':name' => $name !== '' ? $name : null,
    ':city_slug' => $citySlug !== '' ? $citySlug : null,
    ':created_at' => auth_now(),
]);

$already = $stmt->rowCount() === 0;
if (!$already) {
    $opts = [];
    if (mail_valid_address($email)) {
        $opts['reply_to'] = $email;
    }
    mail_notify(
        'Nova inscrição na newsletter',
        mail_format_fields([
            'E-mail' => $email,
            'Nome' => $name !== '' ? $name : null,
            'Cidade' => $citySlug !== '' ? $citySlug : null,
        ]),
        $opts
    );
}

app_success(['ok' => true, 'already' => $already]);
