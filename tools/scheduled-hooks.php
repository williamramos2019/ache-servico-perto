<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

require dirname(__DIR__) . '/api/bootstrap/cron.php';

$task = 'all';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help') {
        echo "Uso: php tools/scheduled-hooks.php [--task=all|welcome|digest|cleanup|jobs]\n";
        exit(0);
    }
    if (str_starts_with($arg, '--task=')) {
        $task = substr($arg, 7);
    }
}
if (!in_array($task, ['all', 'welcome', 'digest', 'cleanup', 'jobs'], true)) {
    fwrite(STDERR, "Tarefa inválida.\n");
    exit(1);
}
try {
    $pdo = db_pdo(false);
    $result = [];
    if ($task === 'all' || $task === 'jobs') {
        $result['jobs'] = cron_jobs_due($pdo, null);
    }
    if ($task === 'all' || $task === 'welcome') {
        $result['welcome'] = cron_whatsapp_welcome($pdo);
    }
    if ($task === 'all' || $task === 'digest') {
        $result['digest'] = cron_whatsapp_digest($pdo);
    }
    if ($task === 'all' || $task === 'cleanup') {
        $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE expires_at < :now');
        $stmt->execute([':now' => auth_now()]);
        $result['cleanup'] = ['expired_password_resets' => $stmt->rowCount()];
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
