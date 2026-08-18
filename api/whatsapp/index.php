<?php

declare(strict_types=1);

$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'subscribe';
if ($op === 'opt-out') {
    require __DIR__ . '/opt-out.php';
}
require __DIR__ . '/subscribe.php';
