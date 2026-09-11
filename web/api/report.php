<?php
declare(strict_types=1);
require_once __DIR__ . '/worker_auth.php';
require_worker_method('POST');
$worker = worker_request();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !filter_var($payload['job_id'] ?? null, FILTER_VALIDATE_INT) || !in_array($payload['result'] ?? '', ['sent','failed','ambiguous'], true)) {
    json_response(false, 'job_id and a valid result are required', null, 422);
}
$jobId = (int) $payload['job_id'];
$result = $payload['result'];
$errorMessage = substr((string) ($payload['error'] ?? ''), 0, 500);
$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM message_jobs WHERE id = ? FOR UPDATE');
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    if (!$job) { $pdo->rollBack(); json_response(false, 'Job not found', null, 404); }
    if ((int) $job['worker_id'] !== (int) $worker['id']) { $pdo->rollBack(); json_response(false, 'Job is owned by another worker', null, 403); }
    if ($job['status'] !== 'processing') {
        $pdo->commit();
        json_response(true, 'Job already finalized', ['status' => $job['status']]);
    }
    $finalStatus = $result === 'sent' ? 'sent' : ((int) $job['attempts'] < (int) $job['max_attempts'] && $result !== 'ambiguous' ? 'pending' : 'failed');
    $availableAt = $finalStatus === 'pending' ? gmdate('Y-m-d H:i:s', time() + 60) : null;
    $update = $pdo->prepare('UPDATE message_jobs SET status = ?, worker_id = NULL, claimed_at = NULL, available_at = COALESCE(?, available_at), last_error = ?, sent_at = IF(? = \'sent\', UTC_TIMESTAMP(), sent_at) WHERE id = ? AND status = \'processing\' AND worker_id = ?');
    $update->execute([$finalStatus, $availableAt, $errorMessage ?: null, $finalStatus, $jobId, $worker['id']]);
    $log = $pdo->prepare('INSERT INTO message_logs (job_id, worker_id, attempt, result, error_message) VALUES (?, ?, ?, ?, ?)');
    $log->execute([$jobId, $worker['id'], $job['attempts'], $result, $errorMessage ?: null]);
    $pdo->prepare('UPDATE workers SET current_job_id = NULL WHERE id = ? AND current_job_id = ?')->execute([$worker['id'], $jobId]);
    $pdo->commit();
    json_response(true, 'Job result recorded', ['status' => $finalStatus]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($error->getMessage());
    json_response(false, 'Unable to record job result', null, 503);
}
