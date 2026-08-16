<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

app_start(['GET', 'OPTIONS']);

app_success([
    'service' => 'api',
    'status' => 'ok',
]);
