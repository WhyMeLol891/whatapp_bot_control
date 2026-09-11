<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$pdo = db();

$records = $pdo->query("SELECT j.id, c.name, c.phone, j.status, j.available_at, j.sent_at, j.last_error
    FROM message_jobs j
    LEFT JOIN contacts c ON c.id = j.contact_id
    ORDER BY j.updated_at DESC, j.id DESC
    LIMIT 100")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Delivery records | WhatsApp Bot Control</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <strong>WBC</strong>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="workers.php">Workers</a>
        <a href="contacts.php">Contacts</a>
        <a href="records.php">Delivery records</a>
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
            <p class="eyebrow">DELIVERY LOG</p>
            <h1>Message records</h1>
            <p class="muted">Track the contact, scheduled time, and final status for each send.</p>
        </div>
    </div>

    <section class="panel">
        <h2>Recent send history</h2>
        <?php if (!$records): ?>
            <p class="muted">No messages have been sent or queued yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Contact</th>
                            <th>Phone</th>
                            <th>Queued time</th>
                            <th>Sent at</th>
                            <th>Status</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?= htmlspecialchars($record['name'] ?: 'Unknown contact', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($record['phone'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($record['available_at'] ? date('d/m/Y H:i', strtotime((string) $record['available_at'])) : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($record['sent_at'] ? date('d/m/Y H:i', strtotime((string) $record['sent_at'])) : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(strtoupper((string) $record['status']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($record['last_error'] ?: ($record['status'] === 'sent' ? 'Success' : ($record['status'] === 'failed' ? 'Failed' : 'Pending')), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
