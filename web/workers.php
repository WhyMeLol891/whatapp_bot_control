<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();
$error = null;
$notice = null;
$generatedKey = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_worker') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '' || strlen($name) > 120) {
                throw new InvalidArgumentException('Worker name must be between 1 and 120 characters.');
            }

            $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
            $stmt = $pdo->prepare('INSERT INTO workers (name, token_hash, is_enabled) VALUES (?, ?, 1)');
            $stmt->execute([$name, hash('sha256', $token)]);
            $generatedKey = $token;
            $notice = 'Worker created successfully.';
        } else {
            throw new InvalidArgumentException('Unsupported action.');
        }
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $error = 'Unable to create worker.';
    }
}

$workers = $pdo->query('SELECT id, name, is_enabled, whatsapp_status, last_heartbeat_at, created_at FROM workers ORDER BY id DESC')->fetchAll();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Workers | WhatsApp Bot Control</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <strong>WBC</strong>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="workers.php">Workers</a>
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
            <p class="eyebrow">WORKER MANAGEMENT</p>
            <h1>Workers</h1>
            <p class="muted">Generate a worker API key and keep one active WhatsApp sender running.</p>
        </div>
    </div>

    <?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($generatedKey): ?>
        <div class="alert success">
            <strong>Worker API key:</strong><br>
            <?= htmlspecialchars($generatedKey, ENT_QUOTES, 'UTF-8') ?><br>
            <span class="muted">Save this key now. It will not be shown again.</span>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h2>Create worker</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create_worker">
            <label>Worker name
                <input name="name" maxlength="120" required placeholder="Main WhatsApp Sender">
            </label>
            <button type="submit">Create API key</button>
        </form>
    </section>

    <section class="panel">
        <h2>Registered workers</h2>
        <?php if (!$workers): ?>
            <p class="muted">No workers yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>WhatsApp</th>
                            <th>Last heartbeat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workers as $worker): ?>
                            <tr>
                                <td><?= (int) $worker['id'] ?></td>
                                <td><?= htmlspecialchars($worker['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (bool) $worker['is_enabled'] ? 'Enabled' : 'Disabled' ?></td>
                                <td><?= htmlspecialchars((string) ($worker['whatsapp_status'] ?? 'unknown'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $worker['last_heartbeat_at'] ? htmlspecialchars($worker['last_heartbeat_at'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
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
