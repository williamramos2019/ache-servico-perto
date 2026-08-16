<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/persist.php';

app_start(['GET', 'POST', 'OPTIONS']);
auth_start_session();

$pdo = db_pdo(false);
$method = app_request_method();
$op = isset($_GET['op']) ? (string) $_GET['op'] : '';

if ($method === 'GET') {
    $userId = require_auth();
    if ($op === 'mine') {
        $stmt = $pdo->prepare(
            'SELECT cl.id, cl.status, cl.created_at, cl.admin_notes, cl.company_id,
                    c.name AS company_name, c.slug AS company_slug
             FROM company_claims cl
             INNER JOIN companies c ON c.id = cl.company_id
             WHERE cl.user_id = :user_id
             ORDER BY cl.created_at DESC'
        );
        $stmt->execute([':user_id' => $userId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'status' => (string) $row['status'],
                'created_at' => (string) $row['created_at'],
                'admin_notes' => $row['admin_notes'] !== null ? (string) $row['admin_notes'] : null,
                'company_id' => (string) $row['company_id'],
                'companies' => [
                    'name' => (string) $row['company_name'],
                    'slug' => (string) $row['company_slug'],
                ],
            ];
        }
        app_success(['claims' => $rows]);
    }

    if ($op === 'for_company') {
        $companyId = companies_require_uuid($_GET['company_id'] ?? null, 'invalid_company_id', 'Empresa inválida.');
        $stmt = $pdo->prepare(
            'SELECT id, status, created_at, admin_notes
             FROM company_claims
             WHERE user_id = :user_id AND company_id = :company_id
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId, ':company_id' => $companyId]);
        $row = $stmt->fetch();
        app_success([
            'claim' => $row === false
                ? null
                : [
                    'id' => (string) $row['id'],
                    'status' => (string) $row['status'],
                    'created_at' => (string) $row['created_at'],
                    'admin_notes' => $row['admin_notes'] !== null ? (string) $row['admin_notes'] : null,
                ],
        ]);
    }

    persist_require_admin();
    $status = isset($_GET['status']) ? (string) $_GET['status'] : 'pending';
    $sql = 'SELECT cl.*, c.id AS c_id, c.name AS c_name, c.slug AS c_slug, c.owner_id, c.email AS c_email, c.phone AS c_phone
            FROM company_claims cl
            INNER JOIN companies c ON c.id = cl.company_id';
    $params = [];
    if ($status !== 'all') {
        $sql .= ' WHERE cl.status = :status';
        $params[':status'] = $status;
    }
    $sql .= ' ORDER BY cl.created_at DESC LIMIT 300';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (string) $row['id'],
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
            'reviewed_at' => $row['reviewed_at'] !== null ? (string) $row['reviewed_at'] : null,
            'admin_notes' => $row['admin_notes'] !== null ? (string) $row['admin_notes'] : null,
            'full_name' => (string) $row['full_name'],
            'role_in_company' => $row['role_in_company'] !== null ? (string) $row['role_in_company'] : null,
            'phone' => (string) $row['phone'],
            'email' => (string) $row['email'],
            'document' => $row['document'] !== null ? (string) $row['document'] : null,
            'message' => $row['message'] !== null ? (string) $row['message'] : null,
            'proof_url' => $row['proof_url'] !== null ? (string) $row['proof_url'] : null,
            'user_id' => (string) $row['user_id'],
            'company_id' => (string) $row['company_id'],
            'companies' => [
                'id' => (string) $row['c_id'],
                'name' => (string) $row['c_name'],
                'slug' => (string) $row['c_slug'],
                'owner_id' => $row['owner_id'] !== null ? (string) $row['owner_id'] : null,
                'email' => $row['c_email'] !== null ? (string) $row['c_email'] : null,
                'phone' => $row['c_phone'] !== null ? (string) $row['c_phone'] : null,
            ],
        ];
    }
    app_success(['claims' => $rows]);
}

require_csrf();
$body = companies_read_json();
$op = is_string($body['op'] ?? null) ? (string) $body['op'] : 'create';

