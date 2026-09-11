<?php
declare(strict_types=1);
require_once __DIR__ . '/worker_auth.php';
require_worker_method('POST');
$worker = worker_request();
$payload = json_decode(file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$status = $payload['whatsapp_status'] ?? 'unknown';
$allowed = ['unknown','qr_required','connected','disconnected'];
if (!in_array($status, $allowed, true)) {
    json_response(false, 'Invalid WhatsApp status', null, 422);
}
$diagnostics = json_encode([
    'browser' => substr((string) ($payload['browser'] ?? ''), 0, 80),
    'python' => substr((string) ($payload['python'] ?? ''), 0, 40),
    'version' => substr((string) ($payload['version'] ?? ''), 0, 40),
], JSON_THROW_ON_ERROR);
$stmt = db()->prepare('UPDATE workers SET last_heartbeat_at = UTC_TIMESTAMP(), whatsapp_status = ?, diagnostics = ? WHERE id = ? AND is_enabled = 1');
$stmt->execute([$status, $diagnostics, $worker['id']]);
json_response(true, 'Heartbeat accepted', ['worker_id' => (int) $worker['id']]);
