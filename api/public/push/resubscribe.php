<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/push.php';

app_start(['POST', 'OPTIONS']);
$pdo = db_pdo(false);
domain_require_tables($pdo, ['push_subscriptions']);
rate_limit_hit('push_resubscribe', (string) ($_SERVER['REMOTE_ADDR'] ?? '0'), 20, 60);
push_handle_resubscribe($pdo, domain_read_json());
