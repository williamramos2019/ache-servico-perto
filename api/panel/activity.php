<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/catalog.php';

app_start(['GET', 'OPTIONS']);
auth_start_session();

$userId = require_auth();
$pdo = db_pdo(false);

$leads = $pdo->prepare(
    'SELECT l.id, l.name, l.phone, l.email, l.message, l.created_at, l.company_id,
            c.id AS c_id, c.name AS c_name, c.slug AS c_slug, c.owner_id
     FROM leads l
     INNER JOIN companies c ON c.id = l.company_id
     WHERE c.owner_id = :owner_id
     ORDER BY l.created_at DESC
     LIMIT 300'
);
$leads->execute([':owner_id' => $userId]);
$leadRows = [];
foreach ($leads->fetchAll() as $row) {
    $leadRows[] = [
        'id' => (string) $row['id'],
        'name' => (string) $row['name'],
        'phone' => (string) $row['phone'],
        'email' => $row['email'] !== null ? (string) $row['email'] : null,
        'message' => $row['message'] !== null ? (string) $row['message'] : null,
        'created_at' => (string) $row['created_at'],
        'company_id' => (string) $row['company_id'],
        'companies' => [
            'id' => (string) $row['c_id'],
            'name' => (string) $row['c_name'],
            'slug' => (string) $row['c_slug'],
            'owner_id' => (string) $row['owner_id'],
        ],
    ];
}

$reviews = $pdo->prepare(
    'SELECT r.id, r.rating, r.comment, r.created_at, r.user_id, r.company_id, r.author_name,
            c.id AS c_id, c.name AS c_name, c.slug AS c_slug, c.owner_id,
            p.name AS profile_name, p.avatar_url
     FROM reviews r
     INNER JOIN companies c ON c.id = r.company_id
     LEFT JOIN profiles p ON p.id = r.user_id
     WHERE c.owner_id = :owner_id
     ORDER BY r.created_at DESC
     LIMIT 300'
);
$reviews->execute([':owner_id' => $userId]);
$reviewRows = [];
foreach ($reviews->fetchAll() as $row) {
    $reviewRows[] = [
        'id' => (string) $row['id'],
        'rating' => (int) $row['rating'],
        'comment' => $row['comment'] !== null ? (string) $row['comment'] : null,
        'created_at' => (string) $row['created_at'],
        'user_id' => $row['user_id'] !== null ? (string) $row['user_id'] : null,
        'company_id' => (string) $row['company_id'],
        'author_name' => $row['author_name'] !== null ? (string) $row['author_name'] : null,
        'companies' => [
            'id' => (string) $row['c_id'],
            'name' => (string) $row['c_name'],
            'slug' => (string) $row['c_slug'],
            'owner_id' => (string) $row['owner_id'],
        ],
        'profile' => $row['user_id'] !== null
            ? [
                'name' => $row['profile_name'] !== null ? (string) $row['profile_name'] : null,
                'avatar_url' => $row['avatar_url'] !== null ? (string) $row['avatar_url'] : null,
            ]
            : null,
    ];
}

$fav = $pdo->prepare(
    'SELECT COUNT(*) FROM favorites f
     INNER JOIN companies c ON c.id = f.company_id
     WHERE c.owner_id = :owner_id'
);
$fav->execute([':owner_id' => $userId]);

app_success([
    'leads' => $leadRows,
    'reviews' => $reviewRows,
    'favorites_count' => (int) $fav->fetchColumn(),
]);
