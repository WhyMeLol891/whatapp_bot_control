<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();
$error = null;
$notice = null;
$lastJobStatus = null;
$lastJobMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_whatsapp') {
    verify_csrf();
    $contactId = filter_input(INPUT_POST, 'contact_id', FILTER_VALIDATE_INT);
    $body = trim((string) ($_POST['body'] ?? ''));
    $scheduledAt = trim((string) ($_POST['scheduled_at'] ?? ''));

    if (!$contactId || $body === '') {
        $error = 'Choose a contact and enter a message.';
    } else {
        $result = queue_whatsapp_message((int) $contactId, $body, 'Manual WhatsApp send', $scheduledAt !== '' ? $scheduledAt : null);
        if ($result['ok']) {
            $notice = $result['message'];
            $latest = $pdo->prepare('SELECT id, status, last_error FROM message_jobs WHERE id = ? LIMIT 1');
            $latest->execute([$result['job_id']]);
            $job = $latest->fetch();
            $lastJobStatus = $job['status'] ?? 'pending';
            $lastJobMessage = $job['status'] === 'sent'
                ? 'Message sent successfully.'
                : ($job['status'] === 'failed'
                    ? ('Delivery failed: ' . ($job['last_error'] ?: 'unknown error'))
                    : 'Message is queued and awaiting worker confirmation.');
        } else {
            $error = $result['message'];
        }
    }
}

$contacts = $pdo->query("SELECT id, name, phone FROM contacts WHERE is_active = 1 AND consent_status = 'opt_in' ORDER BY name, id")->fetchAll();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Send WhatsApp | WhatsApp Bot Control</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <strong>WBC</strong>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="send.php">Send</a>
        <a href="contacts.php">Contacts</a>
        <a href="campaigns.php">Campaigns</a>
        <a href="api/health.php">API health</a>
        <form method="post" action="logout.php" class="inline">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <button class="link-button">Sign out</button>
        </form>
    </nav>
</header>
<main class="shell">
    <div class="page-heading">
        <div>
            <p class="eyebrow">DIRECT MESSAGING</p>
            <h1>Send WhatsApp message</h1>
            <p class="muted">Only active contacts with consent can receive a manual send.</p>
        </div>
    </div>

    <?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($lastJobMessage): ?><div class="alert <?= $lastJobStatus === 'sent' ? 'success' : ($lastJobStatus === 'failed' ? 'danger' : 'info') ?>"><?= htmlspecialchars($lastJobMessage, ENT_QUOTES, 'UTF-8') ?> <strong>Status:</strong> <?= htmlspecialchars(strtoupper($lastJobStatus ?: 'pending'), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="panel">
        <h2>Send message</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="send_whatsapp">
            <label>Contact
                <select name="contact_id" required>
                    <?php foreach ($contacts as $contact): ?>
                        <option value="<?= (int) $contact['id'] ?>"><?= htmlspecialchars($contact['name'] . ' — ' . $contact['phone'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Message
                <textarea name="body" rows="6" maxlength="10000" required placeholder="Hello {{name}}..."></textarea>
            </label>
            <label>Send time <span class="muted">optional</span>
                <input type="datetime-local" name="scheduled_at">
            </label>
            <button type="submit">Send message</button>
        </form>
    </section>
</main>
</body>
</html>
