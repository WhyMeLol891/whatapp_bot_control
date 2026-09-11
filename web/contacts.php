<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();
$error = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $company = trim((string) ($_POST['company'] ?? ''));
            $consentStatus = (string) ($_POST['consent_status'] ?? 'unknown');
            if ($name === '' || strlen($name) > 160 || $phone === '' || strlen($phone) > 32 || !preg_match('/^\+?[0-9][0-9 .()-]{6,30}$/', $phone) || !in_array($consentStatus, ['unknown', 'opt_in', 'opt_out'], true)) {
                throw new InvalidArgumentException('Enter a name and a valid phone number. Use international format where possible.');
            }
            $stmt = $pdo->prepare('INSERT INTO contacts (name, phone, company, consent_status, consented_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $phone, $company !== '' ? $company : null, $consentStatus, $consentStatus === 'opt_in' ? gmdate('Y-m-d H:i:s') : null]);
            $notice = 'Contact added.';
        } elseif ($action === 'toggle') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid contact.');
            }
            $stmt = $pdo->prepare('UPDATE contacts SET is_active = NOT is_active WHERE id = ?');
            $stmt->execute([$id]);
            $notice = 'Contact status updated.';
        } elseif ($action === 'consent') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $consentStatus = (string) ($_POST['consent_status'] ?? 'unknown');
            if (!$id || !in_array($consentStatus, ['unknown', 'opt_in', 'opt_out'], true)) {
                throw new InvalidArgumentException('Invalid consent setting.');
            }
            $stmt = $pdo->prepare('UPDATE contacts SET consent_status = ?, consented_at = ? WHERE id = ?');
            $stmt->execute([$consentStatus, $consentStatus === 'opt_in' ? gmdate('Y-m-d H:i:s') : null, $id]);
            $notice = 'Consent status updated.';
        } elseif ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid contact.');
            }
            $stmt = $pdo->prepare('DELETE FROM contacts WHERE id = ?');
            $stmt->execute([$id]);
            $notice = 'Contact deleted.';
        } else {
            throw new InvalidArgumentException('Unsupported action.');
        }
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        $error = $exception->getCode() === '23000' ? 'That phone number already exists.' : 'Unable to update contacts.';
    }
}

$contacts = $pdo->query('SELECT id, name, phone, company, consent_status, is_active, created_at FROM contacts ORDER BY name, id')->fetchAll();
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contacts | WhatsApp Bot Control</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<header class="topbar"><strong>WBC</strong><nav><a href="index.php">Dashboard</a><a href="contacts.php">Contacts</a><form method="post" action="logout.php" class="inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="link-button">Sign out</button></form></nav></header>
<main class="shell"><div class="page-heading"><div><p class="eyebrow">RECIPIENT DIRECTORY</p><h1>Contacts</h1><p class="muted">Add only people who have consented to receive your messages.</p></div></div>
<?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="panel"><h2>Add contact</h2><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="add"><label>Name<input name="name" maxlength="160" required></label><label>Phone<input name="phone" maxlength="32" placeholder="+60123456789" required></label><label>Company <span class="muted">optional</span><input name="company" maxlength="160"></label><label>Consent<select name="consent_status"><option value="unknown">Unknown</option><option value="opt_in">Opted in</option><option value="opt_out">Opted out</option></select></label><div><button type="submit">Add contact</button></div></form></section>
<section class="panel"><h2>Saved contacts <span class="muted"><?= count($contacts) ?></span></h2><?php if (!$contacts): ?><p class="muted">No contacts yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Name</th><th>Phone</th><th>Consent</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($contacts as $contact): ?><tr><td><?= htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($contact['phone'], ENT_QUOTES, 'UTF-8') ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="consent"><input type="hidden" name="id" value="<?= (int) $contact['id'] ?>"><select name="consent_status" onchange="this.form.submit()"><option value="unknown"<?= $contact['consent_status'] === 'unknown' ? ' selected' : '' ?>>Unknown</option><option value="opt_in"<?= $contact['consent_status'] === 'opt_in' ? ' selected' : '' ?>>Opted in</option><option value="opt_out"<?= $contact['consent_status'] === 'opt_out' ? ' selected' : '' ?>>Opted out</option></select></form></td><td><?= (bool) $contact['is_active'] ? 'Active' : 'Inactive' ?></td><td class="actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $contact['id'] ?>"><button class="secondary" type="submit"><?= (bool) $contact['is_active'] ? 'Deactivate' : 'Activate' ?></button></form><form method="post" onsubmit="return confirm('Delete this contact?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $contact['id'] ?>"><button class="danger-button" type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</main></body></html>
