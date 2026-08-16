<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/catalog.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();
require_csrf();

$userId = require_auth();
rate_limit_hit('review_user', $userId, 20, 3600);

$body = companies_read_json();
$companyId = companies_require_uuid($body['company_id'] ?? null, 'invalid_company_id', 'A valid company is required.');
$rating = isset($body['rating']) ? (int) $body['rating'] : 0;
$comment = isset($body['comment']) && is_string($body['comment']) ? trim($body['comment']) : '';
if ($rating < 1 || $rating > 5) {
    app_error('invalid_rating', 'Rating must be between 1 and 5.', 422);
}
if (strlen($comment) > 1000) {
    app_error('invalid_comment', 'Comment is too long.', 422);
}

$pdo = db_pdo(false);
$company = companies_find($pdo, $companyId);
if ($company === null || (string) $company['status'] !== 'active') {
    app_error('not_found', 'Empresa não encontrada.', 404);
}

$author = null;
$user = auth_public_user($pdo, $userId);
if (is_string($user['profile']['name']) && $user['profile']['name'] !== '') {
    $author = $user['profile']['name'];
}

$find = $pdo->prepare('SELECT id FROM reviews WHERE company_id = :company_id AND user_id = :user_id LIMIT 1');
$find->execute([':company_id' => $companyId, ':user_id' => $userId]);
$existing = $find->fetch();
$now = auth_now();

if ($existing !== false) {
    $upd = $pdo->prepare(
        'UPDATE reviews SET rating = :rating, comment = :comment, author_name = :author_name, review_date = :review_date
         WHERE id = :id'
    );
    $upd->execute([
        ':rating' => $rating,
        ':comment' => $comment !== '' ? $comment : null,
        ':author_name' => $author,
        ':review_date' => $now,
        ':id' => (string) $existing['id'],
    ]);
} else {
    $ins = $pdo->prepare(
        'INSERT INTO reviews (id, company_id, user_id, rating, comment, author_name, source, review_date, created_at)
         VALUES (:id, :company_id, :user_id, :rating, :comment, :author_name, :source, :review_date, :created_at)'
    );
    $ins->execute([
        ':id' => auth_uuid(),
        ':company_id' => $companyId,
        ':user_id' => $userId,
        ':rating' => $rating,
        ':comment' => $comment !== '' ? $comment : null,
        ':author_name' => $author,
        ':source' => 'app',
        ':review_date' => $now,
        ':created_at' => $now,
    ]);
}

catalog_refresh_company_rating($pdo, $companyId);
app_success(['ok' => true]);
