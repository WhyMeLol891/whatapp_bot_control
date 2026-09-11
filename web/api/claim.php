<?php
declare(strict_types=1);
require_once __DIR__ . '/worker_auth.php';
require_worker_method('POST');
$worker = worker_request();
$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->query("SELECT id FROM message_jobs WHERE status = 'pending' AND available_at <= UTC_TIMESTAMP() ORDER BY id LIMIT 1 FOR UPDATE");
    $jobId = $stmt->fetchColumn();
    if (!$jobId) {
        $pdo->commit();
        json_response(true, 'No jobs available', null);
    }
    $update = $pdo->prepare("UPDATE message_jobs SET status = 'processing', worker_id = ?, claimed_at = UTC_TIMESTAMP(), attempts = attempts + 1 WHERE id = ? AND status = 'pending'");
    $update->execute([$worker['id'], $jobId]);
    if ($update->rowCount() !== 1) {
        $pdo->rollBack();
        json_response(true, 'No jobs available', null);
    }
    $job = $pdo->prepare('SELECT id, campaign_id, contact_id, phone, rendered_body, attempts, max_attempts FROM message_jobs WHERE id = ?');
    $job->execute([$jobId]);
    $result = $job->fetch();
    $pdo->prepare('UPDATE workers SET current_job_id = ? WHERE id = ?')->execute([$jobId, $worker['id']]);
    $pdo->commit();
    json_response(true, 'Job claimed', $result);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($error->getMessage());
    json_response(false, 'Unable to claim job', null, 503);
}
