<?php
declare(strict_types=1);
require_once __DIR__ . '/../web/includes/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$timeout = (int) (getenv('WBC_HEARTBEAT_TIMEOUT') ?: 90);
$timeout = max(30, min($timeout, 3600));
$pdo = db();
$pdo->beginTransaction();
try {
    $stale = $pdo->query("SELECT j.id, j.worker_id, j.attempts, j.max_attempts
        FROM message_jobs j LEFT JOIN workers w ON w.id = j.worker_id
        WHERE j.status = 'processing'
          AND j.claimed_at < (UTC_TIMESTAMP() - INTERVAL {$timeout} SECOND)
          AND (w.id IS NULL OR w.last_heartbeat_at IS NULL OR w.last_heartbeat_at < (UTC_TIMESTAMP() - INTERVAL {$timeout} SECOND))
        FOR UPDATE")->fetchAll();
    $requeue = $pdo->prepare("UPDATE message_jobs SET status = 'pending', worker_id = NULL,
        claimed_at = NULL, available_at = UTC_TIMESTAMP(), last_error = 'Recovered after stale worker claim'
        WHERE id = ? AND status = 'processing'");
    $fail = $pdo->prepare("UPDATE message_jobs SET status = 'failed', worker_id = NULL,
        claimed_at = NULL, last_error = 'Failed after stale worker claim'
        WHERE id = ? AND status = 'processing'");
    $log = $pdo->prepare('INSERT INTO message_logs (job_id, worker_id, attempt, result, error_message) VALUES (?, ?, ?, \'failed\', ?)');
    $clearWorker = $pdo->prepare('UPDATE workers SET current_job_id = NULL WHERE id = ? AND current_job_id = ?');
    $count = 0;
    foreach ($stale as $job) {
        $isExhausted = (int) $job['attempts'] >= (int) $job['max_attempts'];
        $isExhausted ? $fail->execute([$job['id']]) : $requeue->execute([$job['id']]);
        if ($isExhausted || $requeue->rowCount() === 1) {
            $log->execute([$job['id'], $job['worker_id'], $job['attempts'], 'Worker claim became stale']);
            $count++;
        }
        if ($job['worker_id'] !== null) {
            $clearWorker->execute([$job['worker_id'], $job['id']]);
        }
    }
    $pdo->commit();
    fwrite(STDOUT, "Recovered {$count} stale job(s).\n");
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
