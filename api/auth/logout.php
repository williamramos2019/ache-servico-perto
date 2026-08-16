<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/auth.php';

app_start(['POST', 'OPTIONS']);
auth_start_session();

if (auth_user_id() !== null || csrf_session_has_token()) {
    require_csrf();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    auth_clear_session_cookie();
}
session_destroy();

app_success([
    'logged_out' => true,
]);
