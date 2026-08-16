<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

app_start(['GET', 'OPTIONS']);

$database = app_database_status();
$payload = [
    'status' => $database === 'ok' ? 'ok' : 'degraded',
    'database' => $database,
];

if ($database === 'ok') {
    app_success($payload, 200);
}

app_json(503, [
    'success' => false,
    'error' => [
        'code' => 'database_unavailable',
        'message' => 'Database is unavailable.',
    ],
    'data' => $payload,
]);
