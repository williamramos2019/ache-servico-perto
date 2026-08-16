<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/persist.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
require_csrf();
rate_limit_hit('plan_lead_ip', security_client_ip(), 8, 3600);

$body = companies_read_json();
$companyName = persist_optional_string($body['company_name'] ?? null, 255);
$contactName = persist_optional_string($body['contact_name'] ?? null, 255);
$email = persist_optional_string($body['email'] ?? null, 255);
$plan = persist_optional_string($body['plan'] ?? null, 64);
if ($companyName === null || $contactName === null || $email === null || $plan === null) {
    app_error('invalid_input', 'Preencha os dados do plano.', 422);
}
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    app_error('invalid_email', 'E-mail inválido.', 422);
}

$phone = persist_optional_string($body['phone'] ?? null, 64);
$city = persist_optional_string($body['city'] ?? null, 255);
$leadMessage = persist_optional_string($body['message'] ?? null, 1000);

$pdo = db_pdo(false);
$stmt = $pdo->prepare(
    'INSERT INTO leads_planos (id, company_name, contact_name, email, phone, city, plan, message, status, created_at)
     VALUES (:id, :company_name, :contact_name, :email, :phone, :city, :plan, :message, :status, :created_at)'
);
$stmt->execute([
    ':id' => auth_uuid(),
    ':company_name' => $companyName,
    ':contact_name' => $contactName,
    ':email' => $email,
    ':phone' => $phone,
    ':city' => $city,
    ':plan' => $plan,
    ':message' => $leadMessage,
    ':status' => 'new',
    ':created_at' => auth_now(),
]);
$opts = [];
if (mail_valid_address($email)) {
    $opts['reply_to'] = $email;
}
mail_notify(
    'Novo lead de plano: ' . $plan,
    mail_format_fields([
        'Plano' => $plan,
        'Empresa' => $companyName,
        'Contato' => $contactName,
        'E-mail' => $email,
        'Telefone' => $phone,
        'Cidade' => $city,
        'Mensagem' => $leadMessage,
    ]),
    $opts
);

app_success(['ok' => true], 201);
