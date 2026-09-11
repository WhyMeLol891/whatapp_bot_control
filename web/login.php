<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

if (current_user() !== null) {
    header('Location: ' . app_redirect_path('web/index.php'));
    exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, username, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && (bool) $user['is_active'] && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        header('Location: ' . app_redirect_path('web/index.php'));
        exit;
    }
    $error = 'Invalid username or password.';
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in | WhatsApp Bot Control</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="auth-page"><main class="auth-card"><p class="eyebrow">INTERNAL OPERATIONS</p><h1>WhatsApp Bot Control</h1><p class="muted">Consent-based messaging operations.</p><?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><label>Username<input name="username" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button type="submit">Sign in</button></form></main></body></html>