if ($op === 'create') {
    $userId = require_auth();
    rate_limit_hit('claim_user', $userId, 8, 3600);
    $companyId = companies_require_uuid($body['company_id'] ?? null, 'invalid_company_id', 'Empresa inválida.');
    $fullName = persist_optional_string($body['full_name'] ?? null, 255);
    $phone = persist_optional_string($body['phone'] ?? null, 64);
    $email = persist_optional_string($body['email'] ?? null, 255);
    if ($fullName === null || $phone === null || $email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        app_error('invalid_input', 'Preencha nome, telefone e e-mail válidos.', 422);
    }
    $company = companies_find($pdo, $companyId);
    if ($company === null) {
        app_error('not_found', 'Empresa não encontrada.', 404);
    }
    $dup = $pdo->prepare(
        'SELECT id FROM company_claims WHERE user_id = :user_id AND company_id = :company_id AND status = :status LIMIT 1'
    );
    $dup->execute([':user_id' => $userId, ':company_id' => $companyId, ':status' => 'pending']);
    if ($dup->fetch() !== false) {
        app_error('already_pending', 'Você já tem uma solicitação pendente para esta empresa.', 409);
    }
    $now = auth_now();
    $id = auth_uuid();
    $ins = $pdo->prepare(
        'INSERT INTO company_claims (id, company_id, user_id, full_name, role_in_company, phone, email, document, message, proof_url, status, created_at, updated_at)
         VALUES (:id, :company_id, :user_id, :full_name, :role_in_company, :phone, :email, :document, :message, :proof_url, :status, :created_at, :updated_at)'
    );
    $ins->execute([
        ':id' => $id,
        ':company_id' => $companyId,
        ':user_id' => $userId,
        ':full_name' => $fullName,
        ':role_in_company' => persist_optional_string($body['role_in_company'] ?? null, 255),
        ':phone' => $phone,
        ':email' => $email,
        ':document' => persist_optional_string($body['document'] ?? null, 64),
        ':message' => persist_optional_string($body['message'] ?? null, 4000),
        ':proof_url' => persist_optional_string($body['proof_url'] ?? null, 2048),
        ':status' => 'pending',
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $claimMessage = persist_optional_string($body['message'] ?? null, 4000);
    $opts = [];
    if (mail_valid_address($email)) {
        $opts['reply_to'] = $email;
    }
    mail_notify(
        'Nova reivindicação: ' . (string) ($company['name'] ?? 'empresa'),
        mail_format_fields([
            'Empresa' => (string) ($company['name'] ?? ''),
            'Nome' => $fullName,
            'Cargo' => persist_optional_string($body['role_in_company'] ?? null, 255),
            'Telefone' => $phone,
            'E-mail' => $email,
            'Documento' => persist_optional_string($body['document'] ?? null, 64),
            'Mensagem' => $claimMessage,
        ]),
        $opts
    );
    app_success(['id' => $id, 'status' => 'pending'], 201);
}

$adminId = persist_require_admin();
$claimId = companies_require_uuid($body['id'] ?? null, 'invalid_id', 'ID inválido.');
$notes = persist_optional_string($body['notes'] ?? null, 4000);
$now = auth_now();

$find = $pdo->prepare('SELECT * FROM company_claims WHERE id = :id LIMIT 1');
$find->execute([':id' => $claimId]);
$claim = $find->fetch();
if ($claim === false) {
    app_error('not_found', 'Solicitação não encontrada.', 404);
}

if ($op === 'approve') {
    $pdo->beginTransaction();
    $upd = $pdo->prepare(
        'UPDATE company_claims
         SET status = :status, admin_notes = :notes, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at
         WHERE id = :id'
    );
    $upd->execute([
        ':status' => 'approved',
        ':notes' => $notes,
        ':reviewed_by' => $adminId,
        ':reviewed_at' => $now,
        ':updated_at' => $now,
        ':id' => $claimId,
    ]);
    $own = $pdo->prepare('UPDATE companies SET owner_id = :owner_id, updated_at = :updated_at WHERE id = :id');
    $own->execute([
        ':owner_id' => (string) $claim['user_id'],
        ':updated_at' => $now,
        ':id' => (string) $claim['company_id'],
    ]);
    $role = $pdo->prepare(
        'INSERT IGNORE INTO user_roles (id, user_id, role, created_at) VALUES (:id, :user_id, :role, :created_at)'
    );
    $role->execute([
        ':id' => auth_uuid(),
        ':user_id' => (string) $claim['user_id'],
        ':role' => 'company_owner',
        ':created_at' => $now,
    ]);
    $pdo->commit();
    app_success(['ok' => true]);
}

if ($op === 'reject') {
    $upd = $pdo->prepare(
        'UPDATE company_claims
         SET status = :status, admin_notes = :notes, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at
         WHERE id = :id'
    );
    $upd->execute([
        ':status' => 'rejected',
        ':notes' => $notes,
        ':reviewed_by' => $adminId,
        ':reviewed_at' => $now,
        ':updated_at' => $now,
        ':id' => $claimId,
    ]);
    app_success(['ok' => true]);
}

app_error('invalid_op', 'Operação inválida.', 400);
