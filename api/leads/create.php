<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/catalog.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
require_csrf();

rate_limit_hit('lead_ip', security_client_ip(), 8, 3600);

$body = companies_read_json();
$companyId = companies_require_uuid($body['company_id'] ?? null, 'invalid_company_id', 'A valid company is required.');
$name = is_string($body['name'] ?? null) ? trim($body['name']) : '';
$phone = is_string($body['phone'] ?? null) ? trim($body['phone']) : '';
$email = isset($body['email']) && is_string($body['email']) ? trim($body['email']) : '';
$message = isset($body['message']) && is_string($body['message']) ? trim($body['message']) : '';

if (strlen($name) < 2 || strlen($name) > 100) {
    app_error('invalid_name', 'Informe seu nome.', 422);
}
if (strlen($phone) < 8 || strlen($phone) > 64) {
    app_error('invalid_phone', 'Telefone inválido.', 422);
}
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    app_error('invalid_email', 'E-mail inválido.', 422);
}
if (strlen($message) > 1000) {
    app_error('invalid_message', 'Mensagem muito longa.', 422);
}

$pdo = db_pdo(false);
$company = companies_find($pdo, $companyId);
if ($company === null || (string) $company['status'] !== 'active') {
    app_error('not_found', 'Empresa não encontrada.', 404);
}

$stmt = $pdo->prepare(
    'INSERT INTO leads (id, company_id, user_id, name, phone, email, message, created_at)
     VALUES (:id, :company_id, :user_id, :name, :phone, :email, :message, :created_at)'
);
$stmt->execute([
    ':id' => auth_uuid(),
    ':company_id' => $companyId,
    ':user_id' => auth_user_id(),
    ':name' => $name,
    ':phone' => $phone,
    ':email' => $email !== '' ? $email : null,
    ':message' => $message !== '' ? $message : null,
    ':created_at' => auth_now(),
]);

$companyName = (string) ($company['name'] ?? 'Empresa');
$companyEmail = isset($company['email']) && is_string($company['email']) ? trim($company['email']) : '';
$text = mail_format_fields([
    'Empresa' => $companyName,
    'Nome' => $name,
    'Telefone' => $phone,
    'E-mail' => $email !== '' ? $email : null,
    'Mensagem' => $message !== '' ? $message : null,
]);
$opts = [];
if ($email !== '' && mail_valid_address($email)) {
    $opts['reply_to'] = $email;
}
mail_notify('Novo orçamento: ' . $companyName, $text, $opts);
if ($companyEmail !== '' && mail_valid_address($companyEmail) && strcasecmp($companyEmail, mail_config()['from']) !== 0) {
    mail_try_send($companyEmail, 'Novo orçamento no AgendaAqui: ' . $companyName, $text, $opts);
}

app_success(['ok' => true], 201);
