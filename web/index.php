<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM workers WHERE is_enabled = 1) AS workers,
    (SELECT COUNT(*) FROM message_jobs WHERE status IN ('pending','processing')) AS queue_count,
    (SELECT COUNT(*) FROM message_jobs WHERE status = 'sent') AS sent_count,
    (SELECT COUNT(*) FROM message_jobs WHERE status = 'failed') AS failed_count")->fetch();

$sendError = null;
$sendNotice = null;
$lastJobStatus = null;
$lastJobMessage = null;
$recentRecords = $pdo->query("SELECT j.id, c.name, c.phone, j.status, j.available_at, j.sent_at, j.last_error
    FROM message_jobs j
    LEFT JOIN contacts c ON c.id = j.contact_id
    ORDER BY j.updated_at DESC, j.id DESC
    LIMIT 8")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_whatsapp') {
    verify_csrf();
    $contactId = filter_input(INPUT_POST, 'contact_id', FILTER_VALIDATE_INT);
    $body = trim((string) ($_POST['body'] ?? ''));
    $scheduledAt = trim((string) ($_POST['scheduled_at'] ?? ''));
    if (!$contactId || $body === '') {
        $sendError = 'Choose a contact and enter a message.';
    } else {
        $result = queue_whatsapp_message((int) $contactId, $body, 'Manual WhatsApp send', $scheduledAt !== '' ? $scheduledAt : null);
        if ($result['ok']) {
            $sendNotice = $result['message'];
            $latest = $pdo->prepare('SELECT id, status, last_error FROM message_jobs WHERE id = ? LIMIT 1');
            $latest->execute([$result['job_id']]);
            $job = $latest->fetch();
            $lastJobStatus = $job['status'] ?? 'pending';
            $lastJobMessage = $job['status'] === 'sent' ? 'Message sent successfully.' : ($job['status'] === 'failed' ? ('Delivery failed: ' . ($job['last_error'] ?: 'unknown error')) : 'Message is queued and awaiting worker confirmation.');
        } else {
            $sendError = $result['message'];
        }
    }
}

$contacts = $pdo->query("SELECT id, name, phone FROM contacts WHERE is_active = 1 AND consent_status = 'opt_in' ORDER BY name, id")->fetchAll();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | WhatsApp Bot Control</title><link rel="stylesheet" href="assets/style.css"></head><body><header class="topbar"><strong>WBC</strong><nav><a href="index.php">Dashboard</a><a href="workers.php">Workers</a><a href="contacts.php">Contacts</a><a href="api/health.php">API health</a><form method="post" action="logout.php" class="inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="link-button">Sign out</button></form></nav></header><main class="shell"><div class="page-heading"><div><p class="eyebrow">OPERATIONS OVERVIEW</p><h1>Good to see you, <?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></h1></div><span class="status-pill">Consent-first mode</span></div><section class="metrics"><article><span>Enabled workers</span><strong><?= (int) $stats['workers'] ?></strong></article><article><span>Queue</span><strong><?= (int) $stats['queue_count'] ?></strong></article><article><span>Sent</span><strong><?= (int) $stats['sent_count'] ?></strong></article><article><span>Failed</span><strong><?= (int) $stats['failed_count'] ?></strong></article></section>
<section class="panel"><h2>Send WhatsApp message</h2><?php if ($sendError): ?><div class="alert danger"><?= htmlspecialchars($sendError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($sendNotice): ?><div class="alert success"><?= htmlspecialchars($sendNotice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($lastJobMessage): ?><div class="alert <?= $lastJobStatus === 'sent' ? 'success' : ($lastJobStatus === 'failed' ? 'danger' : 'info') ?>"><?= htmlspecialchars($lastJobMessage, ENT_QUOTES, 'UTF-8') ?> <strong>Status:</strong> <?= htmlspecialchars(strtoupper($lastJobStatus ?: 'pending'), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="send_whatsapp"><label>Contact<select name="contact_id" required><?php foreach ($contacts as $contact): ?><option value="<?= (int) $contact['id'] ?>"><?= htmlspecialchars($contact['name'] . ' — ' . $contact['phone'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label>Message<textarea name="body" rows="5" maxlength="10000" required placeholder="Hello {{name}}..."></textarea></label><label>Send time <span class="muted">optional</span><input type="datetime-local" name="scheduled_at"></label><button type="submit">Send message</button></form></section>
<section class="panel"><h2>Recent delivery records</h2><?php if (!$recentRecords): ?><p class="muted">No send activity yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Contact</th><th>Phone</th><th>Queued / send time</th><th>Actual sent at</th><th>Status</th><th>Result</th></tr></thead><tbody><?php foreach ($recentRecords as $record): ?><tr><td><?= htmlspecialchars($record['name'] ?: 'Unknown contact', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($record['phone'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($record['available_at'] ? date('d/m/Y H:i', strtotime((string) $record['available_at'])) : '-', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($record['sent_at'] ? date('d/m/Y H:i', strtotime((string) $record['sent_at'])) : '-', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(strtoupper((string) $record['status']), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($record['last_error'] ?: ($record['status'] === 'sent' ? 'Success' : ($record['status'] === 'failed' ? 'Failed' : 'Pending')), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><p class="muted"><a href="records.php">View all delivery records</a></p></section><section class="panel"><h2>System boundary</h2><p>The web application owns authentication, authorization, scheduling, queue state, and audit logs. Workers communicate through the authenticated API and never connect directly to MySQL.</p><p class="muted">Delivery semantics are at-least-once job execution. A worker crash after WhatsApp accepts a message can leave delivery ambiguous.</p></section></main></body></html>
