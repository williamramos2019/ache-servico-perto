<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/auth.php';

app_start(['GET', 'OPTIONS']);
auth_start_session();

app_success([
    'csrf_token' => csrf_ensure(),
]);
